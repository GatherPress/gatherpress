<?php
/**
 * GatherPress Plugin Requirements Check.
 *
 * This file checks the system requirements before loading the GatherPress plugin.
 *
 * Runs before the autoloader is registered, so the Notice class it uses is
 * required explicitly rather than autoloaded. Notice is deliberately written
 * to parse on very old PHP for this reason: on a site below the PHP floor, a
 * parse error here would replace the "please upgrade" notice with a fatal, so
 * the file meant to explain the problem would instead become one.
 *
 * Notices raised here are non-persistent by design. Dismissal is recorded by
 * GatherPress\Core\Admin\Notifications, which only loads once requirements
 * pass, and a blocking requirement failure is not something an administrator
 * should be able to silence anyway.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

use GatherPress\Core\Admin\Notice;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

require_once GATHERPRESS_CORE_PATH . '/includes/core/classes/admin/class-notice.php';

$gatherpress_activation = true;

// Check the PHP version to ensure compatibility with the plugin.
if ( version_compare( PHP_VERSION, GATHERPRESS_REQUIRES_PHP, '<' ) ) {
	$gatherpress_php_notice = new Notice(
		'gatherpress_requires_php',
		array(
			'type'        => Notice::TYPE_ERROR,
			'dismissible' => false,
			'message'     => static function () {
				return sprintf(
					/* translators: %1$s: minimum PHP version, %2$s: current PHP version. */
					esc_html__(
						'GatherPress requires %1$s or higher. Your current PHP version is %2$s. Please upgrade.',
						'gatherpress'
					),
					esc_html( GATHERPRESS_REQUIRES_PHP ),
					esc_html( phpversion() )
				);
			},
		)
	);

	add_action( 'admin_notices', array( $gatherpress_php_notice, 'render' ) );

	$gatherpress_activation = false;
}

// Check if build directory exists. Show an admin notice if it doesn't.
// This is crucial for first-time installations after cloning the repo,
// as the build files are not committed to version control and must be
// generated locally with npm.
if ( ! is_dir( GATHERPRESS_CORE_PATH . '/build' ) ) {
	$gatherpress_build_notice = new Notice(
		'gatherpress_missing_build',
		array(
			'type'        => Notice::TYPE_ERROR,
			'dismissible' => false,
			'message'     => static function () {
				return sprintf(
					/* translators: %1$s: build command, %2$s: the plugin path */
					esc_html__(
						// phpcs:disable Generic.Files.LineLength.TooLong
						'Please run %1$s in the %2$s plugin directory to generate required assets. This is needed after first cloning the plugin for development.',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					'<code>npm run build</code>',
					'<code>gatherpress</code>'
				);
			},
		)
	);

	add_action( 'admin_notices', array( $gatherpress_build_notice, 'render' ) );

	$gatherpress_activation = false;
}

return $gatherpress_activation;
