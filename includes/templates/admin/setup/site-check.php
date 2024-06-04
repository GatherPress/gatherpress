<?php
/**
 * Admin Notice for open membership check.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

?>
	<div class="gatherpress-admin__site-check notice notice-warning">
		<div>
			<?php
			printf(
				/* translators: 1: Enabling user registration 2: Setting time zone */
				esc_html__( 'To ensure GatherPress functions optimally, we recommend enabling user registration and setting your site\'s timezone. You can do so by %1$s and %2$s', 'gatherpress' ),
				'<a href=' . esc_url( admin_url( 'options-general.php#users_can_register' ) ) . '>'
				. esc_html_x( 'enabling user registration here', 'Context: To ensure GatherPress functions optimally, user registration and setting your site\'s timezone. You can do so by %1$s and %2$s.', 'gatherpress' )
				. '</a>',
				'<a href=' . esc_url( admin_url( 'options-general.php#timezone_string' ) ) . '>'
				. esc_html_x( 'setting time zone here', 'Context: To ensure GatherPress functions optimally, user registration and setting your site\'s timezone. You can do so by %1$s and %2$s.', 'gatherpress' )
				. '</a>'
			);
			?>
		</div>
		<div>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'gatherpress_suppress_site_notification' ), 'clear-notification' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Dismiss forever', 'gatherpress' ); ?>
			</a>
		</div>
	</div>
<?php
