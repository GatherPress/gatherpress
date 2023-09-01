<?php
/**
 * Class is responsible for all block related functionality.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) { // @codeCoverageIgnore
	exit; // @codeCoverageIgnore
}

/**
 * Class Block.
 */
class Block {

	use Singleton;

	/**
	 * Block constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register blocks.
	 *
	 * @return void
	 */
	public function register_blocks() {
		$blocks_directory = sprintf( '%1$s/build/blocks/', GATHERPRESS_CORE_PATH );
		$blocks           = array_diff( scandir( $blocks_directory ), array( '..', '.' ) );
		foreach ( $blocks as $block ) {
			register_block_type( sprintf( '%1$s/build/blocks/%2$s', GATHERPRESS_CORE_PATH, $block ) );
		}
	}

}
