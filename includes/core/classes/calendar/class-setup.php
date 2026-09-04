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
use GatherPress\Core\Topic;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;
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
 *
 * @phpstan-type LabelArgs array{
 *   blogtitle: string,
 *   separator: string,
 *   singletitle: string,
 *   feedtitle: string,
 *   posttypetitle: string,
 *   taxtitle: string
 * }
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
		Cache::get_instance();

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
		// PHP_INT_MIN so the calendar redirect runs before redirect_canonical() and any theme's own handler.
		add_action( 'template_redirect', array( $this, 'maybe_handle_content_negotiation' ), PHP_INT_MIN );
		add_filter( 'wp_headers', array( $this, 'filter_wp_headers' ) );
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
		$taxonomy_object = get_taxonomy( $taxonomy );

		// Stop if the currently registered taxonomy does not validate.
		if ( // Stop, if the taxonomy is not registered.
			! $taxonomy_object ||
			// Stop, if taxonomy is not registered for any event-date supporting post type.
			! $this->has_post_type_for_taxonomy( $taxonomy ) ||
			// Stop, if taxonomy is not public.
			! is_taxonomy_viewable( $taxonomy_object ) ||
			false === $taxonomy_object->rewrite
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
	 * @return array{file_name: string, dir_path?: string} Template descriptor with `file_name` (and optional
	 *                                                      `dir_path`) keys.
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
	 * @return array{file_name: string, dir_path?: string} Template descriptor with `file_name` (and optional
	 *                                                      `dir_path`) keys.
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
	 * @return LabelArgs
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
	 * @phpstan-param LabelArgs $args
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
	 * @phpstan-param LabelArgs $args
	 *
	 * @return array<int,array{url:string,attr:string}> One entry per event-supporting post type.
	 */
	protected function collect_post_type_archive_alternate_links( array $args ): array {
		$links = array();

		foreach ( get_post_types_by_support( 'gatherpress-event-date' ) as $post_type ) {
			$feed_link = get_post_type_archive_feed_link( $post_type, self::ICAL_SLUG );

			// A post type registered without an archive has no archive feed to advertise.
			if ( false === $feed_link ) {
				continue;
			}

			$post_type_object = get_post_type_object( $post_type );
			// The fallback to the bare slug only fires when `get_post_type_object()`
			// returns null — structurally unreachable here because the loop
			// iterates `get_post_types_by_support()`, which only yields registered
			// post types. Defensive code that needs no test invocation.
			$archive_title = $post_type_object instanceof WP_Post_Type
				? $post_type_object->labels->name
				: $post_type; // @codeCoverageIgnore
			$links[]       = array(
				'url'  => $feed_link,
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
	 * @phpstan-param LabelArgs $args
	 *
	 * @return array<int,array{url:string,attr:string}>
	 */
	protected function collect_contextual_alternate_links( array $args ): array {
		$queried = get_queried_object();

		if ( is_singular() && $queried instanceof WP_Post ) {
			if ( post_type_supports( $queried->post_type, 'gatherpress-event-date' ) ) {
				return $this->collect_singular_event_alternate_links( $queried, $args );
			}

			if ( $this->is_tax_like_type_for_event_supporting_types( $queried->post_type ) ) {
				return $this->collect_singular_tax_like_alternate_links( $queried, $args );
			}
		}

		if ( is_tax() && $queried instanceof WP_Term && $this->has_post_type_for_taxonomy( $queried->taxonomy ) ) {
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
	 * @phpstan-param LabelArgs $args
	 *
	 * @return array<int,array{url:string,attr:string}>
	 */
	protected function collect_singular_event_alternate_links( WP_Post $event, array $args ): array {
		// the_title_attribute() returns nothing for an empty title.
		$title = the_title_attribute( array( 'echo' => false ) );
		$title = is_string( $title ) ? $title : '';

		$calendar = new Calendar( $event->ID );
		$ical_url = $calendar->get_ical_url();
		$links    = array();

		// False when the event post no longer resolves.
		if ( false !== $ical_url ) {
			$links[] = array(
				'url'  => $ical_url,
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					$title
				),
			);
		}

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
	 * @phpstan-param LabelArgs $args
	 *
	 * @return array<int,array{url:string,attr:string}>
	 */
	protected function collect_singular_tax_like_alternate_links( WP_Post $post, array $args ): array {
		// the_title_attribute() returns nothing for an empty title.
		$title = the_title_attribute( array( 'echo' => false ) );
		$title = is_string( $title ) ? $title : '';

		return array(
			array(
				'url'  => get_post_comments_feed_link( $post->ID, self::ICAL_SLUG ),
				'attr' => sprintf(
					$args['singletitle'],
					$args['blogtitle'],
					$args['separator'],
					$title
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
	 * @phpstan-param LabelArgs $args
	 *
	 * @return array<int,array{url:string,attr:string}>
	 */
	protected function collect_tax_archive_alternate_links( WP_Term $term, array $args ): array {
		$href = get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG );

		// False when the term no longer resolves.
		if ( false === $href ) {
			return array();
		}

		return array(
			array(
				'url'  => $href,
				'attr' => sprintf(
					$args['taxtitle'],
					$args['blogtitle'],
					$args['separator'],
					$term->name,
					Utility::taxonomy_label( 'singular_name', $term->taxonomy )
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
	 * @phpstan-param LabelArgs $args
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

		// Only a `get_terms` filter can produce this; iterating it would fatal below.
		if ( is_wp_error( $terms ) ) {
			return array();
		}

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
	 * @phpstan-param LabelArgs $args
	 *
	 * @return array<int,array{url:string,attr:string}> Empty for sentinel terms; otherwise one entry.
	 */
	protected function collect_term_alternate_link( WP_Term $term, array $args ): array {
		$shadow_source = Shadow_Source::get_instance();
		$href          = '';

		if ( ! $shadow_source->is_shadow_term_slug( $term->taxonomy ) ) {
			$href = get_term_feed_link( $term->term_id, $term->taxonomy, self::ICAL_SLUG );
		} elseif ( $shadow_source->is_shadow_term_slug( $term->slug ) ) {
			// Skip sentinel shadow terms like `online-event` whose slug does
			// not start with `_` - no backing post means no feed to link to.
			$post = $shadow_source->get_post_from_term_slug(
				$term->slug,
				ltrim( $term->taxonomy, '_' )
			);

			// Without this, get_post_comments_feed_link( null ) falls back to the global post.
			if ( $post instanceof WP_Post ) {
				// Feels weird to use a *_comments_* function here, but it delivers clean results
				// in the form of "domain.tld/event/my-sample-event/feed/ical/".
				$href = get_post_comments_feed_link( $post->ID, self::ICAL_SLUG );
			}
		}

		if ( empty( $href ) ) {
			return array();
		}

		return array(
			array(
				'url'  => $href,
				'attr' => sprintf(
					$args['taxtitle'],
					$args['blogtitle'],
					$args['separator'],
					$term->name,
					Utility::taxonomy_label( 'singular_name', $term->taxonomy )
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
		$queried_object  = get_queried_object();

		if (
			is_singular( Venue::POST_TYPE ) &&
			$queried_object instanceof WP_Post &&
			$this->is_tax_like_type_for_event_supporting_types( $queried_object->post_type )
		) {
			$venues = array( '_' . $queried_object->post_name );
		} elseif (
			is_tax( Topic::TAXONOMY ) &&
			$queried_object instanceof WP_Term &&
			$this->has_post_type_for_taxonomy( $queried_object->taxonomy )
		) {
			$topics = array( $queried_object->slug );
		}

		$query = Query::get_instance()->get_events_list( $event_list_type, $number, $topics, $venues );
		while ( $query->have_posts() ) {
			$query->the_post();
			$calendar = new Calendar( (int) get_the_ID() );
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

		if ( is_singular() && $queried_object instanceof WP_Post ) {
			if ( post_type_supports( $queried_object->post_type, 'gatherpress-event-date' ) ) {
				$calendar  = new Calendar( $queried_object->ID );
				$date      = $calendar->event->get_datetime_start( 'Y-m-d' );
				$post_name = $queried_object->post_name;
				$filename  = $date . '_' . $post_name;
			} elseif ( $this->is_tax_like_type_for_event_supporting_types( $queried_object->post_type ) ) {
				$filename = $queried_object->post_name;
			}
		} elseif (
			is_tax() &&
			$queried_object instanceof WP_Term &&
			$this->has_post_type_for_taxonomy( $queried_object->taxonomy )
		) {
			$filename = $queried_object->slug;
		} elseif ( is_post_type_archive() && $queried_object instanceof WP_Post_Type ) {
			// `rewrite` is false when the post type opted out of rewrite rules.
			$filename = is_array( $queried_object->rewrite ) ? $queried_object->rewrite['slug'] : $filename;
		} elseif ( is_feed() && ! is_singular() && ! is_tax() ) {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );

			// A site URL without a parsable host keeps the default filename.
			$filename = is_string( $host ) ? str_replace( '.', '-', $host ) : $filename;
		}

		return $filename . '.ics';
	}

	/**
	 * Send headers for the iCalendar (.ics) file response.
	 *
	 * @since 0.34.0
	 *
	 * @since 0.36.0 Added `$etag` and `$last_modified` for cache validation.
	 *
	 * @param string $filename      Generated name of the file.
	 * @param string $etag          Optional. Entity tag for the body being sent.
	 * @param string $last_modified Optional. GMT timestamp of the last calendar change.
	 *
	 * @return void
	 */
	public function send_ics_headers( string $filename, string $etag = '', string $last_modified = '' ): void {
		$charset = strtolower( get_option( 'blog_charset' ) );
		$max_age = Cache::get_instance()->get_max_age();

		header( 'Content-Description: File Transfer' );

		// Ensure proper content type for the calendar file.
		header( 'Content-Type: text/calendar; charset=' . $charset );

		// Force download in most browsers.
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		// Subscribed clients poll on their own schedule, several times an hour
		// in Outlook's and Apple Calendar's defaults, so the response tells them
		// how long it stays fresh and hands them validators to revalidate with.
		// A site can opt out entirely by filtering the max age to 0.
		if ( 0 < $max_age ) {
			header( sprintf( 'Cache-Control: public, max-age=%d', $max_age ) );
		} else {
			header( 'Cache-Control: no-store, no-cache, must-revalidate' );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
		}

		if ( '' !== $etag ) {
			header( sprintf( 'ETag: %s', $etag ) );
		}

		if ( '' !== $last_modified ) {
			$timestamp = (int) strtotime( $last_modified . ' GMT' );

			header( sprintf( 'Last-Modified: %s GMT', gmdate( 'D, d M Y H:i:s', $timestamp ) ) );
		}

		// Prevent content sniffing which might lead to MIME type mismatch.
		header( 'X-Content-Type-Options: nosniff' );
	}

	/**
	 * Cached iCalendar body for the current request.
	 *
	 * @since 0.36.0
	 *
	 * @return string The complete iCal payload.
	 */
	public function get_ics_body(): string {
		$is_feed = is_feed();

		return Cache::get_instance()->remember(
			$this->get_ics_cache_key(),
			function () use ( $is_feed ): string {
				return (string) ( $is_feed ? $this->get_ical_feed() : $this->get_ical_file() );
			}
		);
	}

	/**
	 * Cache key describing what the current request asks for.
	 *
	 * Built from the resolved query rather than the request URI, so unknown
	 * query parameters cannot fragment the cache into unbounded entries.
	 *
	 * @since 0.36.0
	 *
	 * @return string Scope-specific cache key.
	 */
	public function get_ics_cache_key(): string {
		$queried_object = get_queried_object();
		$scope          = array(
			'feed'   => is_feed() ? 1 : 0,
			'paged'  => (int) get_query_var( 'paged' ),
			'object' => 0,
			'type'   => '',
		);

		if ( $queried_object instanceof WP_Post ) {
			$scope['object'] = (int) $queried_object->ID;
			$scope['type']   = (string) $queried_object->post_type;
		} elseif ( $queried_object instanceof WP_Term ) {
			$scope['object'] = (int) $queried_object->term_id;
			$scope['type']   = (string) $queried_object->taxonomy;
		} elseif ( $queried_object instanceof WP_Post_Type ) {
			$scope['type'] = (string) $queried_object->name;
		}

		return sprintf( 'ics:%s', md5( (string) wp_json_encode( $scope ) ) ); // NOSONAR.
	}

	/**
	 * ETag for an iCalendar body.
	 *
	 * @since 0.36.0
	 *
	 * @param string $body The rendered iCal payload.
	 *
	 * @return string Quoted entity tag, per RFC 9110.
	 */
	public function get_etag( string $body ): string {
		return sprintf( '"%s"', md5( $body ) ); // NOSONAR.
	}

	/**
	 * Whether the client already holds this exact response.
	 *
	 * `If-None-Match` wins over `If-Modified-Since` when both are present,
	 * which is what RFC 9110 asks for: the entity tag is exact where the
	 * timestamp has one-second resolution.
	 *
	 * @since 0.36.0
	 *
	 * @param string $etag          Current entity tag.
	 * @param string $last_modified Current GMT modification timestamp.
	 *
	 * @return bool True when a 304 is the correct answer.
	 */
	public function is_not_modified( string $etag, string $last_modified ): bool {
		$client_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) )
			: '';

		if ( '' !== $client_etag ) {
			// A cache may return the tag weakened, and may hold several.
			$tags = array_map( 'trim', explode( ',', str_replace( 'W/', '', $client_etag ) ) );
			return in_array( $etag, $tags, true );
		}

		$client_time = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
			: '';

		if ( '' === $client_time ) {
			return false;
		}

		$client_timestamp = strtotime( $client_time );
		$current          = strtotime( $last_modified . ' GMT' );

		return false !== $client_timestamp && false !== $current && $client_timestamp >= $current;
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

		// Build the iCalendar content before the headers now, because the ETag
		// is a hash of the body. The body is plain text per RFC 5545 (not
		// HTML), so HTML-sanitizers like `wp_kses_post()` are the wrong tool
		// here — they would encode `&` into `&amp;` and produce broken .ics
		// files. The TEXT-property values inside are already escaped at build
		// time via `Calendar::escape_ical_text()` / sanitized via
		// `sanitize_text_field()`.
		$ics_content   = $this->get_ics_body();
		$etag          = $this->get_etag( $ics_content );
		$last_modified = Cache::get_instance()->get_last_modified();

		// A subscribed client that already holds this exact calendar gets a
		// validator response instead of the payload.
		if ( $this->is_not_modified( $etag, $last_modified ) ) {
			ob_end_clean();
			$this->send_ics_headers( $filename, $etag, $last_modified );
			status_header( 304 );

			exit();
		}

		$this->send_ics_headers( $filename, $etag, $last_modified );

		// Send the file size in the header.
		header( 'Content-Length: ' . strlen( $ics_content ) );

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

	/**
	 * Determine whether the client's `Accept` HTTP header prefers `text/calendar`.
	 *
	 * Implements RFC 7231 / RFC 9110 content negotiation by parsing comma-separated
	 * media ranges and their quality factor (`q=`) weights. Returns `true` if
	 * `text/calendar` is requested with an explicit quality weight equal to or
	 * higher than standard HTML formats (`text/html`, `application/xhtml+xml`).
	 *
	 * @since 0.36.0
	 *
	 * @param string|null $accept_header Optional raw Accept header. Defaults to `$_SERVER['HTTP_ACCEPT']`.
	 *
	 * @return bool True if text/calendar is negotiated/preferred, false otherwise.
	 */
	public function is_calendar_negotiated( ?string $accept_header = null ): bool {
		if ( null === $accept_header ) {
			$accept_header = isset( $_SERVER['HTTP_ACCEPT'] ) && is_string( $_SERVER['HTTP_ACCEPT'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) )
				: '';
		}

		if ( '' === $accept_header ) {
			return false;
		}

		$ranges = array();

		foreach ( explode( ',', $accept_header ) as $range ) {
			$q    = preg_match( '/;\s*q=([0-9.]+)/i', $range, $m ) ? (float) $m[1] : 1.0;
			$mime = strtolower( trim( explode( ';', $range, 2 )[0] ) );

			if ( '' !== $mime ) {
				$ranges[ $mime ] = max( $ranges[ $mime ] ?? 0.0, $q );
			}
		}

		// Only the exact media type counts for the calendar. A wildcard stands
		// in for HTML below, but it must not stand in here: `text/calendar+json,
		// */*` asks for a different type, and the wildcard's quality would
		// otherwise carry the calendar past HTML and redirect a client that
		// never asked for it.
		if ( ! isset( $ranges['text/calendar'] ) ) {
			return false;
		}

		$calendar_q = $ranges['text/calendar'];
		$html_q     = max(
			$this->accept_quality( $ranges, 'text/html' ),
			$this->accept_quality( $ranges, 'application/xhtml+xml' )
		);

		return $calendar_q > 0.0 && $calendar_q >= $html_q;
	}

	/**
	 * Quality value a parsed Accept header assigns to one media type.
	 *
	 * RFC 9110 section 12.5.1 ranks media ranges by precision, so the most
	 * specific range that covers the type wins rather than the highest one.
	 * Against an Accept of `text/calendar;q=0.5` plus a catch-all wildcard at
	 * `q=0.9`, the calendar scores 0.5 from its own entry, while HTML, which
	 * has no entry of its own, scores 0.9 from the wildcard. Taking the maximum
	 * for both would tie them and redirect a client that asked for the
	 * opposite.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, float> $ranges Parsed `media/range => quality` pairs.
	 * @param string               $mime   Media type to score, e.g. `text/html`.
	 *
	 * @return float The quality value, 0.0 when nothing covers the type.
	 */
	protected function accept_quality( array $ranges, string $mime ): float {
		$type = strtok( $mime, '/' );

		foreach ( array( $mime, $type . '/*', '*/*' ) as $candidate ) {
			if ( isset( $ranges[ $candidate ] ) ) {
				return $ranges[ $candidate ];
			}
		}

		return 0.0;
	}

	/**
	 * Resolve the canonical calendar feed URL for the current query context.
	 *
	 * Dispatches across singular events, shadow-source posts (venues),
	 * event-bearing taxonomy archives, event post-type archives, and the sitewide feed.
	 *
	 * @since 0.36.0
	 *
	 * @return string|false Calendar feed or download URL, or false if not an event-related request.
	 */
	public function get_calendar_url_for_request() {
		$queried = get_queried_object();

		if ( is_singular() && $queried instanceof WP_Post ) {
			if ( post_type_supports( $queried->post_type, 'gatherpress-event-date' ) ) {
				return ( new Calendar( $queried->ID ) )->get_ical_url();
			}

			if ( $this->is_tax_like_type_for_event_supporting_types( $queried->post_type ) ) {
				return get_post_comments_feed_link( $queried->ID, self::ICAL_SLUG );
			}
		}

		if ( is_tax() && $queried instanceof WP_Term && $this->has_post_type_for_taxonomy( $queried->taxonomy ) ) {
			return get_term_feed_link( $queried->term_id, $queried->taxonomy, self::ICAL_SLUG );
		}

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			if ( is_string( $post_type ) && post_type_supports( $post_type, 'gatherpress-event-date' ) ) {
				return get_post_type_archive_feed_link( $post_type, self::ICAL_SLUG );
			}
		}

		return ( is_front_page() || is_home() ) ? get_feed_link( self::ICAL_SLUG ) : false;
	}

	/**
	 * Check whether the current request is for an event-related view.
	 *
	 * Used to determine if the `Vary: Accept` HTTP header should be attached.
	 *
	 * @since 0.36.0
	 *
	 * @return bool
	 */
	public function is_event_related_request(): bool {
		return false !== $this->get_calendar_url_for_request();
	}

	/**
	 * Handle HTTP Content Negotiation for `Accept: text/calendar` requests.
	 *
	 * If the client requests `text/calendar` on an event-supporting URL,
	 * safely redirect to the corresponding ICS calendar feed/download URL.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function maybe_handle_content_negotiation(): void {
		$calendar_url = $this->get_negotiated_redirect_url();

		if ( false === $calendar_url ) {
			return;
		}

		// `Vary: Accept` is already on this response: filter_wp_headers() adds
		// it for every request that resolves a calendar URL, and that is the
		// same test the redirect target came from. Setting it again here would
		// replace whatever else the header had gathered by then.
		//
		// The decision is covered through get_negotiated_redirect_url(); what
		// remains here is the side effect, which ends in exit() and so cannot be
		// exercised without ending the test run.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation.
		// @codeCoverageIgnoreStart
		wp_safe_redirect( $calendar_url, 302 );
		exit;
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Resolve where an `Accept: text/calendar` request should be redirected.
	 *
	 * Split out from maybe_handle_content_negotiation() so the decision is
	 * testable on its own: that method ends in exit(), which is why the
	 * redirect-loop guard below shipped inverted and unnoticed.
	 *
	 * @since 0.36.0
	 *
	 * @return string|false The calendar URL to redirect to, or false to leave
	 *                      the request alone.
	 */
	public function get_negotiated_redirect_url() {
		if ( ! $this->is_calendar_negotiated() ) {
			return false;
		}

		// The request is already a calendar representation, so there is nothing
		// to negotiate and redirecting would point it at itself. Both endpoint
		// shapes are covered: the feed links carry is_feed(), and the single
		// rewrite endpoint (/event/my-event/ical/) carries the query var.
		//
		// This replaces a substring test between the target and REQUEST_URI.
		// Every target is the requested URL plus a suffix, so that test matched
		// on every request and disabled negotiation entirely.
		if ( is_feed() || '' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return false;
		}

		$calendar_url = $this->get_calendar_url_for_request();

		if ( ! is_string( $calendar_url ) || '' === $calendar_url ) {
			return false;
		}

		return $calendar_url;
	}

	/**
	 * Add `Vary: Accept` header for event-related HTML views so downstream caches
	 * (proxies, CDNs) differentiate responses based on the Accept header.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, string> $headers Associative array of HTTP headers to be sent.
	 *
	 * @return array<string, string> Updated HTTP headers array.
	 */
	public function filter_wp_headers( array $headers ): array {
		if ( $this->is_event_related_request() ) {
			// Field names, not substrings: `Accept-Encoding` is not `Accept`.
			$fields = array_filter( array_map( 'trim', explode( ',', (string) ( $headers['Vary'] ?? '' ) ) ) );

			if ( ! in_array( 'accept', array_map( 'strtolower', $fields ), true ) ) {
				$fields[] = 'Accept';
			}

			$headers['Vary'] = implode( ', ', $fields );
		}

		return $headers;
	}
}
