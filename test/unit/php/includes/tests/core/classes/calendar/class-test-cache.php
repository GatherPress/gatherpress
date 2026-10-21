<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Cache.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Cache;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Cache.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Cache
 * @group              endpoints
 */
class Test_Cache extends Base {

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
		$reflection = new \ReflectionClass( Cache::class );
		$property   = $reflection->getProperty( 'instance' );

		$property->setAccessible( true );
		$property->setValue( null, null );

		$this->assertInstanceOf(
			Cache::class,
			Cache::get_instance(),
			'Failed to assert that the constructor returns a Cache instance.'
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
		$instance = Cache::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'save_post',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_post' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_post' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'set_object_terms',
				'priority' => 10,
				'callback' => array( $instance, 'mark_changed_for_terms' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * The version stamp seeds itself once and then holds still, so clients get
	 * a validator that means something.
	 *
	 * @covers ::get_last_modified
	 *
	 * @return void
	 */
	public function test_get_last_modified_is_stable_once_seeded(): void {
		delete_option( Cache::LAST_MODIFIED_OPTION );

		$instance = Cache::get_instance();
		$first    = $instance->get_last_modified();

		$this->assertNotEmpty( $first, 'A first read should seed a timestamp.' );
		$this->assertSame(
			$first,
			$instance->get_last_modified(),
			'Repeat reads should return the same timestamp rather than moving.'
		);
	}

	/**
	 * Marking the calendar changed moves the key namespace, which is what
	 * makes every cached response unreachable at once.
	 *
	 * @covers ::mark_changed
	 * @covers ::get_versioned_key
	 *
	 * @return void
	 */
	public function test_mark_changed_moves_the_versioned_key(): void {
		$instance = Cache::get_instance();
		$before   = $instance->get_versioned_key( 'ics:example' );

		update_option( Cache::LAST_MODIFIED_OPTION, '2030-01-01 00:00:00', false );

		$this->assertNotSame(
			$before,
			$instance->get_versioned_key( 'ics:example' ),
			'A new version stamp should produce a different cache key.'
		);
	}

	/**
	 * The payload is stored as a transient, so it survives a site without a
	 * persistent object cache, and its name stays inside the option-name limit.
	 *
	 * @covers ::remember
	 * @covers ::get_versioned_key
	 *
	 * @return void
	 */
	public function test_remember_stores_the_payload_in_a_transient(): void {
		$instance = Cache::get_instance();
		$key      = 'ics:' . str_repeat( 'long-scope-', 40 );
		$renderer = static function (): string {
			return 'BEGIN:VCALENDAR';
		};

		$instance->remember( $key, $renderer );

		$name = $instance->get_versioned_key( $key );

		$this->assertSame(
			'BEGIN:VCALENDAR',
			get_transient( $name ),
			'The rendered payload should be readable back as a transient.'
		);
		$this->assertStringStartsWith(
			Cache::TRANSIENT_PREFIX,
			$name,
			'Transient names should carry the calendar prefix.'
		);
		$this->assertLessThanOrEqual(
			172,
			strlen( $name ),
			'A long scope key must still produce a transient name within the option-name limit.'
		);
	}

	/**
	 * Coverage for remember: renders once, serves the cached copy after.
	 *
	 * @covers ::remember
	 *
	 * @return void
	 */
	public function test_remember_renders_once_then_serves_the_cache(): void {
		$instance = Cache::get_instance();
		$calls    = 0;
		$renderer = static function () use ( &$calls ): string {
			++$calls;

			return 'BEGIN:VCALENDAR';
		};

		$this->assertSame( 'BEGIN:VCALENDAR', $instance->remember( 'ics:test-a', $renderer ) );
		$this->assertSame( 'BEGIN:VCALENDAR', $instance->remember( 'ics:test-a', $renderer ) );
		$this->assertSame( 1, $calls, 'The renderer should run once for a repeated request.' );
	}

	/**
	 * A stamped calendar rebuilds rather than serving the previous payload.
	 *
	 * @covers ::remember
	 * @covers ::mark_changed
	 *
	 * @return void
	 */
	public function test_remember_rebuilds_after_the_calendar_is_marked_changed(): void {
		$instance = Cache::get_instance();
		$payload  = 'first';
		$renderer = static function () use ( &$payload ): string {
			return $payload;
		};

		$instance->remember( 'ics:test-b', $renderer );

		$payload = 'second';

		update_option( Cache::LAST_MODIFIED_OPTION, '2031-01-01 00:00:00', false );

		$this->assertSame(
			'second',
			$instance->remember( 'ics:test-b', $renderer ),
			'A stamped calendar should rebuild instead of serving the stale payload.'
		);
	}

	/**
	 * Filtering the max age to zero opts out of caching entirely.
	 *
	 * @covers ::get_max_age
	 * @covers ::remember
	 *
	 * @return void
	 */
	public function test_zero_max_age_disables_caching(): void {
		$instance = Cache::get_instance();
		$calls    = 0;
		$renderer = static function () use ( &$calls ): string {
			++$calls;

			return 'uncached';
		};

		add_filter( 'gatherpress_calendar_max_age', '__return_zero' );

		$max_age = $instance->get_max_age();

		$instance->remember( 'ics:test-c', $renderer );
		$instance->remember( 'ics:test-c', $renderer );

		remove_filter( 'gatherpress_calendar_max_age', '__return_zero' );

		$this->assertSame( 0, $max_age, 'The filter should be able to disable caching.' );
		$this->assertSame( 2, $calls, 'With caching off the renderer should run every time.' );
	}

	/**
	 * A negative max age is treated as zero rather than passed to the cache.
	 *
	 * @covers ::get_max_age
	 *
	 * @return void
	 */
	public function test_negative_max_age_is_clamped(): void {
		add_filter(
			'gatherpress_calendar_max_age',
			static function (): int {
				return -100;
			}
		);

		$max_age = Cache::get_instance()->get_max_age();

		remove_all_filters( 'gatherpress_calendar_max_age' );

		$this->assertSame( 0, $max_age, 'A negative max age should clamp to zero.' );
	}

	/**
	 * Saving an event stamps the calendar; saving an unrelated post does not.
	 *
	 * @covers ::mark_changed_for_post
	 * @covers ::is_calendar_post_type
	 *
	 * @return void
	 */
	public function test_mark_changed_for_post_only_fires_for_calendar_post_types(): void {
		$instance = Cache::get_instance();

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_post( $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID );

		$this->assertSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'A regular post should not stamp the calendar.'
		);

		$instance->mark_changed_for_post( $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID );

		$this->assertNotSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'An event should stamp the calendar.'
		);
	}

	/**
	 * A venue is part of a VEVENT's LOCATION, so venue edits stamp too.
	 *
	 * @covers ::mark_changed_for_post
	 * @covers ::is_calendar_post_type
	 *
	 * @return void
	 */
	public function test_mark_changed_for_post_fires_for_venues(): void {
		$instance = Cache::get_instance();

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_post( $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get()->ID );

		$this->assertNotSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'A venue edit should stamp the calendar.'
		);
	}

	/**
	 * GatherPress meta stamps the calendar; unrelated meta does not.
	 *
	 * @covers ::mark_changed_for_meta
	 *
	 * @return void
	 */
	public function test_mark_changed_for_meta_only_fires_for_gatherpress_keys(): void {
		$instance = Cache::get_instance();
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_meta( 1, $event_id, '_edit_lock' );

		$this->assertSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'Unrelated meta should not stamp the calendar.'
		);

		$instance->mark_changed_for_meta( 1, $event_id, 'gatherpress_datetime' );

		$this->assertNotSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'Datetime meta should stamp the calendar.'
		);
	}

	/**
	 * Term changes on an event stamp the calendar, but RSVP status changes,
	 * which travel on the same hook against a comment taxonomy, do not.
	 *
	 * @covers ::mark_changed_for_terms
	 *
	 * @return void
	 */
	public function test_mark_changed_for_terms_ignores_comment_taxonomies(): void {
		$instance = Cache::get_instance();
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_terms( 1, array(), array(), Status::TAXONOMY );

		$this->assertSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'An RSVP status change should not invalidate every calendar feed.'
		);

		$instance->mark_changed_for_terms( $event_id, array(), array(), Venue::TAXONOMY );

		$this->assertNotSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			"A change to an event's venue term should stamp the calendar."
		);
	}

	/**
	 * An unregistered taxonomy is ignored rather than stamping on a guess.
	 *
	 * @covers ::mark_changed_for_terms
	 *
	 * @return void
	 */
	public function test_mark_changed_for_terms_ignores_unknown_taxonomies(): void {
		$instance = Cache::get_instance();

		update_option( Cache::LAST_MODIFIED_OPTION, '2029-01-01 00:00:00', false );

		$instance->mark_changed_for_terms( 1, array(), array(), 'not_a_taxonomy' );

		$this->assertSame(
			'2029-01-01 00:00:00',
			$instance->get_last_modified(),
			'An unknown taxonomy should not stamp the calendar.'
		);
	}

	/**
	 * Viewers who get the same events share a scope; only a viewer whose own
	 * private events set them apart gets one to themselves.
	 *
	 * @since TBD
	 *
	 * @covers ::get_viewer_scope
	 *
	 * @return void
	 */
	public function test_get_viewer_scope(): void {
		$instance   = Cache::get_instance();
		$author_id  = $this->factory->user->create( array( 'role' => 'author' ) );
		$scope_of   = function ( int $user_id ) use ( $instance ): string {
			wp_set_current_user( $user_id );

			return $instance->get_viewer_scope();
		};
		$subscriber = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$this->assertSame( 'public', $scope_of( 0 ), 'An anonymous viewer gets the public scope.' );
		$this->assertSame( 'public', $scope_of( $subscriber ), 'A Subscriber sees no private events.' );
		$this->assertSame( 'public', $scope_of( $author_id ), 'An Author without private events sees public ones.' );
		$this->assertSame(
			'private',
			$scope_of( $this->factory->user->create( array( 'role' => 'editor' ) ) ),
			'An Editor reads every private event.'
		);
		$this->assertSame(
			'private',
			$scope_of( $this->factory->user->create( array( 'role' => 'administrator' ) ) ),
			'An Administrator reads every private event.'
		);

		$this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'private',
				'post_author' => $author_id,
			)
		);

		$this->assertSame(
			sprintf( 'user:%d', $author_id ),
			$scope_of( $author_id ),
			'An Author with a private event of their own gets their own scope.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * A viewer who can read the private events of one event post type but not
	 * another sees a set nobody else is known to share.
	 *
	 * @since TBD
	 *
	 * @covers ::get_viewer_scope
	 *
	 * @return void
	 */
	public function test_get_viewer_scope_with_partial_private_access(): void {
		register_post_type(
			'gatherpress_capped',
			array(
				'public'          => true,
				'capability_type' => 'page',
				'supports'        => array( 'title', 'gatherpress-event-date' ),
			)
		);

		$user = $this->factory->user->create_and_get( array( 'role' => 'subscriber' ) );

		$user->add_cap( 'read_private_posts' );
		wp_set_current_user( $user->ID );

		$scope = Cache::get_instance()->get_viewer_scope();

		unregister_post_type( 'gatherpress_capped' );
		wp_set_current_user( 0 );

		$this->assertSame(
			sprintf( 'user:%d', $user->ID ),
			$scope,
			'Reading private posts but not private pages should give the viewer their own scope.'
		);
	}

	/**
	 * Gives an unpublished queried event a scope for the current viewer alone.
	 *
	 * @since TBD
	 *
	 * @covers ::get_viewer_scope
	 *
	 * @return void
	 */
	public function test_get_viewer_scope_for_an_unpublished_queried_event(): void {
		$author_id        = $this->factory->user->create( array( 'role' => 'author' ) );
		$administrator_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$draft_id         = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
				'post_author' => $author_id,
			)
		);
		$published_id     = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);
		$scope_of         = function ( int $user_id, int $post_id ): string {
			wp_set_current_user( $user_id );
			$this->go_to(
				add_query_arg(
					array(
						'p'         => $post_id,
						'post_type' => Event::POST_TYPE,
					),
					home_url( '/' )
				)
			);

			return Cache::get_instance()->get_viewer_scope();
		};

		$this->assertSame(
			sprintf( 'user:%d', $author_id ),
			$scope_of( $author_id, $draft_id ),
			'An Author viewing their own draft event should get their own scope.'
		);
		$this->assertSame(
			sprintf( 'user:%d', $administrator_id ),
			$scope_of( $administrator_id, $draft_id ),
			'An Administrator viewing a draft event should get their own scope.'
		);
		$this->assertSame(
			'public',
			$scope_of( $author_id, $published_id ),
			'An Author viewing a published event should share the public scope.'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Only the public calendar may be kept by shared caches; every other
	 * scope goes out as private.
	 *
	 * @since TBD
	 *
	 * @covers ::get_cacheability
	 *
	 * @return void
	 */
	public function test_get_cacheability(): void {
		$instance         = Cache::get_instance();
		$cacheability_of  = function ( int $user_id ) use ( $instance ): string {
			wp_set_current_user( $user_id );

			return $instance->get_cacheability();
		};
		$author_id        = $this->factory->user->create( array( 'role' => 'author' ) );
		$subscriber_id    = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$administrator_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'private',
				'post_author' => $author_id,
			)
		);

		$this->assertSame( 'public', $cacheability_of( 0 ), 'An anonymous viewer gets a public calendar.' );
		$this->assertSame( 'public', $cacheability_of( $subscriber_id ), 'A Subscriber shares the public calendar.' );
		$this->assertSame(
			'private',
			$cacheability_of( $administrator_id ),
			'An Administrator\'s calendar lists private events, so it is private.'
		);
		$this->assertSame(
			'private',
			$cacheability_of( $author_id ),
			'An Author\'s calendar with their own private event is private.'
		);

		wp_set_current_user( 0 );
	}
}
