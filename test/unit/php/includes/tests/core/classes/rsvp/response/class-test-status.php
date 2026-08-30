<?php
/**
 * Unit tests for the RSVP response status enum.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Rsvp\Response;

use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Tests\Base;

/**
 * Class Test_Status.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Response\Status
 */
class Test_Status extends Base {

	/**
	 * Every case has a translated label.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::label
	 *
	 * @return void
	 */
	public function test_every_case_has_a_label(): void {
		foreach ( Status::cases() as $status ) {
			$this->assertNotEmpty(
				$status->label(),
				sprintf( 'Failed to assert %s has a label.', $status->value )
			);
		}
	}

	/**
	 * Labels read the way the list table's Response column does.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::label
	 *
	 * @return void
	 */
	public function test_labels_match_the_response_column(): void {
		$this->assertSame( 'Attending', Status::ATTENDING->label() );
		$this->assertSame( 'Not Attending', Status::NOT_ATTENDING->label() );
		$this->assertSame( 'Waiting List', Status::WAITING_LIST->label() );
	}

	/**
	 * The filterable list omits the absence of a response.
	 *
	 * `NO_STATUS` carries no term, so offering it as a filter would produce
	 * an empty result rather than the RSVPs a reader expects.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::filterable
	 *
	 * @return void
	 */
	public function test_filterable_excludes_no_status(): void {
		$values = array_map(
			static fn( Status $status ): string => $status->value,
			Status::filterable()
		);

		$this->assertContains( Status::ATTENDING->value, $values );
		$this->assertContains( Status::NOT_ATTENDING->value, $values );
		$this->assertContains( Status::WAITING_LIST->value, $values );
		$this->assertNotContains(
			Status::NO_STATUS->value,
			$values,
			'Failed to assert the no-response case is not offered as a filter.'
		);
	}

	/**
	 * Filterable stays in step with the enum as cases are added.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::filterable
	 *
	 * @return void
	 */
	public function test_filterable_tracks_the_enum(): void {
		$this->assertCount(
			count( Status::cases() ) - 1,
			Status::filterable(),
			'Failed to assert filterable covers every case but the no-response one.'
		);
	}
}
