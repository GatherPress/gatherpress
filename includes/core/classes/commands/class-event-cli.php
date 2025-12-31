<?php
/**
 * Class responsible for WP-CLI commands related to events within GatherPress.
 *
 * This class handles various WP-CLI commands specific to managing events in the GatherPress plugin.
 * Developers can use these commands to interact with and manage event-related functionalities via the command line.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Commands;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use WP_CLI;

/**
 * WP-CLI commands for managing events within GatherPress.
 *
 * This class contains WP-CLI commands specifically designed for managing events in the GatherPress plugin.
 * Developers can use these commands to perform various actions on events, such as updating RSVP status.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */
class Event_Cli extends WP_CLI {
	/**
	 * Update RSVP status for an event.
	 *
	 * This WP-CLI command allows you to update the RSVP status for a user attending an event.
	 *
	 * ## OPTIONS
	 *
	 * [--event_id=<event_id>]
	 * : ID of an event.
	 *
	 * [--user_id=<user_id>]
	 * : ID of a user.
	 *
	 * [--status=<status>]
	 * : Attendance status.
	 * ---
	 * default: attending
	 * options:
	 *  - attending
	 *  - not_attending
	 *  - waiting_list
	 *
	 * ## EXAMPLES
	 *
	 *    # Update RSVP for an event.
	 *    $ wp gatherpress event rsvp --event_id=525 --user_id=1 --status="not_attending"
	 *    Success: The RSVP status for Event ID "525" has been successfully set to "not_attending" for User ID "1".
	 *
	 * @since 1.0.0
	 *
	 * @param array $args       Positional arguments for the script.
	 * @param array $assoc_args Associative arguments for the script.
	 *
	 * @return void
	 */
	public function rsvp( array $args = array(), array $assoc_args = array() ): void {
		$event_id  = (int) $assoc_args['event_id'];
		$user_id   = (int) $assoc_args['user_id'];
		$guests    = ! empty( $assoc_args['guests'] ) ? (int) $assoc_args['guests'] : 0;
		$anonymous = ! empty( $assoc_args['anonymous'] ) ? (int) $assoc_args['anonymous'] : 0;
		$status    = ! empty( $assoc_args['status'] ) ? (string) $assoc_args['status'] : 'attending';
		$event     = new Event( $event_id );
		$response  = $event->rsvp->save( $user_id, $status, $anonymous, $guests );

		static::success(
			sprintf(
				/* translators: %1$d: event ID, %2$s: attendance status, %3$d: user ID. */
				__(
					'The RSVP status for Event ID "%1$d" has been successfully set to "%2$s" for User ID "%3$d".',
					'gatherpress'
				),
				$event_id,
				$response['status'],
				$user_id
			),
		);
	}
}
