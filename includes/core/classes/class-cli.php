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

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Commands\Cli_Event;
use WP_CLI;

/**
 * Class Cli.
 *
 * The Cli class extends WP-CLI and provides custom WP-CLI commands
 * for interacting with and managing GatherPress functionality via the command line.
 *
 * @since 1.0.0
 */
class Cli {

	use Singleton;

	protected function __construct() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) { // @codeCoverageIgnore
			WP_CLI::add_command( 'gatherpress event', Cli_Event::class ); // @codeCoverageIgnore
		}
	}
}
