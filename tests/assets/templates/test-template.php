<?php
/**
 * Test template.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

if ( ! isset( $description ) ) {
	return;
}
?>
<p>
	<?php echo esc_html( $description ); ?>
</p>
