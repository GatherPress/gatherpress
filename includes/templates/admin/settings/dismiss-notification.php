<?php
/**
 * Template for GatherPress settings pages.
 *
 * This template is used to display and manage settings for the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param array  $sub_pages An array of sub-pages and their corresponding values.
 * @param string $page      The current settings page.
 */

$nonce = wp_nonce_url( add_query_arg( 'action', 'suppress_gp_membership_notification' ), 'clear-notification' );

?>
	<div class="notice notice-warning is-dismissible" id="gp-membership">
		<p>
			<?php
				/* translators: %s: search term */
				printf(
					'%s <a href=%s>%s</a>. <a href=%s><button>Dismiss forever</button></a>',
					esc_html__( 'To ensure GatherPress functions optimally, we recommend enabling user registration. You can do so by', 'gatherpress' ),
					esc_url( admin_url( 'options-general.php#users_can_register' ) ),
					esc_html__( 'enabling user registration here', 'gatherpress' ),
					esc_url( $nonce )
				);
				?>
		</p>
	</div>
<?php
