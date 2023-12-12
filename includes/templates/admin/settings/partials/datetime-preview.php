<?php
/**
 * Render a preview of the given datetime value.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 *
 * @param string $name  The name parameter.
 * @param string $value The value parameter representing a datetime.
 */

if ( ! isset( $name, $value ) ) {
	return;
}
?>
<p>
	<strong><?php esc_html_e( 'Preview:', 'gatherpress' ); ?></strong> <span><?php echo esc_html( date_i18n( $value ) ); ?></span>
</p>
