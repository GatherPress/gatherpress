<?php
/**
 * GatherPress Plugin Requirements Check.
 *
 * This file checks the system requirements before loading the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_activation = true;

// Check the PHP version to ensure compatibility with the plugin.
if ( version_compare( PHP_VERSION, GATHERPRESS_REQUIRES_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			wp_admin_notice(
				sprintf(
					/* translators: %1$s: minimum PHP version, %2$s: current PHP version. */
					esc_html__(
						'GatherPress requires %1$s or higher. Your current PHP version is %2$s. Please upgrade.',
						'gatherpress'
					),
					esc_html( GATHERPRESS_REQUIRES_PHP ),
					esc_html( phpversion() )
				),
				array(
					'type' => 'error',
				)
			);
		}
	);

	$gatherpress_activation = false;
}

// Check if build directory exists. Show an admin notice if it doesn't.
// This is crucial for first-time installations after cloning the repo,
// as the build files are not committed to version control and must be
// generated locally with npm.
if ( ! is_dir( GATHERPRESS_CORE_PATH . '/build' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			wp_admin_notice(
				sprintf(
					/* translators: %1$s: build command, %2$s: the plugin path */
					esc_html__(
						// phpcs:ignore Generic.Files.LineLength.TooLong
						'Please run %1$s in the %2$s plugin directory to generate required assets. This is needed after first cloning the plugin for development.',
						'gatherpress'
					),
					'<code>npm run build</code>',
					'<code>gatherpress</code>'
				),
				array(
					'type' => 'error',
				)
			);
		}
	);

	$gatherpress_activation = false;
}

return $gatherpress_activation;
