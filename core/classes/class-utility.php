<?php
/**
 * Class is responsible for all utility related functionality.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Utility.
 */
class Utility {

	/**
	 * Render template.
	 *
	 * @param string $path      Path to template.
	 * @param array  $variables Array of variables to pass to template.
	 *
	 * @return string
	 */
	public static function render_template( string $path, array $variables = array() ) : string {
		if ( ! file_exists( $path ) ) {
			return '';
		}

		if ( ! empty( $variables ) ) {
			extract( $variables, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		ob_start();

		require $path; // better to fail with an error than to continue with incorrect/weird data.

		return ob_get_clean();
	}

}
