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

	/**
	 * Constants.
	 */
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
	protected function setup_hooks(): void {
		add_action(
			sprintf( 'save_post_%s', self::POST_TYPE ),
			array( $this, 'add_venue_term' ),
			10,
			3
		);
		add_action( 'post_updated', array( $this, 'maybe_update_term_slug' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'delete_venue_term' ) );
	}

	/**
	 * Add venue term when venue post type first saves.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function add_venue_term( int $post_id, WP_Post $post, bool $update ): void {
		if ( ! $update ) {
			$term_slug = $this->get_venue_term_slug( $post->post_name );
			$title     = html_entity_decode( get_the_title( $post_id ) );
			$term      = term_exists( $term_slug, self::TAXONOMY );

			if ( empty( $term ) ) {
				wp_insert_term(
					$title,
					self::TAXONOMY,
					array(
						'slug' => $term_slug,
					)
				);
			}
		}
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
	public function maybe_update_term_slug( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		if (
			$post_before->post_name !== $post_after->post_name ||
			$post_before->post_title !== $post_after->post_title
		) {
			$old_term_slug = $this->get_venue_term_slug( $post_before->post_name );
			$new_term_slug = $this->get_venue_term_slug( $post_after->post_name );
			$title         = html_entity_decode( get_the_title( $post_id ) );
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
	 * @return null|WP_Post
	 */
	public function get_venue_post_from_term_slug( string $slug ): ?WP_Post {
		return get_page_by_path( ltrim( $slug, '_' ), OBJECT, self::POST_TYPE );
	}

	/**
	 * @param int    $post_id
	 * @param string $post_type
	 *
	 * @return array
	 */
	public function get_venue_meta( int $post_id, string $post_type ): array {
		$venue_post = null;
		$venue_slug = null;
		$venue_meta = array();

		$venue_meta['isOnlineEventTerm'] = false;
		$venue_meta['onlineEventLink']   = '';

		if ( Event::POST_TYPE === $post_type ) {
			$event = new Event( $post_id );
			$venue_terms = get_the_terms( $post_id, self::TAXONOMY );

			if ( ! empty( $venue_terms ) && is_array( $venue_terms ) ) {
				$venue_term = $venue_terms[0];
				$venue_slug = $venue_term->slug;

				if ( is_a( $venue_term, 'WP_Term' ) ) {
					$venue_post = $this->get_venue_post_from_term_slug( $venue_slug );
				}
			}

			$venue_meta['isOnlineEventTerm'] = ( 'online-event' === $venue_slug );
			$venue_meta['onlineEventLink']   = $event->maybe_get_online_event_link();
		}

		if ( self::POST_TYPE === $post_type ) {
			$venue_post = get_post( $post_id );
		}

		if ( is_a( $venue_post, 'WP_Post' ) ) {
			$venue_meta['name'] = get_the_title( $venue_post );
			$venue_meta = array_merge(
				$venue_meta,
				(array) json_decode( get_post_meta( $venue_post->ID, '_venue_information', true ) )
			);
		}

		return $venue_meta;
	}

}
