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

namespace GatherPress\Core;

use WP_CLI;

/**
 * Class Cli.
 *
 * The Cli class extends WP-CLI and provides custom WP-CLI commands
 * for interacting with and managing GatherPress functionality via the command line.
 *
 * @since 1.0.0
 */
class Cli extends WP_CLI {

	/**
	 * Perform actions on an event.
	 *
	 * @param array $args       Positional arguments for the script.
	 * @param array $assoc_args Associative arguments for the script.
	 *
	 * @return void
	 */
	public function event( array $args = array(), array $assoc_args = array() ): void {
		$event_id = (int) $args[0];
		$action   = (string) $args[1];

		if ( 'add-response' === $action ) {
			$this->add_response( $event_id, $assoc_args );
		}
	}

	/**
	 * Generate credits data for the credits page.
	 *
	 * This method generates credits data for displaying on the credits page.
	 * It retrieves user data from WordPress.org profiles based on the provided version.
	 *
	 * @param array $args       Positional arguments for the script.
	 * @param array $assoc_args Associative arguments for the script.
	 *
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

		foreach ( $credits[ $version ] as $group => $users ) {
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
	 * Add response to an event.
	 *
	 * This method adds a response to the specified event, identified by its Post ID.
	 *
	 * @param int   $event_id   The Post ID of the event.
	 * @param array $assoc_args Associative arguments for the script, including 'user_id', 'status', and 'guests'.
	 *
	 * @return void
	 */
	private function add_response( int $event_id, array $assoc_args ): void {
		$event   = new Event( $event_id );
		$user_id = $assoc_args['user_id'];
		$status  = $assoc_args['status'];
		$guests  = $assoc_args['guests'] ?? 0;

		$response = $event->rsvp->save( $user_id, $status, $guests );

		WP_CLI::success( $response );
	}

}
