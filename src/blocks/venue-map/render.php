<?php
/**
 * Render Venue Map block.
 *
 * Emits the pre-rendered static map as the baseline (no JavaScript required).
 * When `renderMode === 'interactive'` the wrapper also carries the data
 * attributes the view.js script reads to swap the `<img>` for a live Leaflet
 * map at hydration time. When the venue has no coordinates yet — e.g. a brand
 * new venue that hasn't been geocoded — a short placeholder surfaces the
 * "map coming soon" state in place of the image.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Venue_Setup;
use GatherPress\Core\Venue_Map;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_post_id     = (int) get_the_ID();
$gatherpress_post_type   = (string) get_post_type();
$gatherpress_venue_setup = Venue_Setup::get_instance();
$gatherpress_venue_meta  = $gatherpress_venue_setup->get_venue_meta( $gatherpress_post_id, $gatherpress_post_type );

$gatherpress_address = (string) ( $gatherpress_venue_meta['fullAddress'] ?? '' );

if ( '' === $gatherpress_address ) {
	return;
}

$gatherpress_render_mode    = 'static' === ( $attributes['renderMode'] ?? Venue_Map::DEFAULT_RENDER_MODE )
	? 'static'
	: 'interactive';
$gatherpress_zoom           = (int) ( $attributes['zoom'] ?? Venue_Map::DEFAULT_ZOOM );
$gatherpress_height         = (int) ( $attributes['height'] ?? Venue_Map::DEFAULT_HEIGHT );
$gatherpress_static_map_url = Venue_Map::get_instance()->get_url_for_post(
	$gatherpress_post_id,
	$gatherpress_post_type,
	$gatherpress_zoom,
	$gatherpress_height
);

// Hydration data sits on the outer wrapper so view.js can replace the
// wrapper's children with a live Leaflet map (the `<img>` inside is a void
// element that React can't mount into directly).
$gatherpress_wrapper_attr_args = array(
	'class'            => sprintf( 'gatherpress-venue-map gatherpress-venue-map--%s', $gatherpress_render_mode ),
	'data-render-mode' => $gatherpress_render_mode,
	// Apply the block-level height to the wrapper so the static <img> (with
	// object-fit: cover) fills it, and so the interactive Leaflet container
	// gets the exact same footprint after hydration.
	'style'            => sprintf( 'height:%dpx;', $gatherpress_height ),
);

if ( 'interactive' === $gatherpress_render_mode ) {
	$gatherpress_block_attrs = array(
		'fullAddress'  => $gatherpress_address,
		'latitude'     => (string) ( $gatherpress_venue_meta['latitude'] ?? '' ),
		'longitude'    => (string) ( $gatherpress_venue_meta['longitude'] ?? '' ),
		'mapZoomLevel' => $attributes['zoom'] ?? Venue_Map::DEFAULT_ZOOM,
		'mapType'      => $attributes['type'] ?? Venue_Map::DEFAULT_MAP_TYPE,
		'mapHeight'    => $attributes['height'] ?? Venue_Map::DEFAULT_HEIGHT,
		'mapPlatform'  => Settings::get_instance()->get( 'map_platform' ),
		'pluginUrl'    => GATHERPRESS_CORE_URL,
	);

	$gatherpress_wrapper_attr_args['data-gatherpress_block_name']  = 'map-embed';
	$gatherpress_wrapper_attr_args['data-gatherpress_block_attrs'] = htmlspecialchars(
		(string) wp_json_encode( $gatherpress_block_attrs ),
		ENT_QUOTES,
		'UTF-8'
	);
}

printf(
	'<div %s>',
	get_block_wrapper_attributes( $gatherpress_wrapper_attr_args ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped by core.
);

if ( '' !== $gatherpress_static_map_url ) {
	/* translators: %s: full address of the venue. */
	$gatherpress_alt = sprintf( __( 'Map of %s', 'gatherpress' ), $gatherpress_address );

	printf(
		'<img class="gatherpress-venue-map__image" src="%s" alt="%s" loading="lazy" />',
		esc_url( $gatherpress_static_map_url ),
		esc_attr( $gatherpress_alt )
	);

	// CartoDB + OpenStreetMap attribution is required whenever the static
	// image is on display. Leaflet ships its own attribution control, so when
	// the interactive map hydrates it replaces this caption entirely.
	printf(
		'<small class="gatherpress-venue-map__attribution">%s</small>',
		wp_kses(
			Settings::get_map_tile_attribution(),
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
			)
		)
	);
} else {
	printf(
		'<div class="gatherpress-venue-map__placeholder">%s</div>',
		esc_html__( 'Map is being prepared, check back in a moment.', 'gatherpress' )
	);
}

echo '</div>';
