<?php
/**
 * Hidden Form Field Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! isset( $gatherpress_input_attributes ) ) {
	return;
}
?>

<input<?php echo wp_kses_data( $input_attributes ); ?> />
