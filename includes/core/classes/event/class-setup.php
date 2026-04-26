<?php
/**
 * Handles the setup and management of events in GatherPress.
 *
 * This class is responsible for integrating events into the WordPress environment, including the creation
 * of the custom post type for events, managing event metadata, and enhancing the admin dashboard for event management.
 *
 * @package GatherPress\Core\Event
 * @since 1.0.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Feed;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use stdClass;
use WP;
use WP_Block;
use WP_Post;
use WP_REST_Request;

/**
 * Class Setup.
 *
 * Manages event-related functionalities, including registration of event post types and metadata.
 *
 * @since 1.0.0
 */
class Setup {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Title to use as the archive page title.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Admin_List::get_instance();
		Query::get_instance();
		Rest_Api::get_instance();
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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_event_only_meta' ) );
		add_action( 'registered_post_type', array( $this, 'maybe_register_event_date_meta' ) );
		add_action( 'init', array( $this, 'register_calendar_rewrite_rule' ) );
		add_action( 'parse_request', array( $this, 'handle_calendar_ics_request' ) );
		add_action( 'template_redirect', array( $this, 'handle_event_archive_redirect' ) );
		add_action( 'delete_post', array( $this, 'delete_event' ) );
		add_action( 'wp_after_insert_post', array( $this, 'set_datetimes' ) );
		add_action( 'save_post', array( $this, 'check_waiting_list' ) );
		add_filter( 'redirect_canonical', array( $this, 'disable_ics_canonical_redirect' ), 10, 2 );
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
	 * @since 1.0.0
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
					'all_items'                => __( 'View Events', 'gatherpress' ),
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
				'template'      => array(
					array( 'gatherpress/event-date' ),
					array( 'gatherpress/add-to-calendar' ),
					array( 'gatherpress/venue' ),
					array( 'gatherpress/online-event' ),
					array( 'gatherpress/rsvp' ),
					array(
						'core/paragraph',
						array(
							'placeholder' => __(
								// phpcs:ignore Generic.Files.LineLength.TooLong
								'Add a description of the event and let people know what to expect, including the agenda, what they need to bring, and how to find the group.',
								'gatherpress'
							),
						),
					),
					array( 'gatherpress/rsvp-response' ),
				),
				// @todo continue to work on the event-template.
				// 'template'      => array(
				// array( 'core/pattern', array( 'slug' => 'gatherpress/event-template' ) ),
				// ),
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
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function get_localized_post_type_slug(): string {
		$switched_locale = switch_to_locale( get_locale() );
		$slug            = _x( 'Event', 'Post Type Singular Name', 'gatherpress' );
		$slug            = sanitize_title( $slug );
		if ( $switched_locale ) {
			restore_previous_locale();
		}
		return $slug;
	}

	/**
	 * Authorization callback for post meta that mirrors the post-level edit cap.
	 *
	 * Routes through `user_can( $user_id, 'edit_post', $object_id )` so the
	 * per-post permission model (`map_meta_cap` → `edit_others_posts`,
	 * `edit_published_posts`, etc.) gates meta the same way it gates the post
	 * itself. Without this, the meta layer would be more permissive than the
	 * post layer that owns it: a custom REST route or third-party
	 * `update_post_meta()` call could bypass the per-post check that the WP
	 * posts controller already enforces on the post.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $allowed   Whether the user can edit the post meta. Unused;
	 *                          we authoritatively return based on `edit_post`.
	 * @param string $meta_key  The meta key being accessed. Unused.
	 * @param int    $object_id The post ID the meta belongs to.
	 * @param int    $user_id   The user ID attempting the edit.
	 * @return bool True if the user can edit the post, false otherwise.
	 */
	public function can_edit_post_meta(
		bool $allowed,
		string $meta_key,
		int $object_id,
		int $user_id
	): bool {
		return user_can( $user_id, 'edit_post', $object_id );
	}

	/**
	 * Registers datetime meta + the read-only REST filter when a post type
	 * declares gatherpress-event-date support.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type that was just registered.
	 * @return void
	 */
	public function maybe_register_event_date_meta( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
			return;
		}

		$event_date_meta = array(
			'gatherpress_datetime'           => array(
				'auth_callback'     => array( $this, 'can_edit_post_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_datetime_start'     => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
			),
			'gatherpress_datetime_start_gmt' => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_datetime_end'       => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_datetime_end_gmt'   => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_timezone'           => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
		);

		foreach ( $event_date_meta as $meta_key => $args ) {
			register_post_meta( $post_type, $meta_key, $args );
		}

		// Filter read-only datetime meta from REST requests for this post type.
		add_filter(
			sprintf( 'rest_pre_insert_%s', $post_type ),
			array( $this, 'filter_readonly_meta' ),
			10,
			2
		);
	}

	/**
	 * Registers meta that only lives on the built-in event post type (RSVP
	 * toggles, guest / attendance limits, online event link).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_event_only_meta(): void {
		// Always register gatherpress_enable_rsvp so it can be written in all modes.
		// Missing meta is treated as "on"; only an explicit 0 disables RSVP per event.
		$event_only_meta = array(
			'gatherpress_enable_rsvp'           => array(
				'auth_callback'     => array( $this, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 1,
			),
			'gatherpress_max_guest_limit'       => array(
				'auth_callback'     => array( $this, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => (int) Settings::get_instance()->get( 'max_guest_limit' ),
			),
			'gatherpress_enable_anonymous_rsvp' => array(
				'auth_callback'     => array( $this, 'can_edit_post_meta' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'default'           => (bool) Settings::get_instance()->get( 'enable_anonymous_rsvp' ),
			),
			// Always register so it can be written regardless of open RSVP mode.
			// Stored as integer (1 = enabled, 0 = disabled); an unset meta (empty string) is treated as enabled.
			'gatherpress_enable_open_rsvp'      => array(
				'auth_callback'     => array( $this, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => 1,
			),
			'gatherpress_online_event_link'     => array(
				'auth_callback'     => array( $this, 'can_edit_post_meta' ),
				'sanitize_callback' => 'sanitize_url',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
			),
			'gatherpress_max_attendance_limit'  => array(
				'auth_callback'     => array( $this, 'can_edit_post_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'default'           => (int) Settings::get_instance()->get( 'max_attendance_limit' ),
			),
		);

		foreach ( $event_only_meta as $meta_key => $args ) {
			register_post_meta( Event::POST_TYPE, $meta_key, $args );
		}
	}

	/**
	 * Filter out read-only meta fields from REST API requests.
	 *
	 * This prevents the "Publishing failed. Sorry, you are not allowed to edit
	 * the gatherpress_datetime_start custom field." error that occurs when the
	 * block editor tries to save derived meta fields that have auth_callback
	 * set to __return_false.
	 *
	 * The derived datetime fields are populated programmatically via the
	 * set_datetimes() method when gatherpress_datetime is saved, so any
	 * values sent via REST API should be silently discarded.
	 *
	 * @since 1.0.0
	 *
	 * @param stdClass        $prepared_post An object representing a single post prepared for inserting or updating.
	 * @param WP_REST_Request $request       Request object.
	 * @return stdClass The prepared post object.
	 */
	public function filter_readonly_meta( stdClass $prepared_post, WP_REST_Request $request ): stdClass {
		$readonly_keys = array(
			'gatherpress_datetime_start',
			'gatherpress_datetime_start_gmt',
			'gatherpress_datetime_end',
			'gatherpress_datetime_end_gmt',
			'gatherpress_timezone',
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
	 * Register a rewrite rule and query var for serving .ics calendar downloads.
	 *
	 * This adds support for URLs like /events/my-event.ics that serve
	 * dynamically generated ICS files for individual events. The URL slug
	 * matches the configured event post type slug from GatherPress settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_calendar_rewrite_rule(): void {
		$settings     = Settings::get_instance();
		$rewrite_slug = sanitize_title( $settings->get( 'events_url' ) );

		add_rewrite_rule(
			sprintf( '^%s/([^/]+)\.ics$', $rewrite_slug ),
			sprintf( 'index.php?post_type=%s&name=$matches[1]&gatherpress_ics=1', Event::POST_TYPE ),
			'top'
		);

		add_rewrite_tag( '%gatherpress_ics%', '1' );
	}

	/**
	 * Prevent WordPress from redirecting .ics URLs with a trailing slash.
	 *
	 * This ensures calendar download URLs like /event/my-event.ics are treated
	 * as file downloads and not rewritten with a trailing slash.
	 *
	 * @since 1.0.0
	 *
	 * @param string|false $redirect_url  The URL WordPress wants to redirect to.
	 * @param string       $requested_url The original requested URL.
	 * @return string|false The filtered redirect URL or false to cancel redirect.
	 */
	public function disable_ics_canonical_redirect( $redirect_url, string $requested_url ) {
		if ( false !== strpos( $requested_url, '.ics' ) ) {
			return false; // prevent canonical redirect.
		}

		return $redirect_url;
	}

	/**
	 * Handle calendar .ics file requests for single event pages.
	 *
	 * This method intercepts requests for .ics files based on a custom query var
	 * and serves dynamically generated ICS content for the specified event. It is
	 * intended to be hooked into the `parse_request` action.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP $wp The current WP object containing query variables and request context.
	 * @return void
	 */
	public function handle_calendar_ics_request( WP $wp ): void {
		if ( isset( $wp->query_vars['gatherpress_ics'] ) ) {
			$slug = $wp->query_vars['name'] ?? null;
			$post = get_page_by_path( $slug, OBJECT, Event::POST_TYPE );

			if ( $post ) {
				$event = new Event( $post->ID );

				header( 'Content-Type: text/calendar; charset=utf-8' );
				header(
					'Content-Disposition: attachment; filename="'
					. get_post_field( 'post_name', $post->ID )
					. '.ics"'
				);

				// ICS content is safely generated and must not be escaped.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $event->get_ics_calendar_string();
				Utility::safe_exit();

				// Return statement allows unit tests to complete after safe_exit returns instead of exiting.
				return;
			}

			wp_die( esc_html__( 'Event not found.', 'gatherpress' ), '', array( 'response' => 404 ) );
		}
	}

	/**
	 * Handle event post type archive requests.
	 *
	 * When visiting the event archive URL (e.g., /event/), this method checks
	 * if a WordPress page exists with the same slug. If a page exists, it
	 * redirects to that page. Otherwise, it triggers a 404 error.
	 *
	 * This prevents the default archive behavior where visiting /event/ shows
	 * a confusing list of events that may not be in a useful order.
	 *
	 * Note: We keep `has_archive => true` on the post type registration because
	 * it is required for the event feed URLs (e.g., /event/feed/) to work. The
	 * Feed class provides customized RSS feeds for upcoming and past events.
	 * Setting `has_archive => false` would cause feed URLs to 404.
	 *
	 * @since 1.0.0
	 *
	 * @see Feed::handle_events_feed_query()
	 *
	 * @return void
	 */
	public function handle_event_archive_redirect(): void {
		global $wp_query;

		// Only handle event post type archive requests.
		if ( ! is_post_type_archive( Event::POST_TYPE ) ) {
			return;
		}

		// Don't interfere with feed requests - those are handled by the Feed class.
		if ( is_feed() ) {
			return;
		}

		// Don't interfere if event-query already assigned this as an archive page.
		if ( $wp_query->get( Query::EVENT_QUERY_PARAM ) ) {
			return;
		}

		// Get the configured rewrite slug for events.
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get( 'events_url' );

		// Check if a page exists with this slug.
		$page = get_page_by_path( $rewrite_slug );

		if ( ! ( $page instanceof WP_Post ) || 'publish' !== $page->post_status ) {
			// No page exists with this slug, so trigger a 404.
			$wp_query->set_404();
			status_header( 404 );
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
	 * Filter the archive title to use the designated page title.
	 *
	 * @since 1.0.0
	 *
	 * @return string The archive page title.
	 */
	public function filter_archive_title(): string {
		return $this->archive_title;
	}

	/**
	 * Checks and updates the waiting list for the given event.
	 *
	 * This function initializes an RSVP object for the given post ID
	 * and checks the waiting list associated with that post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The ID of the post for which the waiting list should be checked.
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
	 * @since 1.0.0
	 *
	 * @param int $post_id An event post ID.
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
	 * @since 1.0.0
	 *
	 * @param string       $the_date The formatted date.
	 * @param string       $format   PHP date format.
	 * @param WP_Post|null $post     The post object.
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
	 * @since 1.0.0
	 *
	 * @param string   $block_content The block content.
	 * @param array    $block         The full block, including name and attributes.
	 * @param WP_Block $instance      The block instance.
	 * @return string The filtered block content with event datetime.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) -- $block is required by the render_block filter signature.
	 */
	public function render_event_post_date_block( string $block_content, array $block, WP_Block $instance ): string {
		$post_id = $instance->context['postId'] ?? get_the_ID();

		if ( ! $post_id || ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-event-date' ) ) {
			return $block_content;
		}

		$settings       = Settings::get_instance();
		$use_event_date = $settings->get( 'post_or_event_date' );

		if ( 1 !== intval( $use_event_date ) ) {
			return $block_content;
		}

		$event        = new Event( $post_id );
		$display_date = $event->get_display_datetime();
		$iso_date     = $event->get_datetime_start( 'c' );

		if ( empty( $display_date ) || Event::DATETIME_PLACEHOLDER === $display_date ) {
			return $block_content;
		}

		// Replace the datetime attribute and the displayed date text in the block output.
		$block_content = preg_replace(
			'/datetime="[^"]*"/',
			'datetime="' . esc_attr( $iso_date ) . '"',
			$block_content
		);
		$block_content = preg_replace(
			'|(<time[^>]*>).*?(</time>)|s',
			'$1' . esc_html( $display_date ) . '$2',
			$block_content
		);

		return $block_content;
	}

	/**
	 * Add Upcoming and Past Events display states to assigned pages.
	 *
	 * This method adds custom display states to assigned pages for "Upcoming Events" and "Past Events"
	 * based on the plugin settings. It checks if the current post object corresponds to any of the assigned
	 * pages and adds display states accordingly.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
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
	 * @since 1.0.0
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
