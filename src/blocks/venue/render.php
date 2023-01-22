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

// phpcs:ignore
$gp_venue_map = ( get_post( $gatherpress_venue->ID )->post_content ?: '' );

$gatherpress_venue_information = json_decode( get_post_meta( $gatherpress_venue->ID, '_venue_information', true ) );

// echo '<pre>' . print_r( $gatherpress_venue, true ) . '</pre>';

// echo '<pre>' . print_r( $gp_venue_map, true ) . '</pre>';

if ( $gatherpress_venue_information ) {
	Utility::render_template(
		sprintf( '%s/build/blocks/venue-information/render.php', GATHERPRESS_CORE_PATH ),
		array(
			'attributes' => array(
				'name'              => $gatherpress_venue->post_title,
				'fullAddress'       => $gatherpress_venue_information->fullAddress ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'encodedAddressURL' => $gatherpress_venue_information->encodedAddressURL ?? 'https://maps.google.com/maps?q=' . rawurlencode( $gatherpress_venue_information->fullAddress ) . '&z=10&t=m&output=embed', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'phoneNumber'       => $gatherpress_venue_information->phoneNumber ?? '', // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'website'           => $gatherpress_venue_information->website ?? '',
			),
		),
		true
	);
} else {
	printf(
		'<div %s>%s</div>',
		wp_kses_data( get_block_wrapper_attributes() ),
		wp_kses(
			$gp_venue_map,
			array(
				'iframe' => array(
					'src'             => array(),
					'width'           => array(),
					'height'          => array(),
					'title'           => array(),
					'allow'           => array(),
					'allowfullscreen' => array(),
					'frameborder'     => array(),
				),
			)
		)
	);
}
