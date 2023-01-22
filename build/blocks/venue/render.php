<?php
/**
 * Render Venue block.
 *
 * @package    GatherPress
 * @subpackage Core
 * @since      1.0.0
 */

use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_venue = get_post( intval( $attributes['venueId'] ?? 0 ) );

if ( Venue::POST_TYPE !== get_post_type( $gatherpress_venue ) ) {
	return;
}

$gatherpress_venue_information = json_decode( get_post_meta( $gatherpress_venue->ID, '_venue_information', true ) );

$attributes['encoded_addy'] = 'https://maps.google.com/maps?q=' . urlencode( $gatherpress_venue_information->fullAddress ) . '&z=' . urlencode( $attributes['zoom'] ) . '&t=' . urlencode( $attributes['type'] ) . '&output=embed';

?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<iframe
		src="<?php echo esc_attr( $attributes['encoded_addy'] ); ?>"
		title="<?php echo esc_attr( $gatherpress_venue_information->fullAddress ); ?>"
		style="height:<?php echo esc_attr( $attributes['deskHeight'] ); ?>px"
	></iframe>
</div>
<?php
