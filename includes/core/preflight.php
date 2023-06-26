<?php
/**
 * Checks requirements before loading plugin.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

$gatherpress_preflight_return = true;

// Check version of PHP before loading plugin.
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
						esc_html__( 'GatherPress requires PHP Version %1$s or greater. You are currently running PHP %2$s. Please upgrade.', 'gatherpress' ),
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
