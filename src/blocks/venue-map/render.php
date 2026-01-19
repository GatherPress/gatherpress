<?php
/**
 * Render Venue Map block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_venue      = Venue::get_instance();
$gatherpress_venue_meta = $gatherpress_venue->get_venue_meta( get_the_ID(), get_post_type() );

// Get venue data.
$venue_address   = $gatherpress_venue_meta['fullAddress'] ?? '';
$venue_latitude  = $gatherpress_venue_meta['latitude'] ?? '';
$venue_longitude = $gatherpress_venue_meta['longitude'] ?? '';

if ( empty( $venue_address ) ) {
	return;
}

// Prepare attributes for the map.
$map_attrs = array(
	'fullAddress'  => $venue_address,
	'latitude'     => $venue_latitude,
	'longitude'    => $venue_longitude,
	'mapZoomLevel' => $attributes['zoom'] ?? 10,
	'mapType'      => $attributes['type'] ?? 'roadmap',
	'mapHeight'    => $attributes['height'] ?? 300,
);

?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<div data-gatherpress_block_name="map-embed" data-gatherpress_block_attrs="<?php echo esc_attr( htmlspecialchars( wp_json_encode( $map_attrs ), ENT_QUOTES, 'UTF-8' ) ); ?>"></div>
</div>
