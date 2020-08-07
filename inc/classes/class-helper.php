<?php

namespace GatherPress\Inc;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helper {

	/**
	 * Render template.
	 *
	 * @param string $path
	 * @param array  $variables
	 *
	 * @return string
	 */
	public static function render_template( string $path, array $variables = [] ) : string {

		if ( ! file_exists( $path ) ) {
			return '';
		}

		if ( ! empty( $variables ) ) {
			extract( $variables, EXTR_SKIP );
		}

		ob_start();

		require $path; // better to fail with an error than to continue with incorrect/weird data

		return ob_get_clean();

	}

	public static function anchor_classes() {

		return apply_filters( 'gatherpress_anchor_classes', 'text-blue-500 hover:text-blue-800' );

	}

	public static function button_classes() {

		return apply_filters( 'gatherpress_button_classes', '' );

	}

	public static function button_primary_classes() {

	}

}

//EOF
