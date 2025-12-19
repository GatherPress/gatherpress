<?php
/**
 * Class responsible for managing Topic instances.
 *
 * This class facilitates the management of the Topic taxonomy within the context of the Event post type.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Topic.
 *
 * Manages Topic taxonomy for the GatherPress Event post type, including registration and administration.
 *
 * @since 1.0.0
 */
class Topic {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The taxonomy name for GatherPress event topics.
	 *
	 * @since 1.0.0
	 * @var string $TAXONOMY
	 */
	const TAXONOMY = 'gatherpress_topic';

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
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Registers the Topic taxonomy for the Event post type.
	 *
	 * Sets up the Topic taxonomy with labels and settings for admin visibility, REST API support,
	 * and hierarchical structuring. This method ensures Topics are properly integrated within
	 * WordPress for management and querying.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get_value( 'general', 'urls', 'topics' );
		register_taxonomy(
			self::TAXONOMY,
			Event::POST_TYPE,
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
	}

	/**
	 * Returns the taxonomy slug localized for the site language and sanitized as URL part.
	 *
	 * Do not use this directly, use get_value( 'general', 'urls', 'topics' ) instead.
	 *
	 * This method switches to the sites default language and gets the translation of 'topics' for the loaded locale.
	 * After that, the method sanitizes the string to be safely used within an URL,
	 * by removing accents, replacing special characters and replacing whitespace with dashes.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_localized_taxonomy_slug(): string {
		$switched_locale = switch_to_locale( get_locale() );
		$slug            = _x( 'Topic', 'Admin menu and taxonomy singular name', 'gatherpress' );
		$slug            = sanitize_title( $slug );

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		return $slug;
	}
}
