<?php
/**
 * 
 *
 * ....
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Block;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

// Could potentially use a ...
// use GatherPress\Core\Traits\Block_Variation;

/**
 * 
 *
 * ....
 *
 * @since 1.0.0
 */
class Add_To_Calendar {
	/**
	 * Enforces a single instance of this class.
	 */
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
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
        // wp_die('variation test end.');

        // add_action( 'init', array( $this, 'register_block_bindings_sources' ) );
        // add_action( 'init', array( $this, 'register_blocks_styles' ) );
	}

}
