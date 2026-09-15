<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Occurrence_Ref.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use Error;
use GatherPress\Core\Event\Recurrence\Occurrence_Ref;
use GatherPress\Tests\Base;

/**
 * Class Test_Occurrence_Ref.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Occurrence_Ref
 */
class Test_Occurrence_Ref extends Base {

	/**
	 * Every field the constructor takes is readable back off the object, so
	 * occurrence identity travels with the entry instead of with its position
	 * in a list.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor_exposes_the_composite_identity(): void {
		$ref = new Occurrence_Ref( 42, '20260101T090000', '2026-01-01 09:00:00' );

		$this->assertSame(
			42,
			$ref->post_id,
			'Failed to assert that post_id is readable off the reference.'
		);
		$this->assertSame(
			'20260101T090000',
			$ref->recurrence_id,
			'Failed to assert that recurrence_id is readable off the reference.'
		);
		$this->assertSame(
			'2026-01-01 09:00:00',
			$ref->datetime_start_gmt,
			'Failed to assert that datetime_start_gmt is readable off the reference.'
		);
	}

	/**
	 * A null `recurrence_id` is how a non-recurring event rides in the same
	 * ordered list as occurrences, so the constructor has to accept it and keep
	 * it null rather than coercing it to an empty string.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_null_recurrence_id_marks_a_non_recurring_entry(): void {
		$ref = new Occurrence_Ref( 7, null, '2026-03-04 18:30:00' );

		$this->assertNull(
			$ref->recurrence_id,
			'Failed to assert that a null recurrence_id stays null.'
		);
		$this->assertSame(
			7,
			$ref->post_id,
			'Failed to assert that a non-recurring entry still carries its post ID.'
		);
		$this->assertSame(
			'2026-03-04 18:30:00',
			$ref->datetime_start_gmt,
			'Failed to assert that a non-recurring entry still carries its start.'
		);
	}

	/**
	 * The class documents itself as an immutable value object. Promoted
	 * `readonly` properties are what enforce that, so a write has to fail
	 * rather than quietly rewriting one entry's identity mid-list.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_identity_cannot_be_rewritten_after_construction(): void {
		$ref = new Occurrence_Ref( 42, '20260101T090000', '2026-01-01 09:00:00' );

		$this->expectException( Error::class );

		$ref->post_id = 43;
	}
}
