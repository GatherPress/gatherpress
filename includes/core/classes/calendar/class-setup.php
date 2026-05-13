<?php
/**
 * Calendar subsystem orchestrator.
 *
 * Owns hook registration, endpoint instantiation, and the request-scoped
 * feed/aggregate behavior that operates on `get_queried_object()` (the
 * `<link rel="alternate">` tags in `<head>`, the .ics file response,
 * the post-type-archive and taxonomy-term feed list builders, the .ics
 * filename/header/wrapping helpers).
 *
 * Per-event data (URL builders, VEVENT string) lives on the sibling
 * `Calendar` class, which is instantiated with an event post ID.
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Query;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Term;

/**
 * Calendar subsystem orchestrator.
 *
 * Singleton that wires up calendar endpoints, renders alternate-link tags
 * in `<head>`, and serves the .ics download/feed responses. Per-event data
 * surfaces (`get_google_url`, `get_ical_event_string`, etc.) live on the
 * sibling `Calendar` class, instantiated as `new Calendar( $event_id )`.
 *
 * @since 1.0.0
 */
class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	const QUERY_VAR = 'gatherpress_calendar';
	const ICAL_SLUG = 'ical'; // Hardcoded ical slug — must not be translated or renamed.

	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for registering custom calendar endpoints and the
	 * `<head>` link tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'registered_post_type', array( $this, 'init_events' ) );
		add_action(
			sprintf( 'registered_post_type_%s', 'gatherpress_venue' ),
			array( $this, 'init_venues' )
		);
		// @todo Maybe hook this two actions dynamically based on a registered post type?!
		add_action( 'registered_taxonomy_for_object_type', array( $this, 'init_taxonomies' ), 10, 2 );
		add_action( 'registered_taxonomy', array( $this, 'init_taxonomies' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'alternate_links' ) );
	}

	/**
	 * Register calendar endpoints for single events and the event archive.
	 *
	 * Sets up the post-type archive feed plus the per-event endpoints for
	 * iCal download, Outlook download, and Google / Yahoo redirect URLs.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The name of the post type that got registered last.
	 *
	 * @return void
	 */
	public function init_events( string $post_type ): void {

		if ( ! post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
			return;
		}

		// Important: register the feed endpoint before the single endpoint,
		// to make sure rewrite rules get saved in the correct order.
		new Post_Type_Feed(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR,
			$post_type
		);
		new Post_Type_Single(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_file_template' ) ),
				new Template( 'outlook', array( $this, 'get_ical_file_template' ) ),
				new Redirect( 'google-calendar', array( $this, 'queried_event_google_url' ) ),
				new Redirect( 'yahoo-calendar', array( $this, 'queried_event_yahoo_url' ) ),
			),
			self::QUERY_VAR,
			$post_type
		);
	}

	/**
	 * Register the calendar feed endpoint for single venues.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_venues(): void {
		new Post_Type_Single_Feed(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR
		);
	}

	/**
	 * Register a calendar feed endpoint for each event-bearing taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $taxonomy    Name of the taxonomy that got registered last.
	 * @param string|array $object_type String when called via 'registered_taxonomy_for_object_type',
	 *                                  may be an array when called from 'registered_taxonomy'.
	 * @return void
	 */
	public function init_taxonomies( string $taxonomy, $object_type ): void {
		$event_post_types = get_post_types_by_support( 'gatherpress-event-date' );
		// Stop if the currently registered taxonomy does not validate.
		if ( // If not registered for the events post type.
			! array_intersect( $event_post_types, (array) $object_type ) ||
			// … is GatherPress' shadow-taxonomy for venues.
			'_gatherpress_venue' === $taxonomy ||
			// … should not be public.
			! is_taxonomy_viewable( $taxonomy ) ||
			false === get_taxonomy( $taxonomy )->rewrite
		) {
			return;
		}

		new Taxonomy_Feed(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR,
			$taxonomy
		);
	}

	/**
	 * Template config for the iCal/Outlook download endpoint.
	 *
	 * Theme overrides win: a file with the same name placed in the active
	 * theme is loaded ahead of the bundled one.
	 *
	 * @since 1.0.0
	 *
	 * @return array Template descriptor with `file_name` (and optional `dir_path`) keys.
	 */
	public function get_ical_file_template(): array {
		return array(
			'file_name' => Utility::prefix_key( 'ical-download.php' ),
		);
	}

	/**
	 * Template config for the iCal subscribeable feed endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @return array Template descriptor with `file_name` (and optional `dir_path`) keys.
	 */
	public function get_ical_feed_template(): array {
		return array(
			'file_name' => Utility::prefix_key( 'ical-feed.php' ),
		);
	}

	/**
	 * Redirect callback that resolves to the queried event's Google Calendar URL.
	 *
	 * Wired into the `google-calendar` Redirect endpoint so a hit on
	 * `/event/my-event/google-calendar` redirects out to Google.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Google Calendar add-event URL for the queried event.
	 */
	public function queried_event_google_url(): string {
		$calendar = new Calendar( (int) get_queried_object_id() );
		return $calendar->get_google_url();
	}

	/**
	 * Redirect callback that resolves to the queried event's Yahoo! Calendar URL.
	 *
	 * Wired into the `yahoo-calendar` Redirect endpoint so a hit on
	 * `/event/my-event/yahoo-calendar` redirects out to Yahoo!.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Yahoo! Calendar add-event URL for the queried event.
	 */
	public function queried_event_yahoo_url(): string {
		$calendar = new Calendar( (int) get_queried_object_id() );
		return $calendar->get_yahoo_url();
	}

	/**
	 * Print `<link rel="alternate">` tags into `<head>`, one per related calendar feed.
	 *
	 * Depending on the current request this can be one or multiple link tags,
	 * one for each relevant calendar link.
	 *
	 * At least the link tag for the main `/event/feed/ical`-endpoint is
	 * generated on each request.
	 *
	 * DRYed-out adoption of WordPress' core `feed_links_extra()`. Structure
	 * and flow of this method is replicated from the `feed_links()` and
	 * `feed_links_extra()` functions in WordPress core.
	 *
	 * @since  1.0.0
	 * @see    https://developer.wordpress.org/reference/functions/feed_links_extra/
	 *
	 * @return void
	 */
	public function alternate_links(): void {
		if ( ! current_theme_supports( 'automatic-feed-links' ) ) {
			return;
		}

		$args = array(
			'blogtitle'     => get_bloginfo( 'name' ),
			/* translators: Separator between site name and feed type in feed links. */
			'separator'     => _x( '&raquo;', 'feed link separator', 'gatherpress' ),
			/* translators: 1: Site name, 2: Separator (raquo), 3: Post title. */
			'singletitle'   => __( '📅 %1$s %2$s %3$s iCal Download', 'gatherpress' ),
			/* translators: 1: Site title, 2: Separator (raquo). */
			'feedtitle'     => __( '📅 %1$s %2$s iCal Feed', 'gatherpress' ),
			/* translators: 1: Site name, 2: Separator (raquo), 3: Post type name. */
			'posttypetitle' => __( '📅 %1$s %2$s %3$s iCal Feed', 'gatherpress' ),
			/* translators: 1: Site name, 2: Separator (raquo), 3: Term name, 4: Taxonomy singular name. */
			'taxtitle'      => __( '📅 %1$s %2$s %3$s %4$s iCal Feed', 'gatherpress' ),
		);

		$alternate_links = array();

		$alternate_links[] = array(
			'url'  => get_feed_link( self::ICAL_SLUG ),
			'attr' => sprintf(
				$args['feedtitle'],
				$args['blogtitle'],
				$args['separator']
			),
		);

		$event_post_types = get_post_types_by_support( 'gatherpress-event-date' );
		foreach ( $event_post_types as $event_post_type ) {
			$alternate_links[] = array(
				'url'  => get_post_type_archive_feed_link( $event_post_type, self::ICAL_SLUG ),
				'attr' => sprintf(
					$args['posttypetitle'],
					$args['blogtitle'],
					$args['separator'],
					post_type_archive_title( '', false )
				),
			);
		}

		if ( is_singular() && post_type_supports( get_queried_object()->post_type, 'gatherpress-event-date' ) ) {
			$calendar = new Calendar( (int) get_queried_object_id() );

			$alternate_links[] = array(
				'url'  => $calendar->get_ical_url(),
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					the_title_attribute( array( 'echo' => false ) )
				),
			);

			// Get all terms associated with the current event-post.
			$terms = get_terms(
				array(
					'taxonomy'   => get_object_taxonomies( get_queried_object() ),
					'object_ids' => get_queried_object_id(),
				)
			);
			// Loop over terms and generate the ical feed links for the <head>.
			array_walk(
				$terms,
				function ( WP_Term $term ) use ( $args, &$alternate_links ) {
					$tax = get_taxonomy( $term->taxonomy );
					switch ( $term->taxonomy ) {
						case '_gatherpress_venue':
							$venue = Venue_Setup::get_instance()->get_venue_post_from_term_slug( $term->slug );

							// An Online-Event will have no Venue; prevent error on non-existent object.
							// Feels weird to use a *_comments_* function here, but it delivers clean results
							// in the form of "domain.tld/event/my-sample-event/feed/ical/".
							$href = ( $venue )
								? get_post_comments_feed_link( $venue->ID, self::ICAL_SLUG )
								: null;
							break;

						default:
							$href = get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG );
							break;
					}
					// Can be empty for Online-Events.
					if ( ! empty( $href ) ) {
						$alternate_links[] = array(
							'url'  => $href,
							'attr' => sprintf(
								$args['taxtitle'],
								$args['blogtitle'],
								$args['separator'],
								$term->name,
								$tax->labels->singular_name
							),
						);
					}
				}
			);
		} elseif ( is_singular( 'gatherpress_venue' ) ) {
			// Feels weird to use a *_comments_* function here, but it delivers clean results
			// in the form of "domain.tld/venue/my-sample-venue/feed/ical/".
			$alternate_links[] = array(
				'url'  => get_post_comments_feed_link( get_queried_object_id(), self::ICAL_SLUG ),
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					the_title_attribute( array( 'echo' => false ) )
				),
			);
		} elseif ( is_tax() ) {
			$term                = get_queried_object();
			$has_event_post_type = array_filter(
				$event_post_types,
				static fn( $post_type ) => $term instanceof WP_Term && is_object_in_taxonomy( $post_type, $term->taxonomy ) // phpcs:ignore Generic.Files.LineLength.TooLong
			);

			if ( $has_event_post_type ) {
				$tax = get_taxonomy( $term->taxonomy );

				$alternate_links[] = array(
					'url'  => get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG ),
					'attr' => sprintf(
						$args['taxtitle'],
						$args['blogtitle'],
						$args['separator'],
						$term->name,
						$tax->labels->singular_name
					),
				);
			}
		}

		// Render tags into <head/>.
		array_walk(
			$alternate_links,
			function ( $link ) {
				printf(
					'<link rel="alternate" type="%s" title="%s" href="%s" />' . "\n",
					esc_attr( 'text/calendar' ),
					esc_attr( $link['attr'] ),
					esc_url( $link['url'] )
				);
			}
		);
	}

	/**
	 * Wrap iCal `BEGIN:VEVENT` blocks in a `BEGIN:VCALENDAR` envelope.
	 *
	 * Generates the `BEGIN:VCALENDAR` / `END:VCALENDAR` lines, the `VERSION`
	 * header, and the `PRODID` header (which includes the blog title and the
	 * current locale for proper calendar identification).
	 *
	 * @since 1.0.0
	 *
	 * @param string $calendar_data The events to be included in the iCal file.
	 * @return string               The complete iCal data wrapped in the VCALENDAR format.
	 */
	public function get_ical_wrap( string $calendar_data ): string {
		$args = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			sprintf(
				'PRODID:-//%s//GatherPress//%s',
				get_bloginfo( 'title' ),
				// Prepare 2-digit lang code.
				strtoupper( substr( get_locale(), 0, 2 ) )
			),
			$calendar_data,
			'END:VCALENDAR',
		);

		return implode( "\r\n", $args );
	}

	/**
	 * Build the iCal VEVENT list for the current query.
	 *
	 * Iterates the events in the current `WP_Query` (via the events query
	 * helper) and returns the concatenated `BEGIN:VEVENT` … `END:VEVENT`
	 * blocks. Used by `get_ical_feed()` to populate the VCALENDAR envelope.
	 *
	 * Supports:
	 * - The `gatherpress_event` post type archive (upcoming and past events).
	 * - Single `gatherpress_venue` requests (events for the queried venue).
	 * - Event-bearing taxonomies (events tagged with the queried term).
	 *
	 * @since 1.0.0
	 *
	 * @return string Concatenated VEVENT blocks for the queried events.
	 */
	public function get_ical_list(): string {
		$event_list_type = 'upcoming'; // Keep empty, to get all events from upcoming & past.
		$number          = ( is_feed( self::ICAL_SLUG ) ) ? -1 : get_option( 'posts_per_page' );
		$topics          = array();
		$venues          = array();
		$output          = array();

		if ( is_singular( 'gatherpress_venue' ) ) {
			$venues = array( '_' . get_queried_object()->post_name );
		} elseif ( is_tax() ) {
			$term = get_queried_object();

			// @todo How to be prepared for foreign taxonomies that might be registered by 3rd-parties?
			if ( $term && is_object_in_taxonomy( 'gatherpress_event', $term->taxonomy ) ) {
				if ( is_tax( 'gatherpress_topic' ) ) {
					$topics = array( $term->slug );
				}
			}
		}

		$query = Query::get_instance()->get_events_list( $event_list_type, $number, $topics, $venues );
		while ( $query->have_posts() ) {
			$query->the_post();
			$calendar = new Calendar( get_the_ID() );
			$output[] = $calendar->get_ical_event_string();
		}

		// Restore original Post Data.
		wp_reset_postdata();

		return implode( "\r\n", $output );
	}

	/**
	 * Complete iCal file content for the queried single event.
	 *
	 * Builds the VEVENT for the queried event via `Calendar::get_ical_event_string()`
	 * and wraps it in the VCALENDAR envelope.
	 *
	 * @since 1.0.0
	 *
	 * @return string The complete iCal file content for the queried event.
	 */
	public function get_ical_file(): string {
		$calendar = new Calendar( (int) get_queried_object_id() );
		return $this->get_ical_wrap( $calendar->get_ical_event_string() );
	}

	/**
	 * Complete iCal feed content for the current query.
	 *
	 * Builds the VEVENT list via `get_ical_list()` and wraps in VCALENDAR.
	 *
	 * @since 1.0.0
	 *
	 * @return string The complete iCal feed for the queried events.
	 */
	public function get_ical_feed(): string {
		return $this->get_ical_wrap( $this->get_ical_list() );
	}

	/**
	 * Generate the .ics filename based on the queried object.
	 *
	 * @since 1.0.0
	 *
	 * @return string Filename (with `.ics` extension) for the queried object.
	 */
	public function generate_ics_filename(): string {
		$queried_object = get_queried_object();
		$filename       = 'calendar';

		if ( is_singular() && post_type_supports( $queried_object->post_type, 'gatherpress-event-date' ) ) {
			$calendar  = new Calendar( $queried_object->ID );
			$date      = $calendar->event->get_datetime_start( 'Y-m-d' );
			$post_name = $queried_object->post_name;
			$filename  = $date . '_' . $post_name;
		} elseif ( is_singular( 'gatherpress_venue' ) ) {
			$filename = $queried_object->post_name;
		} elseif ( is_tax() ) {
			// @todo How to be prepared for foreign taxonomies that might be registered by 3rd-parties?
			if ( is_object_in_taxonomy( 'gatherpress_event', $queried_object->taxonomy ) ) {
				$filename = $queried_object->slug;
			}
		}

		return $filename . '.ics';
	}

	/**
	 * Send headers for the iCalendar (.ics) file response.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filename Generated name of the file.
	 * @return void
	 */
	public function send_ics_headers( string $filename ): void {
		$charset = strtolower( get_option( 'blog_charset' ) );

		header( 'Content-Description: File Transfer' );

		// Ensure proper content type for the calendar file.
		header( 'Content-Type: text/calendar; charset=' . $charset );

		// Force download in most browsers.
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// Avoid browser caching issues.
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Cache-Control: post-check=0, pre-check=0', false );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Prevent content sniffing which might lead to MIME type mismatch.
		header( 'X-Content-Type-Options: nosniff' );
	}

	/**
	 * Output the queried event(s) as an iCalendar (.ics) file.
	 *
	 * Dispatches to `get_ical_feed()` or `get_ical_file()` based on whether
	 * the request is a feed, sends the appropriate headers (including
	 * `Content-Length`), echoes the body, and exits.
	 *
	 * Called from the iCal templates after the endpoint resolves.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function send_ics_file(): void {
		// Start output buffering to capture all output.
		ob_start();

		// Prepare the filename.
		$filename = $this->generate_ics_filename();

		// Send headers for downloading the .ics file.
		$this->send_ics_headers( $filename );

		// Output the generated iCalendar content.
		$get_ical_method = ( is_feed() ) ? 'get_ical_feed' : 'get_ical_file';
		echo wp_kses_post( $this->{$get_ical_method}() );

		// Get the generated output and calculate file size.
		$ics_content = ob_get_contents();
		$filesize    = strlen( $ics_content );

		// Send the file size in the header.
		header( 'Content-Length: ' . $filesize );

		// End output buffering and clean up.
		ob_end_clean();

		// Output the iCalendar content.
		echo wp_kses_post( $ics_content );

		exit(); // Terminate the script after the file has been output.
	}
}
