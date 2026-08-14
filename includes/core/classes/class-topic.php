<?php
/**
 * Class responsible for managing Topic instances.
 *
 * This class facilitates the management of the Topic taxonomy within the context of the Event post type.
 *
 * @package GatherPress\Core
 * @since 0.29.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use stdClass;

/**
 * Class Topic.
 *
 * Manages Topic taxonomy for the GatherPress Event post type, including registration and administration.
 *
 * @since 0.29.0
 */
final class Topic {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The taxonomy name for GatherPress event topics.
	 *
	 * @since 0.29.0
	 * @var string $TAXONOMY
	 */
	const TAXONOMY = 'gatherpress_topic';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.29.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Priority 11 so post types registered at default priority 10 are available for get_post_types_by_support().
		add_action( 'init', array( $this, 'register_taxonomy' ), 11 );
		add_action( 'registered_post_type', array( $this, 'maybe_attach_to_post_type' ) );
	}

	/**
	 * Attach Topics to a post type that declares event-date support.
	 *
	 * Companion to the priority-11 sweep in {@see self::register_taxonomy()}:
	 * that one catches post types already in the registry, this one catches
	 * the ones registered afterwards, including any registered outside `init`
	 * altogether.
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type that was just registered.
	 *
	 * @return void
	 */
	public function maybe_attach_to_post_type( string $post_type ): void {
		if ( ! taxonomy_exists( self::TAXONOMY ) || ! post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
			return;
		}

		register_taxonomy_for_object_type( self::TAXONOMY, $post_type );
	}

	/**
	 * Registers the Topic taxonomy for every event post type.
	 *
	 * Sets up the Topic taxonomy with labels and settings for admin visibility, REST API support,
	 * and hierarchical structuring. This method ensures Topics are properly integrated within
	 * WordPress for management and querying.
	 *
	 * The taxonomy is attached to every post type declaring `gatherpress-event-date`
	 * rather than to `gatherpress_event` alone, so a custom event post type is
	 * taggable with Topics the way it already gets datetimes, RSVPs and venues.
	 * It registers even when nothing declares that support: the settings screen,
	 * the calendar feeds and the topic archive all read the taxonomy itself, and
	 * an empty object-type list still leaves it registered.
	 *
	 * @since 0.29.0
	 * @since TBD Attaches to every post type supporting `gatherpress-event-date`.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get( 'topics_url' );
		$post_types   = get_post_types_by_support( 'gatherpress-event-date' );
		register_taxonomy(
			self::TAXONOMY,
			$post_types,
			array(
				'labels'            => array(
					'name'                       => _x(
						'Topics',
						'Admin menu and taxonomy general name',
						'gatherpress'
					),
					'singular_name'              => _x(
						'Topic',
						'Admin menu and taxonomy singular name',
						'gatherpress'
					),
					'search_items'               => __( 'Search Topics', 'gatherpress' ),
					'popular_items'              => __( 'Popular Topics', 'gatherpress' ),
					'all_items'                  => __( 'All Topics', 'gatherpress' ),
					'parent_item'                => __( 'Parent Topic', 'gatherpress' ),
					'parent_item_colon'          => __( 'Parent Topic:', 'gatherpress' ),
					'edit_item'                  => __( 'Edit Topic', 'gatherpress' ),
					'view_item'                  => __( 'View Topic', 'gatherpress' ),
					'update_item'                => __( 'Update Topic', 'gatherpress' ),
					'add_new_item'               => __( 'Add New Topic', 'gatherpress' ),
					'new_item_name'              => __( 'New Topic Name', 'gatherpress' ),
					'separate_items_with_commas' => __( 'Separate topics with commas', 'gatherpress' ),
					'add_or_remove_items'        => __( 'Add or remove topics', 'gatherpress' ),
					'choose_from_most_used'      => __( 'Choose from the most used topics', 'gatherpress' ),
					'not_found'                  => __( 'No Topics Found', 'gatherpress' ),
					'no_terms'                   => __( 'No topics', 'gatherpress' ),
					'filter_by_item'             => __( 'Filter by topic', 'gatherpress' ),
					'items_list_navigation'      => __( 'Topics list navigation', 'gatherpress' ),
					'items_list'                 => __( 'Topics list', 'gatherpress' ),
					'back_to_items'              => __( 'Back to Topics', 'gatherpress' ),
					'item_link'                  => _x( 'Topic Link', 'Navigation link block title', 'gatherpress' ),
					'item_link_description'      => _x(
						'A link to a topic.',
						'Navigation link block description',
						'gatherpress'
					),
					'menu_name'                  => __( 'Topics', 'gatherpress' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => $rewrite_slug,
					'with_front' => false,
				),
				'show_in_rest'      => true,
			)
		);

		// register_taxonomy() alone never fires `registered_taxonomy_for_object_type`;
		// these explicit calls do, giving extenders a hook for each pairing (#1639).
		foreach ( $post_types as $post_type ) {
			register_taxonomy_for_object_type( self::TAXONOMY, $post_type );
		}
	}

	/**
	 * Returns the taxonomy slug localized for the site language and sanitized as URL part.
	 *
	 * Do not use this directly, use get( 'topics_url' ) instead.
	 *
	 * This method switches to the sites default language and gets the translation of 'topics' for the loaded locale.
	 * After that, the method sanitizes the string to be safely used within an URL,
	 * by removing accents, replacing special characters and replacing whitespace with dashes.
	 *
	 * @since 0.31.0
	 *
	 * @return string
	 */
	public static function get_localized_taxonomy_slug(): string {
		$switched_locale = switch_to_locale( get_locale() );

		// The taxonomy (to get the singular name from) is typically not registered, when this method is called.
		// Using Utility::taxonomy_label() will not yet work.

		// Prepare a default at first.
		$default_labels                = new stdClass();
		$default_labels->singular_name = _x(
			'Topic',
			'Admin menu and taxonomy singular name',
			'gatherpress'
		);

		// To ensure, we use the proper labels, we get them from the WordPress core filter.
		$taxonomy_labels = apply_filters(
			sprintf( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
				'taxonomy_labels_%s',
				self::TAXONOMY
			),
			$default_labels
		);

		$slug = sanitize_title( $taxonomy_labels->singular_name );

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		return $slug;
	}
}
