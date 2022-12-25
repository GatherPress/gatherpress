<?php
/**
 * Render Venue block.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

use GatherPress\Core\Utility;
use GatherPress\Core\Venue;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_venue = get_post( intval( $attributes['venueId'] ) );

if ( Venue::POST_TYPE !== get_post_type( $gatherpress_venue ) ) {
	return;
}

$venue_information = get_block_wrapper_attributes();
if ( $attributes['className'] ) {
	$venue_information = 'class="' . $attributes['className'] . '"';
}

$gatherpress_venue_information = json_decode( get_post_meta( $gatherpress_venue->ID, '_venue_information', true ) );
?>
<div <?php echo $venue_information; ?>>
	<?php
	Utility::render_template(
		sprintf( '%s/build/blocks/venue-information/render.php', GATHERPRESS_CORE_PATH ),
		array(
			'gatherpress_block_attrs' => array(
				'name'        => $gatherpress_venue->post_title,
				'fullAddress' => $gatherpress_venue_information->fullAddress ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'phoneNumber' => $gatherpress_venue_information->phoneNumber ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'website'     => $gatherpress_venue_information->website ?? '',
			),
		),
		true
	);
	echo '<pre>' . print_r( $attributes, true ) . '</pre>';
	?>
</div>
