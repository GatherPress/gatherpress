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
	protected $react_blocks = array(
		'attendance-list',
		'attendance-selector',
		'events-list',
	);

	/**
	 * List of Static blocks.
	 *
	 * @var array List of block names.
	 */
	protected $static_blocks = array(
		'event-date',
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
		add_filter( 'render_block', array( $this, 'render_block' ), 10, 2 );
	}

	/**
	 * Callback to render blocks.
	 *
	 * @param string $block_content The block content about to be appended.
	 * @param array  $block The full block, including name and attributes.
	 *
	 * @return string
	 */
	public function render_block( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) ) {
			return $block_content;
		}

		$block_name_parts = explode( '/', $block['blockName'] );

		if ( 2 !== count( $block_name_parts ) ) {
			return $block_content;
		}

		if ( 'gatherpress' !== $block_name_parts[0] ) {
			return $block_content;
		}

		$block_name = $block_name_parts[1];

		if ( in_array( $block_name, $this->react_blocks, true ) ) {
			return Utility::render_template(
				sprintf( '%s/templates/blocks/react-block.php', GATHERPRESS_CORE_PATH ),
				array(
					'gatherpress_block_name'  => $block_name,
					'gatherpress_block_attrs' => $block['attrs'] ?? array(),
				)
			);
		} elseif ( in_array( $block_name, $this->static_blocks, true ) ) {
			return Utility::render_template(
				sprintf( '%s/templates/blocks/%s.php', GATHERPRESS_CORE_PATH, $block_name ),
				array(
					'gatherpress_block_attrs' => $block['attrs'] ?? array(),
				)
			);
		}

		return $block_content;
	}

}
