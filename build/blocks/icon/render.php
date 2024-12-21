<?php
/**
 * Render Icon block.
 *
 * Dynamically renders an inline SVG for the Icon block with customizable size and color.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

$gatherpress_icon         = $attributes['gatherpress_icon'] ?? 'yes-alt';
$gatherpress_icon_color   = $attributes['iconColor'] ?? '';
$gatherpress_icon_size    = $attributes['iconSize'] ?? 20;
$gatherpress_svg_base_url = GATHERPRESS_CORE_URL . '/assets/svg/';
$gatherpress_svg_url      = $gatherpress_svg_base_url . $gatherpress_icon . '.svg';
$gatherpress_svg_content  = '<svg><text x="0" y="15">' . esc_html__( 'SVG Error', 'gatherpress' ) . '</text></svg>';
$gatherpress_response     = wp_safe_remote_get( $gatherpress_svg_url );

if ( is_array( $gatherpress_response ) && ! is_wp_error( $gatherpress_response ) ) {
	$gatherpress_http_code = wp_remote_retrieve_response_code( $gatherpress_response );

	if ( 200 === $gatherpress_http_code ) {
		$gatherpress_svg_content = wp_remote_retrieve_body( $gatherpress_response );
	}
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
	'<div class="wp-block-gatherpress-gatherpress_icon-block" style="%s">%s</div>',
	esc_attr( $gatherpress_styles ),
	wp_kses( $gatherpress_svg_content, $gatherpress_allowed_tags )
);
