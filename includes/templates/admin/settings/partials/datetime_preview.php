<?php
if ( ! isset( $name, $value ) ) {
	return;
}
?>
<p>
	<strong><?php esc_html_e( 'Preview:' ); ?></strong> <span><?php echo esc_html( date_i18n( $value ) ); ?></span>
</p>
