<?php
/**
 * Shared wiring for RSVP flags.
 *
 * This file defines the flag `Setup` class, which holds what belongs to every
 * flag rather than to one: reading all flags on an RSVP, and sweeping them
 * when the RSVP is deleted.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since 0.36.0
 */

namespace GatherPress\Core\Rsvp\Flag;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp;
use GatherPress\Core\Traits\Singleton;
use WP_Comment;

/**
 * Class Setup.
 *
 * @since 0.36.0
 */
final class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

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
	 * Every flag on an RSVP.
	 *
	 * Reads through the object term cache the way `is_object_in_term()` does,
	 * and primes it on a miss, so repeated checks on one RSVP query once.
	 *
	 * @since 0.36.0
	 *
	 * @param int $rsvp_id The RSVP comment ID.
	 *
	 * @return string[] Flag slugs, empty when there are none or the taxonomy cannot be read.
	 */
	public function get_flags( int $rsvp_id ): array {
		$terms = get_object_term_cache( $rsvp_id, Base::TAXONOMY );

		if ( false === $terms ) {
			$terms = wp_get_object_terms( $rsvp_id, Base::TAXONOMY, array( 'update_term_meta_cache' => false ) );

			if ( is_array( $terms ) ) {
				wp_cache_set( $rsvp_id, wp_list_pluck( $terms, 'term_id' ), Base::TAXONOMY . '_relationships' );
			}
		}

		return is_array( $terms ) ? array_values( wp_list_pluck( $terms, 'slug' ) ) : array();
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

		wp_delete_object_term_relationships( (int) $comment_id, Base::TAXONOMY );
		clean_comment_cache( (int) $comment_id );
	}
}
