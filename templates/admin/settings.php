<?php
/**
 * Settings template.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

use GatherPress\Core\Settings;

if ( ! isset( $sub_pages, $page ) ) {
	return;
}

$gatherpress_settings = Settings::get_instance();
?>
<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'GatherPress Settings', 'gatherpress' ); ?>
	</h1>
	<h2 class="nav-tab-wrapper">
		<?php
		foreach ( $sub_pages as $gatherpress_sub_page => $gatherpress_value ) {
			$gatherpress_active_page = ( $page === $gatherpress_settings->prefix_key( $gatherpress_sub_page ) ) ? 'nav-tab-active' : '';
			$gatherpress_url         = add_query_arg(
				array( 'page' => $gatherpress_settings->prefix_key( $gatherpress_sub_page ) ),
				admin_url( $gatherpress_settings::PARENT_SLUG )
			);
			?>
			<a class="<?php echo esc_attr( 'nav-tab ' . $gatherpress_active_page ); ?>" href="<?php echo esc_url( $gatherpress_url ); ?>">
				<?php echo esc_html( $gatherpress_value['name'] ); ?>
			</a>
			<?php
		}
		?>
	</h2>
	<?php if ( $gatherpress_settings->prefix_key( 'credits' ) === $page ) : ?>
		<?php do_settings_sections( $page ); ?>
	<?php else : ?>
		<form method="post" action="options.php">
			<?php settings_fields( $page ); ?>
			<?php do_settings_sections( $page ); ?>

			<?php submit_button( __( 'Save Settings', 'gatherpress' ) ); ?>
		</form>
	<?php endif; ?>
</div>
