<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Status.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event\Status;
use GatherPress\Tests\Base;

/**
 * Class Test_Status.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Status
 */
class Test_Status extends Base {
	/**
	 * The defaults cover Schema.org's vocabulary and each carries what the
	 * standards need.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::all
	 * @covers ::slugs
	 *
	 * @return void
	 */
	public function test_all_describes_every_default_status(): void {
		$statuses = Status::all();

		$this->assertSame(
			array(
				Status::SCHEDULED,
				Status::CANCELED,
				Status::POSTPONED,
				Status::RESCHEDULED,
				Status::MOVED,
			),
			Status::slugs(),
			'Failed to assert the default statuses are offered in order.'
		);

		foreach ( $statuses as $slug => $status ) {
			$this->assertNotEmpty( $status['label'], sprintf( '%s should be named.', $slug ) );
			$this->assertNotEmpty( $status['description'], sprintf( '%s should be explained.', $slug ) );
			$this->assertNotEmpty( $status['schema'], sprintf( '%s should name a Schema.org value.', $slug ) );
			$this->assertContains(
				$status['ical'],
				array( 'CONFIRMED', 'CANCELLED', 'TENTATIVE' ),
				sprintf( '%s should name one of RFC 5545\'s three values.', $slug )
			);
		}
	}

	/**
	 * A status reports the words and standard values it was given.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::label
	 * @covers ::schema
	 * @covers ::ical
	 * @covers ::exists
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_a_known_status_reports_itself(): void {
		$this->assertTrue( Status::exists( Status::CANCELED ), 'Failed to assert a default status exists.' );
		$this->assertSame( 'Canceled', Status::label( Status::CANCELED ) );
		$this->assertSame( 'EventCancelled', Status::schema( Status::CANCELED ) );
		$this->assertSame( 'CANCELLED', Status::ical( Status::CANCELED ) );

		// An event that has moved is still going ahead, so it stays confirmed
		// and the new location speaks for itself.
		$this->assertSame( 'Moved', Status::label( Status::MOVED ) );
		$this->assertSame( 'EventScheduled', Status::schema( Status::MOVED ) );
		$this->assertSame( 'CONFIRMED', Status::ical( Status::MOVED ) );
	}

	/**
	 * An unknown status is treated as scheduled rather than published as
	 * something a calendar client would misread.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::exists
	 * @covers ::label
	 * @covers ::schema
	 * @covers ::ical
	 *
	 * @return void
	 */
	public function test_an_unknown_status_falls_back_to_scheduled(): void {
		$this->assertFalse( Status::exists( 'not-a-status' ) );
		$this->assertSame( 'Scheduled', Status::label( 'not-a-status' ) );
		$this->assertSame( 'EventScheduled', Status::schema( 'not-a-status' ) );
		$this->assertSame( 'CONFIRMED', Status::ical( 'not-a-status' ) );
	}

	/**
	 * A site can add a status of its own.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::all
	 * @covers ::slugs
	 * @covers ::exists
	 * @covers ::label
	 *
	 * @return void
	 */
	public function test_a_site_can_register_its_own_status(): void {
		$callback = static function ( array $statuses ): array {
			$statuses['sold-out'] = array(
				'label'       => 'Sold out',
				'description' => 'Every place has been taken.',
				'schema'      => 'EventScheduled',
				'ical'        => 'CONFIRMED',
			);

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		$this->assertTrue( Status::exists( 'sold-out' ), 'Failed to assert a registered status exists.' );
		$this->assertSame( 'Sold out', Status::label( 'sold-out' ) );
		$this->assertContains( 'sold-out', Status::slugs(), 'Failed to assert a registered status is offered.' );

		remove_filter( 'gatherpress_event_statuses', $callback );

		$this->assertFalse( Status::exists( 'sold-out' ), 'Failed to assert the status leaves with its filter.' );
	}

	/**
	 * A registered status that names no standard values still publishes
	 * something true, rather than telling a calendar client nothing.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::schema
	 * @covers ::ical
	 *
	 * @return void
	 */
	public function test_a_registered_status_may_omit_the_standards(): void {
		$callback = static function ( array $statuses ): array {
			$statuses['tentative'] = array( 'label' => 'Tentative' );

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		$this->assertSame( Status::DEFAULT_SCHEMA, Status::schema( 'tentative' ) );
		$this->assertSame( Status::DEFAULT_ICAL, Status::ical( 'tentative' ) );

		remove_filter( 'gatherpress_event_statuses', $callback );
	}

	/**
	 * Scheduled is what an event with no status of its own reports, so it
	 * survives a filter that drops it.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::all
	 *
	 * @return void
	 */
	public function test_scheduled_cannot_be_removed(): void {
		$callback = static function (): array {
			return array();
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		$this->assertTrue( Status::exists( Status::SCHEDULED ), 'Failed to assert scheduled survives.' );
		$this->assertSame( 'Scheduled', Status::label( Status::SCHEDULED ) );

		remove_filter( 'gatherpress_event_statuses', $callback );
	}
}
