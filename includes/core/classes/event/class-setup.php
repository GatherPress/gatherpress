<?php
/**
 * Handles the setup and management of events in GatherPress.
 *
 * This class is responsible for integrating events into the WordPress environment, including the creation
 * of the custom post type for events, managing event metadata, and enhancing the admin dashboard for event management.
 *
 * @package GatherPress\Core\Event
 * @since 0.29.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Feed;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Core\Starter_Pattern_Loader;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use stdClass;
use WP_Block;
use WP_Post;
use WP_Query;

/**
 * Class Setup.
 *
 * Manages event-related functionalities, including registration of event post types and metadata.
 *
 * @since 0.34.0
 */
class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Title to use as the archive page title.
	 *
	 * @since 0.34.0
	 *
	 * @var string
	 */
	protected string $archive_title = '';

	/**
	 * Class constructor.
	 *
	 * Instantiates the sibling Event\* singletons before wiring hooks so
	 * `Setup::instantiate_classes()` can hand off the whole event
	 * subsystem with a single `Event\Setup::get_instance()` line — same
	 * shape as `Settings::instantiate_classes()`.
	 *
	 * @since 0.34.0
	 */
	public function __construct() {
		$this->instantiate_classes();
		$this->setup_hooks();
	}

	/**
	 * Instantiate each Event\* sibling singleton.
	 *
	 * Keeps the outer `Setup::instantiate_classes()` slim — adding a new
	 * Event\* class lands as a single line here rather than edits to
	 * Setup. Each subclass is a singleton, so repeat calls are safe.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Admin_List::get_instance();
		Meta::get_instance();
		Query::get_instance();
		Rest_Api::get_instance();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		// Priority 11 so post types registered at default priority 10 are available for get_post_types_by_support().
		add_action( 'init', array( $this, 'register_starter_pattern' ), 11 );
		add_action( 'template_redirect', array( $this, 'handle_event_archive_redirect' ) );
		add_action( 'delete_post', array( $this, 'delete_event' ) );
		add_action( 'wp_after_insert_post', array( $this, 'set_datetimes' ) );
		add_action( 'save_post', array( $this, 'check_waiting_list' ) );
		add_filter( 'get_the_date', array( $this, 'get_the_event_date' ), 10, 3 );
		add_filter( 'the_time', array( $this, 'get_the_event_date' ) );
		add_filter( 'render_block_core/post-date', array( $this, 'render_event_post_date_block' ), 10, 3 );
		add_filter( 'display_post_states', array( $this, 'set_event_archive_labels' ), 10, 2 );
	}

	/**
	 * Registers the custom post type for Events.
	 *
	 * This method sets up the custom post type 'Event' with all necessary labels and settings,
	 * enabling it to be used within the WordPress REST API, and configuring its appearance and capabilities
	 * within the WordPress admin area. It defines labels for various UI elements, enables Gutenberg support,
	 * sets the post type to be public, and configures other settings such as the menu position and icon.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get( 'events_url' );
		register_post_type(
			Event::POST_TYPE,
			array(
				'labels'        => array(
					'name'                     => _x(
						'Events',
						'Admin menu and post type general name',
						'gatherpress'
					),
					'singular_name'            => _x(
						'Event',
						'Admin menu and post type singular name',
						'gatherpress'
					),
					'add_new'                  => __( 'Add New', 'gatherpress' ),
					'add_new_item'             => __( 'Add New Event', 'gatherpress' ),
					'edit_item'                => __( 'Edit Event', 'gatherpress' ),
					'new_item'                 => __( 'New Event', 'gatherpress' ),
					'view_item'                => __( 'View Event', 'gatherpress' ),
					'view_items'               => __( 'View Events', 'gatherpress' ),
					'search_items'             => __( 'Search Events', 'gatherpress' ),
					'not_found'                => __( 'No Events found.', 'gatherpress' ),
					'not_found_in_trash'       => __( 'No Events found in Trash.', 'gatherpress' ),
					'parent_item_colon'        => __( 'Parent Events:', 'gatherpress' ),
					'all_items'                => __( 'All Events', 'gatherpress' ),
					'archives'                 => __( 'Event Archives', 'gatherpress' ),
					'attributes'               => __( 'Event Attributes', 'gatherpress' ),
					'insert_into_item'         => __( 'Insert into Event', 'gatherpress' ),
					'uploaded_to_this_item'    => __( 'Uploaded to this Event', 'gatherpress' ),
					'menu_name'                => _x( 'Events', 'Admin menu label', 'gatherpress' ),
					'filter_items_list'        => __( 'Filter Event list', 'gatherpress' ),
					'filter_by_date'           => __( 'Filter by date', 'gatherpress' ),
					'items_list_navigation'    => __( 'Events list navigation', 'gatherpress' ),
					'items_list'               => __( 'Events list', 'gatherpress' ),
					'item_published'           => __( 'Event published.', 'gatherpress' ),
					'item_published_privately' => __( 'Event published privately.', 'gatherpress' ),
					'item_reverted_to_draft'   => __( 'Event reverted to draft.', 'gatherpress' ),
					'item_trashed'             => __( 'Event trashed.', 'gatherpress' ),
					'item_scheduled'           => __( 'Event scheduled.', 'gatherpress' ),
					'item_updated'             => __( 'Event updated.', 'gatherpress' ),
					'item_link'                => _x( 'Event Link', 'Block editor link label', 'gatherpress' ),
					'item_link_description'    => _x(
						'A link to an event.',
						'Block editor link description',
						'gatherpress'
					),
				),
				'show_in_rest'  => true,
				'rest_base'     => 'gatherpress_events',
				'public'        => true,
				'hierarchical'  => false,
				'menu_position' => 4,
				'supports'      => array(
					'title',
					'author',
					'editor',
					'excerpt',
					'thumbnail',
					'comments',
					'revisions',
					'custom-fields',
					'gatherpress-event-date',
					'gatherpress-rsvp',
					'gatherpress-venue',
					'gatherpress-online-event',
				),
				'menu_icon'     => 'dashicons-nametag',
				// Note: has_archive must be true for event feed URLs (/event/feed/) to work.
				// The archive page itself is handled by handle_event_archive_redirect() which
				// returns a 404 or redirects to a page with the same slug.
				'has_archive'   => true,
				'rewrite'       => array(
					'slug'       => $rewrite_slug,
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Returns the post type slug localized for the site language and sanitized as URL part.
	 *
	 * Do not use this directly, use get( 'events_url' ) instead.
	 *
	 * This method switches to the sites default language and gets the translation of 'events' for the loaded locale.
	 * After that, the method sanitizes the string to be safely used within an URL,
	 * by removing accents, replacing special characters and replacing whitespace with dashes.
	 *
	 * @since 0.34.0
	 *
	 * @return string
	 */
	public static function get_localized_post_type_slug(): string {
		$switched_locale = switch_to_locale( get_locale() );

		// The post type (to get the singular name from) is typically not registered, when this method is called.
		// Using Utility::post_type_label() will not yet work.

		// Prepare a default at first.
		$default_labels                = new stdClass();
		$default_labels->singular_name = _x(
			'Event',
			'Admin menu and post type singular name',
			'gatherpress'
		);

		// To ensure, we use the proper labels, we get them from the WordPress core filter.
		$post_type_labels = apply_filters(
			sprintf( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
				'post_type_labels_%s',
				Event::POST_TYPE
			),
			$default_labels
		);

		$slug = sanitize_title( $post_type_labels->singular_name );

		if ( $switched_locale ) {
			restore_previous_locale();
		}
		return $slug;
	}

	/**
	 * Register the user-facing event starter patterns.
	 *
	 * Loads every pattern definition from `includes/core/templates/event/`
	 * (each file returns a `name/title/description/content` array), runs
	 * the list through the `gatherpress_event_starter_patterns` filter so
	 * third parties can append their own, and registers each entry scoped
	 * to `core/post-content` plus every post type declaring
	 * `gatherpress-event-date` support (or the entry's own `postTypes`
	 * list when provided). The block editor's starter pattern modal — the
	 * same UX Twenty Twenty-Five uses on new pages — then surfaces them
	 * when authors create a new event.
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
		$post_types = get_post_types_by_support( 'gatherpress-event-date' );

		if ( empty( $post_types ) ) {
			return;
		}

		$patterns = Starter_Pattern_Loader::load(
			GATHERPRESS_CORE_PATH . '/includes/core/templates/event'
		);

		/**
		 * Filters the array of event starter pattern definitions.
		 *
		 * Each entry is an associative array with `name`, `title`,
		 * `description`, and `content` keys, plus an optional `postTypes`
		 * key (an array of post type slugs) narrowing that one pattern to
		 * specific post types. Entries without `postTypes` register
		 * against every post type declaring `gatherpress-event-date`
		 * support, so they appear in the new-event chooser modal for any
		 * post type acting as an event source.
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
		 * companion plugins that register their own event-acting post
		 * type and want to swap a pattern in only when their post type
		 * is in scope.
		 *
		 * @since 0.29.0
		 * @since 0.35.0 Definitions may include a `postTypes` key to
		 *               narrow a single pattern's registration.
		 *
		 * @param array $patterns   Pattern definitions loaded from the
		 *                          `includes/core/templates/event/` directory.
		 * @param array $post_types Post type slugs declaring `gatherpress-event-date`
		 *                          support that patterns without their own
		 *                          `postTypes` key will be registered against.
		 */
		$patterns = apply_filters( 'gatherpress_event_starter_patterns', $patterns, $post_types );

		Starter_Pattern_Loader::register( (array) $patterns, $post_types );
	}

	/**
	 * Handle event post type archive requests.
	 *
	 * When visiting the event archive URL (e.g., /event/), this method
	 * resolves what to render in the following order:
	 *
	 * 1. If a published page exists at the configured event slug, that
	 *    page wins. If the page is also assigned as the upcoming or past
	 *    archive page in settings, the archive query is rewritten with
	 *    the matching ordering; otherwise the page is served as a
	 *    regular page.
	 * 2. Otherwise, fall back to the **Event Archive** setting (see
	 *    {@see self::get_event_archive_mode()}):
	 *      - `upcoming` (default) — rewrite the main query as the
	 *        upcoming events archive (ASC, datetime ≥ now).
	 *      - `past` — rewrite as the past events archive (DESC,
	 *        datetime < now).
	 *      - `none` — trigger a 404.
	 *
	 * `has_archive => true` stays on the post type registration because
	 * it is required for the event feed URLs (e.g., /event/feed/) to
	 * work; the Feed class serves those. Setting `has_archive => false`
	 * would make feed URLs 404 in every mode.
	 *
	 * @since 0.34.0
	 *
	 * @see Feed::handle_events_feed_query()
	 * @see self::get_event_archive_mode()
	 *
	 * @return void
	 */
	public function handle_event_archive_redirect(): void {
		global $wp_query;

		// Bail on feeds (handled separately by Feed) or when something
		// else already claimed this page as an event archive.
		if ( is_feed() || $wp_query->get( Query::EVENT_QUERY_PARAM ) ) {
			return;
		}

		// Bail when not on a post type archive at all, or when the
		// queried post type doesn't declare event-date support.
		$post_type = (string) get_query_var( 'post_type' );

		if ( ! is_post_type_archive() || ! post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
			return;
		}

		// The page-as-archive flow below reads settings that exist only
		// for the standard event post type (`events_url`,
		// `upcoming_events`, `past_events`). Other event-supporting post
		// types skip straight to the mode resolver, which honors the
		// `gatherpress_event_archive_mode` filter the same way for every
		// post type that goes through it (#1611).
		if ( Event::POST_TYPE !== $post_type ) {
			$this->fall_back_to_archive_mode( $wp_query, $post_type );
			return;
		}

		// Get the configured rewrite slug for events.
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get( 'events_url' );

		// Check if a page exists with this slug.
		$page = get_page_by_path( $rewrite_slug );

		if ( ! ( $page instanceof WP_Post ) || 'publish' !== $page->post_status ) {
			// No page exists with this slug — fall back to the configured
			// archive mode (or 404 if no mode is configured).
			$this->fall_back_to_archive_mode( $wp_query, $post_type );
			return;
		}

		// Check if this page is designated as an upcoming or past events archive.
		$archive_pages = array(
			'upcoming' => json_decode( $settings->get( 'upcoming_events' ) ),
			'past'     => json_decode( $settings->get( 'past_events' ) ),
		);

		foreach ( $archive_pages as $key => $value ) {
			if ( ! empty( $value ) && is_array( $value ) && $value[0]->id === $page->ID ) {
				$page_title = get_the_title( $page->ID );

				// Re-query as an event archive with proper sorting.
				$paged = get_query_var( 'paged', 1 );

				$wp_query->query(
					array(
						'post_type'              => Event::POST_TYPE,
						Query::EVENT_QUERY_PARAM => $key,
						'paged'                  => $paged,
					)
				);

				$wp_query->is_page              = false;
				$wp_query->is_singular          = false;
				$wp_query->is_archive           = true;
				$wp_query->is_post_type_archive = true;

				// Preserve the page as queried object so admin bar "Edit Page" works.
				$wp_query->queried_object    = $page;
				$wp_query->queried_object_id = $page->ID;

				// Use the page title as the archive title.
				$this->archive_title = $page_title;
				add_filter( 'get_the_archive_title', array( $this, 'filter_archive_title' ) );

				return;
			}
		}

		// Page exists but is not an archive page — serve it as a regular page.
		$wp_query->init();
		$wp_query->query( array( 'page_id' => $page->ID ) );
		$wp_query->is_post_type_archive = false;
		$wp_query->is_archive           = false;
		$wp_query->is_page              = true;
		$wp_query->is_singular          = true;
		$wp_query->queried_object       = $page;
		$wp_query->queried_object_id    = $page->ID;
	}

	/**
	 * Mutate the global query to render the configured archive mode for an
	 * event-supporting post type.
	 *
	 * The mode comes from `get_event_archive_mode( $post_type )` —
	 * defaults to `upcoming`, with the Event Archive setting and the
	 * `gatherpress_event_archive_mode` filter as the override knobs.
	 * `upcoming` / `past` re-query with the matching temporal filter;
	 * `none` 404s, since the only way to land there is an explicit
	 * opt-out (#1611).
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Query $wp_query  The global query, mutated in place.
	 * @param string   $post_type The event-supporting post type being archived.
	 *
	 * @return void
	 */
	protected function fall_back_to_archive_mode( WP_Query $wp_query, string $post_type ): void {
		$mode = $this->get_event_archive_mode( $post_type );

		if ( 'none' === $mode ) {
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		$paged = get_query_var( 'paged', 1 );

		$wp_query->query(
			array(
				'post_type'              => $post_type,
				Query::EVENT_QUERY_PARAM => $mode,
				'paged'                  => $paged,
			)
		);

		$wp_query->is_page              = false;
		$wp_query->is_singular          = false;
		$wp_query->is_archive           = true;
		$wp_query->is_post_type_archive = true;
	}

	/**
	 * Filter the archive title to use the designated page title.
	 *
	 * @since 0.34.0
	 *
	 * @return string The archive page title.
	 */
	public function filter_archive_title(): string {
		return $this->archive_title;
	}

	/**
	 * Resolve the configured event archive mode for a given post type.
	 *
	 * Every event-supporting post type defaults to `upcoming`. For
	 * `gatherpress_event` the Event Archive setting overrides the
	 * default when it carries a valid value. The
	 * `gatherpress_event_archive_mode` filter then runs for every post
	 * type and gets the last word. Anything outside `upcoming` / `past`
	 * / `none` is coerced back to `upcoming` (#1611).
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type Post type to resolve the mode for. Defaults to the standard event post type.
	 *
	 * @return string One of `upcoming`, `past`, or `none`.
	 */
	public function get_event_archive_mode( string $post_type = Event::POST_TYPE ): string {
		$valid_modes = array( 'upcoming', 'past', 'none' );
		$mode        = 'upcoming';

		if ( Event::POST_TYPE === $post_type ) {
			$stored = (string) Settings::get_instance()->get( 'event_archive' );

			if ( in_array( $stored, $valid_modes, true ) ) {
				$mode = $stored;
			}
		}

		/**
		 * Filters the resolved event archive mode.
		 *
		 * Lets plugins pin a post type's archive to `upcoming`, `past`,
		 * or `none` programmatically — including overriding the Event
		 * Archive setting for `gatherpress_event`. Returned values
		 * outside the valid set are coerced to `upcoming`.
		 *
		 * @since 0.29.0
		 *
		 * @param string $mode      Current archive mode (`upcoming`, `past`, or `none`).
		 * @param string $post_type Post type being archived.
		 */
		$mode = (string) apply_filters( 'gatherpress_event_archive_mode', $mode, $post_type );

		return in_array( $mode, $valid_modes, true ) ? $mode : 'upcoming';
	}

	/**
	 * Checks and updates the waiting list for the given event.
	 *
	 * This function initializes an RSVP object for the given post ID
	 * and checks the waiting list associated with that post.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id The ID of the post for which the waiting list should be checked.
	 *
	 * @return void
	 */
	public function check_waiting_list( int $post_id ): void {
		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-rsvp' ) ) {
			return;
		}

		$rsvp = new Rsvp( $post_id );

		$rsvp->check_waiting_list();
	}

	/**
	 * Delete event record from custom table when an event is deleted.
	 *
	 * This method is called when an event post is deleted, and it ensures that the corresponding
	 * record in the custom table associated with the event is also deleted.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id An event post ID.
	 *
	 * @return void
	 */
	public function delete_event( int $post_id ): void {
		global $wpdb;

		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ) {
			return;
		}

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$table,
			array(
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * Returns the event date instead of the publish date for events.
	 *
	 * This method retrieves the event date based on plugin settings, replacing the publish date
	 * for event posts when appropriate. When a specific date format is provided (e.g., 'c' for
	 * ISO 8601), the event start datetime is returned in that format. This ensures compatibility
	 * with the core/post-date block, which requests ISO 8601 format via block bindings.
	 *
	 * @since 0.34.0
	 *
	 * @param string       $the_date The formatted date.
	 * @param string       $format   PHP date format.
	 * @param WP_Post|null $post     The post object.
	 *
	 * @return string The event date as a formatted string.
	 *
	 * @throws Exception If initializing the Event object fails or event data cannot be retrieved.
	 */
	public function get_the_event_date( string $the_date, string $format = '', $post = null ): string {
		$settings       = Settings::get_instance();
		$use_event_date = $settings->get( 'post_or_event_date' );

		// Determine the post type and ID from the post object or global context.
		$post_type = $post instanceof WP_Post ? $post->post_type : get_post_type();
		$post_id   = $post instanceof WP_Post ? $post->ID : get_the_ID();

		if (
			! post_type_supports( (string) $post_type, 'gatherpress-event-date' )
			|| 1 !== intval( $use_event_date )
		) {
			return $the_date;
		}

		// Get the event date and return it in the requested format.
		$event = new Event( $post_id );

		// When a specific format is requested, return the event start datetime in that format.
		// This ensures compatibility with the core/post-date block (which uses ISO 8601 'c' format).
		if ( ! empty( $format ) ) {
			return $event->get_datetime_start( $format );
		}

		return $event->get_display_datetime();
	}

	/**
	 * Filters the rendered core/post-date block to display the event datetime.
	 *
	 * When the "Display event date instead of publish date for events" setting is enabled,
	 * this method replaces the Post Date block output with the GatherPress-formatted event
	 * datetime (using the event format settings for date, time, and timezone).
	 *
	 * @since 0.34.0
	 *
	 * @param string   $block_content The block content.
	 * @param array    $block         The full block, including name and attributes.
	 * @param WP_Block $instance      The block instance.
	 *
	 * @return string The filtered block content with event datetime.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) -- $block is required by the render_block filter signature.
	 */
	public function render_event_post_date_block( string $block_content, array $block, WP_Block $instance ): string {
		$post_id        = $instance->context['postId'] ?? get_the_ID();
		$use_event_date = Settings::get_instance()->get( 'post_or_event_date' );

		// Bail when there's no post, when the post type doesn't carry event-date
		// support, or when the "use event date" setting isn't enabled.
		if ( ! $post_id
			|| ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' )
			|| 1 !== intval( $use_event_date )
		) {
			return $block_content;
		}

		$event        = new Event( $post_id );
		$display_date = $event->get_display_datetime();

		if ( empty( $display_date ) || Event::DATETIME_PLACEHOLDER === $display_date ) {
			return $block_content;
		}

		// Replace the datetime attribute and the displayed date text in the block output.
		$iso_date      = $event->get_datetime_start( 'c' );
		$block_content = preg_replace(
			'/datetime="[^"]*"/',
			'datetime="' . esc_attr( $iso_date ) . '"',
			$block_content
		);

		return preg_replace(
			'|(<time[^>]*>).*?(</time>)|s',
			'$1' . esc_html( $display_date ) . '$2',
			$block_content
		);
	}

	/**
	 * Add Upcoming and Past Events display states to assigned pages.
	 *
	 * This method adds custom display states to assigned pages for "Upcoming Events" and "Past Events"
	 * based on the plugin settings. It checks if the current post object corresponds to any of the assigned
	 * pages and adds display states accordingly.
	 *
	 * @since 0.34.0
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 *
	 * @return array An updated array of post display states with custom labels if applicable.
	 */
	public function set_event_archive_labels( array $post_states, WP_Post $post ): array {
		// Retrieve archive page settings.
		$settings      = Settings::get_instance();
		$archive_pages = array(
			'past_events'     => json_decode( $settings->get( 'past_events' ) ),
			'upcoming_events' => json_decode( $settings->get( 'upcoming_events' ) ),
		);

		// Check if the current post corresponds to any assigned archive page and add display states.
		foreach ( $archive_pages as $key => $value ) {
			if ( ! empty( $value ) && is_array( $value ) ) {
				$page = $value[0];

				if ( $page->id === $post->ID ) {
					$post_states[ Utility::prefix_key( $key ) ] = sprintf( 'GatherPress %s', $page->value );
				}
			}
		}

		return $post_states;
	}

	/**
	 * Set the date and time metadata for an event post.
	 *
	 * This method checks if the given post ID is for an event post, retrieves the
	 * associated 'gatherpress_datetime' metadata, and processes the date/time and
	 * timezone information. It then saves the event's date and time details.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id The ID of the post being saved.
	 *
	 * @return void
	 */
	public function set_datetimes( int $post_id ): void {
		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ) {
			return;
		}

		$data = get_post_meta( $post_id, 'gatherpress_datetime', true );

		if ( empty( $data ) ) {
			return;
		}

		$data = json_decode( (string) $data, true ) ?? array();

		$event  = new Event( $post_id );
		$params = array(
			'post_id'        => $post_id,
			'datetime_start' => $data['dateTimeStart'] ?? '',
			'datetime_end'   => $data['dateTimeEnd'] ?? '',
			'timezone'       => $data['timezone'] ?? '',
		);

		$event->save_datetimes( $params );
	}
}
