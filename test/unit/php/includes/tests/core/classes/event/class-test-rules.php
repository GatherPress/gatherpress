<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Rules.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use DateTimeZone;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Rule;
use GatherPress\Core\Event\Rules;
use GatherPress\Tests\Base;
use WP_Error;

/**
 * Class Test_Rules.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Rules
 */
class Test_Rules extends Base {

	/**
	 * An event has its valid rules, in saved order, and leaves invalid rows out.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::all
	 *
	 * @return void
	 */
	public function test_all(): void {
		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$row     = array(
			'starts_on' => '2026-12-11',
			'rrule'     => 'FREQ=MONTHLY;BYDAY=2FR',
			'times'     => array( '20:00' ),
			'timezone'  => 'America/New_York',
			'duration'  => 120,
		);

		$this->assertSame( array(), ( new Event( $post_id ) )->rules()->all(), 'An event without rules has none.' );

		// Raw rows, including one that is not a valid rule.
		add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );
		add_post_meta( $post_id, Rules::META_KEY, $row );
		add_post_meta( $post_id, Rules::META_KEY, array_merge( $row, array( 'rrule' => 'FREQ=HOURLY' ) ) );
		add_post_meta( $post_id, Rules::META_KEY, array_merge( $row, array( 'starts_on' => '2027-06-04' ) ) );

		$rules = ( new Event( $post_id ) )->rules();
		$all   = $rules->all();

		$this->assertCount( 2, $all, 'The invalid row is left out.' );
		$this->assertContainsOnlyInstancesOf( Rule::class, $all );
		$this->assertSame( '2026-12-11', $all[0]->starts_on->format( 'Y-m-d' ) );
		$this->assertSame( '2027-06-04', $all[1]->starts_on->format( 'Y-m-d' ) );

		delete_post_meta( $post_id, Rules::META_KEY );

		$this->assertCount( 2, $rules->all(), 'The rules are read once per instance.' );

		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get()->ID;

		$this->assertSame( array(), ( new Event( $post ) )->rules()->all(), 'A post that is not an event has none.' );
	}

	/**
	 * The frequency and weekday lists a form can offer.
	 *
	 * @since TBD
	 *
	 * @coversNothing
	 *
	 * @return void
	 */
	public function test_lists(): void {
		$this->assertSame( array( 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY' ), Rules::FREQUENCIES );
		$this->assertSame( array( 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' ), Rules::WEEKDAYS );
		$this->assertSame( 'gatherpress_rule', Rules::META_KEY );
	}

	/**
	 * Create an event.
	 *
	 * @since TBD
	 *
	 * @param string $status The post status.
	 *
	 * @return int The post ID.
	 */
	protected function event( string $status = 'draft' ): int {
		return $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => $status,
			)
		);
	}

	/**
	 * A rule row.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $changes Fields to replace or add.
	 *
	 * @return array<string, mixed> The row.
	 */
	protected function row( array $changes = array() ): array {
		return array_merge(
			array(
				'starts_on' => '2026-12-11',
				'rrule'     => 'FREQ=MONTHLY;BYDAY=2FR',
				'times'     => array( '20:00' ),
				'timezone'  => 'America/New_York',
				'duration'  => 120,
			),
			$changes
		);
	}

	/**
	 * Save an event's dates.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $start   Local start.
	 * @param string $end     Local end.
	 *
	 * @return void
	 */
	protected function save_dates( int $post_id, string $start, string $end ): void {
		( new Event( $post_id ) )->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => 'America/New_York',
			)
		);
	}

	/**
	 * An event's saved local start and end, and whether it is all day.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return string[] The start, end, timezone and all-day flag.
	 */
	protected function saved( int $post_id ): array {
		return array(
			get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			get_post_meta( $post_id, 'gatherpress_datetime_end', true ),
			get_post_meta( $post_id, 'gatherpress_timezone', true ),
			get_post_meta( $post_id, 'gatherpress_is_all_day', true ) ? 'all day' : 'timed',
		);
	}

	/**
	 * The rows stored for an event, without their change IDs.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return array<int, array<string, mixed>> The rows.
	 */
	protected function stored( int $post_id ): array {
		return array_map(
			static fn( array $row ): array => array_diff_key( $row, array( 'change_id' => '' ) ),
			get_post_meta( $post_id, Rules::META_KEY, false )
		);
	}

	/**
	 * Replacing rules stores them in canonical form under one change ID, and moves the saved dates to the first date.
	 *
	 * @since TBD
	 *
	 * @covers ::replace
	 * @covers ::prepare
	 * @covers ::write
	 * @covers ::sync_saved_dates
	 * @covers ::is_published
	 *
	 * @return void
	 */
	public function test_replace(): void {
		$post_id = $this->event();
		$rules   = ( new Event( $post_id ) )->rules();
		$result  = $rules->replace(
			array(
				$this->row(
					array(
						'starts_on' => '2027-01-04',
						'rrule'     => 'FREQ=WEEKLY',
					)
				),
				$this->row(),
			)
		);

		$this->assertTrue( $result );
		$this->assertSame( 'FREQ=WEEKLY;BYDAY=MO', $rules->all()[0]->rrule(), 'Rows are stored in canonical form.' );
		$this->assertSame( $rules->all()[0]->change_id, $rules->all()[1]->change_id, 'One change ID for the change.' );
		$this->assertTrue( wp_is_uuid( $rules->all()[0]->change_id ) );
		$this->assertSame(
			array( '2026-12-11 20:00:00', '2026-12-11 22:00:00', 'America/New_York', 'timed' ),
			$this->saved( $post_id ),
			'The saved dates move to the earliest first date, whatever the order.'
		);

		$this->assertTrue(
			$rules->replace(
				array(
					$this->row(
						array(
							'starts_on'  => '2026-12-12',
							'is_all_day' => true,
							'times'      => array(),
							'duration'   => 1440,
						)
					),
				)
			)
		);
		$this->assertSame(
			array( '2026-12-12 00:00:00', '2026-12-12 23:59:59', 'America/New_York', 'all day' ),
			$this->saved( $post_id ),
			'An all-day first date makes the event all day.'
		);

		$this->assertTrue( $rules->replace( array() ) );
		$this->assertSame( array(), $rules->all(), 'An empty list removes every rule.' );
		$this->assertSame( '2026-12-12 00:00:00', $this->saved( $post_id )[0], 'Without rules, the saved dates stay.' );
	}

	/**
	 * Replacing is refused for invalid rows, for a post that isn't an event, and on a published event that has started.
	 *
	 * @since TBD
	 *
	 * @covers ::replace
	 * @covers ::prepare
	 * @covers ::is_published
	 *
	 * @return void
	 */
	public function test_replace_refusals(): void {
		$post_id = $this->event();
		$rules   = ( new Event( $post_id ) )->rules();
		$result  = $rules->replace( array( $this->row(), $this->row( array( 'rrule' => 'FREQ=HOURLY' ) ) ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gatherpress_invalid_rule', $result->get_error_code() );
		$this->assertSame( 'Rule 2 is not a valid repeat rule.', $result->get_error_message() );
		$this->assertSame( array( 'index' => 1 ), $result->get_error_data() );
		$this->assertSame( 'gatherpress_invalid_rule', $rules->replace( array( 'FREQ=DAILY' ) )->get_error_code() );
		$this->assertSame( array(), $this->stored( $post_id ), 'Nothing is written.' );

		$post = $this->factory()->post->create();

		$this->assertSame(
			'gatherpress_rules_not_an_event',
			( new Event( $post ) )->rules()->replace( array() )->get_error_code()
		);

		$published = $this->event( 'publish' );

		$this->save_dates( $published, '2020-01-10 20:00:00', '2020-01-10 22:00:00' );
		$this->assertSame(
			'gatherpress_rules_past_dates',
			( new Event( $published ) )->rules()->replace( array( $this->row() ) )->get_error_code()
		);

		$upcoming = $this->event( 'publish' );

		$this->save_dates( $upcoming, '2099-01-09 20:00:00', '2099-01-09 22:00:00' );
		$this->assertTrue(
			( new Event( $upcoming ) )->rules()->replace( array( $this->row() ) ),
			'A published event that has not started can still be replaced.'
		);
	}

	/**
	 * A change from a date ends the current rules the day before and keeps their past.
	 *
	 * @since TBD
	 *
	 * @covers ::change_from
	 * @covers ::stop
	 * @covers ::end_before
	 * @covers ::is_after_today
	 * @covers ::parse_date
	 *
	 * @return void
	 */
	public function test_change_from(): void {
		$post_id = $this->event();
		$rules   = ( new Event( $post_id ) )->rules();

		$rules->replace(
			array(
				$this->row( array( 'starts_on' => '2020-01-10' ) ),
				$this->row(
					array(
						'starts_on' => '2020-01-01',
						'ends_on'   => '2020-06-30',
						'rrule'     => 'FREQ=DAILY',
					)
				),
				$this->row(
					array(
						'starts_on' => '2099-02-05',
						'rrule'     => 'FREQ=YEARLY',
					)
				),
			)
		);
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$first  = $rules->all()[0]->change_id;
		$result = $rules->change_from(
			'2099-01-01',
			array(
				$this->row(
					array(
						'starts_on' => '2099-01-05',
						'rrule'     => 'FREQ=WEEKLY',
					)
				),
			)
		);

		$this->assertTrue( $result );
		$this->assertSame(
			array(
				array( '2020-01-10', '2098-12-31', 'FREQ=MONTHLY;BYDAY=2FR' ),
				array( '2020-01-01', '2020-06-30', 'FREQ=DAILY' ),
				array( '2099-01-05', null, 'FREQ=WEEKLY;BYDAY=MO' ),
			),
			array_map(
				static fn( array $row ): array => array( $row['starts_on'], $row['ends_on'], $row['rrule'] ),
				$this->stored( $post_id )
			),
			'Running rules end the day before, ended ones stay, and one starting after the date is removed.'
		);
		$this->assertNotSame( $first, $rules->all()[2]->change_id, 'The new rule has a new change ID.' );
		$this->assertSame( $rules->all()[2]->change_id, $rules->all()[0]->change_id, 'An ended rule shares the ID.' );
		$this->assertSame( $first, $rules->all()[1]->change_id, 'A rule that had already ended keeps its ID.' );
		$this->assertSame( '2020-01-01 20:00:00', $this->saved( $post_id )[0], 'The first date is unchanged.' );

		$this->assertTrue( $rules->stop( '2099-03-01' ) );
		$this->assertSame( '2099-02-28', $rules->all()[2]->ends_on->format( 'Y-m-d' ), 'Stopping ends every rule.' );
	}

	/**
	 * A change is refused for an invalid date, a rule before it, or a published event changing from today.
	 *
	 * @since TBD
	 *
	 * @covers ::change_from
	 * @covers ::stop
	 * @covers ::prepare
	 * @covers ::is_after_today
	 * @covers ::parse_date
	 *
	 * @return void
	 */
	public function test_change_from_refusals(): void {
		$post_id = $this->event( 'publish' );
		$rules   = ( new Event( $post_id ) )->rules();
		$today   = wp_date( 'Y-m-d', null, new DateTimeZone( 'America/New_York' ) );

		$this->save_dates( $post_id, '2020-01-10 20:00:00', '2020-01-10 22:00:00' );

		$this->assertSame(
			'gatherpress_rules_invalid_date',
			$rules->change_from( '2099-02-30', array() )->get_error_code()
		);
		$this->assertSame(
			'gatherpress_rules_invalid_date',
			$rules->change_from( 'tomorrow', array() )->get_error_code()
		);

		$before = $rules->change_from( '2099-01-10', array( $this->row( array( 'starts_on' => '2099-01-09' ) ) ) );

		$this->assertSame( 'gatherpress_invalid_rule', $before->get_error_code() );
		$this->assertSame( 'Rule 1 starts before the date the change takes effect.', $before->get_error_message() );
		$this->assertSame( 'gatherpress_rules_past_change', $rules->stop( $today )->get_error_code() );
		$this->assertSame( array(), $this->stored( $post_id ), 'Nothing is written.' );

		$draft = $this->event();

		$this->assertTrue( ( new Event( $draft ) )->rules()->stop( $today ), 'A draft can change from any date.' );
	}

	/**
	 * An event without rules keeps its saved date as a rule of one date when its schedule changes.
	 *
	 * @since TBD
	 *
	 * @covers ::change_from
	 * @covers ::stop
	 * @covers ::end_before
	 * @covers ::seed
	 *
	 * @return void
	 */
	public function test_change_from_keeps_the_saved_date(): void {
		$timed = $this->event( 'publish' );

		$this->save_dates( $timed, '2020-03-05 18:00:00', '2020-03-05 20:30:00' );
		$new = array( $this->row( array( 'starts_on' => '2099-01-01' ) ) );

		( new Event( $timed ) )->rules()->change_from( '2099-01-01', $new );

		$this->assertSame(
			array(
				'starts_on'  => '2020-03-05',
				'ends_on'    => null,
				'rrule'      => null,
				'is_all_day' => false,
				'times'      => array( '18:00' ),
				'timezone'   => 'America/New_York',
				'duration'   => 150,
			),
			$this->stored( $timed )[0]
		);
		$this->assertSame( '2020-03-05 18:00:00', $this->saved( $timed )[0] );
		$this->assertSame(
			get_post_meta( $timed, Rules::META_KEY, false )[1]['change_id'],
			get_post_meta( $timed, Rules::META_KEY, false )[0]['change_id'],
			'The kept date takes the change ID.'
		);

		$all_day = $this->event( 'publish' );

		update_post_meta( $all_day, 'gatherpress_is_all_day', true );
		$this->save_dates( $all_day, '2020-03-05 00:00:00', '2020-03-06 23:59:59' );
		( new Event( $all_day ) )->rules()->stop( '2099-01-01' );

		$row = $this->stored( $all_day )[0];

		$this->assertSame( array( true, array(), 2880 ), array( $row['is_all_day'], $row['times'], $row['duration'] ) );

		$long = $this->event( 'publish' );

		$this->save_dates( $long, '2020-01-01 10:00:00', '2022-01-01 10:00:00' );

		$this->assertSame(
			'gatherpress_rules_saved_date',
			( new Event( $long ) )->rules()->stop( '2099-01-01' )->get_error_code(),
			'A saved date too long for a rule would be lost, so the change is refused.'
		);
		$this->assertSame( array(), $this->stored( $long ) );

		$undated = $this->event();

		( new Event( $undated ) )->rules()->change_from( '2099-01-01', $new );

		$this->assertCount( 1, $this->stored( $undated ), 'An event without a saved date has nothing to keep.' );
	}

	/**
	 * A write keeps rows that aren't valid rules, which a later version may have written.
	 *
	 * @since TBD
	 *
	 * @covers ::write
	 *
	 * @return void
	 */
	public function test_write_keeps_unknown_rows(): void {
		$post_id = $this->event();

		add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );
		add_post_meta( $post_id, Rules::META_KEY, $this->row( array( 'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=-1' ) ) );
		remove_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );

		( new Event( $post_id ) )->rules()->replace( array( $this->row() ) );

		$rows = get_post_meta( $post_id, Rules::META_KEY, false );

		$this->assertCount( 2, $rows );
		$this->assertSame( 'FREQ=MONTHLY;BYMONTHDAY=-1', $rows[0]['rrule'] );
	}

	/**
	 * Direct writes of the rule meta are refused, except during a rule write, on revisions, deleting
	 * the key everywhere, or when the filter allows it.
	 *
	 * @since TBD
	 *
	 * @covers ::guard_write
	 * @covers ::guard_delete
	 * @covers ::may_write
	 *
	 * @return void
	 */
	public function test_guards(): void {
		$post_id = $this->event();

		$this->assertFalse( add_post_meta( $post_id, Rules::META_KEY, $this->row() ) );
		$this->assertSame( array(), $this->stored( $post_id ) );
		$this->assertIsInt( add_post_meta( $post_id, 'gatherpress_other', 'x' ), 'Other keys are not affected.' );

		( new Event( $post_id ) )->rules()->replace( array( $this->row() ) );

		$changed = $this->row( array( 'starts_on' => '2027-01-08' ) );

		$this->assertFalse( update_post_meta( $post_id, Rules::META_KEY, $changed ) );
		$this->assertFalse( delete_post_meta( $post_id, Rules::META_KEY ) );
		$this->assertCount( 1, $this->stored( $post_id ), 'The rule is unchanged.' );

		$revision = $this->factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);

		$this->assertIsInt(
			add_metadata( 'post', $revision, Rules::META_KEY, $this->row() ),
			'Revisions can copy rules.'
		);

		add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );
		$this->assertIsInt(
			add_post_meta( $post_id, Rules::META_KEY, $this->row() ),
			'The filter lets a write through.'
		);
		remove_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );

		$this->assertTrue( delete_post_meta_by_key( Rules::META_KEY ), 'Deleting the key everywhere is allowed.' );
		$this->assertSame( array(), $this->stored( $post_id ) );
	}

	/**
	 * The synced dates survive the next save of the event, which rewrites them from the editor's JSON.
	 *
	 * @since TBD
	 *
	 * @covers ::sync_saved_dates
	 *
	 * @return void
	 */
	public function test_sync_survives_a_save(): void {
		$post_id = $this->event();

		update_post_meta(
			$post_id,
			'gatherpress_datetime',
			'{"dateTimeStart":"2026-01-01 09:00:00","dateTimeEnd":"2026-01-01 10:00:00","timezone":"UTC"}'
		);
		( new Event( $post_id ) )->rules()->replace( array( $this->row() ) );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Saved again',
			)
		);

		$this->assertSame(
			array( '2026-12-11 20:00:00', '2026-12-11 22:00:00', 'America/New_York', 'timed' ),
			$this->saved( $post_id )
		);
		$this->assertSame(
			array(
				'dateTimeStart' => '2026-12-11 20:00:00',
				'dateTimeEnd'   => '2026-12-11 22:00:00',
				'timezone'      => 'America/New_York',
			),
			json_decode( get_post_meta( $post_id, 'gatherpress_datetime', true ), true )
		);
	}

	/**
	 * A timezone saved in an old spelling, before timezones were normalized, still works.
	 *
	 * @since TBD
	 *
	 * @covers ::seed
	 * @covers ::is_after_today
	 *
	 * @return void
	 */
	public function test_legacy_timezone(): void {
		$post_id = $this->event( 'publish' );

		$this->save_dates( $post_id, '2020-03-05 18:00:00', '2020-03-05 20:00:00' );
		update_post_meta( $post_id, 'gatherpress_timezone', 'UTC+10' );

		$this->assertTrue( ( new Event( $post_id ) )->rules()->stop( '2099-01-01' ) );
		$this->assertSame( '+10:00', $this->stored( $post_id )[0]['timezone'] );
	}

	/**
	 * Today counts in the timezone furthest ahead, so a rule there can't change the day it is already on.
	 *
	 * @since TBD
	 *
	 * @covers ::is_after_today
	 *
	 * @return void
	 */
	public function test_today_in_the_timezone_furthest_ahead(): void {
		$post_id = $this->event();
		$rules   = ( new Event( $post_id ) )->rules();

		$rules->replace(
			array(
				$this->row(
					array(
						'starts_on' => '2026-01-01',
						'rrule'     => 'FREQ=DAILY',
						'timezone'  => 'Pacific/Kiritimati',
					)
				),
			)
		);
		update_post_meta( $post_id, 'gatherpress_timezone', 'Pacific/Pago_Pago' );
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$kiritimati = new DateTimeZone( 'Pacific/Kiritimati' );
		$today      = wp_date( 'Y-m-d', null, $kiritimati );
		$tomorrow   = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS, $kiritimati );

		$this->assertSame( 'gatherpress_rules_past_change', $rules->stop( $today )->get_error_code() );
		$this->assertTrue( $rules->stop( $tomorrow ) );
	}

	/**
	 * A private event counts as published.
	 *
	 * @since TBD
	 *
	 * @covers ::replace
	 * @covers ::is_published
	 *
	 * @return void
	 */
	public function test_private_event_is_published(): void {
		$post_id = $this->event( 'private' );

		$this->save_dates( $post_id, '2020-01-10 20:00:00', '2020-01-10 22:00:00' );

		$this->assertSame(
			'gatherpress_rules_past_dates',
			( new Event( $post_id ) )->rules()->replace( array( $this->row() ) )->get_error_code()
		);
	}

	/**
	 * A rule write lets through only the post being written.
	 *
	 * @since TBD
	 *
	 * @covers ::may_write
	 * @covers ::write
	 *
	 * @return void
	 */
	public function test_write_lets_through_only_its_post(): void {
		$post_id = $this->event();
		$other   = $this->event();
		$results = array();
		$listen  = static function ( $meta_id, $object_id, $meta_key ) use ( $other, &$results ): void {
			if ( Rules::META_KEY === $meta_key && $other !== $object_id ) {
				$results[] = add_post_meta( $other, Rules::META_KEY, array( 'starts_on' => '2026-12-11' ) );
			}
		};

		add_action( 'added_post_meta', $listen, 10, 3 );
		( new Event( $post_id ) )->rules()->replace( array( $this->row() ) );
		remove_action( 'added_post_meta', $listen );

		$this->assertSame( array( false ), $results, 'A write to another post during the rule write is refused.' );
		$this->assertSame( array(), $this->stored( $other ) );
	}

	/**
	 * A change reads the persisted rules, not ones another `Rules` object has since rewritten.
	 *
	 * @since TBD
	 *
	 * @covers ::all
	 * @covers ::change_from
	 * @covers ::end_before
	 *
	 * @return void
	 */
	public function test_change_from_reads_persisted_rules(): void {
		$post_id = $this->event();
		$stale   = ( new Event( $post_id ) )->rules();

		$this->assertSame( array(), $stale->all() );
		( new Event( $post_id ) )->rules()->replace( array( $this->row() ) );
		$this->assertTrue( $stale->stop( '2099-01-01' ) );

		$rows = $this->stored( $post_id );

		$this->assertCount( 1, $rows );
		$this->assertSame(
			array( 'FREQ=MONTHLY;BYDAY=2FR', '2098-12-31' ),
			array( $rows[0]['rrule'], $rows[0]['ends_on'] ),
			'The rule the other object wrote is ended, not lost.'
		);
	}

	/**
	 * A timed saved date with seconds can't be kept as a rule, so the change is refused.
	 *
	 * @since TBD
	 *
	 * @covers ::change_from
	 * @covers ::end_before
	 * @covers ::seed
	 *
	 * @return void
	 */
	public function test_seed_refuses_seconds(): void {
		$post_id = $this->event( 'publish' );

		$this->save_dates( $post_id, '2020-03-05 18:00:30', '2020-03-05 20:30:45' );

		$this->assertSame(
			'gatherpress_rules_saved_date',
			( new Event( $post_id ) )->rules()->stop( '2099-01-01' )->get_error_code()
		);
		$this->assertSame( array(), $this->stored( $post_id ) );
		$this->assertSame(
			array( '2020-03-05 18:00:30', '2020-03-05 20:30:45' ),
			array_slice( $this->saved( $post_id ), 0, 2 ),
			'The saved date is unchanged.'
		);
	}

	/**
	 * A write keeps backslashes in the rows it keeps.
	 *
	 * @since TBD
	 *
	 * @covers ::write
	 *
	 * @return void
	 */
	public function test_write_keeps_backslashes(): void {
		$post_id = $this->event();
		$unknown = $this->row(
			array(
				'rrule' => 'FREQ=MONTHLY;BYMONTHDAY=-1',
				'note'  => 'C:\plans',
			)
		);

		add_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );
		add_post_meta( $post_id, Rules::META_KEY, wp_slash( $unknown ) );
		remove_filter( 'gatherpress_rule_meta_write_allowed', '__return_true' );

		( new Event( $post_id ) )->rules()->replace( array( $this->row() ) );

		$this->assertSame( $unknown, get_post_meta( $post_id, Rules::META_KEY, false )[0] );
	}

	/**
	 * Today also counts in a new rule's timezone, so a new rule can't start on a day already under way there.
	 *
	 * @since TBD
	 *
	 * @covers ::change_from
	 * @covers ::is_after_today
	 *
	 * @return void
	 */
	public function test_today_in_a_new_rules_timezone(): void {
		$post_id = $this->event( 'publish' );

		$this->save_dates( $post_id, '2020-03-05 18:00:00', '2020-03-05 20:00:00' );
		update_post_meta( $post_id, 'gatherpress_timezone', 'Pacific/Pago_Pago' );

		$rules      = ( new Event( $post_id ) )->rules();
		$kiritimati = new DateTimeZone( 'Pacific/Kiritimati' );
		$today      = wp_date( 'Y-m-d', null, $kiritimati );
		$tomorrow   = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS, $kiritimati );
		$new        = static fn( string $date ): array => array(
			'starts_on' => $date,
			'rrule'     => 'FREQ=DAILY',
			'times'     => array( '00:00' ),
			'timezone'  => 'Pacific/Kiritimati',
			'duration'  => 60,
		);

		$this->assertSame(
			'gatherpress_rules_past_change',
			$rules->change_from( $today, array( $new( $today ) ) )->get_error_code()
		);
		$this->assertTrue( $rules->change_from( $tomorrow, array( $new( $tomorrow ) ) ) );
	}

	/**
	 * A row that fails to save puts the previous rows back and reports the failure.
	 *
	 * @since TBD
	 *
	 * @covers ::write
	 * @covers ::clear_rows
	 * @covers ::add_rows
	 *
	 * @return void
	 */
	public function test_write_restores_on_failure(): void {
		$post_id = $this->event();
		$rules   = ( new Event( $post_id ) )->rules();

		$rules->replace( array( $this->row() ) );

		$before = $this->stored( $post_id );
		$calls  = 0;
		$fail   = static function ( $check, $object_id, $meta_key ) use ( &$calls ) {
			// The second row of the new write fails; the restore after it succeeds.
			return Rules::META_KEY === $meta_key && 2 === ++$calls ? false : $check;
		};

		// Priority 11: after the rule guard at 10, which lets the write through.
		add_filter( 'add_post_metadata', $fail, 11, 3 );
		$result = $rules->replace( array( $this->row( array( 'starts_on' => '2027-01-08' ) ), $this->row() ) );
		remove_filter( 'add_post_metadata', $fail, 11 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'gatherpress_rules_not_saved', $result->get_error_code() );
		$this->assertSame( $before, $this->stored( $post_id ), 'The schedule is as it was.' );
	}

	/**
	 * A failed delete changes nothing, and a failed restore says the schedule may be incomplete.
	 *
	 * @since TBD
	 *
	 * @covers ::write
	 * @covers ::clear_rows
	 * @covers ::add_rows
	 *
	 * @return void
	 */
	public function test_write_reports_delete_and_restore_failures(): void {
		$post_id = $this->event();
		$rules   = ( new Event( $post_id ) )->rules();

		$rules->replace( array( $this->row() ) );

		$before    = $this->stored( $post_id );
		$no_delete = static fn( $check, $object_id, $meta_key ) => Rules::META_KEY === $meta_key ? false : $check;

		// Priority 11: after the rule guard at 10, which lets the write through.
		add_filter( 'delete_post_metadata', $no_delete, 11, 3 );
		$result = $rules->replace( array( $this->row( array( 'starts_on' => '2027-01-08' ) ) ) );
		remove_filter( 'delete_post_metadata', $no_delete, 11 );

		$this->assertSame( 'gatherpress_rules_not_saved', $result->get_error_code() );
		$this->assertSame( $before, $this->stored( $post_id ), 'Nothing was added on top of the old rows.' );

		$calls  = 0;
		$no_add = static function ( $check, $object_id, $meta_key ) use ( &$calls ) {
			// The first new row saves, the second fails, and the restore fails too.
			return Rules::META_KEY === $meta_key && 2 <= ++$calls ? false : $check;
		};

		add_filter( 'add_post_metadata', $no_add, 11, 3 );
		$result = $rules->replace( array( $this->row( array( 'starts_on' => '2027-01-08' ) ), $this->row() ) );
		remove_filter( 'add_post_metadata', $no_add, 11 );

		$this->assertSame( 'gatherpress_rules_lost', $result->get_error_code() );
	}
}
