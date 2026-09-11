<?php
/**
 * Forward splits, the "apply going forward" workflow.
 *
 * An organizer editing a series from occurrence 3 of 6 chooses whether the edit
 * rewrites the whole series (retroactive, the default) or applies only from that
 * occurrence onward. The second choice splits the series here: the original post
 * is capped at the occurrences before the split point, a second event post
 * carries the rest, and the organizer's edit lands on the second post.
 *
 * Three properties of this class are the point of the whole design, not
 * implementation details:
 *
 * - **Occurrence rows are moved, never deleted and regenerated.** Identity is
 *   `(series_post_id, recurrence_id)` with `recurrence_id` derived from
 *   the occurrence's own local start, so moving a row changes which post owns it
 *   and nothing else. Permalinks, cancellation state and RSVP mappings survive
 *   because none of them was recreated.
 * - **RSVP terms are renamed, never re-tagged, and the comments follow them.**
 *   `wp_update_term()` leaves `term_taxonomy_id` alone, so every
 *   `term_relationships` row survives however many RSVPs an occurrence carries.
 *   `comment_post_ID` moves in the same step, because `Rsvp\Storage` narrows on
 *   the post *and* the occurrence term conjoined: a comment whose two owners
 *   disagree is readable through neither.
 * - **The scope degrades automatically.** "Forward" from the first occurrence is
 *   retroactive and produces no split at all; a side left holding exactly one
 *   occurrence is a plain non-recurring event rather than a series of one.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Calendar\Revision;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Rsvp\Cache as Rsvp_Cache;
use GatherPress\Core\Traits\Singleton;
use WP_Error;
use WP_Post;

/**
 * Class Splitter.
 *
 * Singleton owning the forward split and the RSVP-impact reporting.
 *
 * @since 0.36.0
 */
final class Splitter {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Post meta this class never copies onto the forward post.
	 *
	 * The recurrence blob and the datetime blob are rewritten from the split
	 * point, so copying them would seed the forward post with the origin's
	 * anchor. The edit-lock pair is per-user session state.
	 *
	 * `_wp_old_slug` is the rename history of a slug the forward post does not
	 * have. `post_name` is deliberately not copied (see `COPIED_POST_FIELDS`),
	 * so copying the history would have the forward post claim permalinks it
	 * never answered to and redirect them away from the origin, which still
	 * owns the dates those permalinks were published for. Reachable on any
	 * event renamed after publication, which is when WordPress writes the key.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	const UNCOPIED_META_KEYS = array(
		'_edit_last',
		'_edit_lock',
		'_wp_old_slug',
		'gatherpress_datetime',
		'gatherpress_datetime_end',
		'gatherpress_datetime_start',
		'gatherpress_timezone',
		Meta::META_KEY,
	);

	/**
	 * Post fields the forward post inherits from the origin.
	 *
	 * An allowlist rather than a hand-written literal, because the omissions are
	 * what bite. `post_password` was absent, so splitting a password-protected
	 * published event produced a second published event with the same content
	 * and no password at all. `comment_status` decides whether the forward half
	 * can be RSVPd to; `post_parent` and `menu_order` are ordinary authoring
	 * state a copy has no business dropping.
	 *
	 * `post_name` is deliberately absent: two posts cannot share a slug, and
	 * letting WordPress derive the forward post's own is the only correct
	 * answer. The rename history that belongs to a slug is held back with it:
	 * `_wp_old_slug` is named in `UNCOPIED_META_KEYS` above, so after a split
	 * the origin alone answers the series' old permalinks. That pairing is
	 * deliberate rather than an omission on either side.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	const COPIED_POST_FIELDS = array(
		'comment_status',
		'menu_order',
		'ping_status',
		'post_author',
		'post_content',
		'post_excerpt',
		'post_parent',
		'post_password',
		'post_status',
		'post_title',
		'post_type',
	);

	/**
	 * How to undo each durable phase the split in progress has completed.
	 *
	 * A split spans posts, postmeta, the occurrence table, comments and terms,
	 * and no two of those share a transaction. The stack is the compensating
	 * substitute: each phase pushes its own reversal before the next one runs,
	 * and the first failure pops the stack.
	 *
	 * @since 0.36.0
	 * @var callable[]
	 */
	private array $undo = array();

	/**
	 * The calendar revision each post of the series carried before the split.
	 *
	 * @since 0.36.0
	 * @var array<int, mixed>
	 */
	private array $revisions = array();

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
	}

	/**
	 * Split a series forward at one of its occurrences.
	 *
	 * The public entry point, and the one that still accepts a bare pair. It
	 * resolves that pair to the authoritative occurrence identity and hands the
	 * *identity* on, so nothing downstream re-derives an owner from the post the
	 * caller happened to name.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Post the organizer is editing.
	 * @param string $recurrence_id Occurrence the edit is being made from.
	 *
	 * @return array|WP_Error The split result, or an error when the occurrence or rule is missing.
	 */
	public function split_forward( int $post_id, string $recurrence_id ) {
		$identity = Occurrence_Identity::resolve( $post_id, $recurrence_id );

		if ( null === $identity ) {
			return new WP_Error(
				'gatherpress_occurrence_not_found',
				__( 'No occurrence matches the given post and recurrence ID.', 'gatherpress' ),
				array( 'status' => 404 )
			);
		}

		return $this->split_identity( $identity );
	}

	/**
	 * Split a series forward at an occurrence whose identity is already resolved.
	 *
	 * Step 3 of resolve-authorize-use. A caller that has authorized
	 * `$identity->owner_post_id` passes the same immutable instance in here, so
	 * the post this mutates is provably the post that was checked. The route
	 * used to authorize the post the request named and then let this class
	 * discover a different sibling to cap, move and rewrite.
	 *
	 * @since 0.36.0
	 *
	 * @param Occurrence_Identity $identity The occurrence to split at.
	 *
	 * @return array|WP_Error The split result, or an error when the rule or row is missing.
	 */
	public function split_identity( Occurrence_Identity $identity ) {
		$row         = Occurrences::get_instance()->get( $identity->owner_post_id, $identity->recurrence_id );
		$rule        = Rule::from_post( $identity->owner_post_id );
		$origin_post = get_post( $identity->owner_post_id );

		if ( null === $row ) {
			return new WP_Error(
				'gatherpress_occurrence_not_found',
				__( 'No occurrence matches the given post and recurrence ID.', 'gatherpress' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $rule instanceof Rule || ! $origin_post instanceof WP_Post ) {
			return new WP_Error(
				'gatherpress_not_recurring',
				__( 'This event does not carry a recurrence rule to split.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		return $this->split_owned_series( $origin_post, $rule, $row );
	}

	/**
	 * Perform the split once the owning post, its rule and the split row are known.
	 *
	 * A split writes to five WordPress stores that share no transaction: posts,
	 * postmeta, the occurrence table, comments, and terms with their
	 * relationships. This method is therefore the compensating boundary: every
	 * durable phase registers how to undo itself before the next one runs, and
	 * the first failure unwinds the stack in reverse and settles the origin back
	 * to the state it described before the call.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Post $origin_post Post whose rule produces the split occurrence.
	 * @param Rule    $rule        That post's rule.
	 * @param array   $row         The occurrence row the split happens at.
	 *
	 * @return array|WP_Error The split result, or the first failure with everything rolled back.
	 */
	protected function split_owned_series( WP_Post $origin_post, Rule $rule, array $row ) {
		$origin_post_id = (int) $origin_post->ID;
		$rows           = Occurrences::get_instance()->select_for_series( array( $origin_post_id ) );
		$identifiers    = array_map( 'strval', wp_list_pluck( $rows, 'recurrence_id' ) );
		$index          = (int) array_search( (string) $row['recurrence_id'], $identifiers, true );

		// "Forward" from the first occurrence is retroactive: every occurrence
		// the owning post has is at or after it, so a split would leave that
		// post holding nothing and the forward post holding all of it.
		//
		// Which of the organizer's two situations that is depends on whether
		// the post is the whole series. Once a series has been split the
		// occurrence list spans every fragment, so the first row of a later
		// fragment reaches here too, and there the series does hold earlier
		// dates: they sit on a sibling post that this refusal does not touch.
		// The two get their own reason so the panel can say which one it is
		// rather than asserting the first about both.
		if ( 0 === $index ) {
			$whole_series = array( $origin_post_id ) === Series::get_instance()->resolve_post_ids( $origin_post_id );

			return $this->result(
				false,
				$whole_series ? 'first_occurrence' : 'fragment_first_occurrence',
				$origin_post_id
			);
		}

		// The origin's side is capped by `COUNT = $index` (see
		// `apply_capped_rule()`), and `Rule` refuses any count above
		// `Rule::MAX_COUNT`, so a split past that occurrence would write a
		// rule the origin can never re-project: every phase would run, the
		// partition check would fail against it, and the whole split would
		// undo itself into an opaque 500. Refusing here, before any durable
		// phase, names the real limit instead. Capping by `UNTIL` was
		// considered and rejected: the capped side's re-projection must
		// reproduce exactly the rows that stayed behind, which is
		// `verify_partition()`'s contract and something an `UNTIL` bound
		// cannot promise for an open-ended rule.
		if ( $index > Rule::MAX_COUNT ) {
			/* translators: %d: the maximum number of dates a recurrence rule may count. */
			$message = __(
				'A series cannot be split past its first %d dates. Choose an earlier date to split from.',
				'gatherpress'
			);

			return new WP_Error(
				'gatherpress_split_too_long',
				sprintf( $message, Rule::MAX_COUNT ),
				array( 'status' => 400 )
			);
		}

		$this->undo      = array();
		$this->revisions = $this->revision_snapshot( $origin_post_id );

		$result = $this->run_phases(
			$origin_post,
			$rule,
			$row,
			$index,
			$rows,
			$identifiers
		);

		if ( is_wp_error( $result ) ) {
			$this->report_rollback( $result, $this->roll_back( $origin_post_id ) );
		} else {
			$this->undo = array();
		}

		return $result;
	}

	/**
	 * Append a failed rollback to the failure that caused it.
	 *
	 * A rollback that could not finish is the one outcome a caller must not
	 * read as "nothing happened": the compensating undos put the stored values
	 * back, but the re-derivation that follows them did not run, so the series
	 * is left describing rows it does not have. The report therefore carries
	 * both failures, the original first so `WP_Error::get_error_data()` and
	 * every REST `status` derived from it still describe why the split was
	 * refused.
	 *
	 * The second entry is filed under its own code rather than under the
	 * rollback failure's. A database refusing every write produces the same
	 * code twice, and `WP_Error::add()` would fold the second message into the
	 * first code's list, hiding the rollback failure in exactly the situation
	 * that produces it most often. The refusal's own code and data travel in
	 * this entry's data instead.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Error      $failure  The failure that aborted the split, appended to in place.
	 * @param WP_Error|null $rollback The rollback's own failure, or null when it completed.
	 *
	 * @return void
	 */
	protected function report_rollback( WP_Error $failure, ?WP_Error $rollback ): void {
		if ( null === $rollback ) {
			return;
		}

		$failure->add(
			'gatherpress_split_rollback_failed',
			__( 'The split failed and could not be fully undone. This series may be inconsistent.', 'gatherpress' ),
			array(
				'status'              => 500,
				'rollback_error_code' => $rollback->get_error_code(),
				'rollback_error_data' => $rollback->get_error_data(),
			)
		);
	}

	/**
	 * Run every durable phase of a split, stopping at the first failure.
	 *
	 * The order is chosen so the reversible work happens before the work that
	 * rewrites rules: a rule rewrite re-projects, and re-projecting while rows
	 * are half-moved is what turns one failure into duplicate rows on the
	 * composite primary key.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Post  $origin_post Post being split.
	 * @param Rule     $rule        Its rule.
	 * @param array    $row         The occurrence row split at.
	 * @param int      $index       Index of that row in the origin's ordered set.
	 * @param array[]  $rows        The origin's ordered occurrence rows.
	 * @param string[] $identifiers Those rows' identifiers, in the same order.
	 *
	 * @return array|WP_Error The split result, or the first failure.
	 */
	protected function run_phases(
		WP_Post $origin_post,
		Rule $rule,
		array $row,
		int $index,
		array $rows,
		array $identifiers
	) {
		$origin_post_id = (int) $origin_post->ID;
		$forward_rows   = array_slice( $rows, $index );
		$forward_ids    = array_slice( $identifiers, $index );
		$origin_ids     = array_slice( $identifiers, 0, $index );

		$forward_post_id = $this->create_forward_post( $origin_post, $row );

		if ( is_wp_error( $forward_post_id ) ) {
			return $forward_post_id;
		}

		$this->record(
			static function () use ( $forward_post_id ): void {
				wp_delete_post( $forward_post_id, true );
			}
		);

		$failure = $this->phase( 'create_forward_post', $origin_post_id, $forward_post_id );

		if ( null !== $failure ) {
			return $failure;
		}

		$failure = $this->move_occurrences( $origin_post_id, $forward_post_id, $forward_ids );

		if ( null !== $failure ) {
			return $failure;
		}

		$migrated = $this->migrate_rsvps( $origin_post_id, $forward_post_id, $forward_ids );

		if ( is_wp_error( $migrated ) ) {
			return $migrated;
		}

		$failure = $this->join_series( $origin_post_id, $forward_post_id );

		if ( null !== $failure ) {
			return $failure;
		}

		$forward_recurring = $this->rule_phase(
			'forward_rule',
			$forward_post_id,
			$forward_ids,
			$origin_post_id,
			$forward_post_id,
			fn () => $this->apply_forward_rule( $forward_post_id, $rule, $index, $forward_rows )
		);

		if ( is_wp_error( $forward_recurring ) ) {
			return $forward_recurring;
		}

		$origin_recurring = $this->rule_phase(
			'origin_rule',
			$origin_post_id,
			$origin_ids,
			$origin_post_id,
			$forward_post_id,
			fn () => $this->apply_capped_rule( $origin_post_id, $rule, $index, $rows[0] )
		);

		if ( is_wp_error( $origin_recurring ) ) {
			return $origin_recurring;
		}

		$failure = $this->verify_partition(
			$origin_post_id,
			$forward_post_id,
			$origin_recurring ? $origin_ids : array(),
			$forward_recurring ? $forward_ids : array()
		);

		if ( null !== $failure ) {
			return $failure;
		}

		$failure = $this->advance_revision( $origin_post_id, $forward_post_id );

		if ( null !== $failure ) {
			return $failure;
		}

		$this->settle_caches( $origin_post_id, $forward_post_id, $identifiers );

		return array(
			'split'              => true,
			'reason'             => '',
			'origin_post_id'     => $origin_post_id,
			'forward_post_id'    => $forward_post_id,
			'moved'              => count( $forward_ids ),
			'renamed_rsvp_terms' => $migrated['terms'],
			'migrated_rsvps'     => count( $migrated['comments'] ),
			'origin_recurring'   => $origin_recurring,
			'forward_recurring'  => $forward_recurring,
		);
	}

	/**
	 * Move the forward occurrence rows, and insist that every one of them moved.
	 *
	 * A partial move is the failure that matters here: the rows that did not
	 * move keep answering under the origin while their RSVP terms and comments
	 * have already been told to expect the sibling.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $origin_post_id  Post the rows belong to.
	 * @param int      $forward_post_id Post they move to.
	 * @param string[] $forward_ids     Identifiers to move.
	 *
	 * @return WP_Error|null The failure, or null when every row moved.
	 */
	protected function move_occurrences( int $origin_post_id, int $forward_post_id, array $forward_ids ): ?WP_Error {
		$moved = Occurrences::get_instance()->move_to_post( $origin_post_id, $forward_post_id, $forward_ids );

		if ( count( $forward_ids ) !== $moved ) {
			// Whatever did move is put back before the error is reported, so
			// the undo stack stays a record of completed phases only.
			Occurrences::get_instance()->move_to_post( $forward_post_id, $origin_post_id, $forward_ids );

			return new WP_Error(
				'gatherpress_split_rows_not_moved',
				__( 'The occurrences could not be moved to the new event.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		$this->record(
			static function () use ( $origin_post_id, $forward_post_id, $forward_ids ): void {
				Occurrences::get_instance()->move_to_post( $forward_post_id, $origin_post_id, $forward_ids );
			}
		);

		return $this->phase( 'move_occurrences', $origin_post_id, $forward_post_id );
	}

	/**
	 * Move the RSVP terms and the RSVP comments that ride on them.
	 *
	 * Both owners move together because production reads conjoin them: an RSVP
	 * whose `comment_post_ID` and occurrence term name different posts is
	 * invisible from either post through `Rsvp::responses()`.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $origin_post_id  Post the RSVPs belong to.
	 * @param int      $forward_post_id Post they move to.
	 * @param string[] $forward_ids     Identifiers whose RSVPs move.
	 *
	 * @return array{terms: int, comments: int[]}|WP_Error What moved, or the failure.
	 */
	protected function migrate_rsvps( int $origin_post_id, int $forward_post_id, array $forward_ids ) {
		$migrated = Rsvp_Occurrence::get_instance()->migrate_owner(
			$origin_post_id,
			$forward_post_id,
			$forward_ids
		);

		if ( is_wp_error( $migrated ) ) {
			return $migrated;
		}

		$this->record(
			static function () use ( $origin_post_id, $forward_post_id, $forward_ids ): void {
				Rsvp_Occurrence::get_instance()->migrate_owner(
					$forward_post_id,
					$origin_post_id,
					$forward_ids
				);
			}
		);

		$failure = $this->phase( 'migrate_rsvps', $origin_post_id, $forward_post_id );

		return null === $failure ? $migrated : $failure;
	}

	/**
	 * Record that the two posts are one series, and refuse a split that cannot.
	 *
	 * `Series::join()` answers 0 when the term could neither be created nor
	 * recovered. Ignoring that answer produced two posts that resolve as two
	 * unrelated series: an old permalink through the origin can no longer find
	 * the rows that moved, and nothing anywhere reports a failure.
	 *
	 * @since 0.36.0
	 *
	 * @param int $origin_post_id  Post the series already existed on.
	 * @param int $forward_post_id Post the split created.
	 *
	 * @return WP_Error|null The failure, or null when both posts share a term.
	 */
	protected function join_series( int $origin_post_id, int $forward_post_id ): ?WP_Error {
		// Registration first, because the read below has to be able to tell an
		// origin that already belongs to a series from one that does not, and
		// on a site whose first split this is the taxonomy does not exist yet.
		Series::register_taxonomy_for( (string) get_post_type( $origin_post_id ) );

		$existing = Series::get_instance()->term_id_for_post( $origin_post_id );
		$term_id  = Series::get_instance()->join( $origin_post_id, $forward_post_id );

		if ( 0 === $term_id ) {
			return new WP_Error(
				'gatherpress_split_series_not_joined',
				__( 'The split events could not be recorded as one series.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		$this->record(
			static function () use ( $existing, $origin_post_id, $forward_post_id, $term_id ): void {
				wp_remove_object_terms( $forward_post_id, array( $term_id ), Series::TAXONOMY );

				// A term this split created is removed with it; a term the
				// series already carried is left exactly as it was found.
				if ( 0 === $existing ) {
					wp_remove_object_terms( $origin_post_id, array( $term_id ), Series::TAXONOMY );
					wp_delete_term( $term_id, Series::TAXONOMY );
				}

				Series::get_instance()->flush_memo();
			}
		);

		return $this->phase( 'join_series', $origin_post_id, $forward_post_id );
	}

	/**
	 * Rewrite one side's rule, having first captured everything the rewrite can destroy.
	 *
	 * A rule rewrite can demote its post to a plain event, which removes the
	 * rule, deletes the post's occurrence rows and deletes the occurrence terms
	 * whose `term_relationships` rows carry its RSVPs. The snapshot is what
	 * makes that reversible: rows come back from re-projecting the restored
	 * rule, and the RSVP relationships come back from the membership map.
	 *
	 * The undo deliberately restores stored values without re-projecting.
	 * Projection is deferred to `roll_back()`, because projecting while the
	 * rows are still half-moved would upsert a second copy of every moved
	 * occurrence under the origin.
	 *
	 * The undo is recorded **before** the write runs, which is what makes a
	 * write that fails half-way recoverable: the blob is already on the post by
	 * the time its projection is refused, and only a recorded undo takes it off
	 * again.
	 *
	 * The seam is reported the split's own pair of posts, not the one side this
	 * rewrite happens to touch. `origin_rule` writes the origin, so passing the
	 * post being written would hand a listener the origin twice and leave the
	 * forward post unnameable from the phase that caps the origin against it.
	 *
	 * @since 0.36.0
	 *
	 * @param string   $phase           Phase name, for the failure-injection seam.
	 * @param int      $post_id         Post whose rule is being written.
	 * @param string[] $recurrence_ids  Identifiers that post is meant to keep.
	 * @param int      $origin_post_id  Origin post, reported to the phase seam.
	 * @param int      $forward_post_id Forward post, reported to the phase seam.
	 * @param callable $write           Writes the rule and reports whether the post stays recurring, or the failure.
	 *
	 * @return bool|WP_Error Whether the post remains recurring, or the failure.
	 */
	protected function rule_phase(
		string $phase,
		int $post_id,
		array $recurrence_ids,
		int $origin_post_id,
		int $forward_post_id,
		callable $write
	) {
		$snapshot = $this->snapshot( $post_id, $recurrence_ids );

		$this->record(
			function () use ( $post_id, $snapshot ): void {
				$this->restore( $post_id, $snapshot );
			}
		);

		$recurring = $write();

		if ( is_wp_error( $recurring ) ) {
			return $recurring;
		}

		$failure = $this->phase( $phase, $origin_post_id, $forward_post_id );

		return null === $failure ? (bool) $recurring : $failure;
	}

	/**
	 * Capture everything a rule rewrite can destroy on one post.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $post_id        Post to capture.
	 * @param string[] $recurrence_ids Identifiers whose RSVP memberships to capture.
	 *
	 * @return array The snapshot.
	 */
	protected function snapshot( int $post_id, array $recurrence_ids ): array {
		return array(
			'has_rule'    => metadata_exists( 'post', $post_id, Meta::META_KEY ),
			'rule'        => get_post_meta( $post_id, Meta::META_KEY, true ),
			'datetime'    => get_post_meta( $post_id, 'gatherpress_datetime', true ),
			'memberships' => Rsvp_Occurrence::get_instance()->memberships( $post_id, $recurrence_ids ),
		);
	}

	/**
	 * Put back what a rule rewrite changed, without re-deriving anything.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $post_id  Post to restore.
	 * @param array $snapshot The snapshot taken before the rewrite.
	 *
	 * @return void
	 */
	protected function restore( int $post_id, array $snapshot ): void {
		if ( $snapshot['has_rule'] ) {
			update_post_meta( $post_id, Meta::META_KEY, $snapshot['rule'] );
		} else {
			delete_post_meta( $post_id, Meta::META_KEY );
		}

		update_post_meta( $post_id, 'gatherpress_datetime', $snapshot['datetime'] );

		Rsvp_Occurrence::get_instance()->restore_memberships( $post_id, $snapshot['memberships'] );
	}

	/**
	 * Check that the two posts partition the series rather than losing or sharing it.
	 *
	 * The one check that catches a rule rewrite which reported success and
	 * projected something else. Three properties, and the asymmetry between the
	 * first two is real rather than sloppy:
	 *
	 * - The origin owns **exactly** what stayed behind. It is capped by `COUNT`
	 *   at that many rows, so re-projection cannot produce a different set.
	 * - The forward post owns **at least** what moved. Its horizon is measured
	 *   from its own, later anchor, so an open-ended rule legitimately projects
	 *   dates past where the origin had reached.
	 * - Neither owns anything the other does. A shared identifier is the
	 *   duplicate the composite primary key cannot catch, because the key is
	 *   per-post.
	 *
	 * A demoted side is expected to own no rows at all, which is what demotion
	 * means.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $origin_post_id  Origin post.
	 * @param int      $forward_post_id Forward post.
	 * @param string[] $origin_ids      Identifiers the origin should own.
	 * @param string[] $forward_ids     Identifiers the forward post must own.
	 *
	 * @return WP_Error|null The failure, or null when the partition holds.
	 */
	protected function verify_partition(
		int $origin_post_id,
		int $forward_post_id,
		array $origin_ids,
		array $forward_ids
	): ?WP_Error {
		$owned = static function ( int $post_id ): array {
			return array_map(
				'strval',
				wp_list_pluck(
					Occurrences::get_instance()->select_for_series( array( $post_id ) ),
					'recurrence_id'
				)
			);
		};

		$origin_owned  = $owned( $origin_post_id );
		$forward_owned = $owned( $forward_post_id );

		if (
			$origin_owned !== $origin_ids
			|| array() !== array_diff( $forward_ids, $forward_owned )
			|| array() !== array_intersect( $origin_owned, $forward_owned )
		) {
			return new WP_Error(
				'gatherpress_split_partition_mismatch',
				__( 'The split did not leave the occurrences where it said it would.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		return $this->phase( 'verify_partition', $origin_post_id, $forward_post_id );
	}

	/**
	 * Advance the logical series' calendar revision, and be able to put it back.
	 *
	 * Capping the origin's `RRULE` changes published calendar content without
	 * touching an occurrence row, so nothing else advances the revision for it:
	 * `Occurrences::move_to_post()` announces its own change, but the rule cap
	 * is a bare `postmeta` write. Without this the origin's already-published
	 * `UID` acquires a shorter rule while reporting the same `SEQUENCE`, and a
	 * subscriber keeps the dates the split just moved away.
	 *
	 * The advance is a whole-series operation, so it needs only one post to
	 * reach the others. The seam is still reported both of the split's posts,
	 * because a listener keyed on this phase is told about the same split every
	 * other phase reported and must be able to name the same pair.
	 *
	 * @since 0.36.0
	 *
	 * @param int $origin_post_id  Any post of the series.
	 * @param int $forward_post_id Forward post, reported to the phase seam.
	 *
	 * @return WP_Error|null The failure, or null.
	 */
	protected function advance_revision( int $origin_post_id, int $forward_post_id ): ?WP_Error {
		Revision::get_instance()->advance( $origin_post_id );

		return $this->phase( 'advance_revision', $origin_post_id, $forward_post_id );
	}

	/**
	 * Capture the calendar revision every post of the series is carrying.
	 *
	 * Restored last of all by `roll_back()` rather than by an undo entry of its
	 * own, because the settle step re-projects and re-projecting announces an
	 * occurrence change, which advances the revision again. A rollback has to
	 * put the value back after everything that could move it has run.
	 *
	 * @since 0.36.0
	 *
	 * @param int $origin_post_id Any post of the series.
	 *
	 * @return array<int, mixed> The stored revision of each sibling, keyed by post ID.
	 */
	protected function revision_snapshot( int $origin_post_id ): array {
		$revisions = array();

		foreach ( Series::get_instance()->resolve_post_ids( $origin_post_id ) as $sibling_id ) {
			$revisions[ (int) $sibling_id ] = metadata_exists( 'post', (int) $sibling_id, Revision::META_KEY )
				? get_post_meta( (int) $sibling_id, Revision::META_KEY, true )
				: null;
		}

		return $revisions;
	}

	/**
	 * Drop every cache whose identity the split just changed.
	 *
	 * The resolved-context memo maps an occurrence to the post that owned it,
	 * and the RSVP transients are keyed on `(owner post, recurrence id)`. Both
	 * are exactly what moved. `Rsvp\Cache::delete()` drops the series
	 * key and the occurrence key for each identity it is given.
	 *
	 * @since 0.36.0
	 *
	 * @param int      $origin_post_id  Origin post.
	 * @param int      $forward_post_id Forward post.
	 * @param string[] $identifiers     Every identifier the series had.
	 *
	 * @return void
	 */
	protected function settle_caches( int $origin_post_id, int $forward_post_id, array $identifiers ): void {
		Context::flush_resolved();
		Series::get_instance()->flush_memo();

		foreach ( $identifiers as $recurrence_id ) {
			Rsvp_Cache::delete( $origin_post_id, (string) $recurrence_id );
			Rsvp_Cache::delete( $forward_post_id, (string) $recurrence_id );
		}

		Rsvp_Cache::delete( $origin_post_id );
		Rsvp_Cache::delete( $forward_post_id );
	}

	/**
	 * Register how to undo the phase that just completed.
	 *
	 * @since 0.36.0
	 *
	 * @param callable $undo Reverses that phase.
	 *
	 * @return void
	 */
	protected function record( callable $undo ): void {
		$this->undo[] = $undo;
	}

	/**
	 * Unwind every completed phase, then re-derive the origin.
	 *
	 * The stack unwinds in reverse so each phase sees the state it produced.
	 * Projection is deliberately left until afterwards: the individual undos
	 * restore stored values only, and re-deriving before the rows are back
	 * under the origin would upsert a duplicate of every moved occurrence.
	 *
	 * That final re-derivation is a database write like any other, and the
	 * database that refused a projection during the split can refuse this one
	 * too. A refusal here is reported rather than swallowed: the compensating
	 * undos have already run, so the rows are back, but the origin's rule and
	 * its rows have not been reconciled and a caller told the split simply
	 * failed would have no reason to look. The revisions and caches are settled
	 * first regardless, so a failed re-derivation does not also strand the
	 * calendar revision the split advanced.
	 *
	 * @since 0.36.0
	 *
	 * @param int $origin_post_id Post to re-derive once the stack is empty.
	 *
	 * @return WP_Error|null The re-derivation's failure, or null when the rollback completed.
	 */
	protected function roll_back( int $origin_post_id ): ?WP_Error {
		// A failure before the first durable phase completed has nothing to
		// undo, and re-deriving anyway would advance the calendar revision for a
		// split that never touched the series.
		if ( array() === $this->undo ) {
			return null;
		}

		while ( array() !== $this->undo ) {
			$undo = array_pop( $this->undo );

			$undo();
		}

		Meta::get_instance()->set_recurrence( $origin_post_id );
		Event_Setup::get_instance()->set_datetimes( $origin_post_id );

		$projected = Occurrences::get_instance()->project( $origin_post_id );

		foreach ( $this->revisions as $sibling_id => $value ) {
			if ( null === $value ) {
				delete_post_meta( (int) $sibling_id, Revision::META_KEY );

				continue;
			}

			update_post_meta( (int) $sibling_id, Revision::META_KEY, $value );
		}

		Context::flush_resolved();
		Series::get_instance()->flush_memo();
		Rsvp_Cache::delete( $origin_post_id );

		return is_wp_error( $projected ) ? $projected : null;
	}

	/**
	 * Give a durable phase somewhere to fail from.
	 *
	 * Partial-failure behavior is not otherwise reachable from a test: every
	 * store involved succeeds under ordinary conditions, and simulating a
	 * broken one with DDL would commit the surrounding transaction and leak
	 * fixtures into the rest of the run. The filter is a production extension
	 * point as well. An integration that must veto a split part-way through
	 * gets a full rollback rather than a half-migrated series.
	 *
	 * Every phase reports the same pair of posts, whichever side it acted on.
	 * The forward post is inserted before the first phase fires, so there is no
	 * phase at which it does not exist yet and none that may report anything
	 * else in its place.
	 *
	 * @since 0.36.0
	 *
	 * @param string $phase           Name of the phase that just completed.
	 * @param int    $origin_post_id  Post being split.
	 * @param int    $forward_post_id Post the split created.
	 *
	 * @return WP_Error|null The failure a listener reported, or null.
	 */
	protected function phase( string $phase, int $origin_post_id, int $forward_post_id ): ?WP_Error {
		/**
		 * Filters the outcome of one durable phase of a forward split.
		 *
		 * Returning a `WP_Error` aborts the split and rolls every completed
		 * phase back, leaving posts, occurrence rows, RSVP comments, terms,
		 * meta and caches as they were before the split began.
		 *
		 * The two post IDs name the same pair of posts at every phase, so a
		 * listener can act on either side from any phase name. Both are always
		 * real: the forward post is inserted before the first phase fires.
		 *
		 * @since 0.36.0
		 *
		 * @param WP_Error|null $outcome         Null to continue, a WP_Error to abort and roll back.
		 * @param string        $phase           Name of the phase that just completed.
		 * @param int           $origin_post_id  Post being split.
		 * @param int           $forward_post_id Post the split created.
		 *
		 * @return WP_Error|null The outcome.
		 */
		$outcome = apply_filters(
			'gatherpress_split_phase_complete',
			null,
			$phase,
			$origin_post_id,
			$forward_post_id
		);

		return is_wp_error( $outcome ) ? $outcome : null;
	}

	/**
	 * Build the result payload a split reports back.
	 *
	 * @since 0.36.0
	 *
	 * @param bool   $split          Whether a split actually happened.
	 * @param string $reason         Why it did not, when it did not.
	 * @param int    $origin_post_id Post the series was split from.
	 *
	 * @return array The result payload.
	 */
	protected function result( bool $split, string $reason, int $origin_post_id ): array {
		return array(
			'split'              => $split,
			'reason'             => $reason,
			'origin_post_id'     => $origin_post_id,
			'forward_post_id'    => 0,
			'moved'              => 0,
			'renamed_rsvp_terms' => 0,
			'migrated_rsvps'     => 0,
			'origin_recurring'   => true,
			'forward_recurring'  => false,
		);
	}

	/**
	 * Create the event post the forward half of the series lives on.
	 *
	 * Inserted **without** a recurrence blob deliberately. `Occurrences` projects
	 * on `wp_after_insert_post`, and a forward post that arrived already
	 * recurring would have its own rows generated before the origin's rows could
	 * be moved onto it. The moved rows would then collide with freshly generated
	 * ones on the composite primary key, and the required row recycling would
	 * turn into a delete-and-regenerate after all. The rule is written afterwards,
	 * once the rows are already there for the upsert to find.
	 *
	 * Nothing durable happens before the insert has answered with a real post
	 * ID, so an insertion failure leaves every post, row, comment, term, meta
	 * value and cache exactly as it found them.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_Post $origin_post Post being split.
	 * @param array   $row         Occurrence row the forward post is anchored at.
	 *
	 * @return int|WP_Error The new post ID, or the insertion failure.
	 */
	protected function create_forward_post( WP_Post $origin_post, array $row ) {
		$data = array(
			'meta_input' => array( 'gatherpress_datetime' => $this->datetime_blob( $row ) ),
		);

		foreach ( self::COPIED_POST_FIELDS as $field ) {
			$data[ $field ] = $origin_post->{$field};
		}

		// `true` is the whole point: in its default mode `wp_insert_post()`
		// answers 0 for a rejected insert, an empty-content veto included, and
		// the previous `(int)` cast of that answer let the split move four
		// occurrence rows onto post 0 and report success. In error mode every
		// one of those refusals is a `WP_Error` instead, so there is no zero
		// left to mistake for an ID.
		$inserted = wp_insert_post( $data, true );

		if ( is_wp_error( $inserted ) ) {
			return $inserted;
		}

		$forward_post_id = (int) $inserted;

		$this->copy_meta( (int) $origin_post->ID, $forward_post_id );
		$this->copy_terms( (int) $origin_post->ID, $forward_post_id, (string) $origin_post->post_type );

		// The datetime blob arrived through `meta_input`, which lands before
		// `wp_after_insert_post`, so the events-table row is already written by
		// the time this returns. Writing it again here would be a second,
		// identical write. See `Event\Setup::set_datetimes()`.
		return $forward_post_id;
	}

	/**
	 * Serialize one occurrence row as the event datetime blob.
	 *
	 * @since 0.36.0
	 *
	 * @param array $row Occurrence row.
	 *
	 * @return string The JSON blob.
	 */
	protected function datetime_blob( array $row ): string {
		return (string) wp_json_encode(
			array(
				'dateTimeStart' => (string) $row['datetime_start'],
				'dateTimeEnd'   => (string) $row['datetime_end'],
				'timezone'      => (string) $row['timezone'],
			)
		);
	}

	/**
	 * Copy the origin's post meta onto the forward post, cardinality intact.
	 *
	 * Everything the origin carries comes across: venue overrides, online-event
	 * links, attendance limits, anything a companion plugin stored. Only the
	 * keys this class rewrites and the per-session edit lock are excluded.
	 *
	 * `add_post_meta()` per stored value rather than one `meta_input` entry per
	 * key. `meta_input` takes a single value, so a key an extension had stored
	 * twice arrived on the forward post once and the second value was gone with
	 * no error anywhere.
	 *
	 * @since 0.36.0
	 *
	 * @param int $origin_post_id  Post being split.
	 * @param int $forward_post_id Post the split created.
	 *
	 * @return void
	 */
	protected function copy_meta( int $origin_post_id, int $forward_post_id ): void {
		foreach ( (array) get_post_meta( $origin_post_id ) as $meta_key => $values ) {
			if ( in_array( $meta_key, self::UNCOPIED_META_KEYS, true )
				|| in_array( $meta_key, Meta::DERIVED_META_KEYS, true )
			) {
				continue;
			}

			foreach ( (array) $values as $value ) {
				add_post_meta( $forward_post_id, (string) $meta_key, maybe_unserialize( $value ) );
			}
		}
	}

	/**
	 * Copy the origin's taxonomy terms onto the forward post.
	 *
	 * The venue association and any topics come across, so the venue appears on
	 * every occurrence of the series across a split. The series taxonomy is
	 * excluded: `Series::join()` owns it, and copying it here would put the
	 * forward post in the series before the origin had a term of its own.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $origin_post_id  Post being split.
	 * @param int    $forward_post_id Post the split created.
	 * @param string $post_type       Post type both share.
	 *
	 * @return void
	 */
	protected function copy_terms( int $origin_post_id, int $forward_post_id, string $post_type ): void {
		foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
			if ( Series::TAXONOMY === $taxonomy ) {
				continue;
			}

			$term_ids = wp_get_object_terms( $origin_post_id, $taxonomy, array( 'fields' => 'ids' ) );

			if ( is_wp_error( $term_ids ) || array() === $term_ids ) {
				continue;
			}

			wp_set_object_terms( $forward_post_id, array_map( 'intval', $term_ids ), $taxonomy );
		}
	}

	/**
	 * Cap the origin's rule at the occurrences before the split point.
	 *
	 * Capped by `COUNT`, never by `UNTIL`: the count is exactly the number of
	 * rows staying behind, so re-projection reproduces precisely the rows that
	 * were already there and `delete_stale_rows()` has nothing to delete. An
	 * `UNTIL` bound would have to be derived from a date and would depend on the
	 * rule landing on it.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $post_id    Origin post ID.
	 * @param Rule  $rule       The rule being capped.
	 * @param int   $index      Index of the split occurrence, and so the number of rows staying.
	 * @param array $first_row  The origin's first occurrence row, for the demotion path.
	 *
	 * @return bool|WP_Error True when the origin remains a recurring series, or the write's failure.
	 */
	protected function apply_capped_rule( int $post_id, Rule $rule, int $index, array $first_row ) {
		$values             = $rule->to_array();
		$values['end_type'] = Rule::END_TYPE_COUNT;
		$values['count']    = $index;
		$values['until']    = '';

		// One occurrence left behind is not a series: that side becomes a plain
		// non-recurring event.
		if ( 1 === $index ) {
			$demoted = $this->demote_to_plain_event( $post_id, $first_row, $values );

			return is_wp_error( $demoted ) ? $demoted : ! $demoted;
		}

		return $this->write_rule( $post_id, $values ) ?? true;
	}

	/**
	 * Write the forward post's rule, anchored at the split occurrence.
	 *
	 * A `COUNT` rule's count is reduced by the occurrences left behind; `UNTIL`
	 * and `never` bounds carry across unchanged, because both are absolute and
	 * the forward post's own anchor is what moved.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $post_id      Forward post ID.
	 * @param Rule  $rule         The origin's rule.
	 * @param int   $index        Index of the split occurrence.
	 * @param array $forward_rows Occurrence rows that moved to the forward post.
	 *
	 * @return bool|WP_Error True when the forward post is a recurring series, or the write's failure.
	 */
	protected function apply_forward_rule( int $post_id, Rule $rule, int $index, array $forward_rows ) {
		$values = $rule->to_array();

		if ( Rule::END_TYPE_COUNT === $rule->end_type() ) {
			$values['count'] = $rule->count() - $index;
		}

		// Exactly one occurrence forward is a single-occurrence edit, not a
		// series, but only a `COUNT` rule can be *known* to produce exactly
		// one. An `UNTIL` or `never` rule projected to a single row may simply
		// have run into the projection horizon, and demoting it would silently
		// discard every date beyond it.
		if ( 1 === count( $forward_rows ) && Rule::END_TYPE_COUNT === $rule->end_type() ) {
			$demoted = $this->demote_to_plain_event( $post_id, $forward_rows[0], $values );

			return is_wp_error( $demoted ) ? $demoted : ! $demoted;
		}

		return $this->write_rule( $post_id, $values ) ?? true;
	}

	/**
	 * Write a rule blob and run the production derivation and projection for it.
	 *
	 * `Meta::set_recurrence()` then `Occurrences::project()`, in that order,
	 * because that is the order their `wp_after_insert_post` handlers run in
	 * (priority 10, then 20) and `project()` reads the mirrors the first writes.
	 * Calling them directly rather than through `wp_update_post()` keeps the
	 * split synchronous and leaves `post_modified` alone. The REST response
	 * therefore reports the state it produced, not the state a `shutdown`
	 * handler will produce later.
	 *
	 * Because the projection is synchronous, its refusal is this split's to
	 * handle. `Occurrences::project()` answers a `WP_Error` when the database
	 * refuses a write, and discarding it left the post carrying a rule whose
	 * rows do not exist while every later phase measured the rows that do. The
	 * failure is returned instead, so the caller aborts and the undo stack that
	 * was recorded before this ran takes the blob back off.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $post_id Post to write the rule on.
	 * @param array $values  Rule values, in `Rule::to_array()` shape.
	 *
	 * @return WP_Error|null The projection's failure, or null when the rows were written.
	 */
	protected function write_rule( int $post_id, array $values ): ?WP_Error {
		update_post_meta( $post_id, Meta::META_KEY, wp_json_encode( $values ) );

		Meta::get_instance()->set_recurrence( $post_id );

		$projected = Occurrences::get_instance()->project( $post_id );

		return is_wp_error( $projected ) ? $projected : null;
	}

	/**
	 * Turn a post holding exactly one occurrence into a plain non-recurring event.
	 *
	 * The rule goes, the occurrence row goes, the event's own datetime becomes
	 * that occurrence's, and the occurrence's RSVP term is dropped so its RSVPs
	 * read series-wide again, which on a single-date event *is* that date. No
	 * RSVP is moved, deleted or re-attached.
	 *
	 * **A canceled occurrence is never demoted.** Cancellation is occurrence
	 * state, and a plain event has nowhere to record it: demoting
	 * would silently un-cancel a date the organizer canceled. That side keeps a
	 * one-occurrence rule instead, which is the lesser of the two wrongs and the
	 * only one that loses no information.
	 *
	 * @since 0.36.0
	 *
	 * @param int   $post_id Post to demote.
	 * @param array $row     Its single remaining occurrence row.
	 * @param array $values  Rule values to fall back to when the occurrence is canceled.
	 *
	 * @return bool|WP_Error True when the post was demoted, or the fallback write's failure.
	 */
	protected function demote_to_plain_event( int $post_id, array $row, array $values ) {
		if ( Occurrences::STATUS_SCHEDULED !== (string) $row['status'] ) {
			$values['end_type'] = Rule::END_TYPE_COUNT;
			$values['count']    = 1;
			$values['until']    = '';

			return $this->write_rule( $post_id, $values ) ?? false;
		}

		Meta::get_instance()->remove_recurrence( $post_id );
		Occurrences::get_instance()->delete_for_post( $post_id );
		Rsvp_Occurrence::get_instance()->detach_series( $post_id, array( (string) $row['recurrence_id'] ) );

		update_post_meta( $post_id, 'gatherpress_datetime', $this->datetime_blob( $row ) );

		Event_Setup::get_instance()->set_datetimes( $post_id );

		return true;
	}

	/**
	 * Report which occurrences a candidate rule would strand, and how many RSVPs ride on them.
	 *
	 * Migrating an RSVP to a date the attendee never agreed to is worse than
	 * leaving it where it is, so GatherPress leaves it and tells the organizer
	 * how many are affected before they commit the change. The count is of the
	 * approved RSVPs on the dates the candidate rule would remove.
	 *
	 * @since 0.36.0
	 *
	 * @param int  $post_id Series post ID the candidate rule would be saved on.
	 * @param Rule $rule    Candidate rule.
	 *
	 * @return array{removed: string[], rsvp_count: int} The stranded occurrences and their RSVP count.
	 */
	public function rsvp_impact( int $post_id, Rule $rule ): array {
		$rows     = Occurrences::get_instance()->select_for_series( array( $post_id ) );
		$current  = wp_list_pluck( $rows, 'recurrence_id' );
		$proposed = Occurrences::get_instance()->preview_recurrence_ids( $post_id, $rule );
		$removed  = array_values( array_diff( array_map( 'strval', $current ), $proposed ) );

		return array(
			'removed'    => $removed,
			'rsvp_count' => Rsvp_Occurrence::get_instance()->count_rsvps( $post_id, $removed ),
		);
	}
}
