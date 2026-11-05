<?php
/**
 * Replaces calendar feed placeholder links with feed URLs.
 *
 * @package GatherPress\Core\Calendar\External
 * @since TBD
 */

namespace GatherPress\Core\Calendar\External;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Block;
use WP_HTML_Tag_Processor;
use WP_Post;
use WP_Term;

/**
 * Replaces calendar feed placeholder links with feed URLs.
 *
 * A link in any block whose `href` is one of these placeholders gets the
 * matching feed's URL when the block renders:
 *
 * - `#gatherpress-calendar-feed-url`: the feed for the page being viewed.
 * - `#gatherpress-calendar-feed-site-url`: all events on the site.
 * - `#gatherpress-calendar-feed-archive-url`: the events archive.
 * - `#gatherpress-calendar-feed-venue-url`: the current event's venue, or the venue being viewed.
 * - `#gatherpress-calendar-feed-venue-{slug}-url`: the venue with that slug.
 * - `#gatherpress-calendar-feed-topic-{slug}-url`: the topic with that slug.
 *
 * Each has a `subscribe` form, such as `#gatherpress-calendar-subscribe-site-url`,
 * which gives the same feed as a `webcal://` URL.
 *
 * Inner blocks render first, so the innermost block with the link resolves
 * it. A placeholder with no feed loses its `href`, so it is no longer a link
 * and a block around it, rendered for another post, can't resolve it with
 * that post's venue or archive. It is marked with
 * `data-gatherpress-calendar-unresolved` so a theme can hide it.
 *
 * @since TBD
 */
final class Feed_Placeholder {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Attribute marking a link whose placeholder had no feed.
	 *
	 * @since TBD
	 * @var string
	 */
	const UNRESOLVED_ATTRIBUTE = 'data-gatherpress-calendar-unresolved';

	/**
	 * Start of every calendar feed placeholder.
	 *
	 * @since TBD
	 * @var string
	 */
	const PREFIX = '#gatherpress-calendar-';

	/**
	 * Pattern that splits a placeholder into its format and feed.
	 *
	 * @since TBD
	 * @var string
	 */
	const PATTERN = '/^#gatherpress-calendar-(feed|subscribe)(?:-(site|archive|venue|venue-(.+)|topic-(.+)))?-url$/';

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'render_block', array( $this, 'replace_placeholders' ), 10, 3 );
	}

	/**
	 * Replaces calendar feed placeholder hrefs with feed URLs.
	 *
	 * A placeholder with no feed loses its `href`.
	 *
	 * @since TBD
	 *
	 * @param string               $block_content The block's HTML.
	 * @param array<string, mixed> $block         The parsed block data.
	 * @param WP_Block|null        $instance      The block instance.
	 *
	 * @return string The block's HTML, with placeholders replaced.
	 */
	public function replace_placeholders( string $block_content, array $block, ?WP_Block $instance = null ): string {
		if ( ! str_contains( $block_content, self::PREFIX ) ) {
			return $block_content;
		}

		$tag     = new WP_HTML_Tag_Processor( $block_content );
		$post_id = $this->get_post_id( $block, $instance );

		while ( $tag->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$href = (string) $tag->get_attribute( 'href' );

			if ( ! str_starts_with( $href, self::PREFIX ) ) {
				continue;
			}

			$url = $this->get_url( $href, $post_id );

			if ( '' === $url ) {
				$tag->remove_attribute( 'href' );
				$tag->set_attribute( self::UNRESOLVED_ATTRIBUTE, true );
			} else {
				$tag->set_attribute( 'href', $url );
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Gets the post the block renders for.
	 *
	 * The block's `postId` attribute comes first, then its `postId` context,
	 * which Add to Calendar passes to its dropdown items. Outside the loop on
	 * a listing page, the current post is only the first one listed, so it
	 * is used only in the loop or on a single post.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $block    The parsed block data.
	 * @param WP_Block|null        $instance The block instance.
	 *
	 * @return int The post ID, or 0 when the block renders for no post.
	 */
	private function get_post_id( array $block, ?WP_Block $instance ): int {
		foreach ( array( $block['attrs']['postId'] ?? null, $instance?->context['postId'] ?? null ) as $post_id ) {
			if ( is_numeric( $post_id ) && (int) $post_id > 0 ) {
				return (int) $post_id;
			}
		}

		return in_the_loop() || is_singular() ? max( 0, (int) get_the_ID() ) : 0;
	}

	/**
	 * Gets the URL a placeholder stands for.
	 *
	 * @since TBD
	 *
	 * @param string $placeholder The link's href.
	 * @param int    $post_id     The post the block renders for.
	 *
	 * @return string The feed URL, or an empty string when the href has no feed.
	 */
	private function get_url( string $placeholder, int $post_id ): string {
		if ( ! preg_match( self::PATTERN, $placeholder, $matches ) ) {
			return '';
		}

		$feed = $matches[2] ?? '';
		$url  = match ( true ) {
			'' !== ( $matches[4] ?? '' ) => $this->get_topic_feed_url( $matches[4] ),
			'' !== ( $matches[3] ?? '' ) => $this->get_venue_feed_url( $this->get_venue_by_slug( $matches[3] ) ),
			'site' === $feed             => get_feed_link( Setup::ICAL_SLUG ),
			'archive' === $feed          => $this->get_archive_feed_url( $post_id ),
			'venue' === $feed            => $this->get_venue_feed_url( $this->get_venue_in_context( $post_id ) ),
			default                      => $this->get_page_feed_url(),
		};

		if ( 'subscribe' === $matches[1] ) {
			$url = (string) preg_replace( '#^https?://#', 'webcal://', $url );
		}

		return $url;
	}

	/**
	 * Gets the feed for the page being viewed.
	 *
	 * A single event has a download rather than a feed, so it has none. A
	 * single venue has the same feed as the venue placeholder.
	 *
	 * @since TBD
	 *
	 * @return string The feed URL, or an empty string when the page has no feed.
	 */
	private function get_page_feed_url(): string {
		$queried = get_queried_object();

		if ( is_singular() && $queried instanceof WP_Post ) {
			if ( post_type_supports( $queried->post_type, Event::SUPPORT ) ) {
				return '';
			}

			if ( post_type_supports( $queried->post_type, Venue::SUPPORT ) ) {
				return $this->get_venue_feed_url( $queried );
			}
		}

		return (string) Setup::get_instance()->get_calendar_url_for_request();
	}

	/**
	 * Gets the events archive feed for the current event's post type, or the archive being viewed.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post the block renders for.
	 *
	 * @return string The feed URL, or an empty string when there is no events archive in context.
	 */
	private function get_archive_feed_url( int $post_id ): string {
		// `get_post_type( 0 )` would give the global post's type.
		$post_type = $post_id > 0 ? (string) get_post_type( $post_id ) : '';

		if ( ! post_type_supports( $post_type, Event::SUPPORT ) && is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type = is_array( $post_type ) ? (string) reset( $post_type ) : (string) $post_type;
		}

		if ( ! post_type_supports( $post_type, Event::SUPPORT ) ) {
			return '';
		}

		return (string) get_post_type_archive_feed_link( $post_type, Setup::ICAL_SLUG );
	}

	/**
	 * Gets the venue of the current event, or the venue being viewed.
	 *
	 * An event whose venue is hidden by a password has none.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post the block renders for.
	 *
	 * @return WP_Post|null The venue post, or null when there is none.
	 */
	private function get_venue_in_context( int $post_id ): ?WP_Post {
		// `get_post( 0 )` would give the global post.
		$post = $post_id > 0 ? get_post( $post_id ) : null;

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		if ( post_type_supports( $post->post_type, Venue::SUPPORT ) ) {
			return $post;
		}

		$venue_setup = Venue_Setup::get_instance();

		return post_type_supports( $post->post_type, Event::SUPPORT ) && ! $venue_setup->is_event_venue_hidden( $post )
			? $venue_setup->get_venue_post_from_event_post_id( $post->ID )
			: null;
	}

	/**
	 * Gets a venue's feed.
	 *
	 * Only a published venue without a password has one, and only when its
	 * post type has venue feeds.
	 *
	 * @since TBD
	 *
	 * @param WP_Post|null $venue The venue post.
	 *
	 * @return string The feed URL, or an empty string when the venue has none.
	 */
	private function get_venue_feed_url( ?WP_Post $venue ): string {
		if (
			! $venue instanceof WP_Post ||
			'publish' !== $venue->post_status ||
			'' !== $venue->post_password ||
			! Setup::get_instance()->is_tax_like_type_for_event_supporting_types( $venue->post_type )
		) {
			return '';
		}

		return get_post_comments_feed_link( $venue->ID, Setup::ICAL_SLUG );
	}

	/**
	 * Gets a topic's feed by the topic's slug.
	 *
	 * @since TBD
	 *
	 * @param string $slug The topic's slug.
	 *
	 * @return string The feed URL, or an empty string when no topic has the slug
	 *                or no event post type uses topics.
	 */
	private function get_topic_feed_url( string $slug ): string {
		$term = get_term_by( 'slug', $slug, Topic::TAXONOMY );

		if ( ! $term instanceof WP_Term || ! Setup::get_instance()->has_post_type_for_taxonomy( Topic::TAXONOMY ) ) {
			return '';
		}

		return (string) get_term_feed_link( $term->term_id, Topic::TAXONOMY, Setup::ICAL_SLUG );
	}

	/**
	 * Gets a venue with a feed by its slug.
	 *
	 * Only published venues without a password, of a post type with venue
	 * feeds, are searched, so a draft or protected venue of another post type
	 * with the same slug doesn't hide one that has a feed.
	 *
	 * @since TBD
	 *
	 * @param string $slug The venue's slug.
	 *
	 * @return WP_Post|null The venue post, or null when no venue with a feed has the slug.
	 */
	private function get_venue_by_slug( string $slug ): ?WP_Post {
		$post_types = array_values(
			array_filter(
				get_post_types_by_support( Venue::SUPPORT ),
				array( Setup::get_instance(), 'is_tax_like_type_for_event_supporting_types' )
			)
		);

		if ( array() === $post_types ) {
			return null;
		}

		$venues = get_posts(
			array(
				'name'          => $slug,
				'post_type'     => $post_types,
				'post_status'   => 'publish',
				'has_password'  => false,
				'numberposts'   => 1,
				'no_found_rows' => true,
			)
		);

		return $venues[0] ?? null;
	}
}
