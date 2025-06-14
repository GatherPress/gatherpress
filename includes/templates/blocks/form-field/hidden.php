<?php
/**
 * Hidden Form Field Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $gatherpress_attrs ) || ! is_array( $gatherpress_attrs ) ) {
	return;
}
?>

<input type="hidden" name="<?php echo esc_attr( $gatherpress_attrs['field_name'] ); ?>" value="<?php echo esc_attr( $gatherpress_attrs['field_value'] ); ?>" />
