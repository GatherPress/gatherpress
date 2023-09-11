<?php
/**
 * Render a settings form for GatherPress settings pages.
 *
 * This code snippet is responsible for rendering a settings form for GatherPress settings pages.
 * It includes settings fields, sections, and a "Save Settings" button for user interaction.
 *
 * @package GatherPress\Core
 * @param string $page The slug of the current settings page.
 * @since 1.0.0
 */

if ( ! isset( $page ) ) {
	return;
}
?>

<form method="post" action="options.php">
	<?php settings_fields( $page ); ?>
	<?php do_settings_sections( $page ); ?>

	<?php submit_button( __( 'Save Settings', 'gatherpress' ) ); ?>
</form>
