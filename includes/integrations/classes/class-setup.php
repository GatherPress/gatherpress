<?php
/**
 * Manages third-party plugin integrations for GatherPress.
 *
 * @package GatherPress\Integrations
 * @since 1.0.0
 */

namespace GatherPress\Integrations;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Integrations\Duplicate_Post\Setup as Duplicate_Post_Setup;

/**
 * Class Setup.
 *
 * Bootstraps all third-party plugin integrations.
 *
 * @since 1.0.0
 */
class Setup {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constructor for the Setup class.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->instantiate_classes();
	}

	/**
	 * Instantiate integration classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Duplicate_Post_Setup::get_instance();
	}
}
