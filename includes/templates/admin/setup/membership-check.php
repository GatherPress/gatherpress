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
	<div class="gatherpress-admin__membership-check notice notice-warning">
		<div>
			<?php
			printf(
				/* translators: %s: "enabling user registration here" (hyperlinked) */
				esc_html__( 'To ensure GatherPress functions optimally, we recommend enabling user registration and setting your site\'s timezone. You can do so by %s and %s', 'gatherpress' ),
				'<a href=' . esc_url( admin_url( 'options-general.php#users_can_register' ) ) . '>'
				. esc_html_x( 'enabling user registration here', 'Context: To ensure GatherPress functions optimally, user registration and setting your site\'s timezone. You can do so by %s and %s.', 'gatherpress' )
				. '</a>',
				'<a href=' . esc_url( admin_url( 'options-general.php#timezone_string' ) ) . '>'
				. esc_html_x( 'setting time zone here', 'Context: To ensure GatherPress functions optimally, user registration and setting your site\'s timezone. You can do so by %s and %s.', 'gatherpress' )
				. '</a>'
			);
			?>
		</div>
		<div>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'gatherpress_suppress_membership_notification' ), 'clear-notification' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Dismiss forever', 'gatherpress' ); ?>
			</a>
		</div>
	</div>
<?php
