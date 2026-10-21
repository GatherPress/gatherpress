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
	 * Get the raw blockGap value of the block attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return mixed The blockGap value: a string, a split { top, left } array, or null.
	 */
	private static function get_raw_block_gap( array $attributes ) {
		$style   = is_array( $attributes['style'] ?? null ) ? $attributes['style'] : array();
		$spacing = is_array( $style['spacing'] ?? null ) ? $style['spacing'] : array();

		return $spacing['blockGap'] ?? null;
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
	private static function get_gap_side( array $block_gap, string $side ): string {
		return is_scalar( $block_gap[ $side ] ?? null ) ? (string) $block_gap[ $side ] : '1px';
	}

	/**
	 * Extract and resolve blockGap attribute to a valid CSS gap string.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return string CSS gap value (e.g., 'var(--wp--preset--spacing--50)' or '10px 20px') or empty string.
	 */
	public static function get_block_gap_value( array $attributes ): string {
		$block_gap = self::get_raw_block_gap( $attributes );

		if ( empty( $block_gap ) ) {
			return '';
		}

		if ( is_string( $block_gap ) ) {
			return self::resolve_preset_value( $block_gap );
		}

		// Handle axial/split (vertical and horizontal) gap objects.
		if ( is_array( $block_gap ) ) {
			$top  = self::resolve_preset_value( self::get_gap_side( $block_gap, 'top' ) );
			$left = self::resolve_preset_value( self::get_gap_side( $block_gap, 'left' ) );

			// A side that is not safe CSS drops the whole gap.
			return '' !== $top && '' !== $left ? "{$top} {$left}" : '';
		}

		return '';
	}

	/**
	 * Extract the column (horizontal) gap from the blockGap attribute.
	 *
	 * The gap can be a single value or a split { top, left } object; the
	 * column gap is the single value or 'left'. Day cells use it in calc()
	 * to stay square, so it must be one length: '0' becomes '0px'.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return string One CSS length (e.g., 'var(--wp--preset--spacing--50)' or '20px') or empty string.
	 */
	public static function get_block_column_gap_value( array $attributes ): string {
		$block_gap = self::get_raw_block_gap( $attributes );

		// Match get_block_gap_value(): it outputs no gap for an empty value.
		if ( empty( $block_gap ) ) {
			return '';
		}

		if ( is_array( $block_gap ) ) {
			// Same default as get_block_gap_value() for a missing side.
			$block_gap = self::get_gap_side( $block_gap, 'left' );
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
	 * Preset slugs keep only lowercase letters, digits, dashes and underscores.
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
			return sprintf( 'var(--wp--preset--%s--%s)', sanitize_key( $parts[1] ), sanitize_key( $parts[2] ) );
		}

		if ( preg_match( '#[;{}\\\\<>"\']|/\*#', $value ) ) {
			return '';
		}

		return esc_attr( $value );
	}
}
