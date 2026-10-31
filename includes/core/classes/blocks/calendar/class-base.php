<?php
/**
 * Base of the calendar blocks that render from their block context.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Calendar\Time_Grid;
use WP_Block;

/**
 * Class Base.
 *
 * Holds the block a calendar block renders and reads typed values from its context.
 *
 * @since TBD
 */
abstract class Base {

	/**
	 * Block instance.
	 *
	 * @since TBD
	 * @var WP_Block
	 */
	protected readonly WP_Block $block;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 *
	 * @param WP_Block $block Block instance.
	 */
	public function __construct( WP_Block $block ) {
		$this->block = $block;
	}

	/**
	 * Render the block.
	 *
	 * Called from the block's render.php.
	 *
	 * @since TBD
	 *
	 * @return string Rendered HTML.
	 */
	abstract public function render(): string;

	/**
	 * Get a string from the block context.
	 *
	 * @since TBD
	 *
	 * @param string $key Context key.
	 *
	 * @return string The value, or an empty string when it is not a string.
	 */
	protected function get_context_string( string $key ): string {
		$value = $this->block->context[ $key ] ?? null;

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Get an integer from the block context.
	 *
	 * @since TBD
	 *
	 * @param string $key Context key.
	 *
	 * @return int The value, or 0 when it is not numeric.
	 */
	protected function get_context_int( string $key ): int {
		$value = $this->block->context[ $key ] ?? null;

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * Get an array from the block context.
	 *
	 * @since TBD
	 *
	 * @param string $key Context key.
	 *
	 * @return array<mixed> The value, or an empty array when it is not an array.
	 */
	protected function get_context_array( string $key ): array {
		$value = $this->block->context[ $key ] ?? null;

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Get the time grid of the calendar from the block context.
	 *
	 * @since TBD
	 *
	 * @return Time_Grid The time grid, or a disabled one outside a calendar.
	 */
	protected function get_time_grid(): Time_Grid {
		return Time_Grid::from_context( $this->block->context );
	}
}
