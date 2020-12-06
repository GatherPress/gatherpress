<?php
/**
 * Class is responsible for all block related functionality.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Inc;

use GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
	public function render_block( string $block_content, array $block ) : string {
		if ( ! isset( $block['blockName'] ) ) {
			return $block_content;
		}

		$block_name_parts = explode( '/', $block['blockName'] );

		if (
			2 === count( $block_name_parts )
			&& 'gatherpress' === $block_name_parts[0]
			&& ! empty( $block_name_parts[1] )
		) {
			return Utility::render_template(
				sprintf( '%s/template-parts/blocks/%s.php', GATHERPRESS_CORE_PATH, $block_name_parts[1] ),
				array(
					'attrs' => $block['attrs'] ?? [],
				)
			);
		}

		return $block_content;
	}
}
