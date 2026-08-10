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
 * @since 0.34.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Query;
use GatherPress\Core\Shadow_Source;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Post;
use WP_Post_Type;
use WP_Term;

/**
 * Calendar subsystem orchestrator.
 *
 * Singleton that wires up calendar endpoints, renders alternate-link tags
 * in `<head>`, and serves the .ics download/feed responses. Per-event data
 * surfaces (`get_google_url`, `get_ical_event_string`, etc.) live on the
 * sibling `Calendar` class, instantiated as `new Calendar( $event_id )`.
 *
 * @since 0.34.0
 */
final class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	const QUERY_VAR = 'gatherpress_calendar';
	const ICAL_SLUG = 'ical'; // Hardcoded ical slug — must not be translated or renamed.

	/**
	 * Class constructor.
	 *
	 * @since 0.34.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for registering custom calendar endpoints and the
	 * `<head>` link tags.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Register endpoints at `PHP_INT_MAX` on `init` so every post type,
		// taxonomy, and shadow-taxonomy wiring is in place before we ask
		// `is_object_in_taxonomy()` which one belongs to which. The previous
		// design used the per-registration `registered_post_type` /
		// `registered_taxonomy_for_object_type` actions, but those fire at the
		// moment each individual object is registered — before companion
		// subsystems (Venue\Setup, Shadow_Source) have attached their
		// taxonomies to events, so the venue endpoint silently failed its own
		// validity check and never registered its rewrite rule.
		add_action( 'init', array( $this, 'register_endpoints' ), PHP_INT_MAX );
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
		( new Post_Type_Feed(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR,
			$post_type
		) )->init();
		( new Post_Type_Single(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_file_template' ) ),
				new Template( 'outlook', array( $this, 'get_ical_file_template' ) ),
				new Redirect( 'google-calendar', array( $this, 'queried_event_google_url' ) ),
				new Redirect( 'yahoo-calendar', array( $this, 'queried_event_yahoo_url' ) ),
			),
			self::QUERY_VAR,
			$post_type
		) )->init();
	}

	/**
	 * Register the calendar feed endpoint for single venues.
	 *
	 * @since 0.34.0
	 *
	 * @param string $post_type The name of the post type that got registered last.
	 *
	 * @return void
	 */
	public function init_venues( string $post_type ): void {
		if ( ! $this->is_tax_like_type_for_event_supporting_types( $post_type ) ) {
			return;
		}

		( new Post_Type_Single_Feed(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR,
			$post_type
		) )->init();
	}

	/**
	 * Register a calendar feed endpoint for each event-bearing taxonomy.
	 *
	 * @since 0.34.0
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

		( new Taxonomy_Feed(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR,
			$taxonomy
		) )->init();
	}

	/**
	 * Register a sitewide calendar feed endpoint.
	 *
	 * Sets up the main `/feed/ical` endpoint that surfaces all events across the
	 * site, regardless of post type or taxonomy. This is the endpoint that gets
	 * linked in the main `<link rel="alternate">` tag in `<head>`.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function init_sitewide(): void {
		( new Sitewide_Feed(
			array(
				new Template( self::ICAL_SLUG, array( $this, 'get_ical_feed_template' ) ),
			),
			self::QUERY_VAR
		) )->init();
	}

	/**
	 * Template config for the iCal/Outlook download endpoint.
	 *
	 * Theme overrides win: a file with the same name placed in the active
	 * theme is loaded ahead of the bundled one.
	 *
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
	 *
	 * @return string The Google Calendar add-event URL for the queried event.
	 */
	public function queried_event_google_url(): string {
		$calendar = new Calendar( (int) get_queried_object_id() );
		return $calendar->get_google_destination_url();
	}

	/**
	 * Redirect callback that resolves to the queried event's Yahoo! Calendar URL.
	 *
	 * Wired into the `yahoo-calendar` Redirect endpoint so a hit on
	 * `/event/my-event/yahoo-calendar` redirects out to Yahoo!.
	 *
	 * @since 0.34.0
	 *
	 * @return string The Yahoo! Calendar add-event URL for the queried event.
	 */
	public function queried_event_yahoo_url(): string {
		$calendar = new Calendar( (int) get_queried_object_id() );
		return $calendar->get_yahoo_destination_url();
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
	 * @since  0.34.0
	 * @see    https://developer.wordpress.org/reference/functions/feed_links_extra/
	 *
	 * @return void
	 */
	public function alternate_links(): void {
		if ( ! current_theme_supports( 'automatic-feed-links' ) ) {
			return;
		}
		$args  = $this->alternate_link_label_args();
		$links = array_merge(
			$this->collect_sitewide_alternate_link( $args ),
			$this->collect_post_type_archive_alternate_links( $args ),
			$this->collect_contextual_alternate_links( $args )
		);
		$this->render_alternate_links( $links );
	}

	/**
	 * Build the localized label args used to format alternate-link titles.
	 *
	 * Returns the site title, the locale-specific separator (defaults to
	 * `&raquo;`), and the four `sprintf()` templates the link builders
	 * consume: `singletitle`, `feedtitle`, `posttypetitle`, `taxtitle`.
	 *
	 * @since 0.34.0
	 *
	 * @return array{blogtitle:string,separator:string,singletitle:string,feedtitle:string,posttypetitle:string,taxtitle:string}
	 */
	protected function alternate_link_label_args(): array {
		return array(
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
	}

	/**
	 * Build the single sitewide `<link rel="alternate">` entry.
	 *
	 * Always emitted on every request that reaches `alternate_links()`.
	 *
	 * @since 0.34.0
	 *
	 * @param array $args Label args from `alternate_link_label_args()`.
	 *
	 * @return array<int,array{url:string,attr:string}> One-element list.
	 */
	protected function collect_sitewide_alternate_link( array $args ): array {
		return array(
			array(
				'url'  => get_feed_link( self::ICAL_SLUG ),
				'attr' => sprintf(
					$args['feedtitle'],
					$args['blogtitle'],
					$args['separator']
				),
			),
		);
	}

	/**
	 * Build one `<link rel="alternate">` entry per event-supporting post-type archive.
	 *
	 * Reads the archive title straight off the post type object instead of via
	 * `post_type_archive_title()` — that function early-returns outside an
	 * `is_post_type_archive()` context, which is exactly the case here when
	 * this hook fires on a non-archive page. Invoking the
	 * `post_type_archive_title` filter directly from plugin code also trips
	 * WordPress.NamingConventions.PrefixAllGlobals because it's a core hook
	 * not owned by GatherPress.
	 *
	 * @since 0.34.0
	 *
	 * @param array $args Label args from `alternate_link_label_args()`.
	 *
	 * @return array<int,array{url:string,attr:string}> One entry per event-supporting post type.
	 */
	protected function collect_post_type_archive_alternate_links( array $args ): array {
		$links = array();

		foreach ( get_post_types_by_support( 'gatherpress-event-date' ) as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			// The fallback to the bare slug only fires when `get_post_type_object()`
			// returns null — structurally unreachable here because the loop
			// iterates `get_post_types_by_support()`, which only yields registered
			// post types. Defensive code that needs no test invocation.
			$archive_title = $post_type_object instanceof WP_Post_Type
				? $post_type_object->labels->name
				: $post_type; // @codeCoverageIgnore
			$links[]       = array(
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

		return $links;
	}

	/**
	 * Dispatch the contextual `<link rel="alternate">` entries for the current request.
	 *
	 * Returns the per-request additions on top of the always-on sitewide and
	 * per-post-type-archive entries. Dispatches on the queried object:
	 * singular event, singular tax-like shadow-source post, or taxonomy
	 * archive. Returns an empty list for any other context.
	 *
	 * @since 0.34.0
	 *
	 * @param array $args Label args from `alternate_link_label_args()`.
	 *
	 * @return array<int,array{url:string,attr:string}>
	 */
	protected function collect_contextual_alternate_links( array $args ): array {
		$queried = get_queried_object();

		if ( is_singular() && post_type_supports( $queried->post_type, 'gatherpress-event-date' ) ) {
			return $this->collect_singular_event_alternate_links( $queried, $args );
		}

		if ( is_singular() && $this->is_tax_like_type_for_event_supporting_types( $queried->post_type ) ) {
			return $this->collect_singular_tax_like_alternate_links( $queried, $args );
		}

		if ( is_tax() && $this->has_post_type_for_taxonomy( $queried->taxonomy ) ) {
			return $this->collect_tax_archive_alternate_links( $queried, $args );
		}

		return array();
	}

	/**
	 * Build the alternate-link entries for a singular event request.
	 *
	 * Always emits the single-event iCal download link; appends one entry
	 * per related taxonomy term via `collect_event_term_alternate_links()`.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Post $event The queried event post.
	 * @param array   $args  Label args from `alternate_link_label_args()`.
	 *
	 * @return array<int,array{url:string,attr:string}>
	 */
	protected function collect_singular_event_alternate_links( WP_Post $event, array $args ): array {
		$calendar = new Calendar( $event->ID );
		$links    = array(
			array(
				'url'  => $calendar->get_ical_url(),
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					the_title_attribute( array( 'echo' => false ) )
				),
			),
		);

		return array_merge( $links, $this->collect_event_term_alternate_links( $event, $args ) );
	}

	/**
	 * Build the alternate-link entries for a singular tax-like shadow-source request.
	 *
	 * Feels weird to use a `*_comments_*` function here, but it delivers
	 * clean results in the form of `domain.tld/venue/my-sample-venue/feed/ical/`.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Post $post The queried shadow-source post (e.g. a venue).
	 * @param array   $args Label args from `alternate_link_label_args()`.
	 *
	 * @return array<int,array{url:string,attr:string}>
	 */
	protected function collect_singular_tax_like_alternate_links( WP_Post $post, array $args ): array {
		return array(
			array(
				'url'  => get_post_comments_feed_link( $post->ID, self::ICAL_SLUG ),
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					the_title_attribute( array( 'echo' => false ) )
				),
			),
		);
	}

	/**
	 * Build the alternate-link entries for an event-bearing taxonomy archive.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Term $term The queried taxonomy term.
	 * @param array   $args Label args from `alternate_link_label_args()`.
	 *
	 * @return array<int,array{url:string,attr:string}>
	 */
	protected function collect_tax_archive_alternate_links( WP_Term $term, array $args ): array {
		$tax = get_taxonomy( $term->taxonomy );

		return array(
			array(
				'url'  => get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG ),
				'attr' => sprintf(
					$args['taxtitle'],
					$args['blogtitle'],
					$args['separator'],
					$term->name,
					$tax->labels->singular_name
				),
			),
		);
	}

	/**
	 * Walk the queried event's related terms into alternate-link entries.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Post $event The queried event post.
	 * @param array   $args  Label args from `alternate_link_label_args()`.
	 *
	 * @return array<int,array{url:string,attr:string}>
	 */
	protected function collect_event_term_alternate_links( WP_Post $event, array $args ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => get_object_taxonomies( $event ),
				'object_ids' => $event->ID,
			)
		);

		$links = array();

		foreach ( $terms as $term ) {
			$links = array_merge( $links, $this->collect_term_alternate_link( $term, $args ) );
		}

		return $links;
	}

	/**
	 * Resolve a single related term into zero or one alternate-link entries.
	 *
	 * For shadow-source taxonomies the link points at the associated post's
	 * comments-feed URL (so `gatherpress_venue` resolves to the venue
	 * post's feed, not the term archive feed). Sentinel shadow terms like
	 * `online-event` — slugs that don't start with `_` — are skipped because
	 * they have no backing post. For regular taxonomies the term archive
	 * feed link is used directly.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Term $term Term attached to the queried event.
	 * @param array   $args Label args from `alternate_link_label_args()`.
	 *
	 * @return array<int,array{url:string,attr:string}> Empty for sentinel terms; otherwise one entry.
	 */
	protected function collect_term_alternate_link( WP_Term $term, array $args ): array {
		$shadow_source = Shadow_Source::get_instance();
		$href          = '';

		if ( $shadow_source->is_shadow_term_slug( $term->taxonomy ) ) {
			// Skip sentinel shadow terms like `online-event` whose slug does
			// not start with `_` — no backing post means no feed to link to.
			if ( $shadow_source->is_shadow_term_slug( $term->slug ) ) {
				$post = $shadow_source->get_post_from_term_slug(
					$term->slug,
					ltrim( $term->taxonomy, '_' )
				);
				// Feels weird to use a *_comments_* function here, but it delivers clean results
				// in the form of "domain.tld/event/my-sample-event/feed/ical/".
				$href = get_post_comments_feed_link( $post->ID, self::ICAL_SLUG );
			}
		} else {
			$href = get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG );
		}

		if ( empty( $href ) ) {
			return array();
		}

		$tax = get_taxonomy( $term->taxonomy );

		return array(
			array(
				'url'  => $href,
				'attr' => sprintf(
					$args['taxtitle'],
					$args['blogtitle'],
					$args['separator'],
					$term->name,
					$tax->labels->singular_name
				),
			),
		);
	}

	/**
	 * Render the collected alternate-link entries into `<head>`.
	 *
	 * @since 0.34.0
	 *
	 * @param array<int,array{url:string,attr:string}> $links Entries to render.
	 *
	 * @return void
	 */
	protected function render_alternate_links( array $links ): void {
		array_walk(
			$links,
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
	 * @since 0.34.0
	 *
	 * @param string $calendar_data The events to be included in the iCal file.
	 *
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
	 *
	 * @return string The complete iCal feed for the queried events.
	 */
	public function get_ical_feed(): string {
		return $this->get_ical_wrap( $this->get_ical_list() );
	}

	/**
	 * Generate the .ics filename based on the queried object.
	 *
	 * @since 0.34.0
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
	 * @since 0.34.0
	 *
	 * @param string $filename Generated name of the file.
	 *
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
