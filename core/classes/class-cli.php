<?php
/**
 * Class is responsible for WP-CLI commands.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use WP_CLI;

/**
 * Class Cli.
 */
class CLI extends WP_CLI {

	public function event( array $args = array(), array $assoc_args = array() ) {
		$event_id = (int) $args[0];
		$action   = (string) $args[1];

		switch( $action ) {
			case 'add-attendee':
				$this->add_attendee( $event_id, $assoc_args );
				break;
		}
	}

	private function add_attendee( $event_id, $assoc_args ) {
		$event   = new Event( $event_id );
		$user_id = $assoc_args['user_id'];
		$status  = $assoc_args['status'];
		$guests  = $assoc_args['guests'] ?? 0;

		$response = $event->attendee->save_attendee( $user_id, $status, $guests );

		\WP_CLI::success( $response );
	}
}

\WP_CLI::add_command( 'gatherpress', CLI::class );
