<?php
/**
 * Main class for managing custom blocks in GatherPress.
 *
 * This class handles the registration and management of custom blocks used in the GatherPress theme.
 *
 * @package GatherPress/Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore Prevent direct access.
}

/**
 * Class Block.
 *
 * This class is responsible for registering blocks and utilizes the Singleton pattern.
 * It ensures that only one instance of this class can exist.
 *
 * @since 1.0.0
 */
class Block {

	use Singleton;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register custom blocks.
	 *
	 * This method scans a directory for custom block definitions and registers them.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_blocks(): void {
		$blocks_directory = sprintf( '%1$s/build/blocks/', GATHERPRESS_CORE_PATH );
		$blocks           = array_diff( scandir( $blocks_directory ), array( '..', '.' ) );
		foreach ( $blocks as $block ) {
			register_block_type( sprintf( '%1$s/build/blocks/%2$s', GATHERPRESS_CORE_PATH, $block ) );
		}
	}

}
