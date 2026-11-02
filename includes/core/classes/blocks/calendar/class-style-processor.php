<?php
/**
 * Turns Calendar block attributes into CSS values.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Style_Processor.
 *
 * Processes block attributes into CSS style strings.
 *
 * @since TBD
 */
final class Style_Processor {

	/**
	 * The blockGap value of the block: a string, a split { top, left } array, or null.
	 *
	 * @since TBD
	 * @var mixed
	 */
	private readonly mixed $block_gap;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function __construct( array $attributes ) {
		$style   = is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array();
		$spacing = is_array( $style['spacing'] ?? null ) ? $style['spacing'] : array();

		$this->block_gap = $spacing['blockGap'] ?? null;
	}

	/**
	 * Get one side of a split blockGap value.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $block_gap Split blockGap value.
	 * @param string       $side      'top' or 'left'.
	 *
	 * @return string The side, or '1px' when it is missing.
	 */
	private function get_gap_side( array $block_gap, string $side ): string {
		return is_scalar( $block_gap[ $side ] ?? null ) ? (string) $block_gap[ $side ] : '1px';
	}

	/**
	 * Resolve the blockGap attribute to a valid CSS gap string.
	 *
	 * @since TBD
	 *
	 * @return string CSS gap value (e.g., 'var(--wp--preset--spacing--50)' or '10px 20px') or empty string.
	 */
	public function get_block_gap_value(): string {
		$block_gap = $this->block_gap;

		if ( empty( $block_gap ) ) {
			return '';
		}

		if ( is_string( $block_gap ) ) {
			return self::resolve_preset_value( $block_gap );
		}

		// Handle axial/split (vertical and horizontal) gap objects.
		if ( is_array( $block_gap ) ) {
			$top  = self::resolve_preset_value( $this->get_gap_side( $block_gap, 'top' ) );
			$left = self::resolve_preset_value( $this->get_gap_side( $block_gap, 'left' ) );

			// A side that is not safe CSS drops the whole gap.
			return '' !== $top && '' !== $left ? "{$top} {$left}" : '';
		}

		return '';
	}

	/**
	 * Get the column (horizontal) gap from the blockGap attribute.
	 *
	 * The gap can be a single value or a split { top, left } object; the
	 * column gap is the single value or 'left'. Day cells use it in calc()
	 * to stay square, so it must be one length: '0' becomes '0px'.
	 *
	 * @since TBD
	 *
	 * @return string One CSS length (e.g., 'var(--wp--preset--spacing--50)' or '20px') or empty string.
	 */
	public function get_block_column_gap_value(): string {
		$block_gap = $this->block_gap;

		// Match get_block_gap_value(): it outputs no gap for an empty value.
		if ( empty( $block_gap ) ) {
			return '';
		}

		if ( is_array( $block_gap ) ) {
			// Same default as get_block_gap_value() for a missing side.
			$block_gap = $this->get_gap_side( $block_gap, 'left' );
		}

		if ( ! is_string( $block_gap ) ) {
			return '';
		}

		$column_gap = self::resolve_preset_value( $block_gap );

		return '0' === $column_gap ? '0px' : $column_gap;
	}

	/**
	 * Resolve Gutenberg 'var:preset|spacing|50' shorthand to standard CSS 'var(...)'.
	 *
	 * The value goes into a style attribute, so a value that could end the declaration or add
	 * another one (a semicolon, braces, quotes, a backslash or a comment) gives an empty string.
	 * The preset type and slug go through _wp_to_kebab_case(), as WordPress names the preset
	 * variables (x_large becomes x-large), which leaves only letters, digits and dashes.
	 *
	 * @since TBD
	 *
	 * @param string $value Raw attribute value.
	 *
	 * @return string Resolved CSS string, or an empty string for an unsafe value.
	 */
	public static function resolve_preset_value( string $value ): string {
		$parts = explode( '|', $value );

		if ( 3 === count( $parts ) && 'var:preset' === $parts[0] ) {
			return sprintf(
				'var(--wp--preset--%s--%s)',
				_wp_to_kebab_case( $parts[1] ),
				_wp_to_kebab_case( $parts[2] )
			);
		}

		// Rejects any of ; { } \ < > " ' or the start of a CSS comment (/*).
		if ( preg_match( '#[;{}\\\\<>"\']|/\*#', $value ) ) {
			return '';
		}

		return esc_attr( $value );
	}
}
