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
	 * Generate credits data for the credits page.
	 *
	 * This method generates credits data for displaying on the credits page.
	 * It retrieves user data from WordPress.org profiles based on the provided version.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args       Positional arguments for the script.
	 * @param array $assoc_args Associative arguments for the script.
	 * @return void
	 */
	public function generate_credits( array $args = array(), array $assoc_args = array() ): void {
		$credits = require_once GATHERPRESS_CORE_PATH . '/includes/data/credits/credits.php';
		$version = $assoc_args['version'] ?? GATHERPRESS_VERSION;
		$latest  = GATHERPRESS_CORE_PATH . '/includes/data/credits/latest.php';
		$data    = array();

		if ( empty( $credits[ $version ] ) ) {
			WP_CLI::error( 'Version does not exist' );
		}

		unlink( $latest );
		$file = fopen( $latest, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		$data['version'] = $version;

		foreach ( $credits[ $version ] as $group => $users ) {
			if ( 'contributors' === $group ) {
				sort( $users );
			}

			$data[ $group ] = array();

			foreach ( $users as $user ) {
				$response  = wp_remote_request( sprintf( 'https://profiles.wordpress.org/wp-json/wporg/v1/users/%s', $user ) );
				$user_data = json_decode( $response['body'], true );

				// Remove unsecure data (eg http) and data we do not need.
				unset( $user_data['description'], $user_data['url'], $user_data['meta'], $user_data['_links'] );

				$data[ $group ][] = $user_data;
			}
		}
		fwrite( $file, '<?php return ' . var_export( $data, true ) . ';' ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite,WordPress.PHP.DevelopmentFunctions.error_log_var_export
		fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		WP_CLI::success( 'New latest.php file has been generated.' );
	}

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
