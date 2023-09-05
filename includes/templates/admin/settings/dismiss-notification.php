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

wp_nonce_field( 'suppress_gp_membership_notification', 'suppress_gp_membership' );

?>
	<div class="notice notice-warning is-dismissible" id="gp-membership">
		<p>
			<?php
				/* translators: %s: search term */
				__(
					printf(
						'To hide GatherPress functions optimally, we recommend enabling user registration. You can do so by <a href=%s>enabling user registration here</a>. <a href=%s><button>Dismiss forever</button></a>',
						esc_url(
							admin_url( 'options-general.php#users_can_register' )
						),
						wp_nonce_url( add_query_arg( 'action', 'suppress_gp_membership_notification' ), 'clear-notification' ),
						'gatherpress'
					)
				);
				?>
		</p>
	</div>
<?php
