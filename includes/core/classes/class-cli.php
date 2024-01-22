<?php
/**
 * Class responsible for registering WP-CLI commands within GatherPress.
 *
 * This class registers WP-CLI commands specific to the GatherPress plugin,
 * allowing developers to interact with and manage plugin functionality via the command line.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Commands\Cli_Event;
use GatherPress\Core\Commands\Cli_General;
use GatherPress\Core\Traits\Singleton;
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
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constructor for the Setup class.
	 *
	 * Registers WP-CLI commands for GatherPress if WP-CLI is present.
	 *
	 * @since 1.0.0
	 *
	 * @codeCoverageIgnore
	 */
	protected function __construct() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'gatherpress', Cli_General::class );
			WP_CLI::add_command( 'gatherpress event', Cli_Event::class );
		}
	}
}
