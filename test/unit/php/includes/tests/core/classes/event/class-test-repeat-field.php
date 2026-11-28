<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Repeat_Field.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Repeat_Field;
use GatherPress\Core\Event\Rule;
use GatherPress\Core\Event\Rules;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use stdClass;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Test_Repeat_Field.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Repeat_Field
 */
class Test_Repeat_Field extends Base {

	/**
	 * Log in as an administrator, who can edit events, and add the field's check again.
	 *
	 * The REST server is built once and kept, while hooks are restored after
	 * each test, so `rest_api_init` wouldn't add the check again on its own.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );
		Repeat_Field::get_instance()->register();
	}

	/**
	 * A local date some days from today in New York.
	 *
	 * @since TBD
	 *
	 * @param int $days Days from today, negative for the past.
	 *
	 * @return string The date, as `Y-m-d`.
	 */
	protected function day( int $days ): string {
		$today = new DateTimeImmutable( 'today', new DateTimeZone( 'America/New_York' ) );

		return $today->modify( sprintf( '%+d days', $days ) )->format( 'Y-m-d' );
	}

	/**
	 * The two-letter weekday code of a date.
	 *
	 * @since TBD
	 *
	 * @param string $date The date, as `Y-m-d`.
	 *
	 * @return string The code, such as `MO`.
	 */
	protected function weekday( string $date ): string {
		return strtoupper( substr( gmdate( 'D', (int) strtotime( $date ) ), 0, 2 ) );
	}

	/**
	 * An event's schedule as the field describes it.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return array<string, mixed> The schedule.
	 */
	protected function schedule( int $post_id ): array {
		return Utility::invoke_hidden_method(
			Repeat_Field::get_instance(),
			'describe_schedule',
			array( new Event( $post_id ) )
		);
	}

	/**
	 * The editor's `gatherpress_datetime` meta for a date.
	 *
	 * @since TBD
	 *
	 * @param string $date  The local date.
	 * @param string $start The local start time.
	 * @param string $end   The local end time.
	 *
	 * @return string The JSON.
	 */
	protected function datetime_json( string $date, string $start = '19:00:00', string $end = '20:30:00' ): string {
		return (string) wp_json_encode(
			array(
				'dateTimeStart' => "{$date} {$start}",
				'dateTimeEnd'   => "{$date} {$end}",
				'timezone'      => 'America/New_York',
			)
		);
	}

	/**
	 * Create an event with saved dates.
	 *
	 * @since TBD
	 *
	 * @param string $status The post status.
	 * @param int    $days   Days from today of its date.
	 *
	 * @return int The post ID.
	 */
	protected function event( string $status = 'draft', int $days = 7 ): int {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => $status,
			)
		);

		update_post_meta( $post_id, 'gatherpress_datetime', $this->datetime_json( $this->day( $days ) ) );
		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => $this->day( $days ) . ' 19:00:00',
				'datetime_end'   => $this->day( $days ) . ' 20:30:00',
				'timezone'       => 'America/New_York',
			)
		);

		return $post_id;
	}

	/**
	 * A published weekly series that started some days ago.
	 *
	 * @since TBD
	 *
	 * @param int $days_ago Days since its first date.
	 *
	 * @return int The post ID.
	 */
	protected function started_series( int $days_ago = 14 ): int {
		$post_id = $this->event();

		( new Event( $post_id ) )->rules()->replace( array( $this->rule_row( $this->day( -$days_ago ) ) ) );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		return $post_id;
	}

	/**
	 * A weekly rule row.
	 *
	 * @since TBD
	 *
	 * @param string               $starts_on The first date.
	 * @param array<string, mixed> $changes   Fields to replace.
	 *
	 * @return array<string, mixed> The row.
	 */
	protected function rule_row( string $starts_on, array $changes = array() ): array {
		return array_merge(
			array(
				'starts_on' => $starts_on,
				'rrule'     => 'FREQ=WEEKLY',
				'times'     => array( '19:00' ),
				'timezone'  => 'America/New_York',
				'duration'  => 90,
			),
			$changes
		);
	}

	/**
	 * Save an event through REST.
	 *
	 * @since TBD
	 *
	 * @param int                  $post_id The event post ID.
	 * @param array<string, mixed> $body    The request body.
	 *
	 * @return WP_REST_Response The response.
	 */
	protected function save( int $post_id, array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', rest_get_route_for_post( $post_id ) );

		$request->set_body_params( $body );

		return rest_do_request( $request );
	}

	/**
	 * An event's rules as start, end, pattern and times.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return array<int, string> One line per rule.
	 */
	protected function rules( int $post_id ): array {
		$lines = array();

		foreach ( ( new Event( $post_id ) )->rules()->all() as $rule ) {
			$lines[] = sprintf(
				'%s %s %s %s',
				$rule->starts_on->format( 'Y-m-d' ),
				$rule->ends_on ? $rule->ends_on->format( 'Y-m-d' ) : 'open',
				$rule->rrule() ?? 'one date',
				implode( ',', $rule->times )
			);
		}

		return $lines;
	}

	/**
	 * The field is added to every event post type, with its check.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register(): void {
		global $wp_rest_additional_fields;

		$instance = Repeat_Field::get_instance();

		$this->assert_hooks(
			array(
				array(
					'type'     => 'action',
					'name'     => 'rest_api_init',
					'priority' => 10,
					'callback' => array( $instance, 'register' ),
				),
			),
			$instance
		);

		$instance->register();

		$this->assertArrayHasKey( Repeat_Field::FIELD, $wp_rest_additional_fields[ Event::POST_TYPE ] );
		$this->assertSame(
			10,
			has_filter( 'rest_pre_insert_' . Event::POST_TYPE, array( $instance, 'validate' ) )
		);
	}

	/**
	 * A draft gets a pattern from its own dates, and reads it back.
	 *
	 * @since TBD
	 *
	 * @covers ::update_value
	 * @covers ::get_value
	 * @covers ::plan
	 * @covers ::plan_replace
	 * @covers ::apply
	 * @covers ::row
	 * @covers ::valid_row
	 * @covers ::rrule
	 * @covers ::request_dates
	 * @covers ::has_started
	 * @covers ::describe_schedule
	 * @covers ::describe
	 *
	 * @return void
	 */
	public function test_draft_gets_a_pattern(): void {
		$post_id  = $this->event();
		$date     = $this->day( 10 );
		$response = $this->save(
			$post_id,
			array(
				'meta'              => array(
					'gatherpress_datetime' => $this->datetime_json( $date, '18:00:00', '19:00:00' ),
				),
				Repeat_Field::FIELD => array(
					'pattern' => array(
						'frequency' => 'weekly',
						'interval'  => 2,
						'weekdays'  => array( 'MO', 'TH' ),
						'ends_on'   => $this->day( 100 ),
					),
				),
			)
		);
		$schedule = $response->get_data()[ Repeat_Field::FIELD ];

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array( "{$date} " . $this->day( 100 ) . ' FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,TH 18:00' ),
			$this->rules( $post_id ),
			'The rule starts on the new start date, at its time.'
		);
		$this->assertSame( 60, $schedule['pattern']['duration'] );
		$this->assertSame( array( 'MO', 'TH' ), $schedule['pattern']['weekdays'] );
		$this->assertNull( $schedule['scheduled'] );
		$this->assertFalse( $schedule['ended'] );
		$this->assertTrue( $schedule['showable'] );
	}

	/**
	 * A draft can carry an all-day pattern, and stop repeating.
	 *
	 * @since TBD
	 *
	 * @covers ::plan_replace
	 * @covers ::request_dates
	 * @covers ::rrule
	 *
	 * @return void
	 */
	public function test_draft_all_day_and_stop(): void {
		$post_id = $this->event();
		$date    = $this->day( 10 );

		$this->save(
			$post_id,
			array(
				'meta'              => array(
					'gatherpress_datetime'   => $this->datetime_json( $date, '00:00:00', '23:59:59' ),
					'gatherpress_is_all_day' => true,
				),
				Repeat_Field::FIELD => array(
					'pattern' => array(
						'frequency'       => 'MONTHLY',
						'ordinal_weekday' => array( -1, 'fr' ),
					),
				),
			)
		);

		$rule = ( new Event( $post_id ) )->rules()->all()[0];

		$this->assertTrue( $rule->is_all_day );
		$this->assertSame( 1440, $rule->duration );
		$this->assertSame( 'FREQ=MONTHLY;BYDAY=-1FR', $rule->rrule() );

		$this->save( $post_id, array( Repeat_Field::FIELD => array( 'pattern' => null ) ) );

		$this->assertSame( array(), $this->rules( $post_id ), 'Without a pattern, a draft stops repeating.' );
	}

	/**
	 * Saving the same pattern again changes nothing.
	 *
	 * @since TBD
	 *
	 * @covers ::apply
	 * @covers ::rows_of
	 * @covers ::without_change_ids
	 *
	 * @return void
	 */
	public function test_unchanged_pattern_writes_nothing(): void {
		$post_id = $this->event();
		$body    = array(
			Repeat_Field::FIELD => array(
				'pattern' => array(
					'frequency' => 'MONTHLY',
					'month_day' => 11,
				),
			),
		);

		$this->save( $post_id, $body );
		$change_id = ( new Event( $post_id ) )->rules()->all()[0]->change_id;
		$this->save( $post_id, $body );

		$this->assertSame( $change_id, ( new Event( $post_id ) )->rules()->all()[0]->change_id );
	}

	/**
	 * A started series changes from tomorrow by default, with a new time, and shows the change as scheduled.
	 *
	 * @since TBD
	 *
	 * @covers ::plan_change
	 * @covers ::change_date
	 * @covers ::first_date
	 * @covers ::today
	 * @covers ::describe_schedule
	 * @covers ::group_of
	 *
	 * @return void
	 */
	public function test_started_series_changes_from_tomorrow(): void {
		$post_id  = $this->started_series();
		$response = $this->save(
			$post_id,
			array(
				Repeat_Field::FIELD => array(
					'pattern' => array(
						'frequency' => 'DAILY',
						'times'     => array( '20:00' ),
						'duration'  => 60,
					),
				),
			)
		);
		$schedule = $response->get_data()[ Repeat_Field::FIELD ];

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				sprintf(
					'%s %s FREQ=WEEKLY;BYDAY=%s 19:00',
					$this->day( -14 ),
					$this->day( 0 ),
					$this->weekday( $this->day( -14 ) )
				),
				$this->day( 1 ) . ' open FREQ=DAILY 20:00',
			),
			$this->rules( $post_id ),
			'The current rule ends today and the new one starts tomorrow.'
		);
		$this->assertSame( array( '19:00' ), $schedule['pattern']['times'], 'The rule that applies today.' );
		$this->assertSame( array( '20:00' ), $schedule['scheduled']['times'], 'The change to come.' );
	}

	/**
	 * Every other week from a change date starts on the pattern's first date after it.
	 *
	 * @since TBD
	 *
	 * @covers ::first_date
	 *
	 * @return void
	 */
	public function test_change_starts_on_the_first_matching_date(): void {
		$post_id = $this->started_series();
		$from    = new DateTimeImmutable( $this->day( 7 ), new DateTimeZone( 'UTC' ) );
		$weekday = strtoupper( substr( $from->modify( '+2 days' )->format( 'D' ), 0, 2 ) );

		$this->save(
			$post_id,
			array(
				Repeat_Field::FIELD => array(
					'from'    => $from->format( 'Y-m-d' ),
					'pattern' => array(
						'frequency' => 'WEEKLY',
						'interval'  => 2,
						'weekdays'  => array( $weekday ),
					),
				),
			)
		);

		$rules = ( new Event( $post_id ) )->rules()->all();

		$this->assertSame( $from->modify( '+2 days' )->format( 'Y-m-d' ), end( $rules )->starts_on->format( 'Y-m-d' ) );
	}

	/**
	 * A change that only moves the end date ends the current rule, and one that changes nothing writes nothing.
	 *
	 * @since TBD
	 *
	 * @covers ::apply
	 * @covers ::active_row
	 * @covers ::same_pattern
	 * @covers ::move_end
	 *
	 * @return void
	 */
	public function test_moving_the_end_date(): void {
		$post_id = $this->started_series();
		$pattern = array(
			'frequency' => 'WEEKLY',
			'weekdays'  => array( $this->weekday( $this->day( -14 ) ) ),
			'times'     => array( '19:00' ),
			'duration'  => 90,
		);

		$this->save( $post_id, array( Repeat_Field::FIELD => array( 'pattern' => $pattern ) ) );
		$this->assertCount( 1, $this->rules( $post_id ), 'The same pattern adds no rule.' );

		$ending = array(
			Repeat_Field::FIELD => array( 'pattern' => $pattern + array( 'ends_on' => $this->day( 30 ) ) ),
		);

		$this->save( $post_id, $ending );

		$this->assertSame( $this->day( 30 ), ( new Event( $post_id ) )->rules()->all()[0]->ends_on->format( 'Y-m-d' ) );
		$this->assertCount( 1, $this->rules( $post_id ), 'Only the end moved.' );

		$this->save( $post_id, $ending );
		$this->assertCount( 1, $this->rules( $post_id ), 'The same end changes nothing.' );
	}

	/**
	 * A started series stops repeating from a date, and then reads as ended once its dates are history.
	 *
	 * @since TBD
	 *
	 * @covers ::plan_change
	 * @covers ::describe_schedule
	 *
	 * @return void
	 */
	public function test_started_series_stops(): void {
		$post_id = $this->started_series();

		$this->save( $post_id, array( Repeat_Field::FIELD => array( 'pattern' => null ) ) );

		$rule = ( new Event( $post_id ) )->rules()->all()[0];

		$this->assertSame( $this->day( 0 ), $rule->ends_on->format( 'Y-m-d' ), 'It ends today.' );

		$past = $this->event();

		add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );
		$ended = $this->rule_row( $this->day( -30 ), array( 'ends_on' => $this->day( -2 ) ) );

		add_post_meta( $past, Rules::META_KEY, $ended );
		remove_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );

		$schedule = $this->schedule( $past );

		$this->assertTrue( $schedule['ended'] );
		$this->assertSame( $this->day( -30 ), $schedule['pattern']['starts_on'], 'The last rule, not none.' );
	}

	/**
	 * Schedules the editor can't show as one pattern are marked so.
	 *
	 * @since TBD
	 *
	 * @covers ::describe_schedule
	 * @covers ::is_showable
	 * @covers ::event_timezone
	 *
	 * @return void
	 */
	public function test_unshowable_schedules(): void {
		$today      = $this->day( 0 );
		$two_times  = array( $this->rule_row( $today, array( 'times' => array( '09:00', '19:00' ) ) ) );
		$other_zone = array( $this->rule_row( $today, array( 'timezone' => 'Europe/Paris' ) ) );
		$two_today  = array( $this->rule_row( $today ), $this->rule_row( $today ) );
		$two_coming = array( $this->rule_row( $this->day( 5 ) ), $this->rule_row( $this->day( 9 ) ) );
		$cases      = array(
			'No rules'          => array( array(), true ),
			'Two times'         => array( $two_times, false ),
			'Another timezone'  => array( $other_zone, false ),
			'Two apply today'   => array( $two_today, false ),
			'Two still to come' => array( $two_coming, false ),
		);

		foreach ( $cases as $label => list( $rows, $showable ) ) {
			$post_id = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );

			update_post_meta( $post_id, 'gatherpress_timezone', 'America/New_York' );
			add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );

			foreach ( $rows as $row ) {
				add_post_meta( $post_id, Rules::META_KEY, $row );
			}

			remove_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );

			$this->assertSame( $showable, $this->schedule( $post_id )['showable'], $label );
		}
	}


	/**
	 * A save the field can't write is refused before anything is saved.
	 *
	 * @since TBD
	 *
	 * @covers ::validate
	 * @covers ::plan
	 * @covers ::valid_row
	 * @covers ::error
	 *
	 * @return void
	 */
	public function test_refused_before_saving(): void {
		$post_id  = $this->event();
		$response = $this->save(
			$post_id,
			array(
				'status'            => 'publish',
				'title'             => 'Not saved',
				Repeat_Field::FIELD => array( 'pattern' => array( 'frequency' => 'HOURLY' ) ),
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'gatherpress_invalid_repeat', $response->get_data()['code'] );
		$this->assertSame( 'draft', get_post_status( $post_id ), 'Nothing was saved.' );
		$this->assertNotSame( 'Not saved', get_the_title( $post_id ) );

		$instance = Repeat_Field::get_instance();
		$post     = new stdClass();

		$post->ID = $post_id;

		$this->assertSame( $post, $instance->validate( $post, new WP_REST_Request() ), 'No field, no check.' );
	}

	/**
	 * Requests the field can't use are refused with a reason.
	 *
	 * @since TBD
	 *
	 * @covers ::plan
	 * @covers ::plan_change
	 * @covers ::change_date
	 * @covers ::first_date
	 * @covers ::no_dates
	 *
	 * @return void
	 */
	public function test_refusals(): void {
		$instance = Repeat_Field::get_instance();
		$started  = $this->started_series();
		$plan     = static function ( int $post_id, mixed $value ) use ( $instance ): mixed {
			$request = new WP_REST_Request();

			$request->set_param( Repeat_Field::FIELD, $value );

			return Utility::invoke_hidden_method( $instance, 'plan', array( $post_id, $request ) );
		};
		$code     = static function ( mixed $result ): string {
			return $result instanceof WP_Error ? $result->get_error_code() : 'none';
		};

		$this->assertSame( 'gatherpress_invalid_repeat', $code( $plan( $started, 'weekly' ) ), 'Not an object.' );
		$this->assertSame( 'gatherpress_invalid_repeat', $code( $plan( $started, array( 'pattern' => 'weekly' ) ) ) );
		$this->assertSame( 'gatherpress_invalid_repeat', $code( $plan( 0, array( 'pattern' => null ) ) ), 'No dates.' );
		$this->assertSame(
			'gatherpress_invalid_repeat',
			$code(
				$plan(
					$started,
					array(
						'from'    => $this->day( 0 ),
						'pattern' => null,
					)
				)
			),
			'From today.'
		);
		$this->assertSame(
			'gatherpress_invalid_repeat',
			$code(
				$plan(
					$started,
					array(
						'from'    => 'soon',
						'pattern' => null,
					)
				)
			)
		);
		$this->assertSame(
			'gatherpress_invalid_repeat',
			$code(
				$plan(
					$started,
					array(
						'pattern' => array(
							'frequency' => 'WEEKLY',
							'ends_on'   => $this->day( -1 ),
						),
					)
				)
			),
			'A pattern ending before the change date has no dates.'
		);
		$this->assertSame(
			'gatherpress_invalid_repeat',
			$code( $plan( $started, array( 'pattern' => array( 'frequency' => 'HOURLY' ) ) ) ),
			'A change to an invalid pattern.'
		);
	}

	/**
	 * A password-protected event's schedule is hidden, and a missing post has none.
	 *
	 * @since TBD
	 *
	 * @covers ::get_value
	 *
	 * @return void
	 */
	public function test_get_value_edges(): void {
		$instance  = Repeat_Field::get_instance();
		$protected = $this->factory()->post->create(
			array(
				'post_type'     => Event::POST_TYPE,
				'post_password' => 'secret',
			)
		);

		wp_set_current_user( 0 );

		$request = new WP_REST_Request();

		$this->assertNull( $instance->get_value( array( 'id' => $protected ), Repeat_Field::FIELD, $request ) );
		$this->assertNull( $instance->get_value( array( 'id' => PHP_INT_MAX ), Repeat_Field::FIELD, $request ) );
	}

	/**
	 * A started one-off event gets its first pattern from a date on, keeping its date.
	 *
	 * @since TBD
	 *
	 * @covers ::apply
	 * @covers ::active_row
	 *
	 * @return void
	 */
	public function test_started_one_off_gets_a_pattern(): void {
		$post_id = $this->event( 'publish', -3 );

		$this->save(
			$post_id,
			array(
				Repeat_Field::FIELD => array(
					'pattern' => array(
						'frequency' => 'WEEKLY',
						'weekdays'  => array( 'MO' ),
					),
				),
			)
		);

		$rules = $this->rules( $post_id );

		$this->assertCount( 2, $rules );
		$this->assertStringStartsWith( $this->day( -3 ) . ' open one date', $rules[0], 'The past date is kept.' );
	}

	/**
	 * The helpers' edge returns, invoked directly.
	 *
	 * @since TBD
	 *
	 * @covers ::group_of
	 * @covers ::plan_replace
	 * @covers ::rrule
	 * @covers ::request_dates
	 *
	 * @return void
	 */
	public function test_helper_edges(): void {
		$instance = Repeat_Field::get_instance();
		$today    = new DateTimeImmutable( $this->day( 0 ), new DateTimeZone( 'UTC' ) );
		$ended    = Rule::from_meta( $this->rule_row( $this->day( -30 ), array( 'ends_on' => $this->day( -1 ) ) ) );
		$dates    = array(
			'start'      => new DateTimeImmutable( $this->day( 5 ) . ' 19:00', new DateTimeZone( 'America/New_York' ) ),
			'timezone'   => 'America/New_York',
			'is_all_day' => false,
			'duration'   => 60,
		);

		$this->assertSame( 'ended', Utility::invoke_hidden_method( $instance, 'group_of', array( $ended, $today ) ) );
		$this->assertInstanceOf(
			WP_Error::class,
			Utility::invoke_hidden_method( $instance, 'plan_replace', array( array( 'frequency' => 'X' ), $dates ) )
		);
		$this->assertSame(
			'FREQ=MONTHLY;BYMONTHDAY=11',
			Utility::invoke_hidden_method(
				$instance,
				'rrule',
				array(
					array(
						'frequency' => 'monthly',
						'month_day' => 11,
					),
				)
			)
		);
		$no_dates = Utility::invoke_hidden_method( $instance, 'request_dates', array( 0, new WP_REST_Request() ) );

		$this->assertNull( $no_dates );
	}
}
