<?php
/**
 * Class handles unit tests for GatherPress\Core\Commands\Event_Cli.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Commands;

use GatherPress\Core\Commands\Event_Cli;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Block.
 *
 * @coversDefaultClass \GatherPress\Core\Commands\Event_Cli
 */
class Test_Event_Cli extends Base {
	/**
	 * Coverage for rsvp.
	 *
	 * @covers ::rsvp
	 *
	 * @return void
	 */
	public function test_rsvp(): void {
		$cli_event  = new Event_Cli();
		$event      = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$user       = $this->mock->user()->get();
		$status     = 'not_attending';
		$assoc_args = array(
			'event_id' => $event->ID,
			'user_id'  => $user->ID,
			'status'   => $status,
		);

		$output  = Utility::buffer_and_return( array( $cli_event, 'rsvp' ), array( array(), $assoc_args ) );
		$expects = sprintf(
			'Success: The RSVP status for Event ID "%1$d" has been successfully set to "%2$s" for User ID "%3$d".',
			$event->ID,
			$status,
			$user->ID,
		);

		$this->assertSame( $expects, $output, 'Failed to assert output matches.' );
	}
}
