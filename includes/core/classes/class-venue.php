<?php
/**
 * Class is responsible for instances of venues.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) { // @codeCoverageIgnore
	exit; // @codeCoverageIgnore
}

/**
 * Class Venue.
 */
class Venue {

	use Singleton;

	const POST_TYPE = 'gp_venue';
	const TAXONOMY  = '_gp_venue';

	/**
	 * Venue constructor.
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_venue_term' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'delete_venue_term' ) );
	}

	/**
	 * Update or insert a Venue taxonomy term for event queries.
	 *
	 * @param int      $post_id Post ID of venue.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function save_venue_term( int $post_id, \WP_Post $post ): void {
		if ( isset( $post->post_status ) && 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		if ( false !== wp_is_post_revision( $post_id ) ) {
			return;
		}

		$term_slug = $this->get_venue_term_slug( $post_id );
		$term      = term_exists( $term_slug, self::TAXONOMY );
		$title     = get_the_title( $post_id );

		if ( empty( $term ) ) {
			wp_insert_term(
				$title,
				self::TAXONOMY,
				array(
					'slug' => $term_slug,
				)
			);
		} else {
			wp_update_term(
				$term['term_id'],
				self::TAXONOMY,
				array(
					'name' => $title,
				)
			);
		}
	}

	/**
	 * Delete a venue term when a Venue is deleted.
	 *
	 * @param int $post_id Post ID of venue.
	 *
	 * @return void
	 */
	public function delete_venue_term( int $post_id ): void {
		if ( get_post_type( $post_id ) === self::POST_TYPE ) {
			$term_slug = $this->get_venue_term_slug( $post_id );
			$term      = get_term_by( 'slug', $term_slug, self::TAXONOMY );

			if ( is_a( $term, '\WP_Term' ) ) {
				wp_delete_term( $term->term_id, self::TAXONOMY );
			}
		}
	}

	/**
	 * Term slug for venue taxonomy which includes Post ID of venue CPT.
	 *
	 * @param int $post_id Post ID of venue.
	 *
	 * @return string
	 */
	public function get_venue_term_slug( int $post_id ): string {
		return sprintf( '_venue_%d', $post_id );
	}

	/**
	 * Get the Venue CPT ID from Venue taxonomy slug.
	 *
	 * @param string $slug Slug of venue taxonomy to retrieve post type ID.
	 *
	 * @return int
	 */
	public function get_venue_id_from_slug( string $slug ): int {
		return intval( str_replace( '_venue_', '', $slug ) );
	}

}
