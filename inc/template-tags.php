<?php
/**
 * Custom template tags for GatherPress
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( ! function_exists( 'gp_classes' ) ) {

	/**
	 * Helper class to print classes and apply filter.
	 *
	 * @param string $filter
	 * @param string $classes
	 */
	function gp_classes( string $filter, string $classes ) {
		echo esc_attr( apply_filters( $filter, $classes ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
	}
}

if ( ! function_exists( 'gp_button_classes' ) ) {

	/**
	 * Helper function to get default GatherPress button classes.
	 */
	function gp_button_classes() {
		gp_classes( 'gatherpress_button_classes', 'bg-gray-700 text-gray-200 font-semibold py-2 px-4 rounded inline-flex items-center' );
	}
}
