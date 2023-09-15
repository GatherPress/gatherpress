<?php
/**
 * Admin Notice for open membership check.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

?>
	<div class="gp-admin__membership-check notice notice-warning">
		<div>
			<?php esc_html_e( 'To ensure GatherPress functions optimally, we recommend enabling user registration. You can do so by' ); ?>
			<a href="<?php echo esc_url( admin_url( 'options-general.php#users_can_register' ) ); ?>">
				<?php esc_html_e( 'enabling user registration here' ); ?>
			</a>.
		</div>
		<div>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'suppress_gp_membership_notification' ), 'clear-notification' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Dismiss forever', 'gatherpress' ); ?>
			</a>
		</div>
	</div>
<?php
