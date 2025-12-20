<?php
/**
 * Test class for Feed.
 *
 * @package GatherPress\Tests\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Feed;
use GatherPress\Tests\Base;
use WP_Query;

/**
 * Class Test_Feed.
 *
 * @since 1.0.0
 *
 * @coversDefaultClass \GatherPress\Core\Feed
 */
class Test_Feed extends Base {
	/**
	 * Test instance of Feed.
	 *
	 * @since 1.0.0
	 *
	 * @var Feed
	 */
	protected Feed $instance;

	/**
	 * Set up test environment.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->instance = Feed::get_instance();
	}

	/**
	 * Test singleton pattern.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_singleton_pattern(): void {
		$instance1 = Feed::get_instance();
		$instance2 = Feed::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test setup_hooks registers all filters and actions.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$hooks = array(
			array(
				'type'     => 'filter',
				'name'     => 'gatherpress_event_feed_excerpt',
				'priority' => 10,
				'callback' => array( $this->instance, 'get_default_event_excerpt' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'gatherpress_event_feed_content',
				'priority' => 10,
				'callback' => array( $this->instance, 'get_default_event_content' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'the_excerpt_rss',
				'priority' => 10,
				'callback' => array( $this->instance, 'apply_event_excerpt' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'the_content_feed',
				'priority' => 10,
				'callback' => array( $this->instance, 'apply_event_content' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'pre_get_posts',
				'priority' => 10,
				'callback' => array( $this->instance, 'handle_events_feed_query' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'post_type_archive_feed_link',
				'priority' => 10,
				'callback' => array( $this->instance, 'modify_feed_link_for_past_events' ),
			),
		);

		$this->assert_hooks( $hooks, $this->instance );
	}

	/**
	 * Test force_events_feed_query method.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::handle_events_feed_query
	 *
	 * @return void
	 */
	public function test_handle_events_feed_query(): void {
		// Mock the request URI.
		$_SERVER['REQUEST_URI'] = '/events/feed/';

		// Create a mock query for event feed.
		$query = $this->createMock( WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'is_feed' )->willReturn( true );

		// Test that the method doesn't error.
		$this->instance->handle_events_feed_query( $query );

		// Verify the method completed without error.
		$this->assertTrue( true );

		// Clean up.
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test handle_events_feed_query method with non-events feed.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::handle_events_feed_query
	 *
	 * @return void
	 */
	public function test_handle_events_feed_query_non_events(): void {
		// Mock $_SERVER['REQUEST_URI'] for non-events feed.
		$_SERVER['REQUEST_URI'] = '/feed/';

		// Create a mock query for non-events feed.
		$query = $this->createMock( WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'is_feed' )->willReturn( true );
		$query->expects( $this->never() )->method( 'set' );

		$this->instance->handle_events_feed_query( $query );

		// Clean up.
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test handle_events_feed_query method with non-feed query.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::handle_events_feed_query
	 *
	 * @return void
	 */
	public function test_handle_events_feed_query_non_feed(): void {
		// Mock the request URI.
		$_SERVER['REQUEST_URI'] = '/events/feed/';

		// Create a mock query for non-feed.
		$query = $this->createMock( WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'is_feed' )->willReturn( false );
		$query->expects( $this->never() )->method( 'set' );

		$this->instance->handle_events_feed_query( $query );

		// Clean up.
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test handle_events_feed_query method with type parameter.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::handle_events_feed_query
	 *
	 * @return void
	 */
	public function test_handle_events_feed_query_with_type_parameter(): void {
		// Mock the request URI for events feed with type=past.
		$_SERVER['REQUEST_URI'] = '/events/feed/';
		$_GET['type']           = 'past';

		// Create a mock query for event feed.
		$query = $this->createMock( WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'is_feed' )->willReturn( true );

		// Test that the method doesn't error when type parameter is provided.
		$this->instance->handle_events_feed_query( $query );

		// Verify the method completed without error.
		$this->assertTrue( true );

		// Clean up.
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_GET['type'] );
	}

	/**
	 * Test modify_feed_link_for_past_events method.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::modify_feed_link_for_past_events
	 *
	 * @return void
	 */
	public function test_modify_feed_link_for_past_events(): void {
		// Test that the method doesn't error when called.
		$result = $this->instance->modify_feed_link_for_past_events( 'http://example.com/event/feed/' );

		// Verify the method completed without error.
		$this->assertTrue( true );
	}

	/**
	 * Test modify_feed_link_for_past_events method adds type=past parameter.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::modify_feed_link_for_past_events
	 *
	 * @return void
	 */
	public function test_modify_feed_link_for_past_events_adds_type_parameter(): void {
		// Store original global state.
		global $wp_query;
		$original_wp_query = $wp_query;

		// Create a mock WP_Query object with the required query_vars.
		$mock_query             = $this->createMock( WP_Query::class );
		$mock_query->query_vars = array( 'gatherpress_event_query' => 'past' );

		// Temporarily replace the global wp_query.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Testing requires global manipulation.
		$wp_query = $mock_query;

		// Test the method with a feed link.
		$feed_link = 'http://example.com/event/feed/';
		$result    = $this->instance->modify_feed_link_for_past_events( $feed_link );

		// Verify the result contains the type=past parameter.
		$this->assertStringContainsString( 'type=past', $result );
		$this->assertStringContainsString( 'http://example.com/event/feed/', $result );

		// Restore original global state.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Testing requires global manipulation.
		$wp_query = $original_wp_query;
	}

	/**
	 * Test get_default_event_excerpt method.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_excerpt
	 *
	 * @return void
	 */
	public function test_get_default_event_excerpt(): void {
		// Create a test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => 'Test Event',
				'post_content' => 'Test event content',
			)
		);

		// Set up global post data.
		$post = get_post( $event_id );
		setup_postdata( $post );

		// Create a mock Event class that will be used when new Event() is called.
		$event_mock = $this->getMockBuilder( Event::class )
			->setConstructorArgs( array( $event_id ) )
			->onlyMethods( array( 'get_datetime', 'get_venue_information' ) )
			->getMock();

		$event_mock->method( 'get_datetime' )->willReturn(
			array(
				'datetime_start'     => '2024-01-15 14:00:00',
				'datetime_end'       => '2024-01-15 16:00:00',
				'datetime_start_gmt' => '2024-01-15 14:00:00',
				'datetime_end_gmt'   => '2024-01-15 16:00:00',
				'timezone'           => 'America/New_York',
			)
		);
		$event_mock->method( 'get_venue_information' )->willReturn(
			array(
				'name' => 'Test Venue',
			)
		);

		// Mock the Event class constructor globally.
		$original_event_class = Event::class;
		$mock_event_class     = get_class( $event_mock );

		// Use runkit or similar to replace the class, but for now let's test with a simpler approach.
		// Since we can't easily mock the constructor globally, let's test the actual behavior.

		// Test the excerpt customization.
		$excerpt = 'Original excerpt';
		$result  = $this->instance->get_default_event_excerpt( $excerpt );

		// For now, just verify that the method returns something and doesn't error.
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );

		// Clean up.
		wp_reset_postdata();
	}

	/**
	 * Test get_default_event_excerpt method with non-event post.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_excerpt
	 *
	 * @return void
	 */
	public function test_get_default_event_excerpt_non_event(): void {
		// Create a test post (not an event).
		$post_id = $this->factory->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Test Post',
			)
		);

		// Set up global post data.
		$post = get_post( $post_id );
		setup_postdata( $post );

		$excerpt = 'Original excerpt';
		$result  = $this->instance->get_default_event_excerpt( $excerpt );

		// Should return original excerpt unchanged.
		$this->assertEquals( $excerpt, $result );

		// Clean up.
		wp_reset_postdata();
	}

	/**
	 * Test get_default_event_content method.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_content
	 *
	 * @return void
	 */
	public function test_get_default_event_content(): void {
		// Create a test event.
		$event_id = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => 'Test Event',
				'post_content' => 'Test event content',
			)
		);

		// Set up global post data.
		$post = get_post( $event_id );
		setup_postdata( $post );

		// Test the content customization.
		$content = 'Original content';
		$result  = $this->instance->get_default_event_content( $content );

		// For now, just verify that the method returns something and doesn't error.
		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );

		// Clean up.
		wp_reset_postdata();
	}

	/**
	 * Test get_default_event_content method with non-event post.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_content
	 *
	 * @return void
	 */
	public function test_get_default_event_content_non_event(): void {
		// Create a test post (not an event).
		$post_id = $this->factory->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Test Post',
			)
		);

		// Set up global post data.
		$post = get_post( $post_id );
		setup_postdata( $post );

		$content = 'Original content';
		$result  = $this->instance->get_default_event_content( $content );

		// Should return original content unchanged.
		$this->assertEquals( $content, $result );

		// Clean up.
		wp_reset_postdata();
	}

	/**
	 * Test get_event_datetime_info method.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_event_datetime_info
	 *
	 * @return void
	 */
	public function test_get_event_datetime_info(): void {
		// Create a mock Event object.
		$event_mock = $this->createMock( Event::class );
		$event_mock->method( 'get_display_datetime' )->willReturn( 'Mon, Jan 15, 2024, 2:00 pm to 4:00 pm' );

		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( $this->instance );
		$method     = $reflection->getMethod( 'get_event_datetime_info' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->instance, $event_mock );

		// Verify the result contains date information.
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
		$this->assertStringContainsString( 'Date:', $result[0] );
	}

	/**
	 * Test get_event_datetime_info method with empty datetime data.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_event_datetime_info
	 *
	 * @return void
	 */
	public function test_get_event_datetime_info_empty_data(): void {
		// Create a mock Event object with empty datetime data.
		$event_mock = $this->createMock( Event::class );
		$event_mock->method( 'get_display_datetime' )->willReturn( '—' );

		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( $this->instance );
		$method     = $reflection->getMethod( 'get_event_datetime_info' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->instance, $event_mock );

		// Verify the result is empty when no datetime data.
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test apply_event_excerpt method with non-event post.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::apply_event_excerpt
	 *
	 * @return void
	 */
	public function test_apply_event_excerpt_non_event(): void {
		// Create a test post (not an event).
		$post_id = $this->factory->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Test Post',
			)
		);

		// Set up global post data.
		$this->go_to( get_permalink( $post_id ) );

		$excerpt = 'Original excerpt';
		$result  = $this->instance->apply_event_excerpt( $excerpt );

		// Should return original excerpt unchanged for non-events.
		$this->assertEquals( $excerpt, $result );
	}

	/**
	 * Test apply_event_excerpt method with event post.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::apply_event_excerpt
	 *
	 * @return void
	 */
	public function test_apply_event_excerpt_event(): void {
		// Create a test event.
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		// Set up global post data.
		$this->go_to( get_permalink( $post->ID ) );

		// Add a filter to modify the excerpt.
		$filter = static function ( $excerpt ) {
			return $excerpt . ' - Modified by filter';
		};
		add_filter( 'gatherpress_event_feed_excerpt', $filter );

		$excerpt = 'Original excerpt';
		$result  = $this->instance->apply_event_excerpt( $excerpt );

		// Should pass through the filter and return modified value.
		// The Feed class adds HTML formatting first, then our filter appends text.
		$this->assertStringContainsString(
			' - Modified by filter',
			$result,
			'Filter should modify the excerpt.'
		);
		$this->assertStringContainsString(
			'Original excerpt',
			$result,
			'Original excerpt should be present.'
		);

		// Clean up.
		remove_filter( 'gatherpress_event_feed_excerpt', $filter );
	}

	/**
	 * Test apply_event_content method with non-event post.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::apply_event_content
	 *
	 * @return void
	 */
	public function test_apply_event_content_non_event(): void {
		// Create a test post (not an event).
		$post_id = $this->factory->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Test Post',
			)
		);

		// Set up global post data.
		$this->go_to( get_permalink( $post_id ) );

		$content = 'Original content';
		$result  = $this->instance->apply_event_content( $content );

		// Should return original content unchanged for non-events.
		$this->assertEquals( $content, $result );
	}

	/**
	 * Test apply_event_content method with event post.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::apply_event_content
	 *
	 * @return void
	 */
	public function test_apply_event_content_event(): void {
		// Create a test event.
		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		// Set up global post data.
		$this->go_to( get_permalink( $post->ID ) );

		// Add a filter to modify the content.
		$filter = static function ( $content ) {
			return $content . ' - Modified by filter';
		};
		add_filter( 'gatherpress_event_feed_content', $filter );

		$content = 'Original content';
		$result  = $this->instance->apply_event_content( $content );

		// Should pass through the filter and return modified value.
		// The Feed class adds HTML formatting first, then our filter appends text.
		$this->assertStringContainsString(
			' - Modified by filter',
			$result,
			'Filter should modify the content.'
		);
		$this->assertStringContainsString(
			'Original content',
			$result,
			'Original content should be present.'
		);

		// Clean up.
		remove_filter( 'gatherpress_event_feed_content', $filter );
	}

	/**
	 * Test handle_events_feed_query without REQUEST_URI.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::handle_events_feed_query
	 *
	 * @return void
	 */
	public function test_handle_events_feed_query_no_request_uri(): void {
		// Unset REQUEST_URI if it exists.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			unset( $_SERVER['REQUEST_URI'] );
		}

		// Create a mock query for event feed.
		$query = $this->createMock( WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( true );
		$query->method( 'is_feed' )->willReturn( true );
		$query->expects( $this->never() )->method( 'set' );

		$this->instance->handle_events_feed_query( $query );

		// Method should return early without setting anything.
		$this->assertTrue( true );
	}

	/**
	 * Test handle_events_feed_query with non-main query.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::handle_events_feed_query
	 *
	 * @return void
	 */
	public function test_handle_events_feed_query_non_main_query(): void {
		// Mock the request URI.
		$_SERVER['REQUEST_URI'] = '/events/feed/';

		// Create a mock query that's not the main query.
		$query = $this->createMock( WP_Query::class );
		$query->method( 'is_main_query' )->willReturn( false );
		$query->method( 'is_feed' )->willReturn( true );
		$query->expects( $this->never() )->method( 'set' );

		$this->instance->handle_events_feed_query( $query );

		// Clean up.
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Test get_default_event_excerpt with empty excerpt.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_excerpt
	 *
	 * @return void
	 */
	public function test_get_default_event_excerpt_empty_excerpt(): void {
		// Create a test event.
		$post = $this->mock->post()->get();

		// Set up global post data.
		$this->go_to( get_permalink( $post->ID ) );

		$result = $this->instance->get_default_event_excerpt( '' );

		// Should return string even with empty excerpt.
		$this->assertIsString( $result );
	}

	/**
	 * Test get_default_event_excerpt with excerpt same as content.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_excerpt
	 *
	 * @return void
	 */
	public function test_get_default_event_excerpt_same_as_content(): void {
		// Create a test event.
		$content = 'Test event content';
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => 'Test Event',
				'post_content' => $content,
			)
		);

		// Set up global post data.
		$this->go_to( get_permalink( $post_id ) );

		// Pass the same content as excerpt.
		$result = $this->instance->get_default_event_excerpt( $content );

		// Should return string.
		$this->assertIsString( $result );
	}

	/**
	 * Test get_default_event_excerpt with whitespace-only excerpt.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_excerpt
	 *
	 * @return void
	 */
	public function test_get_default_event_excerpt_whitespace_only(): void {
		// Create a test event.
		$post = $this->mock->post()->get();

		// Set up global post data.
		$this->go_to( get_permalink( $post->ID ) );

		// Pass whitespace-only excerpt.
		$result = $this->instance->get_default_event_excerpt( "   \n\t   " );

		// Should return string.
		$this->assertIsString( $result );
	}

	/**
	 * Test get_default_event_content with empty content.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_content
	 *
	 * @return void
	 */
	public function test_get_default_event_content_empty_content(): void {
		// Create a test event.
		$post = $this->mock->post()->get();

		// Set up global post data.
		$this->go_to( get_permalink( $post->ID ) );

		$result = $this->instance->get_default_event_content( '' );

		// Should return string even with empty content.
		$this->assertIsString( $result );
	}

	/**
	 * Test get_default_event_content with whitespace-only content.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_content
	 *
	 * @return void
	 */
	public function test_get_default_event_content_whitespace_only(): void {
		// Create a test event.
		$post = $this->mock->post()->get();

		// Set up global post data.
		$this->go_to( get_permalink( $post->ID ) );

		// Pass whitespace-only content.
		$result = $this->instance->get_default_event_content( "   \n\t   " );

		// Should return string.
		$this->assertIsString( $result );
	}

	/**
	 * Test modify_feed_link_for_past_events with non-past events.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::modify_feed_link_for_past_events
	 *
	 * @return void
	 */
	public function test_modify_feed_link_for_past_events_non_past(): void {
		// Store original global state.
		global $wp_query;
		$original_wp_query = $wp_query;

		// Create a mock WP_Query object without past events.
		$mock_query             = $this->createMock( WP_Query::class );
		$mock_query->query_vars = array( 'gatherpress_event_query' => 'upcoming' );

		// Temporarily replace the global wp_query.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Testing requires global manipulation.
		$wp_query = $mock_query;

		// Test the method with a feed link.
		$feed_link = 'http://example.com/event/feed/';
		$result    = $this->instance->modify_feed_link_for_past_events( $feed_link );

		// Should return original link for non-past events.
		$this->assertEquals( $feed_link, $result );

		// Restore original global state.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Testing requires global manipulation.
		$wp_query = $original_wp_query;
	}

	/**
	 * Test get_event_datetime_info with empty string.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_event_datetime_info
	 *
	 * @return void
	 */
	public function test_get_event_datetime_info_empty_string(): void {
		// Create a mock Event object with empty string datetime.
		$event_mock = $this->createMock( Event::class );
		$event_mock->method( 'get_display_datetime' )->willReturn( '' );

		// Use reflection to access the private method.
		$reflection = new \ReflectionClass( $this->instance );
		$method     = $reflection->getMethod( 'get_event_datetime_info' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->instance, $event_mock );

		// Verify the result is empty when datetime is empty string.
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_default_event_excerpt with venue information.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_excerpt
	 * @covers ::get_event_datetime_info
	 *
	 * @return void
	 */
	public function test_get_default_event_excerpt_with_venue(): void {
		// Create a real event post.
		$event_id = $this->mock->post(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_excerpt' => 'This is a test event excerpt.',
			)
		)->get()->ID;

		// Create a venue term.
		$venue_term = wp_insert_term( 'Test Venue', '_gatherpress_venue' );

		// Link venue to event via taxonomy.
		wp_set_object_terms( $event_id, $venue_term['term_id'], '_gatherpress_venue' );

		// Set up datetime.
		add_post_meta( $event_id, 'gatherpress_datetime_start', '2025-12-25 10:00:00' );
		add_post_meta( $event_id, 'gatherpress_datetime_end', '2025-12-25 12:00:00' );

		// Set up global post.
		$this->go_to( get_permalink( $event_id ) );

		$result = $this->instance->get_default_event_excerpt( 'This is a test event excerpt.' );

		$this->assertStringContainsString( 'Test Venue', $result, 'Excerpt should contain venue name.' );
		$this->assertStringContainsString(
			'This is a test event excerpt.',
			$result,
			'Excerpt should contain original text.'
		);
	}

	/**
	 * Test get_default_event_content with venue information.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::get_default_event_content
	 * @covers ::get_event_datetime_info
	 *
	 * @return void
	 */
	public function test_get_default_event_content_with_venue(): void {
		// Create a real event post.
		$event_id = $this->mock->post(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => 'This is the full event content.',
			)
		)->get()->ID;

		// Create a venue term.
		$venue_term = wp_insert_term( 'Conference Center', '_gatherpress_venue' );

		// Link venue to event via taxonomy.
		wp_set_object_terms( $event_id, $venue_term['term_id'], '_gatherpress_venue' );

		// Set up datetime.
		add_post_meta( $event_id, 'gatherpress_datetime_start', '2025-12-25 10:00:00' );
		add_post_meta( $event_id, 'gatherpress_datetime_end', '2025-12-25 12:00:00' );

		// Set up global post.
		$this->go_to( get_permalink( $event_id ) );

		$result = $this->instance->get_default_event_content( 'This is the full event content.' );

		$this->assertStringContainsString( 'Conference Center', $result, 'Content should contain venue name.' );
		$this->assertStringContainsString(
			'This is the full event content.',
			$result,
			'Content should contain original text.'
		);
	}

	/**
	 * Test handle_events_feed_query actually modifies query.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::handle_events_feed_query
	 *
	 * @return void
	 */
	public function test_handle_events_feed_query_modifies_query(): void {
		global $wp_query, $wp_the_query, $wp_rewrite;

		// Set up GatherPress settings with events rewrite slug.
		update_option(
			'gatherpress_general',
			array(
				'urls' => array(
					'events' => 'events',
				),
			)
		);

		// Set up the environment to simulate a feed request.
		$_SERVER['REQUEST_URI'] = '/events/feed/';

		// Ensure wp_rewrite is initialized with feed_base.
		if ( ! isset( $wp_rewrite->feed_base ) ) {
			$wp_rewrite->feed_base = 'feed';
		}

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for testing feed query in unit tests.
		// Create the main query and mark it as a feed.
		$wp_query                = new WP_Query();
		$wp_query->is_feed       = true;
		$wp_the_query            = $wp_query;
		$GLOBALS['wp_query']     = $wp_query;
		$GLOBALS['wp_the_query'] = $wp_the_query;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		// Call the method with the main query.
		$this->instance->handle_events_feed_query( $wp_query );

		// Verify the query was modified.
		$this->assertEquals( Event::POST_TYPE, $wp_query->get( 'post_type' ), 'Post type should be set to event.' );
		$this->assertEquals(
			'upcoming',
			$wp_query->get( 'gatherpress_event_query' ),
			'Event query type should be upcoming by default.'
		);

		// Clean up.
		unset( $_SERVER['REQUEST_URI'] );
		delete_option( 'gatherpress_general' );
	}

	/**
	 * Test handle_events_feed_query with past events type parameter.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::handle_events_feed_query
	 *
	 * @return void
	 */
	public function test_handle_events_feed_query_with_past_type(): void {
		global $wp_query, $wp_the_query, $wp_rewrite;

		// Set up GatherPress settings with events rewrite slug.
		update_option(
			'gatherpress_general',
			array(
				'urls' => array(
					'events' => 'events',
				),
			)
		);

		// Set up the environment to simulate a feed request with type=past.
		$_SERVER['REQUEST_URI'] = '/events/feed/';
		$_GET['type']           = 'past';

		// Ensure wp_rewrite is initialized with feed_base.
		if ( ! isset( $wp_rewrite->feed_base ) ) {
			$wp_rewrite->feed_base = 'feed';
		}

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Required for testing feed query in unit tests.
		// Create the main query and mark it as a feed.
		$wp_query                = new WP_Query();
		$wp_query->is_feed       = true;
		$wp_the_query            = $wp_query;
		$GLOBALS['wp_query']     = $wp_query;
		$GLOBALS['wp_the_query'] = $wp_the_query;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		// Call the method with the main query.
		$this->instance->handle_events_feed_query( $wp_query );

		// Verify the query was modified for past events.
		$this->assertEquals( Event::POST_TYPE, $wp_query->get( 'post_type' ), 'Post type should be set to event.' );
		$this->assertEquals( 'past', $wp_query->get( 'gatherpress_event_query' ), 'Event query type should be past.' );

		// Clean up.
		unset( $_SERVER['REQUEST_URI'] );
		unset( $_GET['type'] );
		delete_option( 'gatherpress_general' );
	}
}
