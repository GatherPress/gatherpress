<?php
/**
 * Duplicate GatherPress Plugin Check.
 *
 * This file checks to determine if another version of GatherPress is already running.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

$gatherpress_activated = false;

if ( defined( 'GATHERPRESS_VERSION' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			wp_admin_notice(
				esc_html__(
					// phpcs:disable Generic.Files.LineLength.TooLong
					'You have more than one version of GatherPress installed and activated. Please activate only one version of GatherPress at a time.',
					// phpcs:enable Generic.Files.LineLength.TooLong
					'gatherpress'
				),
				array(
					'type' => 'error',
				)
			);
		}
	);

	$gatherpress_activated = true;
}

return $gatherpress_activated;
