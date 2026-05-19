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
use GatherPress\Core\Shadow_Source;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Term;
use WP_Post;

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
		// Register endpoints late on `init` so every post type, taxonomy, and
		// shadow-taxonomy wiring is in place before we ask `is_object_in_taxonomy()`
		// which one belongs to which. The previous design used the per-registration
		// `registered_post_type` / `registered_taxonomy_for_object_type` actions,
		// but those fire at the moment each individual object is registered —
		// before companion subsystems (Venue\Setup, Shadow_Source) have attached
		// their taxonomies to events, so the venue endpoint silently failed its
		// own validity check and never registered its rewrite rule.
		add_action( 'init', array( $this, 'register_endpoints' ), 99 );
		add_action( 'wp_head', array( $this, 'alternate_links' ) );
	}

	/**
	 * Register every calendar endpoint after all post types and taxonomies are set up.
	 *
	 * Iterates supported post types and event-bearing taxonomies and delegates
	 * to the per-target `init_*()` helpers. Runs at `init` priority 99 so it
	 * fires after WP core's built-in post types, GatherPress's own post types,
	 * and any companion plugin that registers on `init` at default priority.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		$event_types = get_post_types_by_support( 'gatherpress-event-date' );
		if ( empty( $event_types ) ) {
			return;
		}

		$this->init_sitewide();

		foreach ( $event_types as $post_type ) {
			$this->init_events( $post_type );
		}

		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			$this->init_venues( $post_type );
		}

		foreach ( get_taxonomies( array( 'public' => true ) ) as $taxonomy ) {
			$this->init_taxonomies( $taxonomy );
		}
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
	 * @param string $post_type The name of the post type that got registered last.
	 *
	 * @return void
	 */
	public function init_venues( string $post_type ): void {

		if ( ! $this->is_tax_like_type_for_event_supporting_types( $post_type ) ) {
			return;
		}

		new Post_Type_Single_Feed(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR,
			$post_type
		);
	}

	/**
	 * Register a calendar feed endpoint for each event-bearing taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy    Name of the taxonomy that got registered last.
	 *
	 * @return void
	 */
	public function init_taxonomies( string $taxonomy ): void {
		// Stop if the currently registered taxonomy does not validate.
		if ( // Stop, if taxonomy is not registered for any event-date supporting post type.
			! $this->has_post_type_for_taxonomy( $taxonomy ) ||
			// Stop, if taxonomy is not public.
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
	 * Register a sitewide calendar feed endpoint.
	 *
	 * Sets up the main `/feed/ical` endpoint that surfaces all events across the
	 * site, regardless of post type or taxonomy. This is the endpoint that gets
	 * linked in the main `<link rel="alternate">` tag in `<head>`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init_sitewide(): void {
		new Sitewide_Feed(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR
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

		foreach ( get_post_types_by_support( 'gatherpress-event-date' ) as $post_type ) {
			// Read the archive title straight off the post type object instead
			// of via `post_type_archive_title()` — that function early-returns
			// outside an `is_post_type_archive()` context, which is exactly the
			// case here when this hook fires on a non-archive page. Invoking
			// the `post_type_archive_title` filter directly from plugin code
			// also trips WordPress.NamingConventions.PrefixAllGlobals because
			// it's a core hook not owned by GatherPress.
			$post_type_object = get_post_type_object( $post_type );
			// The fallback to the bare slug only fires when `get_post_type_object()`
			// returns null — structurally unreachable here because the outer loop
			// iterates `get_post_types_by_support()`, which only yields registered
			// post types. Defensive code that needs no test invocation.
			$archive_title     = $post_type_object instanceof \WP_Post_Type
				? $post_type_object->labels->name
				: $post_type; // @codeCoverageIgnore
			$alternate_links[] = array(
				'url'  => get_post_type_archive_feed_link(
					$post_type,
					self::ICAL_SLUG
				),
				'attr' => sprintf(
					$args['posttypetitle'],
					$args['blogtitle'],
					$args['separator'],
					$archive_title
				),
			);
		}

		if ( is_singular() && post_type_supports( get_queried_object()->post_type, 'gatherpress-event-date' ) ) {
			$calendar = new Calendar( get_queried_object()->ID );

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
					'object_ids' => get_queried_object()->ID,
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
							$href = get_term_feed_link(
								$term->term_id,
								$term->taxonomy,
								self::ICAL_SLUG
							);
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
		} elseif (
			is_singular()
			&& $this->is_tax_like_type_for_event_supporting_types( get_queried_object()->post_type )
		) {
			// Feels weird to use a *_comments_* function here, but it delivers clean results
			// in the form of "domain.tld/venue/my-sample-venue/feed/ical/".
			$alternate_links[] = array(
				'url'  => get_post_comments_feed_link(
					get_queried_object()->ID,
					self::ICAL_SLUG
				),
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					the_title_attribute( array( 'echo' => false ) )
				),
			);
		} elseif ( is_tax() && $this->has_post_type_for_taxonomy( get_queried_object()->taxonomy ) ) {
			$tax = get_taxonomy( get_queried_object()->taxonomy );

			$alternate_links[] = array(
				'url'  => get_term_feed_link(
					get_queried_object()->term_id,
					get_queried_object()->taxonomy,
					self::ICAL_SLUG
				),
				'attr' => sprintf(
					$args['taxtitle'],
					$args['blogtitle'],
					$args['separator'],
					get_queried_object()->name,
					$tax->labels->singular_name
				),
			);
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

		if ( is_singular() && $this->is_tax_like_type_for_event_supporting_types( get_queried_object()->post_type ) ) {
			if ( is_singular( 'gatherpress_venue' ) ) {
				$venues = array( '_' . get_queried_object()->post_name );
			}
		} elseif ( is_tax() && $this->has_post_type_for_taxonomy( get_queried_object()->taxonomy ) ) {
			if ( is_tax( 'gatherpress_topic' ) ) {
				$topics = array( get_queried_object()->slug );
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
		} elseif ( is_singular() && $this->is_tax_like_type_for_event_supporting_types( $queried_object->post_type ) ) {
			$filename = $queried_object->post_name;
		} elseif ( is_tax() && $this->has_post_type_for_taxonomy( $queried_object->taxonomy ) ) {
			$filename = $queried_object->slug;
		} elseif ( is_post_type_archive() ) {
			// `$queried_object` is the WP_Post_Type here. `rewrite` is `false`
			// when the post type opted out of rewrite rules — fall back to the
			// default filename in that case rather than `false['slug']`-ing.
			$filename = is_array( $queried_object->rewrite ) ? $queried_object->rewrite['slug'] : $filename;
		} elseif ( is_feed() && ! is_singular() && ! is_tax() ) {
			$filename = str_replace(
				'.',
				'-',
				wp_parse_url( home_url(), PHP_URL_HOST )
			);
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
		// The whole method body is integration-tested against the live Lando
		// install (see PR #955 testing notes); unit-coverage is impractical
		// because the trailing `exit()` terminates the test runner. Marked
		// untestable rather than restructured so the production flow stays
		// straight (ob_start, headers, body, echo, exit).
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation.
		// @codeCoverageIgnoreStart
		ob_start();

		// Prepare the filename.
		$filename = $this->generate_ics_filename();

		// Send headers for downloading the .ics file.
		$this->send_ics_headers( $filename );

		// Build the iCalendar content. The body is plain text per RFC 5545
		// (not HTML), so HTML-sanitizers like `wp_kses_post()` are the wrong
		// tool here — they would encode `&` into `&amp;` and produce broken
		// .ics files. The TEXT-property values inside are already escaped at
		// build time via `Calendar::escape_ical_text()` / sanitized via
		// `sanitize_text_field()`.
		$get_ical_method = ( is_feed() ) ? 'get_ical_feed' : 'get_ical_file';
		$ics_content     = (string) $this->{$get_ical_method}();
		$filesize        = strlen( $ics_content );

		// Send the file size in the header.
		header( 'Content-Length: ' . $filesize );

		// End output buffering and clean up.
		ob_end_clean();

		// Output the iCalendar content.
		echo $ics_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Terminate the script after the file has been output.
		exit();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Check if any post type is registered with a taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy   Taxonomy slug.
	 *
	 * @return bool
	 */
	protected function has_post_type_for_taxonomy( string $taxonomy ): bool {
		$post_types = get_post_types_by_support( 'gatherpress-event-date' );
		foreach ( $post_types as $post_type ) {
			if ( is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the given post could be related to an event-supporting post type.
	 *
	 * The methods checks whether the given posts type supports 'gatherpress-shadow-source'
	 * and if its taxonomy is one that is related to any 'gatherpress-event-date' supporting post type.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $post_type  The post_type to check.
	 *
	 * @return bool
	 */
	protected function is_tax_like_type_for_event_supporting_types( string $post_type ): bool {
		return post_type_supports( $post_type, 'gatherpress-shadow-source' ) &&
			$this->has_post_type_for_taxonomy( Shadow_Source::get_instance()->get_taxonomy( $post_type ) );
	}
}
