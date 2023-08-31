<?php
/**
 * Render Venue block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_venue      = Venue::get_instance();
$gatherpress_attributes = array_merge(
	$attributes,
	$gatherpress_venue->get_venue_meta( get_the_ID(), get_post_type() )
);

// Don't render name on venue post.
if ( Venue::POST_TYPE === get_post_type() ) {
	$gatherpress_attributes['name'] = '';
}

?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<div class="gp-venue">
		<div data-gp_block_name="venue" data-gp_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $gatherpress_attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>

		<?php if ( $attributes['mapShow'] ) : ?>
			<div data-gp_block_name="map-embed" data-gp_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $gatherpress_attributes ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
		<?php endif; ?>
	</div>
</div>
