<?php
/**
 * Render Icon block.
 *
 * Dynamically renders an inline SVG for the Icon block with customizable size and color.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

$gatherpress_icon        = ! empty( $attributes['icon'] ) ? $attributes['icon'] : 'nametag';
$gatherpress_icon_color  = ! empty( $attributes['iconColor'] ) ? $attributes['iconColor'] : 'inherit';
$gatherpress_icon_size   = ! empty( $attributes['iconSize'] ) ? $attributes['iconSize'] : 20;
$gatherpress_svg_file    = GATHERPRESS_CORE_PATH . '/includes/assets/svg/' . $gatherpress_icon . '.svg';
$gatherpress_svg_content = '<svg><text x="0" y="15">' . esc_html__( 'SVG Error', 'gatherpress' ) . '</text></svg>';

if ( is_readable( $gatherpress_svg_file ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local plugin asset, not a remote URL.
	$gatherpress_svg_content = (string) file_get_contents( $gatherpress_svg_file );
}

$gatherpress_styles = sprintf(
	'width:%dpx;height:%dpx;line-height:0;fill:%s;',
	intval( $gatherpress_icon_size ),
	intval( $gatherpress_icon_size ),
	esc_attr( $gatherpress_icon_color )
);

$gatherpress_kses_defaults = wp_kses_allowed_html( 'post' );
$gatherpress_svg_args      = array(
	'svg'   => array(
		'class'           => true,
		'aria-hidden'     => true,
		'aria-labelledby' => true,
		'role'            => true,
		'xmlns'           => true,
		'width'           => true,
		'height'          => true,
		'viewbox'         => true, // Must be lowercase!
	),
	'g'     => array( 'fill' => true ),
	'title' => array( 'title' => true ),
	'path'  => array(
		'd'    => true,
		'fill' => true,
	),
);
$gatherpress_allowed_tags  = array_merge( $gatherpress_kses_defaults, $gatherpress_svg_args );

printf(
	'<div %1$s><div style="%2$s">%3$s</div></div>',
	wp_kses_data( get_block_wrapper_attributes() ),
	esc_attr( $gatherpress_styles ),
	wp_kses( $gatherpress_svg_content, $gatherpress_allowed_tags )
);
