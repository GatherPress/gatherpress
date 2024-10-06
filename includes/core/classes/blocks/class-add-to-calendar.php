<?php
/**
 * The "Add to calendar" class manages the core-block-variation,
 * registers and enqueues assets and prepares the output of the block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Block_Variation;

/**
 * Class responsible for managing the core-block-variation "Add to calendar".
 *
 * @since 1.0.0
 */
class Add_To_Calendar {
	/**
	 * Common class that handles registering and enqueuing of assets.
	 */
	use Block_Variation;

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
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		$this->register_and_enqueue_assets();

		// wp_die('block_vaaaaaar');
		// phpcs:disable Squiz.PHP.CommentedOutCode.Found
		// add_action( 'init', array( $this, 'register_block_bindings_sources' ) );
		// add_action( 'init', array( $this, 'register_blocks_styles' ) ); //.
		// phpcs:enable Squiz.PHP.CommentedOutCode.Found
	}
}
