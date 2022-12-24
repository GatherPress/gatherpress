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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Block.
 */
class Block {

	use Singleton;
	/**
	 * List of React blocks.
	 *
	 * @var array List of block names.
	 */
	protected $blocks = array(
		'add-to-calendar',
		'attendance-list',
		'attendance-selector',
		'event-date',
		'events-list',
		'venue',
		'venue-information',
	);

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
		foreach ( $this->blocks as $block ) {
			register_block_type( sprintf( '%1$s/build/blocks/%2$s', GATHERPRESS_CORE_PATH, $block ) );
		}
	}

}
