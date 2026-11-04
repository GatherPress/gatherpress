<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\External\Feed_Placeholder.
 *
 * @package GatherPress\Core\Calendar\External
 * @since TBD
 */

namespace GatherPress\Tests\Core\Calendar\External;

use GatherPress\Core\Calendar\External\Feed_Placeholder;
use GatherPress\Core\Calendar\External\Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use ReflectionClass;
use WP_HTML_Tag_Processor;

/**
 * Class Test_Feed_Placeholder.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\External\Feed_Placeholder
 * @group              endpoints
 */
class Test_Feed_Placeholder extends Base {

	/**
	 * Coverage for __construct.
	 *
	 * The instance is built during plugin bootstrap, so the constructor only
	 * runs inside a test once the stored instance is cleared.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_builds_the_instance(): void {
		$reflection = new ReflectionClass( Feed_Placeholder::class );
		$property   = $reflection->getProperty( 'instance' );

		$property->setAccessible( true );
		$property->setValue( null, null );

		$this->assertInstanceOf(
			Feed_Placeholder::class,
			Feed_Placeholder::get_instance(),
			'Failed to assert that the constructor returns a Feed_Placeholder instance.'
		);
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Feed_Placeholder::get_instance();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'render_block',
				'priority' => 10,
				'callback' => array( $instance, 'replace_placeholders' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Renders a link with the given href through the placeholder filter.
	 *
	 * @since TBD
	 *
	 * @param string $href    The link's href.
	 * @param int    $post_id Optional. The post the block renders for.
	 *
	 * @return string The rendered href.
	 */
	private function render_href( string $href, int $post_id = 0 ): string {
		$block = array(
			'blockName' => 'core/paragraph',
			'attrs'     => $post_id ? array( 'postId' => $post_id ) : array(),
		);
		$html  = Feed_Placeholder::get_instance()->replace_placeholders(
			sprintf( '<p><a href="%s">Subscribe</a></p>', $href ),
			$block
		);
		$tag   = new WP_HTML_Tag_Processor( $html );

		$tag->next_tag( array( 'tag_name' => 'a' ) );

		return (string) $tag->get_attribute( 'href' );
	}

	/**
	 * Converts a feed URL to the `webcal://` form.
	 *
	 * @since TBD
	 *
	 * @param string $url The feed URL.
	 *
	 * @return string The URL with the `webcal://` scheme.
	 */
	private function webcal( string $url ): string {
		return (string) preg_replace( '#^https?://#', 'webcal://', $url );
	}

	/**
	 * Content without a placeholder comes back unchanged.
	 *
	 * @covers ::replace_placeholders
	 *
	 * @return void
	 */
	public function test_replace_placeholders_leaves_other_content(): void {
		$html = '<p><a href="#gatherpress-ical-calendar">iCal</a></p>';

		$this->assertSame(
			$html,
			Feed_Placeholder::get_instance()->replace_placeholders( $html, array() ),
			'Content without a calendar feed placeholder should not change.'
		);
	}

	/**
	 * An href that only looks like a placeholder stays as it is.
	 *
	 * @covers ::replace_placeholders
	 * @covers ::get_url
	 *
	 * @return void
	 */
	public function test_unknown_placeholder_stays(): void {
		$this->assertSame(
			'#gatherpress-calendar-feed-nope-url',
			$this->render_href( '#gatherpress-calendar-feed-nope-url' ),
			'An unknown placeholder should stay as it is.'
		);
	}

	/**
	 * The site placeholders give the site-wide feed, as https and webcal.
	 *
	 * @covers ::replace_placeholders
	 * @covers ::get_url
	 *
	 * @return void
	 */
	public function test_site_feed(): void {
		$url = get_feed_link( Setup::ICAL_SLUG );

		$this->assertSame( $url, $this->render_href( '#gatherpress-calendar-feed-site-url' ) );
		$this->assertSame(
			$this->webcal( $url ),
			$this->render_href( '#gatherpress-calendar-subscribe-site-url' ),
			'The subscribe placeholder should give the feed as webcal.'
		);
		$this->assertStringStartsWith( 'webcal://', $this->render_href( '#gatherpress-calendar-subscribe-site-url' ) );
	}

	/**
	 * The page placeholder gives the front page's site-wide feed.
	 *
	 * @covers ::get_page_feed_url
	 *
	 * @return void
	 */
	public function test_page_feed_on_front_page(): void {
		$this->go_to( home_url( '/' ) );

		$this->assertSame(
			get_feed_link( Setup::ICAL_SLUG ),
			$this->render_href( '#gatherpress-calendar-feed-url' )
		);
	}

	/**
	 * The page placeholder gives a venue page's feed.
	 *
	 * @covers ::get_page_feed_url
	 *
	 * @return void
	 */
	public function test_page_feed_on_venue_page(): void {
		$venue_id = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'page-venue',
			)
		)->get()->ID;

		$this->go_to( get_permalink( $venue_id ) );

		$this->assertSame(
			get_post_comments_feed_link( $venue_id, Setup::ICAL_SLUG ),
			$this->render_href( '#gatherpress-calendar-feed-url' )
		);
	}

	/**
	 * The page placeholder stays on a single event, which has no feed of its own.
	 *
	 * @covers ::get_page_feed_url
	 *
	 * @return void
	 */
	public function test_page_feed_stays_on_single_event(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		$this->go_to( get_permalink( $event_id ) );

		$this->assertSame( '#gatherpress-calendar-feed-url', $this->render_href( '#gatherpress-calendar-feed-url' ) );
	}

	/**
	 * The archive placeholder gives the archive feed of the event's post type.
	 *
	 * @covers ::get_archive_feed_url
	 *
	 * @return void
	 */
	public function test_archive_feed_for_event(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		$this->assertSame(
			get_post_type_archive_feed_link( Event::POST_TYPE, Setup::ICAL_SLUG ),
			$this->render_href( '#gatherpress-calendar-feed-archive-url', $event_id )
		);
	}

	/**
	 * The archive placeholder gives the feed of the events archive being viewed.
	 *
	 * @covers ::get_archive_feed_url
	 *
	 * @return void
	 */
	public function test_archive_feed_on_archive_page(): void {
		// An empty archive, so no event in the loop gives the post type instead.
		$this->go_to( get_post_type_archive_link( Event::POST_TYPE ) );

		$this->assertSame(
			get_post_type_archive_feed_link( Event::POST_TYPE, Setup::ICAL_SLUG ),
			$this->render_href( '#gatherpress-calendar-feed-archive-url' )
		);
	}

	/**
	 * The archive placeholder reads an array post type query var.
	 *
	 * @covers ::get_archive_feed_url
	 *
	 * @return void
	 */
	public function test_archive_feed_with_array_post_type_query_var(): void {
		$this->go_to( get_post_type_archive_link( Event::POST_TYPE ) );
		set_query_var( 'post_type', array( Event::POST_TYPE ) );

		$this->assertSame(
			get_post_type_archive_feed_link( Event::POST_TYPE, Setup::ICAL_SLUG ),
			$this->render_href( '#gatherpress-calendar-feed-archive-url' )
		);
	}

	/**
	 * The archive placeholder stays outside an event or events archive.
	 *
	 * @covers ::get_archive_feed_url
	 *
	 * @return void
	 */
	public function test_archive_feed_stays_without_context(): void {
		$page_id = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;

		$this->assertSame(
			'#gatherpress-calendar-feed-archive-url',
			$this->render_href( '#gatherpress-calendar-feed-archive-url', $page_id )
		);
	}

	/**
	 * The venue placeholder gives the event's venue feed.
	 *
	 * @covers ::get_venue_in_context
	 * @covers ::get_venue_feed_url
	 *
	 * @return void
	 */
	public function test_venue_feed_for_event(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$venue_id = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'event-hall',
			)
		)->get()->ID;

		wp_set_post_terms( $event_id, '_event-hall', Venue::TAXONOMY );

		$this->assertSame(
			$this->webcal( get_post_comments_feed_link( $venue_id, Setup::ICAL_SLUG ) ),
			$this->render_href( '#gatherpress-calendar-subscribe-venue-url', $event_id )
		);
	}

	/**
	 * The venue placeholder gives the feed of the venue being rendered.
	 *
	 * @covers ::get_venue_in_context
	 *
	 * @return void
	 */
	public function test_venue_feed_for_venue(): void {
		$venue_id = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get()->ID;

		$this->assertSame(
			get_post_comments_feed_link( $venue_id, Setup::ICAL_SLUG ),
			$this->render_href( '#gatherpress-calendar-feed-venue-url', $venue_id )
		);
	}

	/**
	 * The venue placeholder stays when a password hides the event's venue.
	 *
	 * @covers ::get_venue_in_context
	 *
	 * @return void
	 */
	public function test_venue_feed_stays_for_protected_event(): void {
		$event_id = $this->mock->post(
			array(
				'post_type'     => Event::POST_TYPE,
				'post_password' => 'secret',
			)
		)->get()->ID;

		$this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'secret-hall',
			)
		);
		wp_set_post_terms( $event_id, '_secret-hall', Venue::TAXONOMY );
		wp_set_current_user( 0 );

		$this->assertSame(
			'#gatherpress-calendar-feed-venue-url',
			$this->render_href( '#gatherpress-calendar-feed-venue-url', $event_id )
		);
	}

	/**
	 * The venue placeholder stays without an event or venue in context.
	 *
	 * @covers ::get_venue_in_context
	 *
	 * @return void
	 */
	public function test_venue_feed_stays_without_context(): void {
		$page_id = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;

		$this->assertSame(
			'#gatherpress-calendar-feed-venue-url',
			$this->render_href( '#gatherpress-calendar-feed-venue-url', $page_id ),
			'A page that is neither an event nor a venue has no venue.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method(
				Feed_Placeholder::get_instance(),
				'get_venue_in_context',
				array( PHP_INT_MAX )
			),
			'No post means no venue.'
		);
	}

	/**
	 * The venue slug placeholder gives that venue's feed.
	 *
	 * @covers ::get_venue_by_slug
	 *
	 * @return void
	 */
	public function test_venue_slug_feed(): void {
		$venue_id = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'city-library',
			)
		)->get()->ID;

		$this->assertSame(
			get_post_comments_feed_link( $venue_id, Setup::ICAL_SLUG ),
			$this->render_href( '#gatherpress-calendar-feed-venue-city-library-url' )
		);
	}

	/**
	 * The venue slug placeholder stays for a missing, draft or protected venue.
	 *
	 * @covers ::get_venue_by_slug
	 * @covers ::get_venue_feed_url
	 *
	 * @return void
	 */
	public function test_venue_slug_feed_stays_without_a_public_venue(): void {
		$this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_name'   => 'draft-hall',
				'post_status' => 'draft',
			)
		);
		$this->mock->post(
			array(
				'post_type'     => Venue::POST_TYPE,
				'post_name'     => 'locked-hall',
				'post_password' => 'secret',
			)
		);

		foreach ( array( 'missing-hall', 'draft-hall', 'locked-hall' ) as $slug ) {
			$href = sprintf( '#gatherpress-calendar-feed-venue-%s-url', $slug );

			$this->assertSame(
				$href,
				$this->render_href( $href ),
				sprintf( 'The %s placeholder should stay.', $slug )
			);
		}
	}

	/**
	 * Without a venue post type, no slug finds a venue.
	 *
	 * @covers ::get_venue_by_slug
	 *
	 * @return void
	 */
	public function test_venue_by_slug_without_venue_post_types(): void {
		remove_post_type_support( Venue::POST_TYPE, Venue::SUPPORT );

		$venue = Utility::invoke_hidden_method( Feed_Placeholder::get_instance(), 'get_venue_by_slug', array( 'any' ) );

		add_post_type_support( Venue::POST_TYPE, Venue::SUPPORT );

		$this->assertNull( $venue );
	}

	/**
	 * The topic slug placeholder gives that topic's feed, and stays for a missing topic.
	 *
	 * @covers ::get_topic_feed_url
	 *
	 * @return void
	 */
	public function test_topic_slug_feed(): void {
		$term_id = $this->factory->term->create(
			array(
				'taxonomy' => Topic::TAXONOMY,
				'slug'     => 'workshops',
			)
		);

		// The tag processor reads the href back decoded, so `&amp;` comes back as `&`.
		$this->assertSame(
			html_entity_decode( (string) get_term_feed_link( $term_id, Topic::TAXONOMY, Setup::ICAL_SLUG ) ),
			$this->render_href( '#gatherpress-calendar-feed-topic-workshops-url' )
		);
		$this->assertSame(
			'#gatherpress-calendar-feed-topic-missing-url',
			$this->render_href( '#gatherpress-calendar-feed-topic-missing-url' ),
			'A missing topic should leave the placeholder as it is.'
		);
	}
}
