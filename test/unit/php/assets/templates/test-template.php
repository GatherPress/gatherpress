<?php
/**
 * Test template.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 0.27.0
 */

if ( ! isset( $description ) ) {
	return;
}
?>
<p>
	<?php echo esc_html( $description ); ?>
</p>
