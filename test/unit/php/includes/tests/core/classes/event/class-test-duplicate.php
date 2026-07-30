<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Duplicate.
 *
 * @package GatherPress\Core\Event
 * @since 0.35.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Duplicate;
use GatherPress\Core\Topic;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use WP_Post;

/**
 * Class Test_Duplicate.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Duplicate
 */
class Test_Duplicate extends Base {

	/**
	 * Build a published event with datetimes, a venue term, and a topic.
	 *
	 * @param string $datetime_start Local start datetime for the event.
	 * @param string $datetime_end   Local end datetime for the event.
	 *
	 * @return int The event post ID.
	 */
	private function make_event(
		string $datetime_start = '2020-06-15 18:00:00',
		string $datetime_end = '2020-06-15 20:00:00'
	): int {
		$event_id = $this->mock->post(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_title'   => 'Monthly Meetup',
				'post_name'    => 'monthly-meetup',
				'post_content' => '<!-- wp:paragraph --><p>Same as every month.</p><!-- /wp:paragraph -->',
				'post_excerpt' => 'Same as every month.',
				'post_status'  => 'publish',
			)
		)->get()->ID;

		$event = new Event( $event_id );
		$event->save_datetimes(
			array(
				'datetime_start' => $datetime_start,
				'datetime_end'   => $datetime_end,
				'timezone'       => 'America/New_York',
			)
		);

		update_post_meta( $event_id, 'gatherpress_max_attendance_limit', 25 );
		update_post_meta( $event_id, 'gatherpress_online_event_link', 'https://example.org/room' );

		$venue_id = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_title' => 'Brooklyn Office',
				'post_name'  => 'brooklyn-office',
			)
		)->get()->ID;

		wp_set_post_terms( $event_id, '_brooklyn-office', Venue::TAXONOMY );
		wp_set_post_terms( $event_id, array( 'social' ), Topic::TAXONOMY );

		return $event_id;
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Duplicate::get_instance();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'post_row_actions',
				'priority' => 10,
				'callback' => array( $instance, 'row_action' ),
			),
			array(
				'type'     => 'action',
				'name'     => sprintf( 'admin_action_%s', Duplicate::ACTION ),
				'priority' => 10,
				'callback' => array( $instance, 'handle_request' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for row_action on an event the current user can edit.
	 *
	 * @covers ::row_action
	 * @covers ::get_url
	 *
	 * @return void
	 */
	public function test_row_action_adds_duplicate_link_for_events(): void {
		$this->mock->user( 'admin' );

		$event_id = $this->make_event();
		$actions  = Duplicate::get_instance()->row_action( array(), get_post( $event_id ) );

		$this->assertArrayHasKey(
			'gatherpress_duplicate',
			$actions,
			'Events should offer a Duplicate row action.'
		);
		$this->assertStringContainsString(
			sprintf( 'action=%s', Duplicate::ACTION ),
			$actions['gatherpress_duplicate'],
			'The Duplicate link should carry the duplication action.'
		);
		$this->assertStringContainsString(
			'_wpnonce=',
			$actions['gatherpress_duplicate'],
			'The Duplicate link should be nonced.'
		);
	}

	/**
	 * Coverage for row_action on a post type that is not an event, and on an
	 * event the current user cannot edit.
	 *
	 * @covers ::row_action
	 *
	 * @return void
	 */
	public function test_row_action_skips_non_events_and_unprivileged_users(): void {
		$this->mock->user( 'admin' );

		$post_id = $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID;

		$this->assertSame(
			array(),
			Duplicate::get_instance()->row_action( array(), get_post( $post_id ) ),
			'A post type without event-date support should not offer duplication.'
		);

		$event_id = $this->make_event();

		$this->mock->user( 'subscriber' );

		$this->assertSame(
			array(),
			Duplicate::get_instance()->row_action( array(), get_post( $event_id ) ),
			'A user who cannot edit the event should not be offered duplication.'
		);
	}

	/**
	 * Coverage for duplicate: content, terms, meta, and featured image travel
	 * to a fresh draft while RSVPs, slug, and status do not.
	 *
	 * @covers ::duplicate
	 * @covers ::copy_terms
	 * @covers ::copy_meta
	 * @covers ::copy_datetimes
	 *
	 * @return void
	 */
	public function test_duplicate_copies_event_into_a_draft(): void {
		$this->mock->user( 'admin' );

		$event_id     = $this->make_event();
		$thumbnail_id = $this->mock->post( array( 'post_type' => 'attachment' ) )->get()->ID;

		// update_post_meta rather than set_post_thumbnail: the latter refuses
		// an attachment that renders no image markup, which a mocked one does not.
		update_post_meta( $event_id, '_thumbnail_id', $thumbnail_id );

		$duplicate_id = Duplicate::get_instance()->duplicate( $event_id );

		$this->assertIsInt( $duplicate_id, 'Duplicating an event should return the new post ID.' );

		$duplicate = get_post( $duplicate_id );

		$this->assertSame( 'draft', $duplicate->post_status, 'A duplicate should land as a draft.' );
		$this->assertSame( 'Monthly Meetup', $duplicate->post_title, 'The title should travel.' );
		$this->assertStringContainsString(
			'Same as every month.',
			$duplicate->post_content,
			'The content should travel.'
		);
		$this->assertSame( 'Same as every month.', $duplicate->post_excerpt, 'The excerpt should travel.' );
		$this->assertNotSame(
			get_post_field( 'post_name', $event_id ),
			$duplicate->post_name,
			'The duplicate should get its own slug.'
		);
		$this->assertSame(
			$thumbnail_id,
			get_post_thumbnail_id( $duplicate_id ),
			'The featured image should travel.'
		);
		$this->assertSame(
			'25',
			(string) get_post_meta( $duplicate_id, 'gatherpress_max_attendance_limit', true ),
			'Author-writable event meta should travel.'
		);
		$this->assertSame(
			'https://example.org/room',
			get_post_meta( $duplicate_id, 'gatherpress_online_event_link', true ),
			'The online event link should travel.'
		);
		$this->assertSame(
			wp_get_object_terms( $event_id, Venue::TAXONOMY, array( 'fields' => 'ids' ) ),
			wp_get_object_terms( $duplicate_id, Venue::TAXONOMY, array( 'fields' => 'ids' ) ),
			'The venue association should travel.'
		);
		$this->assertSame(
			wp_get_object_terms( $event_id, Topic::TAXONOMY, array( 'fields' => 'ids' ) ),
			wp_get_object_terms( $duplicate_id, Topic::TAXONOMY, array( 'fields' => 'ids' ) ),
			'Topics should travel.'
		);
	}

	/**
	 * Coverage for copy_datetimes: a past event moves forward by whole weeks,
	 * keeping weekday, time of day, timezone, and duration.
	 *
	 * @covers ::duplicate
	 * @covers ::copy_datetimes
	 *
	 * @return void
	 */
	public function test_duplicate_moves_datetimes_into_the_future(): void {
		$this->mock->user( 'admin' );

		$event_id     = $this->make_event( '2020-06-15 18:00:00', '2020-06-15 20:00:00' );
		$duplicate_id = Duplicate::get_instance()->duplicate( $event_id );
		$datetime     = ( new Event( $duplicate_id ) )->get_datetime();

		$start = strtotime( $datetime['datetime_start'] );
		$end   = strtotime( $datetime['datetime_end'] );

		$this->assertGreaterThan(
			time(),
			strtotime( $datetime['datetime_start_gmt'] . ' GMT' ),
			'The duplicate should start in the future.'
		);
		$this->assertSame(
			'Monday',
			gmdate( 'l', $start ),
			'The weekday of the source event should be preserved.'
		);
		$this->assertSame(
			'18:00:00',
			gmdate( 'H:i:s', $start ),
			'The time of day of the source event should be preserved.'
		);
		$this->assertSame(
			2 * HOUR_IN_SECONDS,
			$end - $start,
			'The duration of the source event should be preserved.'
		);
		$this->assertSame(
			'America/New_York',
			$datetime['timezone'],
			'The timezone of the source event should be preserved.'
		);
	}

	/**
	 * Coverage for copy_datetimes when the source event is in the future: the
	 * duplicate still moves one week out rather than colliding with it.
	 *
	 * @covers ::copy_datetimes
	 *
	 * @return void
	 */
	public function test_duplicate_of_future_event_moves_one_week_out(): void {
		$this->mock->user( 'admin' );

		$start        = gmdate( 'Y-m-d H:i:s', time() + ( 2 * WEEK_IN_SECONDS ) );
		$end          = gmdate( 'Y-m-d H:i:s', time() + ( 2 * WEEK_IN_SECONDS ) + HOUR_IN_SECONDS );
		$event_id     = $this->make_event( $start, $end );
		$duplicate_id = Duplicate::get_instance()->duplicate( $event_id );

		$this->assertSame(
			WEEK_IN_SECONDS,
			strtotime( ( new Event( $duplicate_id ) )->get_datetime()['datetime_start'] ) - strtotime( $start ),
			'A future event should be duplicated one week later.'
		);
	}

	/**
	 * Coverage for copy_datetimes when the source event has no datetimes: the
	 * duplicate is created and the datetime pass bails.
	 *
	 * @covers ::copy_datetimes
	 *
	 * @return void
	 */
	public function test_duplicate_without_datetimes_skips_the_datetime_pass(): void {
		$this->mock->user( 'admin' );

		$event_id     = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$duplicate_id = Duplicate::get_instance()->duplicate( $event_id );

		$this->assertIsInt( $duplicate_id, 'An event without datetimes should still duplicate.' );
		$this->assertEmpty(
			get_post_meta( $duplicate_id, 'gatherpress_datetime', true ),
			'No datetime meta should be written when the source has none.'
		);
	}

	/**
	 * Coverage for the duplicate guards: a missing post, a post type without
	 * event-date support, and a user without the capability.
	 *
	 * @covers ::duplicate
	 *
	 * @return void
	 */
	public function test_duplicate_refuses_invalid_requests(): void {
		$this->mock->user( 'admin' );

		$instance = Duplicate::get_instance();

		$this->assertSame(
			'gatherpress_duplicate_invalid_event',
			$instance->duplicate( 0 )->get_error_code(),
			'A post that does not exist should be refused.'
		);

		$post_id = $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID;

		$this->assertSame(
			'gatherpress_duplicate_invalid_event',
			$instance->duplicate( $post_id )->get_error_code(),
			'A post type without event-date support should be refused.'
		);

		$event_id = $this->make_event();

		$this->mock->user( 'subscriber' );

		$this->assertSame(
			'gatherpress_duplicate_not_allowed',
			$instance->duplicate( $event_id )->get_error_code(),
			'A user without the capability should be refused.'
		);
	}

	/**
	 * Coverage for the postarr filter, which lets consumers reshape the copy.
	 *
	 * @covers ::duplicate
	 *
	 * @return void
	 */
	public function test_duplicate_event_postarr_filter_applies(): void {
		$this->mock->user( 'admin' );

		$event_id = $this->make_event();

		add_filter(
			'gatherpress_duplicate_event_postarr',
			static function ( array $postarr, WP_Post $post ): array {
				$postarr['post_title'] = sprintf( '%s (copy)', $post->post_title );

				return $postarr;
			},
			10,
			2
		);

		$duplicate_id = Duplicate::get_instance()->duplicate( $event_id );

		remove_all_filters( 'gatherpress_duplicate_event_postarr' );

		$this->assertSame(
			'Monthly Meetup (copy)',
			get_post_field( 'post_title', $duplicate_id ),
			'The postarr filter should be able to rename the duplicate.'
		);
	}

	/**
	 * Coverage for the datetime filter, which lets consumers place the copy
	 * somewhere other than the default weekly shift.
	 *
	 * @covers ::copy_datetimes
	 *
	 * @return void
	 */
	public function test_duplicate_event_datetime_filter_applies(): void {
		$this->mock->user( 'admin' );

		$event_id = $this->make_event();

		add_filter(
			'gatherpress_duplicate_event_datetime',
			static function ( array $params ): array {
				$params['datetime_start'] = '2040-01-01 09:00:00';
				$params['datetime_end']   = '2040-01-01 10:00:00';

				return $params;
			}
		);

		$duplicate_id = Duplicate::get_instance()->duplicate( $event_id );

		remove_all_filters( 'gatherpress_duplicate_event_datetime' );

		$this->assertSame(
			'2040-01-01 09:00:00',
			( new Event( $duplicate_id ) )->get_datetime()['datetime_start'],
			'The datetime filter should be able to place the duplicate.'
		);
	}

	/**
	 * Coverage for the duplicated action, which fires with the new post ID.
	 *
	 * @covers ::duplicate
	 *
	 * @return void
	 */
	public function test_duplicate_fires_duplicated_action(): void {
		$this->mock->user( 'admin' );

		$event_id = $this->make_event();
		$fired    = array();

		add_action(
			'gatherpress_event_duplicated',
			static function ( int $duplicate_id, WP_Post $post ) use ( &$fired ): void {
				$fired = array( $duplicate_id, $post->ID );
			},
			10,
			2
		);

		$duplicate_id = Duplicate::get_instance()->duplicate( $event_id );

		remove_all_actions( 'gatherpress_event_duplicated' );

		$this->assertSame(
			array( $duplicate_id, $event_id ),
			$fired,
			'The duplicated action should report the new and source post IDs.'
		);
	}

	/**
	 * Coverage for handle_request: a valid nonced request duplicates the event
	 * and redirects to the new draft.
	 *
	 * @covers ::handle_request
	 *
	 * @return void
	 */
	public function test_handle_request_redirects_to_the_duplicate(): void {
		$this->mock->user( 'admin' );

		$event_id = $this->make_event();

		$_REQUEST['_wpnonce'] = wp_create_nonce( sprintf( '%s_%d', Duplicate::ACTION, $event_id ) );
		$redirects            = array();

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, int $type, string $var_name ) use ( $event_id ) {
				if ( INPUT_GET === $type && 'post' === $var_name ) {
					return (string) $event_id;
				}

				return $pre_value;
			},
			10,
			3
		);
		add_filter(
			'wp_redirect',
			static function ( string $location ) use ( &$redirects ) {
				$redirects[] = $location;

				return false;
			}
		);

		Duplicate::get_instance()->handle_request();

		remove_all_filters( 'gatherpress_pre_get_http_input' );
		remove_all_filters( 'wp_redirect' );
		unset( $_REQUEST['_wpnonce'] );

		$this->assertCount( 1, $redirects, 'A valid request should redirect once.' );
		$this->assertStringContainsString(
			'post.php?post=',
			$redirects[0],
			'The redirect should target the new draft in the editor.'
		);
		$this->assertStringContainsString(
			'action=edit',
			$redirects[0],
			'The redirect should open the new draft for editing.'
		);
	}
}
