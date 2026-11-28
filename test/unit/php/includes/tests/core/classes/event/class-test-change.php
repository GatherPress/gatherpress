<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Change.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Change;
use GatherPress\Core\Event\Email;
use GatherPress\Core\Event\Status;
use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Error;

/**
 * Class Test_Change.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Change
 */
class Test_Change extends Base {

	/**
	 * Emails sent during a test, as recipient, subject and message.
	 *
	 * @var array<int, array{0: string, 1: string, 2: string}>
	 */
	private array $sent = array();

	/**
	 * Start each test as a new request, with mail captured.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->sent = array();
		$this->new_request();

		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	/**
	 * Remove the mail capture, queued jobs and leftover snapshots.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ) );
		wp_unschedule_hook( Mailer::JOB_HOOK );
		$this->new_request();

		parent::tear_down();
	}

	/**
	 * Record an outgoing email and stop it from being sent.
	 *
	 * @param mixed                $previous Short-circuit value.
	 * @param array<string, mixed> $atts     The wp_mail() arguments.
	 *
	 * @return bool True, so wp_mail() reports success.
	 */
	public function capture_mail( $previous, array $atts ): bool {
		$this->sent[] = array( $atts['to'], $atts['subject'], $atts['message'] );

		return true;
	}

	/**
	 * End the request, as shutdown does, so the next writes start fresh.
	 *
	 * @return void
	 */
	private function new_request(): void {
		$instance = Change::get_instance();

		Utility::set_and_get_hidden_property( $instance, 'snapshots', array() );
		Utility::set_and_get_hidden_property( $instance, 'baselines', array() );
		Utility::set_and_get_hidden_property( $instance, 'skipped', array() );
	}

	/**
	 * Create a published event that starts tomorrow and lasts two hours.
	 *
	 * The writes that create it are a request of their own.
	 *
	 * @param string $post_status  Post status.
	 * @param int    $start_offset Seconds from now to the start.
	 *
	 * @return int The event post ID.
	 */
	private function create_event( string $post_status = 'publish', int $start_offset = DAY_IN_SECONDS ): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Book Club',
				'post_status' => $post_status,
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', time() + $start_offset ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', time() + $start_offset + 2 * HOUR_IN_SECONDS ),
				'timezone'       => 'UTC',
			)
		);

		$this->new_request();

		return $post_id;
	}

	/**
	 * Save new dates the way the editor does: meta first, then the post update.
	 *
	 * @param int    $post_id  The event post ID.
	 * @param int    $offset   Seconds to move the start and the end by.
	 * @param string $timezone The time zone to save.
	 * @param array  $post     More post fields to save.
	 *
	 * @return void
	 */
	private function editor_save( int $post_id, int $offset, string $timezone = 'UTC', array $post = array() ): void {
		$event = new Event( $post_id );
		$dates = $event->get_datetime();

		update_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => gmdate( 'Y-m-d H:i:s', strtotime( $dates['datetime_start'] ) + $offset ),
					'dateTimeEnd'   => gmdate( 'Y-m-d H:i:s', strtotime( $dates['datetime_end'] ) + $offset ),
					'timezone'      => $timezone,
				)
			)
		);

		wp_update_post( array_merge( array( 'ID' => $post_id ), $post ) );
	}

	/**
	 * Turn off event updates for an RSVP without an account.
	 *
	 * @param Rsvp   $rsvp  The event's RSVPs.
	 * @param string $email The email of the RSVP.
	 *
	 * @return void
	 */
	private function opt_out( Rsvp $rsvp, string $email ): void {
		$comment_id = (int) $rsvp->find( $email )->comment->comment_ID;

		update_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', '0' );
	}

	/**
	 * Create a venue term in the event's venue taxonomy.
	 *
	 * @param string $name The venue name.
	 *
	 * @return int The term ID.
	 */
	private function create_venue_term( string $name ): int {
		$taxonomy = Venue_Setup::get_instance()->taxonomy_for_event_post_type( Event::POST_TYPE );
		$term     = wp_insert_term( $name, $taxonomy, array( 'slug' => '_' . sanitize_title( $name ) ) );

		return (int) $term['term_id'];
	}

	/**
	 * Check the hooks the change class listens on.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Change::get_instance();

		$this->assert_hooks(
			array(
				array(
					'type'     => 'action',
					'name'     => 'pre_post_update',
					'priority' => 10,
					'callback' => array( $instance, 'snapshot_before_update' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'set_object_terms',
					'priority' => 10,
					'callback' => array( $instance, 'snapshot_before_terms' ),
				),
				array(
					'type'     => 'filter',
					'name'     => 'update_post_metadata',
					'priority' => 10,
					'callback' => array( $instance, 'snapshot_before_meta' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'wp_after_insert_post',
					'priority' => 11,
					'callback' => array( $instance, 'compare' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'shutdown',
					'priority' => 10,
					'callback' => array( $instance, 'finish' ),
				),
				array(
					'type'     => 'action',
					'name'     => 'rest_api_init',
					'priority' => 10,
					'callback' => array( $instance, 'register_rest_field' ),
				),
			),
			$instance
		);
	}

	/**
	 * A date change saved from the editor is stored before the response is built.
	 *
	 * @covers ::snapshot_before_update
	 * @covers ::maybe_snapshot
	 * @covers ::should_snapshot
	 * @covers ::is_eligible
	 * @covers ::compare
	 * @covers ::store
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_editor_date_change_is_stored(): void {
		$post_id = $this->create_event();

		$this->editor_save( $post_id, HOUR_IN_SECONDS );

		$change = Change::get_instance()->get( $post_id );

		$this->assertSame( array( 'datetime_start', 'datetime_end' ), $change->keys );
		$this->assertTrue( $change->show_notice );
		$this->assertNotSame( $change->before->datetime_start, $change->after->datetime_start );
	}

	/**
	 * A time zone change counts on its own.
	 *
	 * @covers ::store
	 *
	 * @return void
	 */
	public function test_time_zone_change_is_stored(): void {
		$post_id = $this->create_event();

		$this->editor_save( $post_id, 0, 'America/New_York' );

		$this->assertSame( array( 'timezone' ), Change::get_instance()->get( $post_id )->keys );
	}

	/**
	 * A save that changes none of the compared values stores nothing and keeps an older change.
	 *
	 * The editor's meta box save is such a save: a second `wp_update_post()`
	 * right after the REST save. Clearing the change there made Send fail.
	 *
	 * @covers ::store
	 * @covers ::finish
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_save_without_a_change_keeps_the_stored_change(): void {
		$post_id = $this->create_event();
		$fired   = 0;
		$count   = static function () use ( &$fired ): void {
			++$fired;
		};

		$this->new_request();
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Book Club, renamed',
			)
		);
		Change::get_instance()->finish();
		$this->assertNull(
			Change::get_instance()->get( $post_id ),
			'Failed to assert a title change is not a change.'
		);

		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		Change::get_instance()->finish();
		$stored = Change::get_instance()->get( $post_id );
		$this->assertNotNull( $stored );

		add_action( 'gatherpress_event_changed', $count );

		// The meta box save, then a later title fix.
		wp_update_post( array( 'ID' => $post_id ) );
		Change::get_instance()->finish();
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Book Club, renamed again',
			)
		);
		Change::get_instance()->finish();

		remove_action( 'gatherpress_event_changed', $count );

		$this->assertEquals( $stored, Change::get_instance()->get( $post_id ) );
		$this->assertSame( 0, $fired, 'Failed to assert a save without a change does not fire the action again.' );
	}

	/**
	 * Later saves add to the stored change and keep the values from before the first change.
	 *
	 * @covers ::store
	 *
	 * @return void
	 */
	public function test_later_saves_add_to_the_stored_change(): void {
		$post_id  = $this->create_event();
		$taxonomy = Venue_Setup::get_instance()->taxonomy_for_event_post_type( Event::POST_TYPE );
		$hall     = $this->create_venue_term( 'Main Hall' );
		$library  = $this->create_venue_term( 'Library' );
		$park     = $this->create_venue_term( 'Park' );

		wp_set_object_terms( $post_id, $hall, $taxonomy );
		$this->new_request();
		$start = get_post_meta( $post_id, 'gatherpress_datetime_start', true );

		wp_set_object_terms( $post_id, $library, $taxonomy );
		Change::get_instance()->finish();

		wp_set_object_terms( $post_id, $park, $taxonomy );
		Change::get_instance()->finish();

		$change = Change::get_instance()->get( $post_id );
		$this->assertSame(
			array( '_main-hall' => 'Main Hall' ),
			$change->before->venue,
			'Failed to assert two moves keep the first venue.'
		);
		$this->assertSame(
			array( '_park' => 'Park' ),
			$change->after->venue,
			'Failed to assert two moves end at the last venue.'
		);

		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		Change::get_instance()->finish();

		$change = Change::get_instance()->get( $post_id );
		$this->assertSame( array( 'datetime_start', 'datetime_end', 'venue' ), $change->keys );
		$this->assertSame( $start, $change->before->datetime_start );
		$this->assertSame( array( '_main-hall' => 'Main Hall' ), $change->before->venue );
	}

	/**
	 * An editor save compares twice, and its second compare keeps what the first one found.
	 *
	 * The REST save compares at `wp_after_insert_post`, and again at
	 * shutdown. When the first compare clears the change, the second must
	 * still compare with what attendees were last told, not with this
	 * request's snapshot.
	 *
	 * @covers ::take_snapshot
	 * @covers ::store
	 * @covers ::finish
	 *
	 * @return void
	 */
	public function test_editor_saves_add_up_and_put_back(): void {
		$post_id = $this->create_event();
		$start   = get_post_meta( $post_id, 'gatherpress_datetime_start', true );

		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		Change::get_instance()->finish();
		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		Change::get_instance()->finish();

		$this->assertSame(
			$start,
			Change::get_instance()->get( $post_id )->before->datetime_start,
			'Failed to assert two editor saves keep the first start.'
		);

		$this->editor_save( $post_id, -2 * HOUR_IN_SECONDS );
		Change::get_instance()->finish();

		$this->assertSame( $start, get_post_meta( $post_id, 'gatherpress_datetime_start', true ) );
		$this->assertNull(
			Change::get_instance()->get( $post_id ),
			'Failed to assert saving the first start back clears the change.'
		);
	}

	/**
	 * Putting every value back clears the stored change.
	 *
	 * @covers ::store
	 *
	 * @return void
	 */
	public function test_putting_values_back_clears_the_stored_change(): void {
		$post_id  = $this->create_event();
		$taxonomy = Venue_Setup::get_instance()->taxonomy_for_event_post_type( Event::POST_TYPE );
		$hall     = $this->create_venue_term( 'Main Hall' );
		$library  = $this->create_venue_term( 'Library' );

		wp_set_object_terms( $post_id, $hall, $taxonomy );
		$this->new_request();

		wp_set_object_terms( $post_id, $library, $taxonomy );
		Change::get_instance()->finish();
		$this->assertNotNull( Change::get_instance()->get( $post_id ) );

		wp_set_object_terms( $post_id, $hall, $taxonomy );
		Change::get_instance()->finish();

		$this->assertNull( Change::get_instance()->get( $post_id ) );
		$this->assertSame( '', get_post_meta( $post_id, Change::META_KEY, true ) );
	}

	/**
	 * A draft, and an event published by this request, never look changed.
	 *
	 * @covers ::should_snapshot
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_unpublished_and_newly_published_events_are_skipped(): void {
		$draft = $this->create_event( 'draft' );

		$this->editor_save( $draft, HOUR_IN_SECONDS );
		$this->assertNull( Change::get_instance()->get( $draft ) );

		$this->new_request();
		$this->editor_save( $draft, HOUR_IN_SECONDS, 'UTC', array( 'post_status' => 'publish' ) );
		Change::get_instance()->finish();

		$this->assertSame( 'publish', get_post_status( $draft ) );
		$this->assertNull( Change::get_instance()->get( $draft ) );
	}

	/**
	 * Creating a published event, which writes its dates one key at a time, is not a change.
	 *
	 * @covers ::snapshot_before_meta
	 * @covers ::should_snapshot
	 *
	 * @return void
	 */
	public function test_creating_a_published_event_is_not_a_change(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS + HOUR_IN_SECONDS ),
				'timezone'       => 'UTC',
			)
		);
		Change::get_instance()->finish();

		$this->assertNull( Change::get_instance()->get( $post_id ) );
	}

	/**
	 * An event that has started is skipped.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_started_event_is_skipped(): void {
		$post_id = $this->create_event( 'publish', -HOUR_IN_SECONDS );

		$this->editor_save( $post_id, DAY_IN_SECONDS );

		$this->assertNull( Change::get_instance()->get( $post_id ) );
	}

	/**
	 * A direct `Event::save_datetimes()` call is caught by the meta write and compared at shutdown.
	 *
	 * @covers ::snapshot_before_meta
	 * @covers ::finish
	 *
	 * @return void
	 */
	public function test_direct_date_save_is_compared_at_shutdown(): void {
		$post_id = $this->create_event();
		$fired   = array();

		add_action(
			'gatherpress_event_changed',
			static function ( int $id, array $keys ) use ( &$fired ): void {
				$fired[] = array( $id, $keys );
			},
			10,
			2
		);

		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS + HOUR_IN_SECONDS ),
				'timezone'       => 'UTC',
			)
		);

		$this->assertNull(
			Change::get_instance()->get( $post_id ),
			'Failed to assert nothing is stored before the request ends.'
		);

		Change::get_instance()->finish();

		$this->assertSame( array( 'datetime_start', 'datetime_end' ), Change::get_instance()->get( $post_id )->keys );
		$this->assertSame( array( array( $post_id, array( 'datetime_start', 'datetime_end' ) ) ), $fired );
		$this->assertSame( array(), Utility::get_hidden_property( Change::get_instance(), 'snapshots' ) );
	}

	/**
	 * Another meta key does not take a snapshot, and the short-circuit value passes through.
	 *
	 * @covers ::snapshot_before_meta
	 *
	 * @return void
	 */
	public function test_snapshot_before_meta_ignores_other_keys(): void {
		$post_id  = $this->create_event();
		$instance = Change::get_instance();

		$this->assertSame( 'kept', $instance->snapshot_before_meta( 'kept', $post_id, 'gatherpress_max_guest_limit' ) );
		$this->assertSame( array(), Utility::get_hidden_property( $instance, 'snapshots' ) );

		$this->assertNull( $instance->snapshot_before_meta( null, $post_id, 'gatherpress_timezone' ) );
		$this->assertArrayHasKey( $post_id, Utility::get_hidden_property( $instance, 'snapshots' ) );
	}

	/**
	 * Canceling through `Status::set()` takes the old status from the replaced terms.
	 *
	 * @covers ::snapshot_before_terms
	 * @covers ::status_from_tt_ids
	 * @covers ::terms_from_tt_ids
	 *
	 * @return void
	 */
	public function test_status_change_is_stored(): void {
		$post_id = $this->create_event();

		( new Status( $post_id ) )->set( 'postponed' );
		$this->new_request();

		// As if the message about the postponement was sent.
		delete_post_meta( $post_id, Change::META_KEY );

		$this->assertTrue( ( new Status( $post_id ) )->set( 'canceled' ) );
		Change::get_instance()->finish();

		$change = Change::get_instance()->get( $post_id );

		$this->assertSame( array( 'status' ), $change->keys );
		$this->assertSame( array( 'postponed' ), $change->before->status );
		$this->assertSame( array( 'canceled' ), $change->after->status );
	}

	/**
	 * A status that does not affect attendance does not count, unless a site says it does.
	 *
	 * @covers ::store
	 * @covers ::finish
	 *
	 * @return void
	 */
	public function test_status_that_does_not_affect_attendance(): void {
		$post_id = $this->create_event();

		( new Status( $post_id ) )->set( 'tentative' );
		Change::get_instance()->finish();

		$this->assertNull( Change::get_instance()->get( $post_id ) );

		$count = static fn( array $keys, array $found ): array => $found;

		add_filter( 'gatherpress_event_change_fields', $count, 10, 2 );
		$this->new_request();
		( new Status( $post_id ) )->set( 'scheduled' );
		Change::get_instance()->finish();
		remove_filter( 'gatherpress_event_change_fields', $count );

		$this->assertSame( array( 'status' ), Change::get_instance()->get( $post_id )->keys );
	}

	/**
	 * The fields filter can ignore a change, but cannot invent one.
	 *
	 * @covers ::store
	 *
	 * @return void
	 */
	public function test_fields_filter_cannot_add_a_change(): void {
		$post_id = $this->create_event();
		$filter  = static fn(): array => array( 'venue' );

		add_filter( 'gatherpress_event_change_fields', $filter );
		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		remove_filter( 'gatherpress_event_change_fields', $filter );

		$this->assertNull( Change::get_instance()->get( $post_id ) );
	}

	/**
	 * A venue change is stored with the venue names.
	 *
	 * @covers ::snapshot_before_terms
	 * @covers ::terms_from_tt_ids
	 *
	 * @return void
	 */
	public function test_venue_change_is_stored(): void {
		$post_id  = $this->create_event();
		$taxonomy = Venue_Setup::get_instance()->taxonomy_for_event_post_type( Event::POST_TYPE );
		$hall     = $this->create_venue_term( 'Main Hall' );
		$library  = $this->create_venue_term( 'Library' );

		wp_set_object_terms( $post_id, $hall, $taxonomy );
		$this->new_request();

		wp_set_object_terms( $post_id, $library, $taxonomy );
		Change::get_instance()->finish();

		$change = Change::get_instance()->get( $post_id );

		$this->assertSame( array( 'venue' ), $change->keys );
		$this->assertSame( array( '_main-hall' => 'Main Hall' ), $change->before->venue );
		$this->assertSame( array( '_library' => 'Library' ), $change->after->venue );
	}

	/**
	 * A write to another taxonomy, or to an RSVP comment, takes no snapshot.
	 *
	 * @covers ::snapshot_before_terms
	 *
	 * @return void
	 */
	public function test_other_taxonomy_write_takes_no_snapshot(): void {
		$post_id  = $this->create_event();
		$instance = Change::get_instance();

		wp_set_object_terms( $post_id, 'Books', Topic::TAXONOMY );
		$this->assertSame( array(), Utility::get_hidden_property( $instance, 'snapshots' ) );

		// An RSVP is a comment, so its ID can match an unrelated post's ID.
		$rsvp = ( new Rsvp( $post_id ) )->save( 'one@example.test', 'attending' );
		$this->assertNotNull( $rsvp );
		$this->assertSame( array(), Utility::get_hidden_property( $instance, 'snapshots' ) );
		$this->assertSame( array(), Utility::get_hidden_property( $instance, 'skipped' ) );

		// The status taxonomy on a post type that does not have it.
		$page = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$instance->snapshot_before_terms( $page, array(), array(), Event::TAXONOMY_STATUS, false, array() );
		$this->assertSame( array(), Utility::get_hidden_property( $instance, 'skipped' ) );
	}

	/**
	 * A post that stops being published in the same request stores nothing.
	 *
	 * @covers ::store
	 *
	 * @return void
	 */
	public function test_unpublished_in_the_same_request_stores_nothing(): void {
		$post_id = $this->create_event();

		$this->editor_save( $post_id, HOUR_IN_SECONDS, 'UTC', array( 'post_status' => 'draft' ) );
		Change::get_instance()->finish();

		$this->assertNull( Change::get_instance()->get( $post_id ) );
	}

	/**
	 * `compare()` does nothing for a post without a snapshot.
	 *
	 * @covers ::compare
	 *
	 * @return void
	 */
	public function test_compare_without_snapshot(): void {
		$post_id = $this->create_event();

		Change::get_instance()->compare( $post_id );

		$this->assertNull( Change::get_instance()->get( $post_id ) );
	}

	/**
	 * The description says what changed, to whom, and whether to show the notice.
	 *
	 * @covers ::describe
	 *
	 * @return void
	 */
	public function test_describe_a_date_change(): void {
		$post_id = $this->create_event();
		$rsvp    = new Rsvp( $post_id );

		$rsvp->save( 'one@example.test', 'attending' );
		$rsvp->save( 'two@example.test', 'not_attending' );

		$this->assertNull( Change::get_instance()->describe( $post_id ) );

		$this->editor_save( $post_id, HOUR_IN_SECONDS );

		$description = Change::get_instance()->describe( $post_id );

		$this->assertSame( array( 'datetime_start', 'datetime_end' ), $description['keys'] );
		$this->assertSame( 'Several event details changed.', $description['headline'] );
		$this->assertSame( 'Book Club: Several event details changed.', $description['subject'] );
		$this->assertStringStartsWith( 'Start: was ', $description['message'] );
		$this->assertStringContainsString( "\nEnd: was ", $description['message'] );
		$this->assertStringContainsString( ' UTC', $description['message'] );
		$this->assertSame( 1, $description['attendees'] );
		$this->assertTrue( $description['offer_not_attending'] );
		$this->assertTrue( $description['show_notice'] );
	}

	/**
	 * Without anyone attending or waiting, and when a site says so, the notice stays off.
	 *
	 * @covers ::describe
	 * @covers ::store
	 *
	 * @return void
	 */
	public function test_notice_stays_off(): void {
		$post_id = $this->create_event();

		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		$this->assertFalse(
			Change::get_instance()->describe( $post_id )['show_notice'],
			'Failed to assert no notice without attendees.'
		);

		( new Rsvp( $post_id ) )->save( 'one@example.test', 'waiting_list' );
		$this->new_request();
		add_filter( 'gatherpress_event_change_show_notice', '__return_false' );
		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		remove_filter( 'gatherpress_event_change_show_notice', '__return_false' );

		$this->assertSame( 1, Change::get_instance()->describe( $post_id )['attendees'] );
		$this->assertFalse(
			Change::get_instance()->describe( $post_id )['show_notice'],
			'Failed to assert the filter turns the notice off.'
		);
	}

	/**
	 * Old status terms resolve to slugs, and fall back to the default.
	 *
	 * @covers ::status_from_tt_ids
	 * @covers ::terms_from_tt_ids
	 *
	 * @return void
	 */
	public function test_status_from_tt_ids(): void {
		$post_id  = $this->create_event();
		$instance = Change::get_instance();
		$unknown  = wp_insert_term( 'Unknown', Event::TAXONOMY_STATUS, array( 'slug' => 'unknown' ) );

		Status::ensure_term( 'canceled' );
		Status::ensure_term( 'postponed' );

		$canceled  = get_term_by( 'slug', 'canceled', Event::TAXONOMY_STATUS );
		$postponed = get_term_by( 'slug', 'postponed', Event::TAXONOMY_STATUS );

		$this->assertSame(
			array( 'scheduled' ),
			Utility::invoke_hidden_method( $instance, 'status_from_tt_ids', array( $post_id, array() ) )
		);
		$this->assertSame(
			array( 'scheduled' ),
			Utility::invoke_hidden_method(
				$instance,
				'status_from_tt_ids',
				array( $post_id, array( (int) $unknown['term_taxonomy_id'] ) )
			)
		);
		$this->assertSame(
			array( 'canceled', 'postponed' ),
			Utility::invoke_hidden_method(
				$instance,
				'status_from_tt_ids',
				array( $post_id, array( $postponed->term_taxonomy_id, $canceled->term_taxonomy_id ) )
			)
		);
	}

	/**
	 * The REST field is registered on events and shown only to users who can edit the event.
	 *
	 * @covers ::register_rest_field
	 * @covers ::get_rest_value
	 *
	 * @return void
	 */
	public function test_rest_field(): void {
		global $wp_rest_additional_fields;

		$post_id = $this->create_event();

		register_post_type( 'gp_no_rsvp', array( 'supports' => array( Event::SUPPORT ) ) );
		register_post_type( 'gp_rsvp_only', array( 'supports' => array( Rsvp::SUPPORT ) ) );
		Change::get_instance()->register_rest_field();

		$this->assertArrayHasKey( Change::REST_FIELD, $wp_rest_additional_fields[ Event::POST_TYPE ] );
		$this->assertArrayNotHasKey( Change::REST_FIELD, $wp_rest_additional_fields['gp_no_rsvp'] ?? array() );
		$this->assertArrayNotHasKey( Change::REST_FIELD, $wp_rest_additional_fields['gp_rsvp_only'] ?? array() );

		unregister_post_type( 'gp_no_rsvp' );
		unregister_post_type( 'gp_rsvp_only' );

		$this->editor_save( $post_id, HOUR_IN_SECONDS );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertNull( Change::get_instance()->get_rest_value( array( 'id' => $post_id ) ) );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame(
			array( 'datetime_start', 'datetime_end' ),
			Change::get_instance()->get_rest_value( array( 'id' => $post_id ) )['keys']
		);

		// The change stays stored after the event starts, but is no longer offered.
		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
				'datetime_end'   => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
				'timezone'       => 'UTC',
			)
		);
		$this->new_request();

		$this->assertNotNull( Change::get_instance()->get( $post_id ) );
		$this->assertNull( Change::get_instance()->get_rest_value( array( 'id' => $post_id ) ) );
	}

	/**
	 * The subject and the venue names are plain text, without HTML entities.
	 *
	 * @covers ::describe
	 *
	 * @return void
	 */
	public function test_describe_decodes_entities(): void {
		$post_id  = $this->create_event();
		$taxonomy = Venue_Setup::get_instance()->taxonomy_for_event_post_type( Event::POST_TYPE );
		$grill    = $this->create_venue_term( 'Bar & Grill' );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => "Sam's & Co",
			)
		);
		$this->new_request();

		wp_set_object_terms( $post_id, $grill, $taxonomy );
		Change::get_instance()->finish();

		$description = Change::get_instance()->describe( $post_id );

		$this->assertSame( "Sam\u{2019}s & Co: The venue changed.", $description['subject'] );
		$this->assertSame( 'Venue: was no venue, now Bar & Grill.', $description['message'] );
	}

	/**
	 * Sending without a stored change fails closed.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_without_a_change_fails_closed(): void {
		$post_id = $this->create_event();
		$result  = Change::get_instance()->send( $post_id, false, 'Hello', 'Hi' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gatherpress_no_event_change', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertFalse( wp_next_scheduled( Mailer::JOB_HOOK, array( Email::CHANGE_TYPE ) ) );
	}

	/**
	 * Sending about an event that is no longer eligible fails closed, even with a stored change.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_after_the_event_is_unpublished_fails_closed(): void {
		$post_id = $this->create_event();

		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		$this->new_request();

		// A change stored earlier stays when the post leaves the published state
		// by a path that skips the compare, such as a direct database write.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );

		$this->assertNotNull( Change::get_instance()->get( $post_id ) );
		$this->assertInstanceOf( WP_Error::class, Change::get_instance()->send( $post_id, false, 'Hello', 'Hi' ) );
	}

	/**
	 * The message reaches attending and waiting list whatever their opt-in, and uses the change up.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_skips_the_opt_in_for_attendees_once(): void {
		$post_id = $this->create_event();

		// One spot, so the second person stays on the waiting list. Rsvp reads
		// the capacity when it is constructed, so set it first.
		update_post_meta( $post_id, 'gatherpress_capacity', 1 );

		$rsvp = new Rsvp( $post_id );

		$rsvp->save( 'yes@example.test', 'attending' );
		$rsvp->save( 'wait@example.test', 'attending' );
		$rsvp->save( 'no@example.test', 'not_attending' );

		$this->assertTrue( $rsvp->find( 'wait@example.test' )->is_waiting_list() );

		foreach ( array( 'yes@example.test', 'wait@example.test', 'no@example.test' ) as $email ) {
			$this->opt_out( $rsvp, $email );
		}

		$this->editor_save( $post_id, HOUR_IN_SECONDS );

		$this->assertTrue( Change::get_instance()->send( $post_id, false, 'The time moved.', 'Book Club moved' ) );
		$this->assertNull( Change::get_instance()->get( $post_id ), 'Failed to assert sending uses the change up.' );

		$this->run_mail_jobs();

		$recipients = array_column( $this->sent, 0 );
		sort( $recipients );

		$this->assertSame( array( 'wait@example.test', 'yes@example.test' ), $recipients );
		$this->assertSame( 'Book Club moved', $this->sent[0][1] );
		$this->assertStringContainsString( 'The time moved.', $this->sent[0][2] );
		$this->assertInstanceOf( WP_Error::class, Change::get_instance()->send( $post_id, false, 'Again', '' ) );
	}

	/**
	 * Not attending gets a date change only when asked, and only with the opt-in.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_to_not_attending_follows_the_opt_in(): void {
		$post_id = $this->create_event();
		$rsvp    = new Rsvp( $post_id );

		$rsvp->save( 'in@example.test', 'not_attending' );
		$rsvp->save( 'out@example.test', 'not_attending' );
		$this->opt_out( $rsvp, 'out@example.test' );

		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		$this->assertTrue( Change::get_instance()->send( $post_id, true, 'The time moved.', '' ) );
		$this->run_mail_jobs();

		$this->assertSame( array( 'in@example.test' ), array_column( $this->sent, 0 ) );
	}

	/**
	 * Not attending is not offered for a change that is not about the date.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_to_not_attending_only_for_a_date_change(): void {
		$post_id = $this->create_event();
		$groups  = array();

		( new Rsvp( $post_id ) )->save( 'in@example.test', 'not_attending' );

		add_filter(
			'gatherpress_event_change_recipients',
			static function ( array $send ) use ( &$groups ): array {
				$groups = $send;

				return $send;
			}
		);

		( new Status( $post_id ) )->set( 'canceled' );
		Change::get_instance()->finish();

		$this->assertTrue( Change::get_instance()->send( $post_id, true, 'Canceled.', '' ) );
		$this->run_mail_jobs();

		$this->assertFalse( $groups['not_attending'] );
		$this->assertSame( array(), $this->sent );
	}

	/**
	 * A failed queue keeps the change, so the organizer can try again.
	 *
	 * @covers ::send
	 *
	 * @return void
	 */
	public function test_send_keeps_the_change_when_queuing_fails(): void {
		$post_id = $this->create_event();
		$none    = static fn(): array => array(
			'attending'     => false,
			'waiting_list'  => false,
			'not_attending' => false,
		);
		$fail    = static fn(): bool => false;

		$this->editor_save( $post_id, HOUR_IN_SECONDS );

		add_filter( 'gatherpress_event_change_recipients', $none );
		$this->assertTrue( Change::get_instance()->send( $post_id, false, 'Nobody.', '' ) );
		remove_filter( 'gatherpress_event_change_recipients', $none );

		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		$this->new_request();

		// WP-Cron refusing the job is the one way queuing fails.
		add_filter( 'pre_schedule_event', $fail );
		$this->assertFalse( Change::get_instance()->send( $post_id, false, 'Hello', '' ) );
		remove_filter( 'pre_schedule_event', $fail );

		$this->assertNotNull( Change::get_instance()->get( $post_id ) );
	}

	/**
	 * The send filter sends the default message at the end of the request.
	 *
	 * @covers ::finish
	 * @covers ::maybe_send_automatically
	 *
	 * @return void
	 */
	public function test_send_automatically(): void {
		$post_id = $this->create_event();

		( new Rsvp( $post_id ) )->save( 'yes@example.test', 'attending' );

		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		Change::get_instance()->finish();
		$this->assertNotNull(
			Change::get_instance()->get( $post_id ),
			'Failed to assert automatic sending is off by default.'
		);

		add_filter( 'gatherpress_event_change_send', '__return_true' );
		$this->editor_save( $post_id, HOUR_IN_SECONDS );
		Change::get_instance()->finish();
		remove_filter( 'gatherpress_event_change_send', '__return_true' );

		$this->assertNull( Change::get_instance()->get( $post_id ) );

		$this->run_mail_jobs();

		$this->assertSame( array( 'yes@example.test' ), array_column( $this->sent, 0 ) );
		$this->assertSame( 'Book Club: Several event details changed.', $this->sent[0][1] );
	}

	/**
	 * Nothing is sent automatically when the change was used up during the request.
	 *
	 * @covers ::maybe_send_automatically
	 *
	 * @return void
	 */
	public function test_send_automatically_without_a_change(): void {
		$post_id = $this->create_event();

		add_filter( 'gatherpress_event_change_send', '__return_true' );
		Utility::invoke_hidden_method(
			Change::get_instance(),
			'maybe_send_automatically',
			array( $post_id, array( 'venue' ) )
		);
		remove_filter( 'gatherpress_event_change_send', '__return_true' );

		$this->assertFalse( (bool) wp_next_scheduled( Mailer::JOB_HOOK ) );
		$this->assertSame( array(), $this->sent );
	}
}
