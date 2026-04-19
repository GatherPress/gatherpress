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
 * The wrapper sizing is driven by three block attributes:
 *
 *   - `width` / `height`: explicit pixel dimensions. Either can be `0`
 *     meaning "auto" — in which case it's derived from the other side and
 *     `aspectRatio`. When both are auto, the block fills its container
 *     width and computes height from the ratio (CSS aspect-ratio on the
 *     interactive wrapper; the static PNG is composed at the effective
 *     pixel size).
 *   - `aspectRatio`: CSS-style string (e.g. "16/9"). Drives auto-
 *     dimension math on the server and also lands as a CSS
 *     `aspect-ratio` hint on the wrapper so an aligned block that
 *     shrinks stays at the right shape.
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

$gatherpress_render_mode = 'static' === ( $attributes['renderMode'] ?? Venue_Map::DEFAULT_RENDER_MODE )
	? 'static'
	: 'interactive';
$gatherpress_zoom        = (int) ( $attributes['zoom'] ?? Venue_Map::DEFAULT_ZOOM );
$gatherpress_raw_width   = (int) ( $attributes['width'] ?? 0 );
$gatherpress_raw_height  = (int) ( $attributes['height'] ?? Venue_Map::DEFAULT_HEIGHT );
$gatherpress_ratio       = (string) ( $attributes['aspectRatio'] ?? Venue_Map::DEFAULT_ASPECT_RATIO );

// Allow-list for the `scale` block attribute. Anything outside this set
// (a hand-edited block attr, a filter that mutates the value, a migration
// miss) falls back to `cover` so the inline style stays safe and
// predictable.
$gatherpress_scale_candidate = (string) ( $attributes['scale'] ?? Venue_Map::DEFAULT_SCALE );
$gatherpress_scale           = in_array( $gatherpress_scale_candidate, Venue_Map::SCALE_OPTIONS, true )
	? $gatherpress_scale_candidate
	: Venue_Map::DEFAULT_SCALE;

$gatherpress_static_map_url = Venue_Map::get_instance()->get_url_for_post(
	$gatherpress_post_id,
	$gatherpress_post_type,
	$gatherpress_zoom,
	$gatherpress_raw_width,
	$gatherpress_raw_height,
	$gatherpress_ratio
);

// Hydration data sits on the outer wrapper so view.js can replace the
// wrapper's children with a live Leaflet map (the `<img>` inside is a void
// element that React can't mount into directly).
$gatherpress_wrapper_attr_args = array(
	'class'            => sprintf( 'gatherpress-venue-map gatherpress-venue-map--%s', $gatherpress_render_mode ),
	'data-render-mode' => $gatherpress_render_mode,
);

// Sizing rules:
// - Explicit height → inline height in px.
// - Explicit width  → inline width in px (but NOT when the block is
// aligned wide or full — then the alignment owns the horizontal space
// via the `.alignwide` / `.alignfull` CSS rules, and a hard pixel
// width would fight those classes).
// - Any auto dimension → CSS `aspect-ratio` stamp so the container can
// still give the block its shape as its surrounding width changes
// (aligned block, responsive container, etc.). Static <img> inside
// uses object-fit: cover so the raster stays crisp at any size.
$gatherpress_styles          = array();
$gatherpress_align           = (string) ( $attributes['align'] ?? '' );
$gatherpress_is_wide_or_full = in_array( $gatherpress_align, array( 'wide', 'full' ), true );

if ( 0 < $gatherpress_raw_height ) {
	$gatherpress_styles[] = sprintf( 'height:%dpx', $gatherpress_raw_height );
}

if ( 0 < $gatherpress_raw_width && ! $gatherpress_is_wide_or_full ) {
	$gatherpress_styles[] = sprintf( 'width:%dpx', $gatherpress_raw_width );
}

if ( 0 === $gatherpress_raw_width || 0 === $gatherpress_raw_height || $gatherpress_is_wide_or_full ) {
	// The `aspectRatio` block attr lands directly in an inline style.
	// get_block_wrapper_attributes() doesn't sanitize individual CSS
	// values, so an editor-role attacker with edit_posts could otherwise
	// stamp a payload like `1/1;background:url(...)`. Accept only the
	// narrow `N/N` or `N:N` form the block ever emits; anything else
	// falls back to the server default. The pattern is shared with the
	// REST validator so the two can never drift apart.
	$gatherpress_ratio_candidate = '' !== $gatherpress_ratio
		? $gatherpress_ratio
		: Venue_Map::DEFAULT_ASPECT_RATIO;
	$gatherpress_ratio_is_valid  = (bool) preg_match(
		Venue_Map::ASPECT_RATIO_PATTERN,
		$gatherpress_ratio_candidate
	);
	$gatherpress_styles[]        = sprintf(
		'aspect-ratio:%s',
		$gatherpress_ratio_is_valid ? $gatherpress_ratio_candidate : Venue_Map::DEFAULT_ASPECT_RATIO
	);
}

if ( ! empty( $gatherpress_styles ) ) {
	$gatherpress_wrapper_attr_args['style'] = implode( ';', $gatherpress_styles ) . ';';
}

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
	$gatherpress_wrapper_attr_args['data-gatherpress_block_attrs'] = esc_attr(
		(string) wp_json_encode( $gatherpress_block_attrs )
	);
}

printf(
	'<div %s>',
	get_block_wrapper_attributes( $gatherpress_wrapper_attr_args ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped by core.
);

if ( '' !== $gatherpress_static_map_url ) {
	/* translators: %s: full address of the venue. */
	$gatherpress_alt    = sprintf( __( 'Map of %s', 'gatherpress' ), $gatherpress_address );
	$gatherpress_href   = (string) ( $attributes['href'] ?? '' );
	$gatherpress_target = (string) ( $attributes['linkTarget'] ?? '' );
	$gatherpress_rel    = (string) ( $attributes['rel'] ?? '' );

	if ( '_blank' === $gatherpress_target ) {
		// Auto-append the safety `noopener noreferrer` tokens so a user
		// only has to think about semantic rel values (nofollow, sponsored).
		$gatherpress_existing_rel = preg_split( '/\s+/', trim( $gatherpress_rel ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( array( 'noopener', 'noreferrer' ) as $gatherpress_safety_token ) {
			if ( ! in_array( $gatherpress_safety_token, $gatherpress_existing_rel, true ) ) {
				$gatherpress_existing_rel[] = $gatherpress_safety_token;
			}
		}
		$gatherpress_rel = implode( ' ', $gatherpress_existing_rel );
	}

	if ( '' !== $gatherpress_href ) {
		printf(
			'<a href="%s"%s%s>',
			esc_url( $gatherpress_href ),
			'_blank' === $gatherpress_target ? ' target="_blank"' : '',
			'' !== $gatherpress_rel ? sprintf( ' rel="%s"', esc_attr( $gatherpress_rel ) ) : ''
		);
	}

	printf(
		'<img class="gatherpress-venue-map__image" src="%s" alt="%s" loading="lazy" style="object-fit:%s;" />',
		esc_url( $gatherpress_static_map_url ),
		esc_attr( $gatherpress_alt ),
		esc_attr( $gatherpress_scale )
	);

	if ( '' !== $gatherpress_href ) {
		echo '</a>';
	}

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
