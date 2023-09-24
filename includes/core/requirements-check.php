<?php
/**
 * GatherPress Plugin Requirements Check.
 *
 * This file checks the system requirements before loading the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

$gatherpress_preflight_return = true;

// Check the PHP version to ensure compatibility with the plugin.
if ( version_compare( PHP_VERSION_ID, GATHERPRESS_MINIMUM_PHP_VERSION, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo sprintf(
						/* translators: %1$s: minimum PHP version, %2$s current PHP version. */
						esc_html__(
							'GatherPress requires PHP %1$s or higher. Your current PHP version is %2$s. Please upgrade.',
							'gatherpress'
						),
						esc_html( GATHERPRESS_MINIMUM_PHP_VERSION ),
						esc_html( phpversion() )
					);
					?>
				</p>
			</div>
			<?php
		}
	);

	$gatherpress_preflight_return = false;
}

return $gatherpress_preflight_return;
