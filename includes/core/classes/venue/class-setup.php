<?php
/**
 * Handles WordPress integration for the Venue post type.
 *
 * This singleton registers the post type, taxonomy, and meta for venues, plus
 * all the cross-cutting save/delete hooks (term creation, template seeding,
 * slug maintenance). Per-venue data accessors live on the `Venue` instance
 * class instead.
 *
 * @package GatherPress\Core\Venue
 * @since 1.0.0
 */

namespace GatherPress\Core\Venue;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Event;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Validate;
use stdClass;
use WP_Block_Patterns_Registry;
use WP_Post;
use WP_REST_Request;
use WP_Term;

/**
 * Class Setup.
 *
 * Registers the Venue post type + taxonomy and wires the WordPress hooks that
 * keep venue data consistent (term lifecycle, template content). Also owns
 * instantiation of the Venue\* sibling singletons (Map, Map_Prewarm) so the
 * outer `Setup::instantiate_classes()` can hand off the whole venue subsystem
 * with a single `Venue\Setup::get_instance()` line — same shape as
 * `Settings::instantiate_classes()`.
 *
 * @since 1.0.0
 */
class Setup {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Suffixes of the structured-address venue meta keys.
	 *
	 * These eight keys are derived from `gatherpress_address` by the async
	 * geocode cron handler — populated server-side from the Photon response,
	 * never written through the REST API. Stored here as the unprefixed
	 * "field" form so consumers can either iterate them directly (e.g.
	 * {@see Venue::get_information()}) or map through {@see Utility::prefix_key()}
	 * to get the full meta keys (e.g. registration in
	 * {@see self::maybe_register_post_meta()}).
	 *
	 * Single source of truth for: meta registration, REST readonly stripping,
	 * cron handler write loop, and `Venue::get_information()` field list.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	public const STRUCTURED_ADDRESS_FIELDS = array(
		'house_number',
		'street',
		'city',
		'county',
		'state',
		'postcode',
		'country',
		'country_code',
	);

	/**
	 * Class constructor.
	 *
	 * Instantiates the sibling Venue\* singletons before wiring hooks so
	 * `Setup::instantiate_classes()` can hand off the whole venue
	 * subsystem with a single `Venue\Setup::get_instance()` line — same
	 * shape as `Settings::instantiate_classes()`.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->instantiate_classes();
		$this->setup_hooks();
	}

	/**
	 * Instantiate each Venue\* sibling singleton.
	 *
	 * Keeps the outer `Setup::instantiate_classes()` slim — adding a new
	 * Venue\* class lands as a single line here rather than edits to
	 * Setup. Each subclass is a singleton, so repeat calls are safe.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Map::get_instance();
		Map_Prewarm::get_instance();
	}

	/**
	 * Set up hooks for post-type, taxonomy, and save-lifecycle integration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action(
			sprintf( 'save_post_%s', Venue::POST_TYPE ),
			array( $this, 'maybe_apply_venue_template' ),
			10,
			3
		);
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'registered_post_type', array( $this, 'maybe_register_post_type_hooks' ) );
		add_action( 'registered_post_type', array( $this, 'maybe_register_post_meta' ) );
		// Priority 11 so post types registered at default priority 10 are available for get_post_types_by_support().
		add_action( 'init', array( $this, 'register_taxonomy' ), 11 );
		// post_updated has no per-type variant in WP core, and we need $post_before
		// for the old/new post_name diff, so this stays on the global hook.
		add_action( 'post_updated', array( $this, 'maybe_update_term_slug' ), 10, 3 );
		add_filter( 'block_editor_settings_all', array( $this, 'add_editor_settings' ) );
	}

	/**
	 * Wire per-post-type lifecycle hooks when a venue post type registers.
	 *
	 * Registration is gated on `gatherpress-venue-information` support so any
	 * post type that declares that support — including companion-plugin
	 * post types — automatically gets the venue term wired to its save and
	 * delete lifecycle, without needing to hook the site-wide
	 * `save_post`/`delete_post` actions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type that was just registered.
	 * @return void
	 */
	public function maybe_register_post_type_hooks( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			return;
		}

		add_action(
			sprintf( 'save_post_%s', $post_type ),
			array( $this, 'add_venue_term' ),
			10,
			3
		);
		add_action(
			sprintf( 'delete_post_%s', $post_type ),
			array( $this, 'delete_venue_term' )
		);
	}

	/**
	 * Adds GatherPress venue configuration to the block editor settings.
	 *
	 * Exposes the venue post type map under settings['gatherpress']['config']['venuePostTypes']
	 * so that the block editor can resolve the correct venue post type for each
	 * event post type without relying on window globals.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The block editor settings array.
	 * @return array The modified block editor settings array.
	 */
	public function add_editor_settings( array $settings ): array {
		if ( ! isset( $settings['gatherpress'] ) ) {
			$settings['gatherpress'] = array();
		}

		if ( ! isset( $settings['gatherpress']['config'] ) ) {
			$settings['gatherpress']['config'] = array();
		}

		$settings['gatherpress']['config']['venuePostTypes'] = $this->get_venue_post_type_map();

		return $settings;
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
		$rewrite_slug = $settings->get( 'venues_url' );
		register_post_type(
			Venue::POST_TYPE,
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
					'gatherpress-venue-information',
					'gatherpress-venue-map',
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
	 * Sanitize callback for venue coordinate meta (latitude / longitude).
	 *
	 * Numeric values within the ±180 range are normalized to a string form via
	 * a float cast (trims whitespace, normalizes scientific notation). Anything
	 * else collapses to an empty string — the "no coords yet" sentinel the
	 * editor and `Map::parse_coord()` already treat as unset.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The submitted value.
	 * @return string Normalized coordinate string, or '' for invalid / unset input.
	 */
	public function sanitize_coordinate( $value ): string {
		return Validate::coordinate( $value ) ? (string) (float) $value : '';
	}

	/**
	 * Registers venue meta fields when a post type declares venue support.
	 *
	 * Meta is registered per support:
	 * - gatherpress-venue-information: address, phone, website, lat/lng as
	 *   individual keys so they can be bound directly via core/post-meta
	 *   block bindings without an intermediate JSON parse step.
	 * - gatherpress-venue-map: map display settings (show, zoom, height).
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type that was just registered.
	 * @return void
	 */
	public function maybe_register_post_meta( string $post_type ): void {
		// Structured-address pieces derived from `gatherpress_address` by an
		// async cron handler that fires only when the address actually
		// changes (via `updated_post_meta` short-circuit on no-op). These
		// are server-populated from the geocoder, so REST writes are denied
		// (`__return_false` auth_callback) and submitted values get stripped
		// in `filter_readonly_meta` rather than triggering a permission
		// error. Read access via REST stays open so JSON-LD / schema.org
		// emitters and downstream API consumers can read them.
		$structured_address_meta = array_map(
			array( Utility::class, 'prefix_key' ),
			self::STRUCTURED_ADDRESS_FIELDS
		);

		$venue_information_meta = array(
			'gatherpress_address'   => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'revisions_enabled' => true,
			),
			'gatherpress_latitude'  => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => array( $this, 'sanitize_coordinate' ),
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'revisions_enabled' => true,
			),
			'gatherpress_longitude' => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => array( $this, 'sanitize_coordinate' ),
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'revisions_enabled' => true,
			),
			'gatherpress_phone'     => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'revisions_enabled' => true,
			),
			'gatherpress_website'   => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'sanitize_url',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'revisions_enabled' => true,
			),
			// Map keeps a per-zoom descriptor map here: { "15": { url,
			// hash }, ... }. Exposed read-only via REST so the block editor
			// can preview the cached static image when the user picks
			// renderMode="static". Writes are denied ­— the server-side
			// pipeline is the only thing allowed to populate this meta.
			Map::META_KEY           => array(
				'auth_callback' => '__return_false',
				'show_in_rest'  => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type'       => 'object',
							'properties' => array(
								'url'  => array( 'type' => 'string' ),
								'hash' => array( 'type' => 'string' ),
							),
						),
					),
				),
				'single'        => true,
				'type'          => 'object',
			),
		);

		$venue_map_meta = array(
			// Map display settings.
			'gatherpress_venue_map_show'   => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'default'           => true,
			),
			'gatherpress_venue_map_zoom'   => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 10,
			),
			'gatherpress_venue_map_height' => array(
				'auth_callback'     => array( Utility::class, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 300,
			),
		);

		if ( post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			$supports_revisions = post_type_supports( $post_type, 'revisions' );

			foreach ( $venue_information_meta as $meta_key => $args ) {
				// revisions_enabled is only valid when the post type supports revisions.
				// Silently drop it for venue post types that opt out (e.g. companion plugins
				// registering a minimal venue post type without revisions support).
				if ( ! $supports_revisions ) {
					unset( $args['revisions_enabled'] );
				}

				register_post_meta( $post_type, $meta_key, $args );
			}

			// Structured-address meta share an identical args shape, so
			// register them in a tight loop rather than duplicating the
			// array literal eight times. `auth_callback` denies REST writes
			// — these are populated by the async geocode cron handler, not
			// the editor.
			$structured_args = array(
				'auth_callback'     => '__return_false',
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'revisions_enabled' => true,
			);

			if ( ! $supports_revisions ) {
				unset( $structured_args['revisions_enabled'] );
			}

			foreach ( $structured_address_meta as $meta_key ) {
				register_post_meta( $post_type, $meta_key, $structured_args );
			}

			// Strip read-only meta from REST requests so the editor can't write it directly.
			add_filter(
				sprintf( 'rest_pre_insert_%s', $post_type ),
				array( $this, 'filter_readonly_meta' ),
				10,
				2
			);
		}

		if ( post_type_supports( $post_type, 'gatherpress-venue-map' ) ) {
			foreach ( $venue_map_meta as $meta_key => $args ) {
				register_post_meta( $post_type, $meta_key, $args );
			}
		}
	}

	/**
	 * Filter out read-only meta from REST API requests.
	 *
	 * Some venue meta keys are populated by server-side pipelines (e.g. the
	 * Map static map descriptors) and should never be written by the
	 * block editor. Values submitted for those keys are silently discarded
	 * rather than triggering a permission error from the __return_false auth
	 * callback.
	 *
	 * @since 1.0.0
	 *
	 * @param stdClass        $prepared_post An object representing a single post prepared for inserting or updating.
	 * @param WP_REST_Request $request       Request object.
	 * @return stdClass The prepared post object.
	 */
	public function filter_readonly_meta( stdClass $prepared_post, WP_REST_Request $request ): stdClass {
		// Structured-address fields are derived from `gatherpress_address`
		// by the async geocode cron handler. REST writes are stripped
		// rather than rejected so a client that PATCHes them alongside
		// editor-writable fields doesn't fail the whole request.
		$readonly_keys = array_merge(
			array( Map::META_KEY ),
			array_map(
				array( Utility::class, 'prefix_key' ),
				self::STRUCTURED_ADDRESS_FIELDS
			)
		);

		$meta = $request->get_param( 'meta' );

		if ( is_array( $meta ) ) {
			foreach ( $readonly_keys as $key ) {
				unset( $meta[ $key ] );
			}

			$request->set_param( 'meta', $meta );
		}

		return $prepared_post;
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
	 * The taxonomy is publicly queryable so that it appears in the Query Loop block's taxonomy
	 * filter controls, while rewrite rules are disabled to prevent public archive URLs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$taxonomy_args = array(
			'labels'             => array(
				'name'          => _x( 'Venues', 'Admin menu and taxonomy general name', 'gatherpress' ),
				'singular_name' => _x( 'Venue', 'Admin menu and taxonomy singular name', 'gatherpress' ),
			),
			'hierarchical'       => false,
			'public'             => true,
			'show_ui'            => false,
			'show_admin_column'  => false,
			'query_var'          => true,
			'publicly_queryable' => true,
			'rewrite'            => false,
			'show_in_rest'       => true,
		);

		// Register one taxonomy per venue post type: '_' . venue_post_type_slug.
		foreach ( get_post_types_by_support( 'gatherpress-venue-information' ) as $venue_post_type ) {
			register_taxonomy( $this->get_taxonomy( $venue_post_type ), array(), $taxonomy_args );
		}

		// Register each event post type with the taxonomy of its resolved venue post type.
		foreach ( get_post_types_by_support( 'gatherpress-venue' ) as $event_post_type ) {
			$venue_post_type = $this->get_venue_post_type( $event_post_type );
			register_taxonomy_for_object_type( $this->get_taxonomy( $venue_post_type ), $event_post_type );
		}
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

		if ( ! post_type_supports( $post->post_type, 'gatherpress-venue-information' ) ) {
			return;
		}

		if ( $update || empty( $post->post_name ) || 'publish' !== $post->post_status ) {
			return;
		}

		$venue     = new Venue( $post_id );
		$term_slug = $venue->get_term_slug();
		$taxonomy  = $venue->get_taxonomy();

		if ( term_exists( $term_slug, $taxonomy ) ) {
			return;
		}

		wp_insert_term(
			html_entity_decode( get_the_title( $post_id ) ),
			$taxonomy,
			array( 'slug' => $term_slug )
		);
	}

	/**
	 * Apply the venue template content when a venue is first created with empty content.
	 *
	 * When a venue is created via the REST API (e.g., the "Add New Venue" button
	 * in the Event editor), no content is sent. This method populates the post
	 * content from the registered venue template pattern, including any hooked blocks.
	 *
	 * Only runs on insert (`$update` false) so a user who intentionally clears
	 * the content of an existing venue and saves is not silently re-seeded.
	 *
	 * @since 1.0.0
	 *
	 * @param int     $post_id Post ID of the venue post.
	 * @param WP_Post $post    The venue post object.
	 * @param bool    $update  True when updating an existing post, false on initial insert.
	 * @return void
	 */
	public function maybe_apply_venue_template( int $post_id, WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { // @codeCoverageIgnore
			return; // @codeCoverageIgnore
		}

		// Respect intentional edits: do not re-seed content on updates.
		if ( $update ) {
			return;
		}

		// Only apply template to published venues with empty content.
		if ( 'publish' !== $post->post_status || ! empty( $post->post_content ) ) {
			return;
		}

		$registry = WP_Block_Patterns_Registry::get_instance();
		$pattern  = $registry->get_registered( 'gatherpress/venue-template' );

		if ( ! $pattern || empty( $pattern['content'] ) ) {
			return;
		}

		$content = apply_block_hooks_to_content( $pattern['content'], $pattern );

		// Prevent infinite recursion when updating the post. If
		// wp_update_post() throws (strict-typed hook listeners on PHP 8+,
		// a misbehaving filter, etc.), the try/finally guarantees the
		// hook is put back for subsequent saves in the same request.
		$hook = sprintf( 'save_post_%s', Venue::POST_TYPE );

		remove_action( $hook, array( $this, 'maybe_apply_venue_template' ) );

		try {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
				)
			);
		} finally {
			add_action(
				$hook,
				array( $this, 'maybe_apply_venue_template' ),
				10,
				3
			);
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
		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-venue-information' ) ) {
			return;
		}

		if ( ! in_array( $post_after->post_status, array( 'publish', 'trash' ), true ) ) {
			return;
		}

		if (
			$post_before->post_name === $post_after->post_name &&
			$post_before->post_title === $post_after->post_title
		) {
			return;
		}

		$venue = new Venue( $post_id );

		// Derive both slugs from the hook-supplied post objects rather than
		// re-reading from the DB: the hook already gives us the trusted pre/post
		// state, and we avoid a race where a concurrent save would leak into the
		// slug calculation.
		$old_term_slug = $this->term_slug_from_post_name( $post_before->post_name );
		$new_term_slug = $this->term_slug_from_post_name( $post_after->post_name );
		$taxonomy      = $venue->get_taxonomy();
		$title         = html_entity_decode( get_the_title( $post_id ) );

		$term = term_exists( $old_term_slug, $taxonomy );

		if ( empty( $term ) ) {
			wp_insert_term(
				$title,
				$taxonomy,
				array( 'slug' => $new_term_slug )
			);
			return;
		}

		wp_update_term(
			intval( $term['term_id'] ),
			$taxonomy,
			array(
				'name' => $title,
				'slug' => $new_term_slug,
			)
		);
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
		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-venue-information' ) ) {
			return;
		}

		$venue = new Venue( $post_id );
		$term  = $venue->get_term();

		if ( $term instanceof WP_Term ) {
			wp_delete_term( $term->term_id, $venue->get_taxonomy() );
		}
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
		$venue_meta = array(
			'isOnlineEventTerm' => false,
			'onlineEventLink'   => '',
		);

		$venue = null;

		if ( post_type_supports( $post_type, 'gatherpress-venue' ) ) {
			$event       = new Event( $post_id );
			$venue_terms = get_the_terms( $post_id, $this->taxonomy_for_event_post_type( $post_type ) );
			$venue_slug  = ( is_array( $venue_terms ) && ! empty( $venue_terms ) ) ? $venue_terms[0]->slug : null;

			$venue_meta['isOnlineEventTerm'] = ( 'online-event' === $venue_slug );
			$venue_meta['onlineEventLink']   = $event->maybe_get_online_event_link();

			$venue_post = $this->get_venue_post_from_event_post_id( $post_id );

			if ( $venue_post instanceof WP_Post ) {
				$venue = new Venue( $venue_post->ID );
			}
		} elseif ( post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			$venue = new Venue( $post_id );
		}

		if ( $venue instanceof Venue && $venue->get_post_id() > 0 ) {
			$venue_meta['name'] = get_the_title( $venue->get_post_id() );
			$venue_meta         = array_merge( $venue_meta, $venue->get_information() );
		}

		return $venue_meta;
	}

	/**
	 * Retrieve a venue post by its taxonomy term slug.
	 *
	 * Strips the leading underscore from the taxonomy slug and looks up the
	 * corresponding venue post via `get_page_by_path()`. Returns null when no
	 * matching post exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug            The venue taxonomy term slug (e.g. `_my-venue`).
	 * @param string $event_post_type Optional event post-type context, used when
	 *                                mapping custom event post types to a non-default
	 *                                venue post type via the `gatherpress_venue_post_type` filter.
	 * @return WP_Post|null The matching venue post, or null.
	 */
	public function get_venue_post_from_term_slug( string $slug, string $event_post_type = '' ): ?WP_Post {
		return get_page_by_path(
			ltrim( $slug, '_' ),
			OBJECT,
			$this->get_venue_post_type( $event_post_type )
		);
	}

	/**
	 * Retrieve the venue post associated with a given event post.
	 *
	 * Events may carry both a physical-venue term and a sentinel term (e.g.
	 * `online-event`) at the same time. Real venue terms always carry a leading
	 * underscore — the sentinels do not — so we filter on that invariant and
	 * return the first term that resolves to an actual venue post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_post_id The event post ID.
	 * @return WP_Post|null The linked venue post, or null.
	 */
	public function get_venue_post_from_event_post_id( int $event_post_id ): ?WP_Post {
		$event_post_type = (string) get_post_type( $event_post_id );
		$taxonomy        = $this->taxonomy_for_event_post_type( $event_post_type );
		$venue_terms     = get_the_terms( $event_post_id, $taxonomy );

		if ( ! is_array( $venue_terms ) || empty( $venue_terms ) ) {
			return null;
		}

		foreach ( $venue_terms as $term ) {
			if ( ! $this->is_venue_term_slug( $term->slug ) ) {
				continue;
			}

			$venue_post = $this->get_venue_post_from_term_slug( $term->slug, $event_post_type );

			if ( $venue_post instanceof WP_Post ) {
				return $venue_post;
			}
		}

		return null;
	}

	/**
	 * Returns true when `$slug` is a real venue taxonomy term slug.
	 *
	 * Real venue terms always carry a leading underscore — the auto-generated
	 * prefix added by {@see self::term_slug_from_post_name()}. Sentinels like
	 * `online-event` deliberately don't, so this predicate filters them out
	 * and keeps venue-resolution logic from treating sentinels as venues.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The term slug to test.
	 * @return bool
	 */
	public function is_venue_term_slug( string $slug ): bool {
		return str_starts_with( $slug, '_' );
	}

	/**
	 * Returns the venue taxonomy slug that corresponds to an event post type.
	 *
	 * Convenience wrapper that collapses
	 * `$setup->get_taxonomy( $setup->get_venue_post_type( $pt ) )` — the most
	 * common lookup at call sites that need to tag/query events by venue.
	 *
	 * @since 1.0.0
	 *
	 * @param string $event_post_type The event post type.
	 * @return string The venue taxonomy slug.
	 */
	public function taxonomy_for_event_post_type( string $event_post_type = '' ): string {
		return $this->get_taxonomy( $this->get_venue_post_type( $event_post_type ) );
	}

	/**
	 * Format a venue taxonomy term slug from a post_name.
	 *
	 * Pure formatter — prepends an underscore to the given post_name. Used by
	 * callers that already have the name in hand (e.g. rename-diff callers
	 * comparing old vs. new post_name during a save-post transition).
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_name The venue post's post_name (e.g. `my-venue`).
	 * @return string The taxonomy term slug (e.g. `_my-venue`).
	 */
	public function term_slug_from_post_name( string $post_name ): string {
		return sprintf( '_%s', $post_name );
	}

	/**
	 * Returns the taxonomy slug for a given venue post type.
	 *
	 * The taxonomy slug is always derived by prepending an underscore to the venue
	 * post type slug — for example, 'gatherpress_venue' uses '_gatherpress_venue'.
	 * Custom venue post types follow the same convention automatically.
	 *
	 * @since 1.0.0
	 *
	 * @param string $venue_post_type The venue post type slug. Defaults to the built-in venue post type.
	 * @return string The taxonomy slug for the given venue post type.
	 */
	public function get_taxonomy( string $venue_post_type = '' ): string {
		if ( ! $venue_post_type ) {
			$venue_post_type = Venue::POST_TYPE;
		}

		return '_' . $venue_post_type;
	}

	/**
	 * Get the venue post type slug for a given event post type.
	 *
	 * Applies the 'gatherpress_venue_post_type' filter so developers can map
	 * custom event post types to their own venue post types.
	 *
	 * Results are cached in a static array for the lifetime of the request to
	 * avoid repeated filter invocations. If a plugin adds or removes the
	 * 'gatherpress_venue_post_type' filter after this method has already been
	 * called for a given event post type, the cached value will be returned
	 * rather than the updated filter result. This is an unlikely edge case in
	 * normal WordPress request flow, where filters are registered before any
	 * post-type lookups occur.
	 *
	 * @since 1.0.0
	 *
	 * @param string $event_post_type The event post type requesting a venue post type.
	 * @return string The venue post type slug.
	 */
	public function get_venue_post_type( string $event_post_type = '' ): string {
		static $cache = array();

		if ( isset( $cache[ $event_post_type ] ) ) {
			return $cache[ $event_post_type ];
		}

		/**
		 * Filters the post type used as the venue.
		 *
		 * @since 1.0.0
		 *
		 * @param string $post_type       The venue post type slug. Default 'gatherpress_venue'.
		 * @param string $event_post_type The event post type requesting a venue post type.
		 */
		$cache[ $event_post_type ] = (string) apply_filters(
			'gatherpress_venue_post_type',
			Venue::POST_TYPE,
			$event_post_type
		);

		return $cache[ $event_post_type ];
	}

	/**
	 * Returns a map of event post types to their corresponding venue post types.
	 *
	 * Iterates over all post types that support 'gatherpress-venue' and resolves
	 * the venue post type for each via get_venue_post_type(). This map is used
	 * to expose the per-event-type venue post type to the block editor.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Map of event post type slug to venue post type slug.
	 */
	public function get_venue_post_type_map(): array {
		$map = array();

		foreach ( get_post_types_by_support( 'gatherpress-venue' ) as $event_post_type ) {
			$map[ $event_post_type ] = $this->get_venue_post_type( $event_post_type );
		}

		return $map;
	}

	/**
	 * Returns the post type slug localized for the site language and sanitized as URL part.
	 *
	 * Do not use this directly, use get( 'venues_url' ) instead.
	 *
	 * This method switches to the sites default language and gets the translation of 'venues' for the loaded locale.
	 * After that, the method sanitizes the string to be safely used within an URL,
	 * by removing accents, replacing special characters and replacing whitespace with dashes.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_localized_post_type_slug(): string {
		$switched_locale = switch_to_locale( get_locale() );
		$slug            = _x( 'Venue', 'Admin menu and post type singular name', 'gatherpress' );
		$slug            = sanitize_title( $slug );

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		return $slug;
	}
}
