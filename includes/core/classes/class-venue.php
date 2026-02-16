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

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Venue.
 *
 * Handles the management of Venue instances, including Venue post types and taxonomies.
 *
 * @since 1.0.0
 */
class Venue {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Venue Post Type.
	 *
	 * This constant defines the post type for venues in your application.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const POST_TYPE = 'gatherpress_venue';

	/**
	 * Constant representing the Venue Taxonomy.
	 *
	 * This constant defines the associated taxonomy for venues.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const TAXONOMY = '_gatherpress_venue';

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
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action(
			sprintf( 'save_post_%s', self::POST_TYPE ),
			array( $this, 'add_venue_term' ),
			10,
			3
		);
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'post_updated', array( $this, 'maybe_update_term_slug' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'delete_venue_term' ) );
	}

	/**
	 * Registers the custom post type for Venues.
	 *
	 * Initializes the Venues post type with all its associated labels, settings,
	 * and supports features, making it accessible within the WordPress REST API,
	 * searchable, and manageable within the custom 'Venues' menu in the dashboard.
	 * It is designed to handle venue information for events, including titles,
	 * descriptions, images, and custom fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get_value( 'general', 'urls', 'venues' );
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'                     => _x(
						'Venues',
						'Admin menu and post type general name',
						'gatherpress'
					),
					'singular_name'            => _x(
						'Venue',
						'Admin menu and post type singular name',
						'gatherpress'
					),
					'add_new'                  => __( 'Add New', 'gatherpress' ),
					'add_new_item'             => __( 'Add New Venue', 'gatherpress' ),
					'edit_item'                => __( 'Edit Venue', 'gatherpress' ),
					'new_item'                 => __( 'New Venue', 'gatherpress' ),
					'view_item'                => __( 'View Venue', 'gatherpress' ),
					'view_items'               => __( 'View Venues', 'gatherpress' ),
					'search_items'             => __( 'Search Venues', 'gatherpress' ),
					'not_found'                => __( 'No Venues found.', 'gatherpress' ),
					'not_found_in_trash'       => __( 'No Venues found in Trash.', 'gatherpress' ),
					'parent_item_colon'        => __( 'Parent Venues:', 'gatherpress' ),
					'all_items'                => __( 'Venues', 'gatherpress' ),
					'archives'                 => __( 'Venue Archives', 'gatherpress' ),
					'attributes'               => __( 'Venue Attributes', 'gatherpress' ),
					'insert_into_item'         => __( 'Insert into Venue', 'gatherpress' ),
					'uploaded_to_this_item'    => __( 'Uploaded to this Venue', 'gatherpress' ),
					'menu_name'                => _x( 'Venues', 'Admin menu label', 'gatherpress' ),
					'filter_items_list'        => __( 'Filter Venue list', 'gatherpress' ),
					'filter_by_date'           => __( 'Filter by date', 'gatherpress' ),
					'items_list_navigation'    => __( 'Venues list navigation', 'gatherpress' ),
					'items_list'               => __( 'Venues list', 'gatherpress' ),
					'item_published'           => __( 'Venue published.', 'gatherpress' ),
					'item_published_privately' => __( 'Venue published privately.', 'gatherpress' ),
					'item_reverted_to_draft'   => __( 'Venue reverted to draft.', 'gatherpress' ),
					'item_trashed'             => __( 'Venue trashed.', 'gatherpress' ),
					'item_scheduled'           => __( 'Venue scheduled.', 'gatherpress' ),
					'item_updated'             => __( 'Venue updated.', 'gatherpress' ),
					'item_link'                => _x( 'Venue Link', 'Block editor link label', 'gatherpress' ),
					'item_link_description'    => _x(
						'A link to a venue.',
						'Block editor link description',
						'gatherpress'
					),
				),
				'show_in_rest' => true,
				'rest_base'    => 'gatherpress_venues',
				'public'       => true,
				'hierarchical' => false,
				'show_in_menu' => 'edit.php?post_type=gatherpress_event',
				'supports'     => array(
					'title',
					'author',
					'editor',
					'thumbnail',
					'revisions',
					'custom-fields',
				),
				'menu_icon'    => 'dashicons-location',
				'template'     => array(
					array( 'core/pattern', array( 'slug' => 'gatherpress/venue-template' ) ),
				),
				'has_archive'  => true,
				'rewrite'      => array(
					'slug'       => $rewrite_slug,
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Authorization callback for post meta that requires edit_posts capability.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if user can edit posts, false otherwise.
	 */
	public function can_edit_posts_meta(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Returns the post type slug localized for the site language and sanitized as URL part.
	 *
	 * Do not use this directly, use get_value( 'general', 'urls', 'venues' ) instead.
	 *
	 * This method switches to the sites default language and gets the translation of 'venues' for the loaded locale.
	 * After that, the method sanitizes the string to be safely used within an URL,
	 * by removing accents, replacing special characters and replacing whitespace with dashes.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_localized_post_type_slug(): string {
		$switched_locale = switch_to_locale( get_locale() );
		$slug            = _x( 'Venue', 'Admin menu and post type singular name', 'gatherpress' );
		$slug            = sanitize_title( $slug );

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		return $slug;
	}

	/**
	 * Registers custom meta fields for the Venue post type.
	 *
	 * Sets up meta fields associated with the Venue post type, such as 'venue_information',
	 * configuring capabilities, sanitization, and REST API visibility. This method ensures
	 * that only users with the appropriate permissions can edit these fields, and that
	 * the data stored is properly sanitized. Each meta field is registered to be
	 * single and of a string type, optimized for use within the WordPress REST API.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		$post_meta = array(
			// Venue information stored as JSON.
			'gatherpress_venue_information' => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
			),
			'gatherpress_venue_online_link' => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'sanitize_url',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
			),
			// Map display settings.
			'gatherpress_venue_map_show'    => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'default'           => true,
			),
			'gatherpress_venue_map_zoom'    => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 10,
			),
			'gatherpress_venue_map_height'  => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 300,
			),
		);

		foreach ( $post_meta as $meta_key => $args ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				$args
			);
		}
	}

	/**
	 * Registers a custom taxonomy for the Venue post type, not accessible to users.
	 *
	 * This taxonomy, programmatically managed and linked to the Venue post type, is hidden from users
	 * and designed for internal purposes only. Slugs for taxonomy terms are prefixed with an underscore,
	 * emphasizing their programmatic nature and ensuring they remain uneditable through the WordPress UI.
	 * It supports query var and REST API interactions but is entirely excluded from the admin UI
	 * and user-facing interfaces.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			self::TAXONOMY,
			Event::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => _x( 'Venues', 'Admin menu and taxonomy general name', 'gatherpress' ),
					'singular_name' => _x( 'Venue', 'Admin menu and taxonomy singular name', 'gatherpress' ),
				),
				'hierarchical'       => false,
				'public'             => true,
				'show_ui'            => false,
				'show_admin_column'  => false,
				'query_var'          => true,
				'publicly_queryable' => false,
				'show_in_rest'       => true,
			)
		);
		// It is necessary to make this taxonomy visible on event posts, within REST responses.
		register_taxonomy_for_object_type( self::TAXONOMY, Event::POST_TYPE );
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
	 * @return void
	 */
	public function add_venue_term( int $post_id, WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { // @codeCoverageIgnore
			return; // @codeCoverageIgnore
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
	 * @return void
	 */
	public function maybe_update_term_slug( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
		// Check if the post type is Venue.
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		// Only proceed if the venue post is being published or trashed.
		if ( ! in_array(
			$post_after->post_status,
			array(
				'publish',
				'trash',
			),
			true
		) ) {
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
					intval( $term['term_id'] ),
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
	 * @return null|WP_Post The Venue post object if found; otherwise, null.
	 */
	public function get_venue_post_from_term_slug( string $slug ): ?WP_Post {
		// Remove any leading underscores from the slug and retrieve the corresponding Venue post.
		return get_page_by_path( ltrim( $slug, '_' ), OBJECT, self::POST_TYPE );
	}

	/**
	 * Retrieves the Venue Custom Post Type (CPT) from a given Event post ID.
	 *
	 * This method fetches the terms attached to the Event post in the Venue taxonomy,
	 * and returns the post associated with the first related Venue term.
	 * Returns null if no Venue is found.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Event post ID to get the first venue from.
	 * @return null|WP_Post The Venue post object if found; otherwise, null.
	 */
	public function get_venue_post_from_event_post_id( int $post_id ): ?WP_Post {
		$venue_terms = get_the_terms( $post_id, self::TAXONOMY );
		if ( ! is_array( $venue_terms ) || empty( $venue_terms ) ) {
			return null;
		}
		// Assuming that we have only ONE venue related.
		return $this->get_venue_post_from_term_slug( $venue_terms[0]->slug );
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
				$venue_post = $this->get_venue_post_from_term_slug( $venue_slug );
			}

			$venue_meta['isOnlineEventTerm'] = ( 'online-event' === $venue_slug );
			$venue_meta['onlineEventLink']   = $event->maybe_get_online_event_link();
		}

		if ( self::POST_TYPE === $post_type ) {
			$venue_post = get_post( $post_id );
		}

		if ( is_a( $venue_post, 'WP_Post' ) ) {
			$venue_meta['name'] = get_the_title( $venue_post );

			// Get venue information from JSON field.
			$venue_info_json = get_post_meta( $venue_post->ID, 'gatherpress_venue_information', true );
			$venue_info      = json_decode( $venue_info_json, true );

			if ( is_array( $venue_info ) ) {
				$venue_meta['fullAddress'] = $venue_info['fullAddress'] ?? '';
				$venue_meta['phoneNumber'] = $venue_info['phoneNumber'] ?? '';
				$venue_meta['website']     = $venue_info['website'] ?? '';
				$venue_meta['latitude']    = $venue_info['latitude'] ?? '';
				$venue_meta['longitude']   = $venue_info['longitude'] ?? '';
			} else {
				// Fallback to empty values if JSON parse fails.
				$venue_meta['fullAddress'] = '';
				$venue_meta['phoneNumber'] = '';
				$venue_meta['website']     = '';
				$venue_meta['latitude']    = '';
				$venue_meta['longitude']   = '';
			}

			$venue_meta['onlineEventLink'] = get_post_meta( $venue_post->ID, 'gatherpress_venue_online_link', true );
		}

		return $venue_meta;
	}
}
