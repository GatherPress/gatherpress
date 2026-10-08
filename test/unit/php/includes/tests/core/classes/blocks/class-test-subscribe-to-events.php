<?php
/**
 * Class handles unit tests for the Subscribe to Events block.
 *
 * The block has no PHP class of its own — render.php resolves the feed URL
 * through GatherPress\Core\Calendar\Feed_Url and prints the markup — so these
 * tests drive it through the block registry the way the frontend does.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Calendar\Calendar;
use GatherPress\Core\Calendar\Feed_Url;
use GatherPress\Core\Calendar\Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use WP_Block;
use WP_Block_Type_Registry;
use WP_Post;

/**
 * Class Test_Subscribe_To_Events.
 *
 * @group endpoints
 */
class Test_Subscribe_To_Events extends Base {

	/**
	 * The block name under test.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/subscribe-to-events';

	/**
	 * Coverage for the sitewide scope rendering both links.
	 *
	 * @return void
	 */
	public function test_render_sitewide_scope_renders_both_links(): void {
		$output = do_blocks( sprintf( '<!-- wp:%s /-->', self::BLOCK_NAME ) );

		$feed_url = get_feed_link( Setup::ICAL_SLUG );

		$this->assertStringContainsString(
			sprintf( 'href="%s"', esc_url( $feed_url ) ),
			$output,
			'The iCal link should point at the sitewide feed.'
		);
		$this->assertStringContainsString(
			sprintf( 'href="%s"', esc_url( Feed_Url::to_webcal( $feed_url ) ) ),
			$output,
			'The subscribe link should point at the webcal form of the feed.'
		);
	}

	/**
	 * Coverage for the linkFormat attribute.
	 *
	 * @return void
	 */
	public function test_render_respects_link_format(): void {
		$ical_only = do_blocks(
			sprintf( '<!-- wp:%s {"linkFormat":"ical"} /-->', self::BLOCK_NAME )
		);

		$this->assertStringContainsString(
			'iCal feed',
			$ical_only,
			'The iCal-only format should render the iCal link.'
		);
		$this->assertStringNotContainsString(
			'webcal://',
			$ical_only,
			'The iCal-only format should not render the subscribe link.'
		);

		$webcal_only = do_blocks(
			sprintf( '<!-- wp:%s {"linkFormat":"webcal"} /-->', self::BLOCK_NAME )
		);

		$this->assertStringContainsString(
			'webcal://',
			$webcal_only,
			'The subscribe-only format should render the subscribe link.'
		);
		$this->assertStringNotContainsString(
			sprintf( 'href="%s"', esc_url( get_feed_link( Setup::ICAL_SLUG ) ) ),
			$webcal_only,
			'The subscribe-only format should not render the plain iCal link.'
		);
	}

	/**
	 * Coverage for the custom link labels.
	 *
	 * @return void
	 */
	public function test_render_uses_custom_link_text(): void {
		$output = do_blocks(
			sprintf(
				'<!-- wp:%s {"linkText":"Grab the feed","subscribeText":"Follow us"} /-->',
				self::BLOCK_NAME
			)
		);

		$this->assertStringContainsString( 'Grab the feed', $output, 'The custom iCal label should render.' );
		$this->assertStringContainsString( 'Follow us', $output, 'The custom subscribe label should render.' );
	}

	/**
	 * Coverage for the archive scope.
	 *
	 * @return void
	 */
	public function test_render_archive_scope(): void {
		$output = do_blocks(
			sprintf(
				'<!-- wp:%s {"scope":"archive","postType":"%s"} /-->',
				self::BLOCK_NAME,
				Event::POST_TYPE
			)
		);

		$this->assertStringContainsString(
			sprintf(
				'href="%s"',
				esc_url( get_post_type_archive_feed_link( Event::POST_TYPE, Setup::ICAL_SLUG ) )
			),
			$output,
			'The archive scope should link to the post type archive feed.'
		);
	}

	/**
	 * Coverage for the venue scope.
	 *
	 * @return void
	 */
	public function test_render_venue_scope(): void {
		$venue = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();

		$output = do_blocks(
			sprintf(
				'<!-- wp:%s {"scope":"venue","venueId":%d} /-->',
				self::BLOCK_NAME,
				$venue->ID
			)
		);

		$this->assertStringContainsString(
			sprintf(
				'href="%s"',
				esc_url( get_post_comments_feed_link( $venue->ID, Setup::ICAL_SLUG ) )
			),
			$output,
			'The venue scope should link to the venue feed.'
		);
	}

	/**
	 * Coverage for a venue scope with no venue selected.
	 *
	 * The docs say a scope that needs an ID renders nothing. `get_post( 0 )`
	 * returns the global post, so a venue page must not leak its own feed.
	 *
	 * @return void
	 */
	public function test_render_venue_scope_without_a_venue(): void {
		$venue = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();
		$this->go_to( get_permalink( $venue->ID ) );

		$output = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"venue"} /-->', self::BLOCK_NAME )
		);

		$this->assertStringNotContainsString(
			'gatherpress-subscribe-to-events',
			$output,
			'A venue scope with no venue selected should render no wrapper.'
		);
	}

	/**
	 * Coverage for the topic scope.
	 *
	 * @return void
	 */
	public function test_render_topic_scope(): void {
		$term = $this->factory->term->create_and_get( array( 'taxonomy' => Topic::TAXONOMY ) );

		$output = do_blocks(
			sprintf(
				'<!-- wp:%s {"scope":"topic","topicId":%d} /-->',
				self::BLOCK_NAME,
				$term->term_id
			)
		);

		$this->assertStringContainsString(
			sprintf(
				'href="%s"',
				esc_url( get_term_feed_link( $term->term_id, Topic::TAXONOMY, Setup::ICAL_SLUG ) )
			),
			$output,
			'The topic scope should link to the term feed.'
		);
	}

	/**
	 * Coverage for scopes that cannot resolve, which render nothing.
	 *
	 * @return void
	 */
	public function test_render_returns_nothing_for_unresolvable_scopes(): void {
		$venue_scope = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"venue","venueId":999999999} /-->', self::BLOCK_NAME )
		);
		$this->assertStringNotContainsString(
			'gatherpress-subscribe-to-events',
			$venue_scope,
			'A venue scope pointing at a missing post should render no wrapper.'
		);

		$topic_scope = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"topic","topicId":999999999} /-->', self::BLOCK_NAME )
		);
		$this->assertStringNotContainsString(
			'gatherpress-subscribe-to-events',
			$topic_scope,
			'A topic scope pointing at a missing term should render no wrapper.'
		);

		$bad_scope = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"nope"} /-->', self::BLOCK_NAME )
		);
		$this->assertStringNotContainsString(
			'gatherpress-subscribe-to-events',
			$bad_scope,
			'An unknown scope should render no wrapper.'
		);
	}

	/**
	 * Coverage for the filter reaching the block output.
	 *
	 * @return void
	 */
	public function test_render_honors_the_feed_url_filter(): void {
		$callback = function (): string {
			return 'https://example.org/filtered-feed/';
		};

		add_filter( 'gatherpress_calendar_feed_url', $callback );

		$output = do_blocks( sprintf( '<!-- wp:%s /-->', self::BLOCK_NAME ) );

		remove_filter( 'gatherpress_calendar_feed_url', $callback );

		$this->assertStringContainsString(
			'href="https://example.org/filtered-feed/"',
			$output,
			'The resolved feed URL should pass through the gatherpress_calendar_feed_url filter.'
		);
	}

	/**
	 * Coverage for the filter supplying a feed URL for a scope core does not know.
	 *
	 * @return void
	 */
	public function test_render_honors_the_feed_url_filter_for_unknown_scope(): void {
		$callback = function (): string {
			return 'https://example.org/companion-feed/';
		};

		add_filter( 'gatherpress_calendar_feed_url', $callback );

		$output = do_blocks(
			sprintf( '<!-- wp:%s {"scope":"companion"} /-->', self::BLOCK_NAME )
		);

		remove_filter( 'gatherpress_calendar_feed_url', $callback );

		$this->assertStringContainsString(
			'href="https://example.org/companion-feed/"',
			$output,
			'The block should render a feed URL the filter supplies for an unknown scope.'
		);
	}

	/**
	 * Render the block with block context the way a template does.
	 *
	 * `do_blocks()` renders with no context, so a contextual render needs a
	 * `WP_Block` whose context is supplied the way a parent block supplies it.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<string, mixed> $context    Available block context.
	 *
	 * @return string Rendered block markup.
	 */
	protected function render_with_context( array $attributes, array $context ): string {
		$block = new WP_Block(
			array(
				'blockName' => self::BLOCK_NAME,
				'attrs'     => $attributes,
			),
			$context
		);

		return $block->render();
	}

	/**
	 * Attach a venue post to an event so the contextual feed can resolve it.
	 *
	 * @param int $event_id The event post ID.
	 *
	 * @return WP_Post The attached venue post.
	 */
	protected function attach_venue( int $event_id ): WP_Post {
		$venue = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'contextual-venue',
				'post_title' => 'Contextual Venue',
			)
		)->get();

		$slug = Venue\Setup::get_instance()->term_slug_from_post_name( $venue->post_name );
		wp_insert_term( $venue->post_title, Venue::TAXONOMY, array( 'slug' => $slug ) );
		wp_set_post_terms( $event_id, $slug, Venue::TAXONOMY );

		return $venue;
	}

	/**
	 * Coverage for the event scope rendering the event, venue, and topic feeds.
	 *
	 * @return void
	 */
	public function test_render_event_scope_renders_contextual_feeds(): void {
		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$venue = $this->attach_venue( $event->ID );

		$topic_a = $this->factory->term->create_and_get( array( 'taxonomy' => Topic::TAXONOMY ) );
		$topic_b = $this->factory->term->create_and_get( array( 'taxonomy' => Topic::TAXONOMY ) );
		wp_set_post_terms( $event->ID, array( $topic_a->term_id, $topic_b->term_id ), Topic::TAXONOMY );

		$output = $this->render_with_context(
			array( 'scope' => 'event' ),
			array( 'postId' => $event->ID )
		);

		$ical_url  = ( new Calendar( $event->ID ) )->get_ical_url();
		$venue_url = (string) Feed_Url::get(
			array(
				'scope'    => 'venue',
				'venue_id' => $venue->ID,
			)
		);

		$this->assertStringContainsString(
			sprintf( 'href="%s"', esc_url( (string) $ical_url ) ),
			$output,
			'The event scope should link to the event iCal download.'
		);
		$this->assertStringContainsString(
			sprintf( 'href="%s"', esc_url( $venue_url ) ),
			$output,
			'The event scope should link to the venue feed.'
		);
		$this->assertStringContainsString(
			'Contextual Venue: iCal feed',
			$output,
			'The venue feed link should name the venue.'
		);
		$this->assertStringContainsString(
			esc_url( (string) get_term_feed_link( $topic_a->term_id, Topic::TAXONOMY, Setup::ICAL_SLUG ) ),
			$output,
			'The event scope should link to the first topic feed.'
		);
		$this->assertStringContainsString(
			esc_url( (string) get_term_feed_link( $topic_b->term_id, Topic::TAXONOMY, Setup::ICAL_SLUG ) ),
			$output,
			'The event scope should link to the second topic feed.'
		);
		$this->assertStringContainsString(
			$topic_a->name . ': iCal feed',
			$output,
			'A topic feed link should name the topic.'
		);
	}

	/**
	 * Coverage for the event scope when the event has no venue and no topics.
	 *
	 * @return void
	 */
	public function test_render_event_scope_without_venue_or_topics(): void {
		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$output = $this->render_with_context(
			array( 'scope' => 'event' ),
			array( 'postId' => $event->ID )
		);

		$ical_url = ( new Calendar( $event->ID ) )->get_ical_url();

		$this->assertStringContainsString(
			sprintf( 'href="%s"', esc_url( (string) $ical_url ) ),
			$output,
			'The event scope should still link to the event iCal download.'
		);
		$this->assertSame(
			1,
			substr_count( $output, '<a ' ),
			'Only the event download link should render when there is no venue or topic.'
		);
	}

	/**
	 * Coverage for the event scope download getting no subscribe link.
	 *
	 * The event iCal is a one-off file, so a webcal-only format leaves only the
	 * venue and topic subscriptions.
	 *
	 * @return void
	 */
	public function test_render_event_scope_webcal_only_skips_the_download(): void {
		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$venue = $this->attach_venue( $event->ID );

		$output = $this->render_with_context(
			array(
				'scope'      => 'event',
				'linkFormat' => 'webcal',
			),
			array( 'postId' => $event->ID )
		);

		$ical_url  = ( new Calendar( $event->ID ) )->get_ical_url();
		$venue_url = (string) Feed_Url::get(
			array(
				'scope'    => 'venue',
				'venue_id' => $venue->ID,
			)
		);

		$this->assertStringNotContainsString(
			esc_url( (string) $ical_url ),
			$output,
			'The event download should not render as a subscribe link.'
		);
		$this->assertStringContainsString(
			esc_url( Feed_Url::to_webcal( $venue_url ) ),
			$output,
			'The venue feed should render as a subscribe link.'
		);
		$this->assertStringContainsString(
			'Contextual Venue: Subscribe',
			$output,
			'The venue subscribe link should name the venue.'
		);
	}

	/**
	 * Coverage for the event scope with no subscribable feed in webcal-only mode.
	 *
	 * The event's own iCal is a download and gets no subscribe link, so an event
	 * with no venue and no topics leaves nothing to render.
	 *
	 * @return void
	 */
	public function test_render_event_scope_webcal_only_without_sources_renders_nothing(): void {
		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$output = $this->render_with_context(
			array(
				'scope'      => 'event',
				'linkFormat' => 'webcal',
			),
			array( 'postId' => $event->ID )
		);

		$this->assertSame(
			'',
			$output,
			'A webcal-only event scope with no venue or topic has nothing to subscribe to.'
		);
	}

	/**
	 * Coverage for the event scope iCal-only format.
	 *
	 * @return void
	 */
	public function test_render_event_scope_ical_only_leaves_the_download(): void {
		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		$output = $this->render_with_context(
			array(
				'scope'      => 'event',
				'linkFormat' => 'ical',
			),
			array( 'postId' => $event->ID )
		);

		$this->assertStringContainsString(
			esc_url( (string) ( new Calendar( $event->ID ) )->get_ical_url() ),
			$output,
			'The iCal-only format should render the event download.'
		);
		$this->assertStringNotContainsString(
			'webcal://',
			$output,
			'The iCal-only format should not render a subscribe link.'
		);
	}

	/**
	 * Coverage for the event scope using the custom link labels.
	 *
	 * @return void
	 */
	public function test_render_event_scope_uses_custom_link_text(): void {
		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$venue = $this->attach_venue( $event->ID );

		$output = $this->render_with_context(
			array(
				'scope'         => 'event',
				'linkText'      => 'Grab the feed',
				'subscribeText' => 'Follow us',
			),
			array( 'postId' => $event->ID )
		);

		$this->assertStringContainsString(
			'Grab the feed',
			$output,
			'The custom iCal label should reach the event download.'
		);
		$this->assertStringContainsString(
			$venue->post_title . ': Follow us',
			$output,
			'The custom subscribe label should reach the venue feed.'
		);
	}

	/**
	 * Coverage for the event scope outside of any event context.
	 *
	 * @return void
	 */
	public function test_render_event_scope_without_event_context_renders_nothing(): void {
		$page = $this->mock->post( array( 'post_type' => 'page' ) )->get();

		$output = $this->render_with_context(
			array( 'scope' => 'event' ),
			array( 'postId' => $page->ID )
		);

		$this->assertSame(
			'',
			$output,
			'The event scope outside an event should render nothing at all.'
		);
	}

	/**
	 * Coverage for the event scope preferring the context post over the queried one.
	 *
	 * The request is a different event, so the links can only have come from
	 * `postId` in context. This is the Query Loop behavior: each instance shows
	 * the feeds of the event it sits in, not the queried one.
	 *
	 * @return void
	 */
	public function test_render_event_scope_reads_the_context_post(): void {
		$context_event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'context-event',
			)
		)->get();
		$queried_event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'queried-event-instead',
			)
		)->get();

		$this->go_to( get_permalink( $queried_event->ID ) );

		$output = $this->render_with_context(
			array( 'scope' => 'event' ),
			array( 'postId' => $context_event->ID )
		);

		$this->assertStringContainsString(
			esc_url( (string) ( new Calendar( $context_event->ID ) )->get_ical_url() ),
			$output,
			'The event scope should render from the context post.'
		);
		$this->assertStringNotContainsString(
			esc_url( (string) ( new Calendar( $queried_event->ID ) )->get_ical_url() ),
			$output,
			'The event scope should not render from the queried post when context supplies one.'
		);
	}

	/**
	 * Coverage for the event scope falling back to the queried post.
	 *
	 * @return void
	 */
	public function test_render_event_scope_falls_back_to_the_queried_post(): void {
		$event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'queried-event',
			)
		)->get();

		$this->go_to( get_permalink( $event->ID ) );

		$output = $this->render_with_context( array( 'scope' => 'event' ), array() );

		$this->assertStringContainsString(
			esc_url( (string) ( new Calendar( $event->ID ) )->get_ical_url() ),
			$output,
			'The event scope should fall back to the queried post when there is no context.'
		);
	}

	/**
	 * Coverage for the event scope when no post can be resolved at all.
	 *
	 * @return void
	 */
	public function test_render_event_scope_without_any_post_renders_nothing(): void {
		$this->go_to( home_url( '/?p=' . PHP_INT_MAX ) );

		$output = $this->render_with_context( array( 'scope' => 'event' ), array() );

		$this->assertSame(
			'',
			$output,
			'The event scope with no post at all should render nothing at all.'
		);
	}

	/**
	 * Coverage for the text alignment and layout supports decorating the wrapper.
	 *
	 * Both are core-supplied: the alignment lands as a class and the layout as
	 * the flex classes that let the links render side by side. Rendering through
	 * `do_blocks()` proves the supports reach the frontend markup.
	 *
	 * @return void
	 */
	public function test_render_decorates_the_wrapper_with_block_supports(): void {
		$output = do_blocks(
			sprintf(
				'<!-- wp:%s {"className":"my-links",' .
				'"style":{"typography":{"textAlign":"center"}},' .
				'"layout":{"type":"flex","orientation":"horizontal"}} /-->',
				self::BLOCK_NAME
			)
		);

		$this->assertStringContainsString(
			'gatherpress-subscribe-to-events',
			$output,
			'The wrapper should carry the block class.'
		);
		$this->assertStringContainsString( 'my-links', $output, 'The wrapper should carry the custom class name.' );
		$this->assertStringContainsString(
			'has-text-align-center',
			$output,
			'The text alignment support should decorate the wrapper.'
		);
		$this->assertStringContainsString(
			'is-layout-flex',
			$output,
			'The layout support should let the links render side by side.'
		);
	}

	/**
	 * Coverage for the spacing support being declared on the block.
	 *
	 * Core emits the block gap as layout CSS through the style engine rather
	 * than as an attribute on the wrapper, so the declaration on the registered
	 * block type is what the frontend gap depends on.
	 *
	 * @return void
	 */
	public function test_block_declares_the_block_gap_support(): void {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME );

		$this->assertNotNull( $block_type, 'The block should be registered.' );
		$this->assertNotEmpty(
			$block_type->supports['spacing']['blockGap'] ?? null,
			'The block should declare block gap support so the link list can be spaced.'
		);
	}
}
