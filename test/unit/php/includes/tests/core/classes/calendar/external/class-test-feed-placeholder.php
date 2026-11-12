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
 * @since TBD
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
	 * @since TBD
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
	 * @since TBD
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
	 * @return string The rendered href, or an empty string when the link has none.
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
	 * @since TBD
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
	 * An href that only looks like a placeholder loses its href.
	 *
	 * @since TBD
	 *
	 * @covers ::replace_placeholders
	 * @covers ::get_url
	 *
	 * @return void
	 */
	public function test_unknown_placeholder_loses_its_href(): void {
		$this->assertSame(
			'',
			$this->render_href( '#gatherpress-calendar-feed-nope-url' ),
			'An unknown placeholder should lose its href.'
		);
	}

	/**
	 * The site placeholders give the site-wide feed, as https and webcal.
	 *
	 * @since TBD
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
	 * @since TBD
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
	 * @since TBD
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
	 * The page placeholder has no feed on a single event, which has no feed of its own.
	 *
	 * @since TBD
	 *
	 * @covers ::get_page_feed_url
	 *
	 * @return void
	 */
	public function test_page_feed_stays_on_single_event(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		$this->go_to( get_permalink( $event_id ) );

		$this->assertSame( '', $this->render_href( '#gatherpress-calendar-feed-url' ) );
	}

	/**
	 * The archive placeholder gives the archive feed of the event's post type.
	 *
	 * @since TBD
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
	 * @since TBD
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
	 * @since TBD
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
	 * The archive placeholder has no feed outside an event or events archive.
	 *
	 * @since TBD
	 *
	 * @covers ::get_archive_feed_url
	 *
	 * @return void
	 */
	public function test_archive_feed_stays_without_context(): void {
		$page_id = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;

		$this->assertSame( '', $this->render_href( '#gatherpress-calendar-feed-archive-url', $page_id ) );
	}

	/**
	 * The venue placeholder gives the event's venue feed.
	 *
	 * @since TBD
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
	 * @since TBD
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
	 * The venue placeholder has no feed when a password hides the event's venue.
	 *
	 * @since TBD
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

		$this->assertSame( '', $this->render_href( '#gatherpress-calendar-feed-venue-url', $event_id ) );
	}

	/**
	 * The venue placeholder has no feed without an event or venue in context.
	 *
	 * @since TBD
	 *
	 * @covers ::get_venue_in_context
	 *
	 * @return void
	 */
	public function test_venue_feed_stays_without_context(): void {
		$page_id = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;

		$this->assertSame(
			'',
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
	 * @since TBD
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
	 * The venue slug placeholder has no feed for a missing, draft or protected venue.
	 *
	 * @since TBD
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
				'',
				$this->render_href( $href ),
				sprintf( 'The %s placeholder should lose its href.', $slug )
			);
		}
	}

	/**
	 * Without a venue post type, no slug finds a venue.
	 *
	 * @since TBD
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
	 * The topic slug placeholder gives that topic's feed, and none for a missing topic.
	 *
	 * @since TBD
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
			'',
			$this->render_href( '#gatherpress-calendar-feed-topic-missing-url' ),
			'A missing topic should remove the href.'
		);
	}

	/**
	 * Every placeholder in one block is replaced.
	 *
	 * @since TBD
	 *
	 * @covers ::replace_placeholders
	 *
	 * @return void
	 */
	public function test_replaces_every_placeholder_in_one_block(): void {
		$url   = get_feed_link( Setup::ICAL_SLUG );
		$html  = '<p><a href="https://example.org/">Other</a> '
			. '<a href="#gatherpress-calendar-feed-site-url">Feed</a> '
			. '<a href="#gatherpress-calendar-subscribe-site-url">Subscribe</a></p>';
		$html  = Feed_Placeholder::get_instance()->replace_placeholders(
			$html,
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
			)
		);
		$tag   = new WP_HTML_Tag_Processor( $html );
		$hrefs = array();

		while ( $tag->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$hrefs[] = (string) $tag->get_attribute( 'href' );
		}

		$this->assertSame( array( 'https://example.org/', $url, $this->webcal( $url ) ), $hrefs );
	}

	/**
	 * A block around an inner block's unresolved placeholder leaves it alone,
	 * and still resolves its own.
	 *
	 * @since TBD
	 *
	 * @covers ::replace_placeholders
	 *
	 * @return void
	 */
	public function test_ancestor_leaves_inner_unresolved_placeholder(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$page_id  = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;
		$instance = Feed_Placeholder::get_instance();

		// The inner block renders for a page, which has no events archive.
		$child = $instance->replace_placeholders(
			'<p><a href="#gatherpress-calendar-feed-archive-url">Child</a></p>',
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array( 'postId' => $page_id ),
			)
		);
		$html  = $instance->replace_placeholders(
			'<div class="wp-block-group"><a href="#gatherpress-calendar-feed-archive-url">Own</a>' . $child . '</div>',
			array(
				'blockName' => 'core/group',
				'attrs'     => array( 'postId' => $event_id ),
			)
		);
		$tag   = new WP_HTML_Tag_Processor( $html );
		$hrefs = array();

		while ( $tag->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$hrefs[] = (string) $tag->get_attribute( 'href' );
		}

		$this->assertSame(
			array(
				get_post_type_archive_feed_link( Event::POST_TYPE, Setup::ICAL_SLUG ),
				'',
			),
			$hrefs,
			'The block around resolves its own link and leaves the inner block\'s unresolved one.'
		);
		$this->assertStringContainsString( Feed_Placeholder::UNRESOLVED_ATTRIBUTE, $html );
	}

	/**
	 * A block that renders its link from an attribute resolves the placeholder.
	 *
	 * @since TBD
	 *
	 * @covers ::replace_placeholders
	 *
	 * @return void
	 */
	public function test_resolves_placeholders_from_block_attributes(): void {
		$html = Feed_Placeholder::get_instance()->replace_placeholders(
			'<li><a href="#gatherpress-calendar-feed-site-url">Feed</a></li>',
			array(
				'blockName' => 'core/navigation-link',
				'attrs'     => array( 'url' => '#gatherpress-calendar-feed-site-url' ),
			)
		);

		$this->assertStringContainsString( esc_url( get_feed_link( Setup::ICAL_SLUG ) ), $html );
	}

	/**
	 * A venue of a post type without venue feeds has no feed.
	 *
	 * @since TBD
	 *
	 * @covers ::get_venue_by_slug
	 * @covers ::get_venue_feed_url
	 *
	 * @return void
	 */
	public function test_venue_feed_stays_for_post_type_without_feeds(): void {
		register_post_type(
			'gatherpress_qa_hall',
			array(
				'public'   => true,
				'supports' => array( 'title', Venue::SUPPORT ),
			)
		);

		$hall = $this->mock->post(
			array(
				'post_type' => 'gatherpress_qa_hall',
				'post_name' => 'orphan-hall',
			)
		)->get();
		$href = $this->render_href( '#gatherpress-calendar-feed-venue-orphan-hall-url' );
		$url  = Utility::invoke_hidden_method( Feed_Placeholder::get_instance(), 'get_venue_feed_url', array( $hall ) );

		unregister_post_type( 'gatherpress_qa_hall' );

		$this->assertSame( '', $href );
		$this->assertSame( '', $url );
	}

	/**
	 * A protected venue of one post type doesn't hide a public one of another with the same slug.
	 *
	 * @since TBD
	 *
	 * @covers ::get_venue_by_slug
	 *
	 * @return void
	 */
	public function test_venue_slug_feed_skips_a_protected_venue_with_the_same_slug(): void {
		register_post_type(
			'gatherpress_qa_hall',
			array(
				'public'   => true,
				'supports' => array( 'title', Venue::SUPPORT ),
			)
		);
		register_taxonomy( '_gatherpress_qa_hall', Event::POST_TYPE );

		$this->mock->post(
			array(
				'post_type'     => 'gatherpress_qa_hall',
				'post_name'     => 'shared-hall',
				'post_password' => 'secret',
			)
		);

		$venue_id = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'shared-hall',
			)
		)->get()->ID;
		$href     = $this->render_href( '#gatherpress-calendar-feed-venue-shared-hall-url' );

		unregister_taxonomy( '_gatherpress_qa_hall' );
		unregister_post_type( 'gatherpress_qa_hall' );

		$this->assertSame( get_post_comments_feed_link( $venue_id, Setup::ICAL_SLUG ), $href );
	}

	/**
	 * The topic slug placeholder has no feed when no event post type uses topics.
	 *
	 * @since TBD
	 *
	 * @covers ::get_topic_feed_url
	 *
	 * @return void
	 */
	public function test_topic_slug_feed_stays_without_event_topics(): void {
		$this->factory->term->create(
			array(
				'taxonomy' => Topic::TAXONOMY,
				'slug'     => 'orphan-topic',
			)
		);
		unregister_taxonomy_for_object_type( Topic::TAXONOMY, Event::POST_TYPE );

		$href = $this->render_href( '#gatherpress-calendar-feed-topic-orphan-topic-url' );

		register_taxonomy_for_object_type( Topic::TAXONOMY, Event::POST_TYPE );

		$this->assertSame( '', $href );
	}

	/**
	 * An unresolved link loses its href and is marked, so it is no longer a link.
	 *
	 * @since TBD
	 *
	 * @covers ::replace_placeholders
	 *
	 * @return void
	 */
	public function test_unresolved_placeholder_is_no_longer_a_link(): void {
		$html = Feed_Placeholder::get_instance()->replace_placeholders(
			'<p><a href="#gatherpress-calendar-feed-topic-missing-url">Subscribe</a></p>',
			array(
				'blockName' => 'core/paragraph',
				'attrs'     => array(),
			)
		);
		$tag  = new WP_HTML_Tag_Processor( $html );

		$tag->next_tag( array( 'tag_name' => 'a' ) );

		$this->assertNull( $tag->get_attribute( 'href' ) );
		$this->assertTrue( $tag->get_attribute( Feed_Placeholder::UNRESOLVED_ATTRIBUTE ) );
	}

	/**
	 * A dropdown item in Add to Calendar uses the event Add to Calendar is set to.
	 *
	 * @since TBD
	 *
	 * @covers ::replace_placeholders
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_dropdown_item_uses_the_add_to_calendar_event(): void {
		$venue_a  = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'hall-a',
			)
		)->get()->ID;
		$event_a  = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$event_b  = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$page_id  = $this->mock->post( array( 'post_type' => 'page' ) )->get()->ID;
		$expected = $this->webcal( get_post_comments_feed_link( $venue_a, Setup::ICAL_SLUG ) );

		$this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'hall-b',
			)
		);
		wp_set_post_terms( $event_a, '_hall-a', Venue::TAXONOMY );
		wp_set_post_terms( $event_b, '_hall-b', Venue::TAXONOMY );

		$markup = sprintf(
			'<!-- wp:gatherpress/add-to-calendar {"postId":%d} -->'
			. '<div class="wp-block-gatherpress-add-to-calendar">'
			. '<!-- wp:gatherpress/dropdown --><div class="wp-block-gatherpress-dropdown">'
			. '<!-- wp:gatherpress/dropdown-item --><div class="wp-block-gatherpress-dropdown-item">'
			. '<a href="#gatherpress-calendar-subscribe-venue-url">Subscribe</a>'
			. '</div><!-- /wp:gatherpress/dropdown-item -->'
			. '</div><!-- /wp:gatherpress/dropdown -->'
			. '</div><!-- /wp:gatherpress/add-to-calendar -->',
			$event_a
		);

		foreach ( array( $page_id, $event_b ) as $host_id ) {
			$this->go_to( get_permalink( $host_id ) );
			$GLOBALS['post'] = get_post( $host_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			$tag = new WP_HTML_Tag_Processor( do_blocks( $markup ) );

			$tag->next_tag( array( 'tag_name' => 'a' ) );

			$this->assertSame(
				$expected,
				$tag->get_attribute( 'href' ),
				sprintf( 'Failed to assert the venue of the Add to Calendar event on a %s.', get_post_type( $host_id ) )
			);
		}

		unset( $GLOBALS['post'] );
	}

	/**
	 * An unresolved placeholder in a dropdown item is not a button.
	 *
	 * The dropdown item turns a link with no `href` into a button. A placeholder
	 * with no feed loses its `href`, and must stay plain text.
	 *
	 * @since TBD
	 *
	 * @covers ::replace_placeholders
	 *
	 * @return void
	 */
	public function test_unresolved_dropdown_item_is_not_a_button(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$markup   = sprintf(
			'<!-- wp:gatherpress/add-to-calendar {"postId":%d} -->'
			. '<div class="wp-block-gatherpress-add-to-calendar">'
			. '<!-- wp:gatherpress/dropdown {"label":"Add to calendar"} --><div class="wp-block-gatherpress-dropdown">'
			. '<a href="#" class="wp-block-gatherpress-dropdown__trigger">Add to calendar</a>'
			. '<div class="wp-block-gatherpress-dropdown__menu">'
			. '<!-- wp:gatherpress/dropdown-item --><div class="wp-block-gatherpress-dropdown-item">'
			. '<a href="#gatherpress-calendar-subscribe-venue-url">Subscribe</a>'
			. '</div><!-- /wp:gatherpress/dropdown-item -->'
			. '</div></div><!-- /wp:gatherpress/dropdown -->'
			. '</div><!-- /wp:gatherpress/add-to-calendar -->',
			$event_id
		);

		$this->go_to( get_permalink( $event_id ) );

		$tag   = new WP_HTML_Tag_Processor( do_blocks( $markup ) );
		$found = false;

		// The first link is the dropdown trigger, so find the item's link by its marker.
		while ( ! $found && $tag->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$found = null !== $tag->get_attribute( Feed_Placeholder::UNRESOLVED_ATTRIBUTE );
		}

		$this->assertTrue( $found, 'Failed to assert the unresolved dropdown item link is rendered.' );
		$this->assertNull( $tag->get_attribute( 'href' ), 'Failed to assert the unresolved link has no href.' );
		$this->assertNull( $tag->get_attribute( 'role' ), 'Failed to assert the unresolved link has no role.' );
		$this->assertNull( $tag->get_attribute( 'tabindex' ), 'Failed to assert the unresolved link takes no focus.' );
	}

	/**
	 * Outside the loop on a listing page, the first listed post is not used.
	 *
	 * @since TBD
	 *
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_listing_page_outside_the_loop_has_no_post(): void {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$venue_id = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'listed-hall',
			)
		)->get()->ID;
		$term_id  = $this->factory->term->create(
			array(
				'taxonomy' => Topic::TAXONOMY,
				'slug'     => 'listed-topic',
			)
		);

		wp_set_post_terms( $event_id, '_listed-hall', Venue::TAXONOMY );
		wp_set_post_terms( $event_id, array( $term_id ), Topic::TAXONOMY );
		$this->go_to( get_term_link( $term_id, Topic::TAXONOMY ) );
		$GLOBALS['post'] = get_post( $event_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( '', $this->render_href( '#gatherpress-calendar-feed-venue-url' ) );
		$this->assertSame( '', $this->render_href( '#gatherpress-calendar-feed-archive-url' ) );

		$GLOBALS['wp_query']->in_the_loop = true;

		$this->assertSame(
			get_post_comments_feed_link( $venue_id, Setup::ICAL_SLUG ),
			$this->render_href( '#gatherpress-calendar-feed-venue-url' ),
			'In the loop, the current post is used.'
		);

		$GLOBALS['wp_query']->in_the_loop = false;
		unset( $GLOBALS['post'] );
	}

	/**
	 * The page placeholder has no feed on a password-protected venue page, as the venue placeholder.
	 *
	 * @since TBD
	 *
	 * @covers ::get_page_feed_url
	 *
	 * @return void
	 */
	public function test_page_feed_on_protected_venue_page(): void {
		$venue_id = $this->mock->post(
			array(
				'post_type'     => Venue::POST_TYPE,
				'post_name'     => 'locked-page-hall',
				'post_password' => 'secret',
			)
		)->get()->ID;

		$this->go_to( get_permalink( $venue_id ) );

		$this->assertSame( '', $this->render_href( '#gatherpress-calendar-feed-url' ) );
		$this->assertSame( '', $this->render_href( '#gatherpress-calendar-feed-venue-url', $venue_id ) );
	}
}
