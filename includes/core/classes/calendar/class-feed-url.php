<?php
/**
 * Calendar feed URL resolver.
 *
 * The calendar subsystem serves subscribable iCalendar feeds at a fixed set of
 * URL shapes: `/feed/ical`, `/event/feed/ical`, `/venue/<slug>/feed/ical`, and
 * `/topic/<slug>/feed/ical`. Those URLs are built by WordPress core's feed-link
 * functions, which each take a different object — a post type, a post ID, or a
 * term — so resolving one from a scope plus an identifier needs the dispatch
 * this file provides.
 *
 * The block that renders subscribe links and the `<link rel="alternate">` tags
 * in `<head>` both need that dispatch, which is why it lives here rather than
 * in either caller.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.36.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Shadow_Source;
use WP_Post;
use WP_Term;

/**
 * Resolves a calendar feed URL from a scope and its identifiers.
 *
 * Every method is static: this is a pure mapping from (scope, identifier) to
 * URL, with no state to hold and nothing to wire up.
 *
 * @since 0.36.0
 */
final class Feed_Url {

	/**
	 * Scopes this resolver understands.
	 *
	 * @since 0.36.0
	 *
	 * @var string[]
	 */
	const SCOPES = array(
		'sitewide',
		'archive',
		'venue',
		'topic',
	);

	/**
	 * Resolve the feed URL for a scope.
	 *
	 * Unknown scopes resolve to false, as does a scope whose identifiers do not
	 * point at something the feed can be built from — a venue that is not a
	 * shadow source, a term whose taxonomy carries no events, and so on.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, mixed> $args {
	 *     Scope args.
	 *
	 *     @type string $scope     One of self::SCOPES. Default 'sitewide'.
	 *     @type string $post_type Post type for the 'archive' scope. Empty
	 *                             falls back to the primary event post type.
	 *     @type int    $venue_id  Venue post ID for the 'venue' scope.
	 *     @type int    $topic_id  Term ID for the 'topic' scope.
	 * }
	 *
	 * @return string|false Feed URL, or false when the scope cannot be resolved.
	 */
	public static function get( array $args ): string|false {
		$args = wp_parse_args(
			$args,
			array(
				'scope'     => 'sitewide',
				'post_type' => '',
				'venue_id'  => 0,
				'topic_id'  => 0,
			)
		);

		$scope = is_string( $args['scope'] ) ? $args['scope'] : '';

		// An unlisted scope resolves to nothing, but the filter below still runs
		// so a companion plugin can supply a feed shape core does not know about.
		$url = in_array( $scope, self::SCOPES, true ) ? self::resolve( $scope, $args ) : false;

		/**
		 * Filters the feed URL resolved for a scope.
		 *
		 * Returning false hides the feed for that scope; returning a URL
		 * replaces it, which is how a companion plugin can surface a feed
		 * shape the core resolver does not know about.
		 *
		 * @since 0.36.0
		 *
		 * @param string|false         $url  Resolved feed URL, or false when unresolved.
		 * @param array<string, mixed> $args Scope args the URL was resolved from.
		 *
		 * @return string|false Feed URL, or false to render nothing.
		 */
		$url = apply_filters( 'gatherpress_calendar_feed_url', $url, $args );

		return is_string( $url ) ? $url : false;
	}

	/**
	 * Rewrite an HTTP feed URL to its `webcal://` equivalent.
	 *
	 * Calendar apps treat the `webcal` scheme as "subscribe to this", which is
	 * what makes a one-click subscription possible. Anything that is not an
	 * HTTP URL — an already-webcal URL, a relative path, a false — is returned
	 * unchanged so callers can map over mixed values safely.
	 *
	 * @since 0.36.0
	 *
	 * @param string $url Feed URL to rewrite.
	 *
	 * @return string The webcal URL, or the input when it is not an HTTP URL.
	 */
	public static function to_webcal( string $url ): string {
		if ( ! str_starts_with( $url, 'https://' ) && ! str_starts_with( $url, 'http://' ) ) {
			return $url;
		}

		return (string) preg_replace( '/^https?:/', 'webcal:', $url );
	}

	/**
	 * Check whether any event-bearing post type is registered with a taxonomy.
	 *
	 * @since 0.36.0
	 *
	 * @param string $taxonomy Taxonomy slug to look up.
	 *
	 * @return bool True when the taxonomy is attached to an event-supporting post type.
	 */
	public static function has_post_type_for_taxonomy( string $taxonomy ): bool {
		foreach ( get_post_types_by_support( Event::SUPPORT ) as $post_type ) {
			if ( is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a post type could be related to an event-supporting post type.
	 *
	 * Venue-shaped post types declare `gatherpress-shadow-source` and are
	 * tagged onto events through the shadow taxonomy their slug derives. A
	 * post type only qualifies when both halves are true.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type Post type to check.
	 *
	 * @return bool True when the post type is a tax-like shadow source for events.
	 */
	public static function is_tax_like_type_for_event_supporting_types( string $post_type ): bool {
		return post_type_supports( $post_type, Shadow_Source::SUPPORT ) &&
			self::has_post_type_for_taxonomy( Shadow_Source::get_instance()->get_taxonomy( $post_type ) );
	}

	/**
	 * Dispatch a validated scope to its URL builder.
	 *
	 * @since 0.36.0
	 *
	 * @param string               $scope One of self::SCOPES.
	 * @param array<string, mixed> $args  Scope args.
	 *
	 * @return string|false Feed URL, or false when the scope cannot be resolved.
	 */
	protected static function resolve( string $scope, array $args ): string|false {
		return match ( $scope ) {
			'archive' => self::archive_url( (string) $args['post_type'] ),
			'venue'   => self::venue_url( (int) $args['venue_id'] ),
			'topic'   => self::topic_url( (int) $args['topic_id'] ),
			// 'sitewide' is the only scope left after validation above.
			default   => self::sitewide_url(),
		};
	}

	/**
	 * Build the sitewide events feed URL.
	 *
	 * @since 0.36.0
	 *
	 * @return string|false Feed URL, or false when the feed link cannot be built.
	 */
	protected static function sitewide_url(): string|false {
		$url = get_feed_link( Setup::ICAL_SLUG );

		return '' === $url ? false : $url;
	}

	/**
	 * Build the events-archive feed URL for a post type.
	 *
	 * An empty post type means the editor did not pin one, so the primary event
	 * post type is used and the block works with no configuration.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type Event post type slug, or empty for the default.
	 *
	 * @return string|false Feed URL, or false when the post type carries no event dates.
	 */
	protected static function archive_url( string $post_type ): string|false {
		$post_type = '' === $post_type ? self::primary_event_post_type() : $post_type;

		if ( '' === $post_type || ! post_type_supports( $post_type, Event::SUPPORT ) ) {
			return false;
		}

		$url = get_post_type_archive_feed_link( $post_type, Setup::ICAL_SLUG );

		return is_string( $url ) && '' !== $url ? $url : false;
	}

	/**
	 * Build the feed URL for events at one venue.
	 *
	 * Venue feeds are served off the venue post's comments feed, so the ID has
	 * to resolve to a published tax-like shadow source.
	 *
	 * @since 0.36.0
	 *
	 * @param int $venue_id Venue post ID.
	 *
	 * @return string|false Feed URL, or false when the ID is not a venue.
	 */
	protected static function venue_url( int $venue_id ): string|false {
		$venue = get_post( $venue_id );

		if (
			! $venue instanceof WP_Post ||
			! is_post_publicly_viewable( $venue ) ||
			! self::is_tax_like_type_for_event_supporting_types( $venue->post_type )
		) {
			return false;
		}

		$url = get_post_comments_feed_link( $venue->ID, Setup::ICAL_SLUG );

		return '' === $url ? false : $url;
	}

	/**
	 * Build the feed URL for events in one term.
	 *
	 * The taxonomy has to be attached to an event-bearing post type, otherwise
	 * the term feed would carry no events.
	 *
	 * @since 0.36.0
	 *
	 * @param int $topic_id Term ID.
	 *
	 * @return string|false Feed URL, or false when the ID is not an event term.
	 */
	protected static function topic_url( int $topic_id ): string|false {
		$term = get_term( $topic_id );

		if ( ! $term instanceof WP_Term || ! self::has_post_type_for_taxonomy( $term->taxonomy ) ) {
			return false;
		}

		$url = get_term_feed_link( $term->term_id, $term->taxonomy, Setup::ICAL_SLUG );

		return is_string( $url ) && '' !== $url ? $url : false;
	}

	/**
	 * Return the first post type registered with event dates.
	 *
	 * @since 0.36.0
	 *
	 * @return string Post type slug, or an empty string when none is registered.
	 */
	protected static function primary_event_post_type(): string {
		$post_types = get_post_types_by_support( Event::SUPPORT );

		return (string) reset( $post_types );
	}
}
