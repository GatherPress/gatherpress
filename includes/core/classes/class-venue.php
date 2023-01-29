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
use WP_Post;

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
		add_action( 'post_updated', array( $this, 'maybe_update_term_slug' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'delete_venue_term' ) );
	}

	/**
	 * If the slug of venue post changes, update slug for corresponding venue term.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object after save.
	 * @param WP_Post $post_before Post object before save.
	 *
	 * @return void
	 */
	public function maybe_update_term_slug( int $post_id, WP_Post $post_after, WP_Post $post_before ) {
		if (
			$post_before->post_name !== $post_after->post_name ||
			$post_before->post_title !== $post_after->post_title
		) {
			$old_term_slug = $this->get_venue_term_slug( $post_before->post_name );
			$new_term_slug = $this->get_venue_term_slug( $post_after->post_name );
			$title         = get_the_title( $post_id );
			$term          = term_exists( $old_term_slug, self::TAXONOMY );

			if ( empty( $term ) ) {
				wp_insert_term(
					$title,
					self::TAXONOMY,
					array(
						'slug' => $new_term_slug,
					)
				);
			} else {
				wp_update_term(
					$term['term_id'],
					self::TAXONOMY,
					array(
						'name' => $title,
						'slug' => $new_term_slug,
					)
				);
			}
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
			$post      = get_post( $post_id );
			$term_slug = $this->get_venue_term_slug( $post->post_name );
			$term      = get_term_by( 'slug', $term_slug, self::TAXONOMY );

			if ( is_a( $term, '\WP_Term' ) ) {
				wp_delete_term( $term->term_id, self::TAXONOMY );
			}
		}
	}

	/**
	 * Term slug for venue taxonomy which incorporates post slug of venue CPT.
	 *
	 * @param string $post_name Post name of venue.
	 *
	 * @return string
	 */
	public function get_venue_term_slug( string $post_name ): string {
		return sprintf( '_%s', $post_name );
	}

	/**
	 * Get the Venue CPT from Venue taxonomy slug.
	 *
	 * @param string $slug Slug of venue taxonomy to retrieve venue post object.
	 *
	 * @return WP_Post|null
	 */
	public function get_venue_post_from_term_slug( string $slug ) {
		return get_page_by_path( ltrim( '_', $slug ), OBJECT, Venue::POST_TYPE );
	}

}
