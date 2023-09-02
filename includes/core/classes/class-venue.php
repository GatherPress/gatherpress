<?php
/**
 * Class responsible for managing Venue instances.
 *
 * This class handles the management of Venue instances, including actions related to Venue post types,
 * Venue taxonomies, and associated operations such as adding Venue terms, updating term slugs, and more.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore Prevent direct access.
}

/**
 * Class Venue.
 *
 * Handles the management of Venue instances, including Venue post types and taxonomies.
 *
 * @since 1.0.0
 */
class Venue {

	use Singleton;

	/**
	 * Constants for Venue Post Type and Taxonomy.
	 *
	 * Defines constants for the Venue post type and its associated taxonomy.
	 *
	 * @since 1.0.0
	 */
	const POST_TYPE = 'gp_venue';
	const TAXONOMY  = '_gp_venue';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @return void
	 * @since 1.0.0
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
	 * Add a venue term when a venue post type is first saved.
	 *
	 * This method is responsible for automatically adding a term to the venue taxonomy
	 * when a new venue post is created and published.
	 *
	 * @since 1.0.0
	 *
	 * @param int     $post_id Post ID of the venue post.
	 * @param WP_Post $post    The venue post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function add_venue_term( int $post_id, WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if (
			! $update &&
			! empty( $post->post_name ) &&
			'publish' === $post->post_status
		) {
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
	 * Update the slug of the corresponding venue term if the venue post's slug changes.
	 *
	 * This method is triggered when a venue post is updated and checks if the slug of the
	 * venue post has changed. If it has changed, it updates the corresponding venue term's
	 * slug to match the new venue post slug.
	 *
	 * @since 1.0.0
	 *
	 * @param int     $post_id     Post ID of the venue post.
	 * @param WP_Post $post_after  Post object after the save operation.
	 * @param WP_Post $post_before Post object before the save operation.
	 *
	 * @return void
	 */
	public function maybe_update_term_slug( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
		// Check if the post type is Venue.
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		// Only proceed if the venue post is being published.
		if ( 'publish' !== $post_after->post_status ) {
			return;
		}

		// Check if the post slug or title has changed.
		if (
			$post_before->post_name !== $post_after->post_name ||
			$post_before->post_title !== $post_after->post_title
		) {
			// Calculate the old and new term slugs.
			$old_term_slug = $this->get_venue_term_slug( $post_before->post_name );
			$new_term_slug = $this->get_venue_term_slug( $post_after->post_name );

			// Decode the title to ensure special characters are handled correctly.
			$title = html_entity_decode( get_the_title( $post_id ) );

			// Check if the old term exists, and if not, insert the new term.
			$term = term_exists( $old_term_slug, self::TAXONOMY );

			if ( empty( $term ) ) {
				wp_insert_term(
					$title,
					self::TAXONOMY,
					array(
						'slug' => $new_term_slug,
					)
				);
			} else {
				// Update the existing term with the new name and slug.
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
	 * Delete the corresponding venue term when a Venue post is deleted.
	 *
	 * This method is triggered when a Venue post is deleted. It checks if the deleted post
	 * is of the Venue post type and, if so, deletes the corresponding venue term associated
	 * with the post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID of the Venue post being deleted.
	 *
	 * @return void
	 */
	public function delete_venue_term( int $post_id ): void {
		// Check if the post type is Venue.
		if ( get_post_type( $post_id ) === self::POST_TYPE ) {
			// Retrieve the post object.
			$post = get_post( $post_id );

			// Generate the term slug associated with the Venue post.
			$term_slug = $this->get_venue_term_slug( $post->post_name );

			// Get the term by slug.
			$term = get_term_by( 'slug', $term_slug, self::TAXONOMY );

			// Check if the term exists and delete it.
			if ( is_a( $term, '\WP_Term' ) ) {
				wp_delete_term( $term->term_id, self::TAXONOMY );
			}
		}
	}

	/**
	 * Generate a term slug for the Venue taxonomy based on the post slug of a Venue post.
	 *
	 * This method generates a unique term slug for the Venue taxonomy by incorporating
	 * the post slug of a Venue post. It is used to create and identify the corresponding
	 * venue term associated with a Venue post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_name Post name (slug) of the Venue post.
	 *
	 * @return string The generated term slug.
	 */
	public function get_venue_term_slug( string $post_name ): string {
		// Generate the term slug by prefixing it with an underscore.
		return sprintf( '_%s', $post_name );
	}

	/**
	 * Retrieve the Venue Custom Post Type (CPT) from a Venue taxonomy slug.
	 *
	 * This method retrieves the Venue Custom Post Type (CPT) associated with a given
	 * Venue taxonomy slug. It allows you to obtain the Venue post object based on the
	 * taxonomy slug used for venues.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Slug of the Venue taxonomy to retrieve the Venue post.
	 *
	 * @return null|WP_Post The Venue post object if found; otherwise, null.
	 */
	public function get_venue_post_from_term_slug( string $slug ): ?WP_Post {
		// Remove any leading underscores from the slug and retrieve the corresponding Venue post.
		return get_page_by_path( ltrim( $slug, '_' ), OBJECT, self::POST_TYPE );
	}

	/**
	 * Retrieve venue information from meta data.
	 *
	 * This method retrieves and assembles venue-related information from meta data
	 * associated with a given post. The returned array includes details such as the
	 * venue's name, whether it's an online event term, an online event link, and any
	 * additional venue information stored as JSON in post meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id   The post ID for which to retrieve venue information.
	 * @param string $post_type The post type of the provided post ID.
	 *
	 * @return array An array containing venue-related information.
	 */
	public function get_venue_meta( int $post_id, string $post_type ): array {
		$venue_post = null;
		$venue_slug = null;
		$venue_meta = array();

		$venue_meta['isOnlineEventTerm'] = false;
		$venue_meta['onlineEventLink']   = '';

		if ( Event::POST_TYPE === $post_type ) {
			$event       = new Event( $post_id );
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
			$venue_meta         = array_merge(
				$venue_meta,
				(array) json_decode( get_post_meta( $venue_post->ID, '_venue_information', true ) )
			);
		}

		return $venue_meta;
	}

}
