<?php
/**
 * Handles WordPress integration for the Venue post type.
 *
 * This singleton registers the venue post type and meta, wires the venue
 * shadow taxonomy onto event post types, and seeds the venue template on
 * first save. The per-post-type taxonomy registration and term lifecycle
 * are owned by {@see \GatherPress\Core\Shadow_Source} — venue post types
 * inherit that lifecycle by also declaring `gatherpress-shadow-source`
 * support. Per-venue data accessors live on the `Venue` instance class
 * instead.
 *
 * @package GatherPress\Core\Venue
 * @since 0.27.0
 */

namespace GatherPress\Core\Venue;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Event;
use GatherPress\Core\Settings;
use GatherPress\Core\Shadow_Source;
use GatherPress\Core\Starter_Pattern_Loader;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue\Map\Setup as Map_Setup;
use stdClass;
use WP_Block_Patterns_Registry;
use WP_Post;

/**
 * Class Setup.
 *
 * Registers the Venue post type + taxonomy and wires the WordPress hooks that
 * keep venue data consistent (term lifecycle, template content). Hands off
 * the map subsystem to `Map\Setup` so the outer `Setup::instantiate_classes()`
 * can hand off the whole venue subsystem
 * with a single `Venue\Setup::get_instance()` line — same shape as
 * `Settings::instantiate_classes()`.
 *
 * @since 0.34.0
 */
class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * Instantiates the sibling Venue\* singletons before wiring hooks so
	 * `Setup::instantiate_classes()` can hand off the whole venue
	 * subsystem with a single `Venue\Setup::get_instance()` line — same
	 * shape as `Settings::instantiate_classes()`.
	 *
	 * @since 0.34.0
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
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Map_Setup::get_instance();
		Meta::get_instance();
	}

	/**
	 * Set up hooks for post-type, taxonomy, and save-lifecycle integration.
	 *
	 * @since 0.34.0
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
		// Priority 9 so the implicit `gatherpress-shadow-source` support is
		// declared before Shadow_Source's own priority-10 `registered_post_type`
		// callback wires its per-post-type lifecycle hooks for that post type.
		add_action( 'registered_post_type', array( $this, 'maybe_link_shadow_source_support' ), 9 );
		// Priority 11 so post types registered at default priority 10 are available for get_post_types_by_support().
		add_action( 'init', array( $this, 'register_taxonomy' ), 11 );
		// Priority 11 so post types registered at default priority 10 are available for get_post_types_by_support().
		add_action( 'init', array( $this, 'register_starter_pattern' ), 11 );
		add_filter( 'block_editor_settings_all', array( $this, 'add_editor_settings' ) );
		// Declare the venue-event wiring via the shared shadow filter so it's
		// discoverable in the hook reference rather than hidden behind a
		// venue-specific `register_taxonomy_for_object_type()` loop.
		add_filter(
			'gatherpress_shadow_taxonomy_object_types',
			array( $this, 'attach_venue_taxonomy_to_event_types' ),
			10,
			2
		);
	}

	/**
	 * Implicitly declare `gatherpress-shadow-source` for every venue post type.
	 *
	 * Declaring `gatherpress-venue-information` is the canonical way to mark a post type
	 * as a venue. We treat that declaration as also declaring
	 * `gatherpress-shadow-source` so the shadow-taxonomy primitive is wired
	 * up automatically — companion plugins don't have to remember to declare
	 * both.
	 *
	 * Hooked on `registered_post_type` at priority 9 so the support is in
	 * place before Shadow_Source's own callback (priority 10) reads it.
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The post type that was just registered.
	 *
	 * @return void
	 */
	public function maybe_link_shadow_source_support( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			return;
		}

		add_post_type_support( $post_type, 'gatherpress-shadow-source' );
	}

	/**
	 * Adds GatherPress venue configuration to the block editor settings.
	 *
	 * Exposes the venue post type map under settings['gatherpress']['config']['venuePostTypes']
	 * so that the block editor can resolve the correct venue post type for each
	 * event post type without relying on window globals.
	 *
	 * @since 0.34.0
	 *
	 * @param array $settings The block editor settings array.
	 *
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
	 * @since 0.34.0
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
					'gatherpress-shadow-source',
				),
				'menu_icon'    => 'dashicons-location',
				'has_archive'  => true,
				'rewrite'      => array(
					'slug'       => $rewrite_slug,
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Ensure the venue shadow taxonomy is registered and wired to event post types.
	 *
	 * The per-venue-post-type taxonomy registration itself is owned by
	 * {@see Shadow_Source::register_taxonomies()} — venue post types inherit
	 * it because they declare `gatherpress-shadow-source` (either explicitly
	 * or via {@see self::maybe_link_shadow_source_support()}). This method
	 * delegates there and then performs the venue-specific event-side
	 * wiring: any post type with `gatherpress-venue` support gets its
	 * resolved venue's taxonomy registered for it via
	 * `register_taxonomy_for_object_type()`.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		Shadow_Source::get_instance()->register_taxonomies();
	}

	/**
	 * Filter callback that wires the venue shadow taxonomy to event CPTs.
	 *
	 * Returns the event-supporting CPTs whose declared venue post type
	 * matches `$source_post_type`, resolved via the existing
	 * `gatherpress_venue_post_type` filter. Routes the venue's wiring through
	 * the shared `gatherpress_shadow_taxonomy_object_types` hook so the
	 * venue ↔ event relationship is discoverable alongside every other
	 * shadow-source CPT a site or extension registers.
	 *
	 * @since 0.34.0
	 *
	 * @param string[] $object_types     Existing event CPTs the taxonomy attaches to.
	 * @param string   $source_post_type Shadow-source CPT slug being wired.
	 *
	 * @return string[] The augmented list of event CPTs.
	 */
	public function attach_venue_taxonomy_to_event_types( array $object_types, string $source_post_type ): array {
		foreach ( get_post_types_by_support( 'gatherpress-venue' ) as $event_post_type ) {
			if ( $this->get_venue_post_type( $event_post_type ) === $source_post_type ) {
				$object_types[] = $event_post_type;
			}
		}

		return $object_types;
	}

	/**
	 * Register the user-facing venue starter patterns.
	 *
	 * Loads every pattern definition from `includes/core/templates/venue/`
	 * (each file returns a `name/title/description/content` array), runs
	 * the list through the `gatherpress_venue_starter_patterns` filter so
	 * third parties can append their own, and registers each entry scoped
	 * to `core/post-content` plus every post type declaring
	 * `gatherpress-venue-information` support (or the entry's own
	 * `postTypes` list when provided). The block editor's starter pattern
	 * modal — the same UX Twenty Twenty-Five uses on new pages — then
	 * surfaces them when authors create a new venue.
	 *
	 * Per-user dismissal is handled by the modal's own "Always show
	 * starter patterns for new pages" toggle, so no site-wide setting
	 * is needed here.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_starter_pattern(): void {
		$post_types = get_post_types_by_support( 'gatherpress-venue-information' );

		if ( empty( $post_types ) ) {
			return;
		}

		$patterns = Starter_Pattern_Loader::load(
			GATHERPRESS_CORE_PATH . '/includes/core/templates/venue'
		);

		/**
		 * Filters the array of venue starter pattern definitions.
		 *
		 * Each entry is an associative array with `name`, `title`,
		 * `description`, and `content` keys, plus an optional `postTypes`
		 * key (an array of post type slugs) narrowing that one pattern to
		 * specific post types. Entries without `postTypes` register
		 * against every post type declaring `gatherpress-venue-information`
		 * support, so they appear in the new-venue chooser modal for any
		 * post type acting as a venue source.
		 *
		 * Prefer this filter over calling `register_block_pattern()`
		 * directly: definitions inherit the support-resolved post type
		 * list (a companion post type declaring the support is included
		 * automatically — no slugs to enumerate), the `core/post-content`
		 * scoping that surfaces patterns in the chooser modal is applied
		 * for you, and the bundled defaults arrive in the same array so
		 * they can be reordered, modified, or removed — not just
		 * appended to.
		 *
		 * The `$post_types` array lets consumers tailor the returned
		 * patterns to the post types about to receive them — useful for
		 * companion plugins that register their own venue-acting post
		 * type and want to swap a pattern in only when their post type
		 * is in scope.
		 *
		 * @since 0.27.0
		 * @since 0.35.0 Definitions may include a `postTypes` key to
		 *               narrow a single pattern's registration.
		 *
		 * @param array $patterns   Pattern definitions loaded from the
		 *                          `includes/core/templates/venue/` directory.
		 * @param array $post_types Post type slugs declaring `gatherpress-venue-information`
		 *                          support that patterns without their own
		 *                          `postTypes` key will be registered against.
		 */
		$patterns = apply_filters( 'gatherpress_venue_starter_patterns', $patterns, $post_types );

		Starter_Pattern_Loader::register( (array) $patterns, $post_types );
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
	 * @since 0.34.0
	 *
	 * @param int     $post_id Post ID of the venue post.
	 * @param WP_Post $post    The venue post object.
	 * @param bool    $update  True when updating an existing post, false on initial insert.
	 *
	 * @return void
	 */
	public function maybe_apply_venue_template( int $post_id, WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { // @codeCoverageIgnore
			return; // @codeCoverageIgnore
		}

		// Respect intentional edits (no re-seed on updates) and only apply
		// template to published venues with empty content.
		if ( $update
			|| 'publish' !== $post->post_status
			|| ! empty( $post->post_content )
		) {
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
	 * Retrieve venue information from meta data.
	 *
	 * This method retrieves and assembles venue-related information from meta data
	 * associated with a given post. The returned array includes details such as the
	 * venue's name, whether it's an online event term, an online event link, and any
	 * additional venue information stored as JSON in post meta.
	 *
	 * @since 0.34.0
	 *
	 * @param int    $post_id   The post ID for which to retrieve venue information.
	 * @param string $post_type The post type of the provided post ID.
	 *
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
	 * Resolves the venue post type for the given event post type context (so
	 * custom event post types pointing at a non-default venue post type via
	 * the `gatherpress_venue_post_type` filter look up the right post type)
	 * and then delegates to {@see Shadow_Source::get_post_from_term_slug()}.
	 *
	 * @since 0.34.0
	 *
	 * @param string $slug            The venue taxonomy term slug (e.g. `_my-venue`).
	 * @param string $event_post_type Optional event post-type context.
	 *
	 * @return WP_Post|null The matching venue post, or null.
	 */
	public function get_venue_post_from_term_slug( string $slug, string $event_post_type = '' ): ?WP_Post {
		return Shadow_Source::get_instance()->get_post_from_term_slug(
			$slug,
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
	 * @since 0.34.0
	 *
	 * @param int $event_post_id The event post ID.
	 *
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
	 * Thin wrapper around {@see Shadow_Source::is_shadow_term_slug()} —
	 * real venue terms always carry a leading underscore (the auto-generated
	 * prefix added by {@see self::term_slug_from_post_name()}). Sentinels
	 * like `online-event` deliberately don't, so this predicate filters
	 * them out and keeps venue-resolution logic from treating sentinels as
	 * venues.
	 *
	 * @since 0.34.0
	 *
	 * @param string $slug The term slug to test.
	 *
	 * @return bool
	 */
	public function is_venue_term_slug( string $slug ): bool {
		return Shadow_Source::get_instance()->is_shadow_term_slug( $slug );
	}

	/**
	 * Returns the venue taxonomy slug that corresponds to an event post type.
	 *
	 * Convenience wrapper that collapses
	 * `$setup->get_taxonomy( $setup->get_venue_post_type( $pt ) )` — the most
	 * common lookup at call sites that need to tag/query events by venue.
	 *
	 * @since 0.34.0
	 *
	 * @param string $event_post_type The event post type.
	 *
	 * @return string The venue taxonomy slug.
	 */
	public function taxonomy_for_event_post_type( string $event_post_type = '' ): string {
		return $this->get_taxonomy( $this->get_venue_post_type( $event_post_type ) );
	}

	/**
	 * Format a venue taxonomy term slug from a post_name.
	 *
	 * Thin wrapper around {@see Shadow_Source::term_slug_from_post_name()}.
	 * Used by callers that already have the name in hand (e.g. rename-diff
	 * callers comparing old vs. new post_name during a save-post transition).
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_name The venue post's post_name (e.g. `my-venue`).
	 *
	 * @return string The taxonomy term slug (e.g. `_my-venue`).
	 */
	public function term_slug_from_post_name( string $post_name ): string {
		return Shadow_Source::get_instance()->term_slug_from_post_name( $post_name );
	}

	/**
	 * Returns the taxonomy slug for a given venue post type.
	 *
	 * Thin wrapper around {@see Shadow_Source::get_taxonomy()} that defaults
	 * the post type to the built-in `gatherpress_venue` so venue-side callers
	 * can omit the argument.
	 *
	 * @since 0.34.0
	 *
	 * @param string $venue_post_type The venue post type slug. Defaults to the built-in venue post type.
	 *
	 * @return string The taxonomy slug for the given venue post type.
	 */
	public function get_taxonomy( string $venue_post_type = '' ): string {
		if ( ! $venue_post_type ) {
			$venue_post_type = Venue::POST_TYPE;
		}

		return Shadow_Source::get_instance()->get_taxonomy( $venue_post_type );
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
	 * @since 0.34.0
	 *
	 * @param string $event_post_type The event post type requesting a venue post type.
	 *
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
		 * @since 0.27.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
	 *
	 * @return string
	 */
	public function get_localized_post_type_slug(): string {
		$switched_locale = switch_to_locale( get_locale() );

		// The post type (to get the singular name from) is typically not registered, when this method is called.
		// Using Utility::post_type_label() will not yet work.

		// Prepare a default at first.
		$default_labels                = new stdClass();
		$default_labels->singular_name = _x(
			'Venue',
			'Admin menu and post type singular name',
			'gatherpress'
		);

		// To ensure, we use the proper labels, we get them from the WordPress core filter.
		$post_type_labels = apply_filters(
			sprintf( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
				'post_type_labels_%s',
				Venue::POST_TYPE
			),
			$default_labels
		);

		$slug = sanitize_title( $post_type_labels->singular_name );

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		return $slug;
	}
}
