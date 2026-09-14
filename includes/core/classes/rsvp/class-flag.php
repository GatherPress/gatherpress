<?php
/**
 * Stores per-RSVP markers in one shared taxonomy.
 *
 * This file defines the `Flag` class, the single place GatherPress and
 * companion plugins record yes/no state about an RSVP, such as whether the
 * responder checked in.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.36.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Comment;

/**
 * Stores per-RSVP markers in one shared taxonomy.
 *
 * A flag is a term in the `_gatherpress_rsvp_flag` taxonomy attached to an
 * RSVP comment. Its presence means yes and its absence means no, so existing
 * RSVPs need no backfill. Slugs are free-form rather than registered, so a
 * companion plugin can add one without coordinating with GatherPress.
 *
 * Every write goes through this class. Calling `wp_set_object_terms()` on the
 * taxonomy without `$append = true` replaces every other flag on the RSVP.
 *
 * @since 0.36.0
 */
final class Flag {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Taxonomy that holds RSVP flags.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	public const TAXONOMY = '_gatherpress_rsvp_flag';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.36.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'deleted_comment', array( $this, 'delete_flags' ), 10, 2 );
	}

	/**
	 * Add a flag to an RSVP.
	 *
	 * Idempotent: adding a flag the RSVP already carries keeps it, leaves every
	 * other flag in place, and announces nothing.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $rsvp_id The RSVP comment ID.
	 * @param string $flag    The flag slug: lowercase letters, digits, hyphens and underscores.
	 *
	 * @return bool True when the RSVP carries the flag, false when it is not an RSVP,
	 *              the slug is invalid, the taxonomy is not registered yet, or the
	 *              flag could not be stored.
	 */
	public function add( int $rsvp_id, string $flag ): bool {
		if ( ! $this->can_write( $rsvp_id, $flag ) ) {
			return false;
		}

		if ( ! $this->has( $rsvp_id, $flag ) ) {
			// Nothing was stored, so there is nothing to announce.
			if ( is_wp_error( wp_set_object_terms( $rsvp_id, $flag, self::TAXONOMY, true ) ) ) {
				return false;
			}

			clean_comment_cache( $rsvp_id );

			/**
			 * Fires after a flag has been added to an RSVP.
			 *
			 * @since 0.36.0
			 *
			 * @param int    $rsvp_id The RSVP comment ID.
			 * @param string $flag    The flag slug.
			 *
			 * @return void
			 */
			do_action( 'gatherpress_rsvp_flag_added', $rsvp_id, $flag );
		}

		return true;
	}

	/**
	 * Remove a flag from an RSVP.
	 *
	 * Idempotent: removing a flag the RSVP does not carry counts as done and
	 * announces nothing.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $rsvp_id The RSVP comment ID.
	 * @param string $flag    The flag slug.
	 *
	 * @return bool True when the RSVP does not carry the flag, false when it is not
	 *              an RSVP, the slug is invalid, the taxonomy is not registered yet,
	 *              or the flag could not be removed.
	 */
	public function remove( int $rsvp_id, string $flag ): bool {
		if ( ! $this->can_write( $rsvp_id, $flag ) ) {
			return false;
		}

		if ( $this->has( $rsvp_id, $flag ) ) {
			// Nothing was removed, so there is nothing to announce.
			if ( true !== wp_remove_object_terms( $rsvp_id, $flag, self::TAXONOMY ) ) {
				return false;
			}

			clean_comment_cache( $rsvp_id );

			/**
			 * Fires after a flag has been removed from an RSVP.
			 *
			 * @since 0.36.0
			 *
			 * @param int    $rsvp_id The RSVP comment ID.
			 * @param string $flag    The flag slug.
			 *
			 * @return void
			 */
			do_action( 'gatherpress_rsvp_flag_removed', $rsvp_id, $flag );
		}

		return true;
	}

	/**
	 * Whether an RSVP carries a flag.
	 *
	 * Compares exact slugs rather than using `is_object_in_term()`, which also
	 * matches term names and treats a numeric string as a term ID.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $rsvp_id The RSVP comment ID.
	 * @param string $flag    The flag slug.
	 *
	 * @return bool True when the flag is on the RSVP.
	 */
	public function has( int $rsvp_id, string $flag ): bool {
		return in_array( $flag, $this->get( $rsvp_id ), true );
	}

	/**
	 * Every flag on an RSVP.
	 *
	 * One query for all of them, so a screen showing several flags reads the
	 * RSVP once rather than once per flag.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return string[] Flag slugs, empty when there are none.
	 */
	public function get( int $rsvp_id ): array {
		$flags = wp_get_object_terms( $rsvp_id, self::TAXONOMY, array( 'fields' => 'slugs' ) );

		return is_array( $flags ) ? array_values( array_filter( $flags, 'is_string' ) ) : array();
	}

	/**
	 * How many RSVPs on an event carry a flag.
	 *
	 * Counts approved RSVPs only, matching what the attendee list shows: a held
	 * or spammed RSVP is not part of the event's audience even if it was
	 * flagged before it was moderated.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $flag    The flag slug.
	 *
	 * @return int Number of approved RSVPs carrying the flag.
	 */
	public function count( int $post_id, string $flag ): int {
		if ( ! $this->is_valid_flag( $flag ) ) {
			return 0;
		}

		$count = Query::get_instance()->get_rsvps(
			array(
				'post_id'   => $post_id,
				'status'    => 'approve',
				'count'     => true,
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'slug',
						'terms'    => $flag,
					),
				),
			)
		);

		return (int) $count;
	}

	/**
	 * Drop every flag when its RSVP is deleted.
	 *
	 * WordPress core deletes commentmeta on `wp_delete_comment()` but never
	 * touches term relationships, so without this the flags would be left
	 * behind as orphaned rows. Other comment types carry no flags and are
	 * skipped without a query.
	 *
	 * @since 0.36.0
	 *
	 * @param int|string $comment_id The deleted comment ID.
	 * @param WP_Comment $comment    The deleted comment.
	 *
	 * @return void
	 */
	public function delete_flags( $comment_id, WP_Comment $comment ): void {
		if ( Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return;
		}

		wp_delete_object_term_relationships( (int) $comment_id, self::TAXONOMY );
		clean_comment_cache( (int) $comment_id );
	}

	/**
	 * Whether a flag can be written to an RSVP.
	 *
	 * Both writers refuse until the taxonomy is registered on `init`. Before
	 * then every read comes back empty, so `remove()` would report a flag gone
	 * that is still stored.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $rsvp_id The RSVP comment ID.
	 * @param string $flag    The flag slug.
	 *
	 * @return bool True when the slug is valid, the taxonomy exists, and the comment is an RSVP.
	 */
	protected function can_write( int $rsvp_id, string $flag ): bool {
		return $this->is_valid_flag( $flag )
			&& taxonomy_exists( self::TAXONOMY )
			&& Rsvp::is_comment_type( $rsvp_id );
	}

	/**
	 * Whether a string is a usable flag slug.
	 *
	 * Only a slug that `sanitize_key()` leaves untouched is accepted, so the
	 * term stored is exactly the flag the caller passed and `get()` hands the
	 * same string back.
	 *
	 * @since 0.36.0
	 *
	 * @param string $flag The flag slug.
	 *
	 * @return bool True when the slug is non-empty and already sanitized.
	 */
	protected function is_valid_flag( string $flag ): bool {
		return '' !== $flag && sanitize_key( $flag ) === $flag;
	}
}
