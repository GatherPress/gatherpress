<?php
/**
 * Base class for RSVP flags.
 *
 * This file defines the abstract `Base` class. A flag is a yes/no marker on an
 * RSVP, such as whether the responder checked in. Each flag is a subclass that
 * names its slug; this class supplies storage, reads, counts and hooks.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since 0.36.0
 */

namespace GatherPress\Core\Rsvp\Flag;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Query;

/**
 * Class Base.
 *
 * A flag is a term in the `_gatherpress_rsvp_flag` taxonomy attached to an
 * RSVP comment. Its presence means yes and its absence means no, so existing
 * RSVPs need no backfill. Every flag shares the one taxonomy, so all writes go
 * through `add()` and `remove()`: calling `wp_set_object_terms()` on it without
 * `$append = true` replaces every other flag on the RSVP.
 *
 * @since 0.36.0
 */
abstract class Base {

	/**
	 * Taxonomy that holds RSVP flags.
	 *
	 * @since 0.36.0
	 *
	 * @var string
	 */
	public const TAXONOMY = '_gatherpress_rsvp_flag';

	/**
	 * Get the slug for this flag.
	 *
	 * Child classes must implement this method. The slug is stored as the term,
	 * so it must already be what `sanitize_key()` produces: lowercase letters,
	 * digits, hyphens and underscores.
	 *
	 * @since 0.36.0
	 *
	 * @return string
	 */
	abstract protected function get_slug(): string;

	/**
	 * Add this flag to an RSVP.
	 *
	 * Idempotent: adding a flag the RSVP already carries keeps it, leaves every
	 * other flag in place, and announces nothing.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP carries the flag, false when it is not an RSVP,
	 *              the slug is invalid, the taxonomy is not registered yet, or the
	 *              flag could not be stored.
	 */
	public function add( int $rsvp_id ): bool {
		if ( ! $this->can_write( $rsvp_id ) ) {
			return false;
		}

		if ( ! $this->has( $rsvp_id ) ) {
			// Nothing was stored, so there is nothing to announce.
			if ( is_wp_error( wp_set_object_terms( $rsvp_id, $this->get_slug(), self::TAXONOMY, true ) ) ) {
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
			do_action( 'gatherpress_rsvp_flag_added', $rsvp_id, $this->get_slug() );

			$this->after_add( $rsvp_id );
		}

		return true;
	}

	/**
	 * Remove this flag from an RSVP.
	 *
	 * Idempotent: removing a flag the RSVP does not carry counts as done and
	 * announces nothing.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the RSVP does not carry the flag, false when it is not
	 *              an RSVP, the slug is invalid, the taxonomy is not registered yet,
	 *              or the flag could not be removed.
	 */
	public function remove( int $rsvp_id ): bool {
		if ( ! $this->can_write( $rsvp_id ) ) {
			return false;
		}

		if ( $this->has( $rsvp_id ) ) {
			// Nothing was removed, so there is nothing to announce.
			if ( true !== wp_remove_object_terms( $rsvp_id, $this->get_slug(), self::TAXONOMY ) ) {
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
			do_action( 'gatherpress_rsvp_flag_removed', $rsvp_id, $this->get_slug() );

			$this->after_remove( $rsvp_id );
		}

		return true;
	}

	/**
	 * Whether an RSVP carries this flag.
	 *
	 * Reads every flag on the RSVP through the object term cache, so a screen
	 * checking several flags on one RSVP queries once.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the flag is on the RSVP.
	 */
	public function has( int $rsvp_id ): bool {
		return in_array( $this->get_slug(), Setup::get_instance()->get_flags( $rsvp_id ), true );
	}

	/**
	 * How many RSVPs on an event carry this flag.
	 *
	 * Counts approved RSVPs only, matching what the attendee list shows: a held
	 * or spammed RSVP is not part of the event's audience even if it was
	 * flagged before it was moderated.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return int Number of approved RSVPs carrying the flag.
	 */
	public function count( int $post_id ): int {
		if ( ! $this->is_valid_slug( $this->get_slug() ) ) {
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
						'terms'    => $this->get_slug(),
					),
				),
			)
		);

		return (int) $count;
	}

	/**
	 * Runs after this flag is added to an RSVP that did not carry it.
	 *
	 * Child classes override this to fire their own actions or side effects.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function after_add( int $rsvp_id ): void {
		// Nothing by default.
	}

	/**
	 * Runs after this flag is removed from an RSVP that carried it.
	 *
	 * Child classes override this to fire their own actions or side effects.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	protected function after_remove( int $rsvp_id ): void {
		// Nothing by default.
	}

	/**
	 * Whether this flag can be written to an RSVP.
	 *
	 * Both writers refuse until the taxonomy is registered on `init`. Before
	 * then every read comes back empty, so `remove()` would report a flag gone
	 * that is still stored.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return bool True when the slug is valid, the taxonomy exists, and the comment is an RSVP.
	 */
	protected function can_write( int $rsvp_id ): bool {
		return $this->is_valid_slug( $this->get_slug() )
			&& taxonomy_exists( self::TAXONOMY )
			&& Rsvp::is_comment_type( $rsvp_id );
	}

	/**
	 * Whether a string is a usable flag slug.
	 *
	 * Only a slug that `sanitize_key()` leaves untouched is accepted, so the
	 * term stored is exactly the slug the class declares.
	 *
	 * @since 0.36.0
	 *
	 * @param string $slug The flag slug.
	 *
	 * @return bool True when the slug is non-empty and already sanitized.
	 */
	protected function is_valid_slug( string $slug ): bool {
		return '' !== $slug && sanitize_key( $slug ) === $slug;
	}
}
