<?php
/**
 * Class responsible for WP-CLI commands within GatherPress.
 *
 * This class handles WP-CLI commands specific to the GatherPress plugin,
 * allowing developers to interact with and manage plugin functionality via the command line.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Commands;
use GatherPress\Core\Event;

use WP_CLI;

/**
 * Class Cli.
 *
 * The Cli class extends WP-CLI and provides custom WP-CLI commands
 * for interacting with and managing GatherPress functionality via the command line.
 *
 * @since 1.0.0
 */
class Cli_Event extends WP_CLI {

	/**
	 * Perform actions on an event.
	 *
	 * This method allows you to perform various actions related to events, such as adding responses.
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
	 * @since 1.0.0
	 *
	 * @param array $args       Positional arguments for the script.
	 * @param array $assoc_args Associative arguments for the script.
	 * @return void
	 */
	public function rsvp( array $args = array(), array $assoc_args = array() ): void {
		$event_id = (int) $assoc_args['event_id'];
		$user_id  = (int) $assoc_args['user_id'];
		$status   = (string) $assoc_args['status'] ?? 'attending';
		$event    = new Event( $event_id );

		$response = $event->rsvp->save( $user_id, $status );

		WP_CLI::success(
			sprintf(
				__( 'The RSVP status for Event ID "%1$d" has been successfully set to "%2$s" for User ID "%3$d".', 'gatherpress' ),
				$event_id,
				$response,
				$user_id
			),
		);
	}

}
