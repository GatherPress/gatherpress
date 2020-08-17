<?php
/**
 * Custom template tags for GatherPress
 *
 * @package gatherpress
 */
if ( ! function_exists( 'gp_classes' ) ) {

	function gp_classes( $filter, $classes ) {
		echo esc_attr( apply_filters( $filter, $classes ) );
	}
}

if ( ! function_exists( 'gp_button_classes' ) ) {

	function gp_button_classes() {
		gp_classes( 'gatherpress_button_classes', 'bg-gray-700 text-gray-200 font-semibold py-2 px-4 rounded inline-flex items-center' );
	}
}

// EOF
