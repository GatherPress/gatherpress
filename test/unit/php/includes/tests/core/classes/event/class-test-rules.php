<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Rules.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event\Event;
use GatherPress\Core\Event\Rule;
use GatherPress\Core\Event\Rules;
use GatherPress\Tests\Base;

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
}
