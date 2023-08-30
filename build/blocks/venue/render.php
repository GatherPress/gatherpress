<?php
/**
 * Render Venue block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_venue = Venue::get_instance();
$attributes        = array_merge(
	$attributes,
	$gatherpress_venue->get_venue_meta( get_the_ID(), get_post_type() )
);

if (
	empty( $attributes['name'] ) &&
	empty( $attributes['fullAddress'] ) &&
	empty( $attributes['phoneNumber'] ) &&
	empty( $attributes['website'] )
) {
	return;
}

//$gatherpress_full_address = $attributes['fullAddress'];

?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<div class="gp-venue">
		<div data-gp_block_name="venue" data-gp_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>

		<?php if ( $attributes['mapShow'] ) : ?>
			<div data-gp_block_name="map-embed" data-gp_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
		<?php endif; ?>
	</div>
</div>
