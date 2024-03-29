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

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

use GatherPress\Core\Settings;
use GatherPress\Core\Utility;

if ( ! isset( $sub_pages, $page ) ) {
	return;
}

$gatherpress_settings = Settings::get_instance();
?>
<div class="wrap gp-settings">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'GatherPress Settings', 'gatherpress' ); ?>
	</h1>

	<?php settings_errors(); ?>

	<h2 class="nav-tab-wrapper">
		<?php
		foreach ( $sub_pages as $gatherpress_sub_page => $gatherpress_value ) {
			$gatherpress_active_page = ( Utility::prefix_key( $gatherpress_sub_page ) === $page ) ? 'nav-tab-active' : '';
			$gatherpress_url         = add_query_arg(
				array( 'page' => Utility::prefix_key( $gatherpress_sub_page ) ),
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
	<?php do_action( 'gatherpress_settings_section', $page ); ?>
</div>
