<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Repeat_Summary.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event\Current_Occurrence;
use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Repeat_Summary;
use GatherPress\Core\Event\Rule;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Repeat_Summary.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Repeat_Summary
 */
class Test_Repeat_Summary extends Base {

	/**
	 * Reset the current dates.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		Utility::set_and_get_hidden_property( Current_Occurrence::get_instance(), 'current', array() );
		update_option( 'date_format', 'F j, Y' );
	}

	/**
	 * A rule from an RRULE.
	 *
	 * @since TBD
	 *
	 * @param string|null $rrule   The RRULE, or null for a single date.
	 * @param string|null $ends_on The last date, if any.
	 *
	 * @return Rule The rule.
	 */
	protected function rule( ?string $rrule, ?string $ends_on = null ): Rule {
		return Rule::from_meta(
			array(
				'starts_on' => '2026-11-30',
				'ends_on'   => $ends_on,
				'rrule'     => $rrule,
				'times'     => array( '19:00' ),
				'timezone'  => 'UTC',
				'duration'  => 60,
			)
		);
	}

	/**
	 * Each frequency and form reads as a sentence.
	 *
	 * @since TBD
	 *
	 * @covers ::of_rule
	 * @covers ::repeat_phrase
	 * @covers ::weekly_phrase
	 * @covers ::monthly_phrase
	 * @covers ::ordinal_weekday
	 * @covers ::weekday_name
	 * @covers ::list_of
	 *
	 * @return void
	 */
	public function test_of_rule(): void {
		$cases = array(
			'FREQ=DAILY'                           => 'Every day.',
			'FREQ=DAILY;INTERVAL=3'                => 'Every 3 days.',
			'FREQ=WEEKLY;BYDAY=MO'                 => 'Every week on Monday.',
			'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,FR'   => 'Every 2 weeks on Monday and Friday.',
			'FREQ=WEEKLY;BYDAY=MO,WE,FR'           => 'Every week on Monday, Wednesday and Friday.',
			'FREQ=MONTHLY;BYMONTHDAY=5'            => 'Every month on day 5.',
			'FREQ=MONTHLY;INTERVAL=2;BYMONTHDAY=5' => 'Every 2 months on day 5.',
			'FREQ=MONTHLY;BYDAY=2FR'               => 'Every month on the second Friday.',
			'FREQ=MONTHLY;INTERVAL=3;BYDAY=-1TH'   => 'Every 3 months on the last Thursday.',
			'FREQ=YEARLY'                          => 'Every year.',
			'FREQ=YEARLY;INTERVAL=2'               => 'Every 2 years.',
		);

		foreach ( $cases as $rrule => $sentence ) {
			$this->assertSame( $sentence, Repeat_Summary::of_rule( $this->rule( $rrule ) ), $rrule );
		}

		$this->assertSame(
			'Every week on Monday, until March 1, 2027.',
			Repeat_Summary::of_rule( $this->rule( 'FREQ=WEEKLY;BYDAY=MO', '2027-03-01' ) )
		);
	}

	/**
	 * Unknown places and codes fall back.
	 *
	 * @since TBD
	 *
	 * @covers ::ordinal_weekday
	 * @covers ::weekday_name
	 * @covers ::list_of
	 *
	 * @return void
	 */
	public function test_fallbacks(): void {
		$this->assertSame( '', Repeat_Summary::ordinal_weekday( 5, 'MO' ) );
		$this->assertSame( 'XX', Repeat_Summary::weekday_name( 'XX' ) );
		$this->assertSame( '', Repeat_Summary::list_of( array() ) );
		$this->assertSame( 'Sunday', Repeat_Summary::list_of( array( 'Sunday' ) ) );
	}

	/**
	 * An event describes the rule of the date it shows, or the one running today, or its last.
	 *
	 * @since TBD
	 *
	 * @covers ::for_event
	 * @covers ::rule_for
	 * @covers ::today
	 *
	 * @return void
	 */
	public function test_for_event(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		$event   = new Event( $post_id );
		$base    = array(
			'times'    => array( '19:00' ),
			'timezone' => 'UTC',
			'duration' => 60,
		);

		$this->assertSame( '', Repeat_Summary::for_event( $event ), 'No rules.' );

		$event->rules()->replace(
			array(
				$base + array(
					'starts_on' => '2020-01-06',
					'ends_on'   => '2020-03-30',
					'rrule'     => 'FREQ=WEEKLY;BYDAY=MO',
				),
				$base + array(
					'starts_on' => '2020-04-02',
					'rrule'     => 'FREQ=WEEKLY;BYDAY=TH',
				),
			)
		);

		$this->assertSame( 'Every week on Thursday.', Repeat_Summary::for_event( $event ), 'The rule running today.' );

		Current_Occurrence::get_instance()->set(
			$post_id,
			array(
				'recurrence_id'      => '20200113T190000',
				'datetime_start'     => '2020-01-13 19:00:00',
				'datetime_start_gmt' => '2020-01-13 19:00:00',
				'datetime_end'       => '2020-01-13 20:00:00',
				'datetime_end_gmt'   => '2020-01-13 20:00:00',
				'timezone'           => 'UTC',
			)
		);

		$this->assertSame(
			'Every week on Monday, until March 30, 2020.',
			Repeat_Summary::for_event( $event ),
			"The shown date's rule."
		);

		Utility::set_and_get_hidden_property( Current_Occurrence::get_instance(), 'current', array() );
		$event->rules()->replace(
			array(
				$base + array(
					'starts_on' => '2020-01-06',
					'ends_on'   => '2020-03-30',
					'rrule'     => 'FREQ=WEEKLY;BYDAY=MO',
				),
			)
		);

		$this->assertSame(
			'Every week on Monday, until March 30, 2020.',
			Repeat_Summary::for_event( $event ),
			'The last rule, once every rule has ended.'
		);

		$event->rules()->replace(
			array(
				$base + array(
					'starts_on' => '2020-01-06',
					'rrule'     => null,
				),
			)
		);

		$this->assertSame( '', Repeat_Summary::for_event( $event ), 'A single date.' );
	}
}
