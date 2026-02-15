<?php
/**
 * Handles the setup and management of events in GatherPress.
 *
 * This class is responsible for integrating events into the WordPress environment, including the creation
 * of the custom post type for events, managing event metadata, and enhancing the admin dashboard for event management.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Traits\Singleton;
use stdClass;
use WP;
use WP_Post;
use WP_Query;
use WP_REST_Request;

/**
 * Class Event_Setup.
 *
 * Manages event-related functionalities, including registration of event post types and metadata.
 *
 * @since 1.0.0
 */
class Event_Setup {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Cached event counts for the current request.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, int>|null
	 */
	protected ?array $event_counts = null;

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
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_post_meta' ) );
		add_action( 'init', array( $this, 'register_calendar_rewrite_rule' ) );
		add_action( 'parse_request', array( $this, 'handle_calendar_ics_request' ) );
		add_action( 'template_redirect', array( $this, 'handle_event_archive_redirect' ) );
		add_action( 'delete_post', array( $this, 'delete_event' ) );
		add_action( 'wp_after_insert_post', array( $this, 'set_datetimes' ) );
		add_action( sprintf( 'save_post_%s', Event::POST_TYPE ), array( $this, 'check_waiting_list' ) );
		add_action(
			sprintf( 'manage_%s_posts_custom_column', Event::POST_TYPE ),
			array( $this, 'custom_columns' ),
			10,
			2
		);
		add_action( 'load-edit.php', array( $this, 'default_sort' ) );
		add_action( 'admin_menu', array( $this, 'modify_all_events_menu_link' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_events_submenu' ) );

		add_filter( 'redirect_canonical', array( $this, 'disable_ics_canonical_redirect' ), 10, 2 );
		add_filter(
			sprintf( 'rest_pre_insert_%s', Event::POST_TYPE ),
			array( $this, 'filter_readonly_meta' ),
			10,
			2
		);
		add_filter(
			sprintf( 'manage_%s_posts_columns', Event::POST_TYPE ),
			array( $this, 'set_custom_columns' )
		);
		add_filter(
			sprintf( 'manage_edit-%s_sortable_columns', Event::POST_TYPE ),
			array( $this, 'sortable_columns' )
		);
		add_filter(
			sprintf( 'views_edit-%s', Event::POST_TYPE ),
			array( $this, 'views_edit' )
		);
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_rsvp_sorting' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_venue_sorting' ) );
		add_filter( 'get_the_date', array( $this, 'get_the_event_date' ) );
		add_filter( 'the_time', array( $this, 'get_the_event_date' ) );
		add_filter( 'display_post_states', array( $this, 'set_event_archive_labels' ), 10, 2 );
		add_filter(
			sprintf( 'manage_%s_posts_columns', Event::POST_TYPE ),
			array( $this, 'remove_comments_column' )
		);
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
		$rewrite_slug = $settings->get_value( 'general', 'urls', 'events' );
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
	 * Do not use this directly, use get_value( 'general', 'urls', 'events' ) instead.
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
	 * Registers post meta for the Event custom post type.
	 *
	 * This method sets up custom metadata fields associated with Event posts, including
	 * an online event link and an option to enable anonymous RSVPs. Each meta field is configured
	 * with authorization, sanitization callbacks, visibility in the REST API, and data type specifications.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		$post_meta = array(
			'gatherpress_datetime'              => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_datetime_start'        => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
			),
			'gatherpress_datetime_start_gmt'    => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_datetime_end'          => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_datetime_end_gmt'      => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_timezone'              => array(
				'auth_callback'     => '__return_false', // Read-only: derived from gatherpress_datetime.
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_max_guest_limit'       => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
			),
			'gatherpress_enable_anonymous_rsvp' => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'default'           => false,
			),
			'gatherpress_online_event_link'     => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'sanitize_url',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			),
			'gatherpress_max_attendance_limit'  => array(
				'auth_callback'     => array( $this, 'can_edit_posts_meta' ),
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
			),
		);

		foreach ( $post_meta as $meta_key => $args ) {
			register_post_meta(
				Event::POST_TYPE,
				$meta_key,
				$args
			);
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
		$rewrite_slug = sanitize_title( $settings->get_value( 'general', 'urls', 'events' ) );

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

		// Get the configured rewrite slug for events.
		$settings     = Settings::get_instance();
		$rewrite_slug = $settings->get_value( 'general', 'urls', 'events' );

		// Check if a page exists with this slug.
		$page = get_page_by_path( $rewrite_slug );

		if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
			// Convert the archive query to serve the page instead.
			// This avoids a redirect loop since /event/ resolves to the archive.
			$wp_query->init();
			$wp_query->query( array( 'page_id' => $page->ID ) );
			$wp_query->is_post_type_archive = false;
			$wp_query->is_archive           = false;
			$wp_query->is_page              = true;
			$wp_query->is_singular          = true;
			$wp_query->queried_object       = $page;
			$wp_query->queried_object_id    = $page->ID;
			return;
		}

		// No page exists with this slug, so trigger a 404.
		$wp_query->set_404();
		status_header( 404 );
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

		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
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
	 * Populate custom columns for Event post type in the admin dashboard.
	 *
	 * Displays additional information, like event datetime and RSVP count, for Event post types.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column  The name of the column to display.
	 * @param int    $post_id The current post ID.
	 * @return void
	 *
	 * @throws Exception If initializing Event or Rsvp object fails, due to invalid post ID or database issues.
	 */
	public function custom_columns( string $column, int $post_id ): void {
		if ( 'datetime' === $column ) {
			$event = new Event( $post_id );
			echo esc_html( $event->get_display_datetime() );
		}

		if ( 'venue' === $column ) {
			$event             = new Event( $post_id );
			$venue_information = $event->get_venue_information();
			$venue_name        = $venue_information['name'];

			if ( $venue_information['is_online_event'] ) {
				echo '<span class="dashicons dashicons-video-alt3"></span> ';
			}

			if ( ! empty( $venue_name ) ) {
				echo esc_html( $venue_name );
			} else {
				echo '—';
			}
		}

		if ( 'rsvps' === $column ) {
			$rsvp_query = Rsvp_Query::get_instance();

			// Get approved RSVPs (standard display).
			$approved_rsvps = $rsvp_query->get_rsvps(
				array(
					'post_id' => $post_id,
					'status'  => 'approve',
					'count'   => true,
				)
			);

			// Get unapproved RSVPs (pending approval).
			$unapproved_rsvps = $rsvp_query->get_rsvps(
				array(
					'post_id' => $post_id,
					'status'  => 'hold',
					'count'   => true,
				)
			);

			// If no RSVPs at all, show dash.
			if ( 0 === $approved_rsvps && 0 === $unapproved_rsvps ) {
				echo '—';
				return;
			}

			// Create link to filtered RSVPs page for approved RSVPs.
			$approved_rsvp_url = add_query_arg(
				array(
					'post_type' => Event::POST_TYPE,
					'page'      => Rsvp::COMMENT_TYPE,
					'post_id'   => $post_id,
					'status'    => 'approved',
				),
				admin_url( 'edit.php' )
			);

			// Display approved RSVP count with rounded box.
			echo '<span class="gatherpress-rsvp-container">';
			printf(
				'<a href="%s" class="gatherpress-rsvp-approved"><span class="gatherpress-rsvp-icon">%d</span></a>',
				esc_url( $approved_rsvp_url ),
				(int) $approved_rsvps
			);

			// Show unapproved RSVPs indicator if there are any unapproved.
			if ( $unapproved_rsvps > 0 ) {
				$unapproved_rsvp_url = add_query_arg(
					array(
						'post_type' => Event::POST_TYPE,
						'page'      => Rsvp::COMMENT_TYPE,
						'post_id'   => $post_id,
						'status'    => 'pending',
					),
					admin_url( 'edit.php' )
				);

				printf(
					'<a href="%s" class="gatherpress-rsvp-pending" title="%s">%d</a>',
					esc_url( $unapproved_rsvp_url ),
					esc_attr( __( 'Unapproved RSVPs', 'gatherpress' ) ),
					(int) $unapproved_rsvps
				);
			}

			echo '</span>';
		}
	}

	/**
	 * Set custom columns for Event post type in the admin dashboard.
	 *
	 * This method is used to define custom columns for Event post types in the WordPress admin dashboard.
	 * It adds additional columns for displaying event date and time, and RSVP count.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An associative array of column headings.
	 * @return array An updated array of column headings, including the custom columns.
	 */
	public function set_custom_columns( array $columns ): array {
		// Remove the author column.
		unset( $columns['author'] );

		$placement = 2;
		$insert    = array(
			'datetime' => __( 'Event date &amp; time', 'gatherpress' ),
			'venue'    => __( 'Venue', 'gatherpress' ),
			'rsvps'    => __( 'RSVPs', 'gatherpress' ),
		);

		return array_slice( $columns, 0, $placement, true ) + $insert + array_slice( $columns, $placement, null, true );
	}

	/**
	 * Sets the default sort field and sort order on the event post type admin screen, to order by event date.
	 *
	 * @author John Blackbourn @johnbillion
	 * @source https://github.com/johnbillion/extended-cpts/blob/20b7e9773b60f7301cd59ee520affa0ff63f90e6/src/PostTypeAdmin.php#L160-L178
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function default_sort(): void {
		$screen_id = sprintf( 'edit-%s', Event::POST_TYPE );
		if ( ! function_exists( 'get_current_screen' ) || get_current_screen()->id !== $screen_id ) {
			return;
		}

		// If the screen is already ordered, bail out.
		if ( isset( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Default to sorting by event date ascending.
		$_GET['orderby'] = 'datetime';
		$_GET['order']   = 'asc';
	}

	/**
	 * Modify the "Upcoming Events" admin submenu link to default to upcoming events.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function modify_all_events_menu_link(): void {
		global $submenu;

		$menu_slug = sprintf( 'edit.php?post_type=%s', Event::POST_TYPE );

		if ( empty( $submenu[ $menu_slug ] ) ) {
			return;
		}

		foreach ( $submenu[ $menu_slug ] as &$item ) {
			if ( $menu_slug === $item[2] ) {
				$item[2] = add_query_arg( 'gatherpress_event_query', 'upcoming', $item[2] );
				break;
			}
		}
	}

	/**
	 * Highlight the "Upcoming Events" submenu when viewing events with query filters.
	 *
	 * WordPress cannot match the modified submenu URL against the current page,
	 * so this filter ensures the correct submenu item stays highlighted.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $submenu_file The current submenu file.
	 * @return string|null The submenu file to highlight.
	 */
	public function highlight_events_submenu( $submenu_file ) {
		$menu_slug = sprintf( 'edit.php?post_type=%s', Event::POST_TYPE );

		if ( $menu_slug === $submenu_file ) {
			return add_query_arg( 'gatherpress_event_query', 'upcoming', $menu_slug );
		}

		return $submenu_file;
	}

	/**
	 * Make custom columns sortable for Event post type in the admin dashboard.
	 *
	 * This method allows the custom columns, including the 'Event date & time' and 'RSVPs' columns,
	 * to be sortable in the WordPress admin dashboard for Event post types.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of sortable columns.
	 * @return array An updated array of sortable columns.
	 */
	public function sortable_columns( array $columns ): array {
		// Add 'datetime' as a sortable column.
		$columns['datetime'] = 'datetime';
		// Add 'venue' as a sortable column.
		$columns['venue'] = 'venue';
		// Add 'rsvps' as a sortable column.
		$columns['rsvps'] = 'rsvps';

		return $columns;
	}

	/**
	 * Add 'Upcoming' & 'Past' to the available admin event list table views.
	 *
	 * This method adds links to filter the shown events in the admin list,
	 * the filtering allows to show 'upcoming' or 'past' events.
	 *
	 * @since 1.0.0
	 *
	 * @param array $view_links An array of available list table views.
	 *
	 * @return array Updated list table views.
	 */
	public function views_edit( array $view_links ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_view = isset( $_GET['gatherpress_event_query'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			? sanitize_text_field( wp_unslash( $_GET['gatherpress_event_query'] ) )
			: '';

		$counts    = $this->get_event_counts();
		$placement = 1;
		$inserts   = array(
			'upcoming' => __( 'Upcoming', 'gatherpress' ),
			'past'     => __( 'Past', 'gatherpress' ),
		);
		$base_url  = admin_url( 'edit.php' );

		foreach ( $inserts as $key => $value ) {
			$count           = isset( $counts[ $key ] ) ? $counts[ $key ] : 0;
			$inserts[ $key ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				add_query_arg(
					array(
						'gatherpress_event_query' => $key,
						'post_type'               => Event::POST_TYPE,
					),
					$base_url
				),
				$key === $current_view ? ' class="current" aria-current="page"' : '',
				$value,
				number_format_i18n( $count )
			);
		}

		if ( isset( $view_links['all'] ) ) {
			if ( $current_view ) {
				// Remove the "current" class from "All" when an event query filter is active.
				$view_links['all'] = str_replace(
					array( ' class="current"', ' aria-current="page"' ),
					'',
					$view_links['all']
				);
			} elseif ( false === strpos( $view_links['all'], 'class="current"' ) ) {
				// Add "current" class to "All" when no filter is active.
				// default_sort() adds orderby/order to $_GET which prevents
				// WordPress from detecting this as a base request.
				$view_links['all'] = str_replace(
					'<a ',
					'<a class="current" aria-current="page" ',
					$view_links['all']
				);
			}
		}

		return array_slice( $view_links, 0, $placement, true )
			+ $inserts
			+ array_slice( $view_links, $placement, null, true );
	}

	/**
	 * Get counts of upcoming and past events.
	 *
	 * Uses the same datetime comparison logic as Event_Query::adjust_event_sql()
	 * with inclusive=true: upcoming uses datetime_end_gmt (includes running events),
	 * past uses datetime_start_gmt (excludes running events).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int> Associative array with 'upcoming' and 'past' counts.
	 */
	protected function get_event_counts(): array {
		if ( null !== $this->event_counts ) {
			return $this->event_counts;
		}

		global $wpdb;

		$table   = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
		$current = gmdate( Event::DATETIME_FORMAT, time() );

		// Upcoming: events whose end time is still in the future (includes currently running).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$upcoming = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(1) FROM %i INNER JOIN %i ON %i.ID = %i.post_id'
				. ' WHERE %i.post_type = %s AND %i.post_status NOT IN'
				. " ('trash', 'auto-draft') AND %i.datetime_end_gmt >= %s",
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$table,
				$wpdb->posts,
				Event::POST_TYPE,
				$wpdb->posts,
				$table,
				$current
			)
		);

		// Past: events whose start time is in the past (excludes currently running).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$past = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(1) FROM %i INNER JOIN %i ON %i.ID = %i.post_id'
				. ' WHERE %i.post_type = %s AND %i.post_status NOT IN'
				. " ('trash', 'auto-draft') AND %i.datetime_start_gmt < %s",
				$wpdb->posts,
				$table,
				$wpdb->posts,
				$table,
				$wpdb->posts,
				Event::POST_TYPE,
				$wpdb->posts,
				$table,
				$current
			)
		);

		$this->event_counts = array(
			'upcoming' => $upcoming,
			'past'     => $past,
		);

		return $this->event_counts;
	}

	/**
	 * Allowlist for additional query parameters.
	 *
	 * Adds 'gatherpress_event_query' to the list of allowed query variables,
	 * to be able to request 'upcoming' or 'past' events in the admin list view.
	 *
	 * @since 1.0.0
	 *
	 * @param  string[] $query_vars List of allowed query variables.
	 *
	 * @return string[] Updated list of allowed query variables.
	 */
	public function query_vars( array $query_vars ) {
		$query_vars[] = 'gatherpress_event_query';
		return $query_vars;
	}

	/**
	 * Handle RSVP column sorting in the events list.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function handle_rsvp_sorting( $query ): void {
		// Only proceed if we're in admin, on the main query, and dealing with events.
		if ( ! is_admin() || ! $query->is_main_query() || Event::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		// Only proceed if sorting by RSVPs.
		if ( 'rsvps' !== $orderby ) {
			return;
		}

		// Use WordPress's standard comment count sorting approach.
		$order = $query->get( 'order', 'ASC' );
		$order = strtoupper( $order );

		// Ensure order is either ASC or DESC.
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'ASC';
		}

		// Modify the query to sort by approved RSVP count.
		add_filter( 'posts_join_paged', array( $this, 'rsvp_sorting_join_paged' ) );
		add_filter( 'posts_groupby', array( $this, 'rsvp_sorting_groupby' ) );
		add_filter( 'posts_orderby', array( $this, 'rsvp_sorting_orderby' ) );

		// Store the order for use in orderby method.
		$query->set( 'rsvp_sort_order', $order );
	}

	/**
	 * Join comments table for RSVP sorting (WordPress style).
	 *
	 * @since 1.0.0
	 *
	 * @param string $join The JOIN clause of the query.
	 * @return string Modified JOIN clause.
	 */
	public function rsvp_sorting_join_paged( string $join ): string {
		global $wpdb;

		$join .= " LEFT JOIN {$wpdb->comments} AS rsvp_sort_comments"
		. " ON {$wpdb->posts}.ID = rsvp_sort_comments.comment_post_ID";
		$join .= " AND rsvp_sort_comments.comment_type = 'gatherpress_rsvp'";
		$join .= " AND rsvp_sort_comments.comment_approved = '1'";

		return $join;
	}

	/**
	 * Group by post ID for RSVP sorting (WordPress style).
	 *
	 * @since 1.0.0
	 *
	 * @param string $groupby The GROUP BY clause of the query.
	 * @return string Modified GROUP BY clause.
	 */
	public function rsvp_sorting_groupby( string $groupby ): string {
		global $wpdb;

		if ( empty( $groupby ) ) {
			$groupby = "{$wpdb->posts}.ID";
		}

		return $groupby;
	}

	/**
	 * Order by RSVP count for RSVP sorting (WordPress style).
	 *
	 * @since 1.0.0
	 *
	 * @return string Modified ORDER BY clause.
	 */
	public function rsvp_sorting_orderby(): string {
		global $wp_query;

		$order = $wp_query->get( 'rsvp_sort_order', 'ASC' );

		// Remove the filters to prevent them from affecting other queries.
		remove_filter( 'posts_join_paged', array( $this, 'rsvp_sorting_join_paged' ) );
		remove_filter( 'posts_groupby', array( $this, 'rsvp_sorting_groupby' ) );
		remove_filter( 'posts_orderby', array( $this, 'rsvp_sorting_orderby' ) );

		return "COUNT(rsvp_sort_comments.comment_ID) {$order}";
	}

	/**
	 * Handle venue sorting in the admin list table.
	 *
	 * This method modifies the query to sort events by venue name alphabetically.
	 * Similar to how WordPress core handles taxonomy sorting.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function handle_venue_sorting( $query ): void {
		// Only proceed if we're in admin, on the main query, and dealing with events.
		if ( ! is_admin() || ! $query->is_main_query() || Event::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		// Only proceed if sorting by venue.
		if ( 'venue' !== $orderby ) {
			return;
		}

		// Get the sort order (ASC or DESC).
		$order = strtoupper( $query->get( 'order', 'ASC' ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'ASC';
		}

		// Modify the query to sort by venue name alphabetically.
		add_filter( 'posts_join_paged', array( $this, 'venue_sorting_join_paged' ) );
		add_filter( 'posts_orderby', array( $this, 'venue_sorting_orderby' ) );

		// Store the order for use in orderby method.
		$query->set( 'venue_sort_order', $order );
	}

	/**
	 * Join term relationships and terms tables for venue sorting.
	 *
	 * @since 1.0.0
	 *
	 * @param string $join The JOIN clause of the query.
	 * @return string Modified JOIN clause.
	 */
	public function venue_sorting_join_paged( string $join ): string {
		global $wpdb;

		$join .= " LEFT JOIN {$wpdb->term_relationships} AS venue_tr ON {$wpdb->posts}.ID = venue_tr.object_id";
		$join .= " LEFT JOIN {$wpdb->term_taxonomy} AS venue_tt"
		. ' ON venue_tr.term_taxonomy_id = venue_tt.term_taxonomy_id'
		. " AND venue_tt.taxonomy = '" . Venue::TAXONOMY . "'";
		$join .= " LEFT JOIN {$wpdb->terms} AS venue_terms ON venue_tt.term_id = venue_terms.term_id";

		return $join;
	}

	/**
	 * Modify the ORDER BY clause for venue sorting.
	 *
	 * @since 1.0.0
	 *
	 * @return string Modified ORDER BY clause.
	 */
	public function venue_sorting_orderby(): string {
		global $wp_query;

		$order = $wp_query->get( 'venue_sort_order', 'ASC' );

		// Remove the filters to prevent them from affecting other queries.
		remove_filter( 'posts_join_paged', array( $this, 'venue_sorting_join_paged' ) );
		remove_filter( 'posts_orderby', array( $this, 'venue_sorting_orderby' ) );

		// Sort by venue name, with NULL/empty values last.
		return "CASE WHEN venue_terms.name IS NULL THEN 1 ELSE 0 END ASC, venue_terms.name {$order}";
	}

	/**
	 * Returns the event date instead of the publish date for events.
	 *
	 * This method retrieves the event date based on plugin settings, replacing the publish date
	 * for event posts when appropriate.
	 *
	 * @since 1.0.0
	 *
	 * @param string $the_date The formatted date.
	 * @return string The event date as a formatted string.
	 *
	 * @throws Exception If initializing the Event object fails or event data cannot be retrieved.
	 */
	public function get_the_event_date( string $the_date ): string {
		$settings       = Settings::get_instance();
		$use_event_date = $settings->get_value( 'general', 'general', 'post_or_event_date' );

		// Check if the post is of the 'Event' post type and if event date should be used.
		if ( Event::POST_TYPE !== get_post_type() || 1 !== intval( $use_event_date ) ) {
			return $the_date;
		}

		// Get the event date and return it as the formatted date.
		$event = new Event( get_the_ID() );

		return $event->get_display_datetime();
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
		// Retrieve plugin general settings.
		$general = get_option( Utility::prefix_key( 'general' ) );
		$pages   = $general['pages'] ?? '';

		if ( empty( $pages ) || ! is_array( $pages ) ) {
			return $post_states;
		}

		// Define archive pages for "Upcoming Events" and "Past Events".
		$archive_pages = array(
			'past_events'     => json_decode( $pages['past_events'] ),
			'upcoming_events' => json_decode( $pages['upcoming_events'] ),
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
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
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

	/**
	 * Remove the comments column from the events list table.
	 *
	 * This method removes the comments column from the events list table in the WordPress admin
	 * to avoid confusion between regular comments and RSVP submissions. The comment count
	 * bubble can be misleading as it combines unapproved comments and RSVPs without
	 * distinguishing their types.
	 *
	 * @todo Address limitations in WordPress core get_pending_comments_num function that is too
	 *       generic and does not take custom comment types into account. It just looks for
	 *       unapproved comments of any type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of column names.
	 * @return array The modified array of column names without the comments column.
	 */
	public function remove_comments_column( array $columns ): array {
		unset( $columns['comments'] );

		return $columns;
	}
}
