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
 * The wrapper sizing model: width always comes from the container (and
 * the block's alignment) — never from a stored value.
 *
 *   - `style.dimensions.height`: CSS value written by core's dimensions
 *     support (serialization is skipped, so this template owns the
 *     output). An absent height falls back to the site-wide default
 *     from Settings → Venues; unset there too means the aspect ratio
 *     shapes the wrapper as its container width changes. Values in
 *     non-px units apply as CSS only — the static PNG treats them as
 *     auto. (Content saved before 0.35.0 carried numeric width/height
 *     attributes; the GatherPress Alpha migration rewrites those.)
 *   - `aspectRatio`: CSS-style string (e.g. "16/9"). Shapes the wrapper
 *     when no explicit height is set, and derives the static PNG's
 *     width from its height on the server.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Venue\Map;
use GatherPress\Core\Venue\Map\Dimensions;
use GatherPress\Core\Venue\Setup;

if ( ! isset( $attributes ) || ! is_array( $attributes ) ) {
	return;
}

$gatherpress_post_id     = (int) get_the_ID();
$gatherpress_post_type   = (string) get_post_type();
$gatherpress_venue_setup = Setup::get_instance();
$gatherpress_venue_meta  = $gatherpress_venue_setup->get_venue_meta( $gatherpress_post_id, $gatherpress_post_type );

$gatherpress_address = (string) ( $gatherpress_venue_meta['address'] ?? '' );

if ( '' === $gatherpress_address ) {
	return;
}

$gatherpress_render_mode = 'static' === ( $attributes['renderMode'] ?? Map::DEFAULT_RENDER_MODE )
	? 'static'
	: 'interactive';
$gatherpress_zoom        = (int) ( $attributes['zoom'] ?? Map::DEFAULT_ZOOM );
$gatherpress_ratio       = (string) ( $attributes['aspectRatio'] ?? Map::DEFAULT_ASPECT_RATIO );

// The height value as authored (style.dimensions CSS string), falling
// back to the site-wide default from Settings → Venues (a pixel integer;
// '' or 0 means unset). Null = auto — the ratio shapes the wrapper. The
// px projection feeds the static-map pipeline (0 = auto; non-px units
// land as CSS only). Width is never stored: the PNG derives its width
// from height × ratio, and the wrapper takes its width from the
// container.
$gatherpress_default_height = (int) Settings::get_instance()->get( 'venue_map_default_height' );
$gatherpress_height_value   = Dimensions::get_dimension_value( $attributes, 'height' )
	?? ( 0 < $gatherpress_default_height ? $gatherpress_default_height : null );
$gatherpress_raw_height     = Dimensions::parse_px_dimension( $gatherpress_height_value );

// Allow-list for the `scale` block attribute. Anything outside this set
// (a hand-edited block attr, a filter that mutates the value, a migration
// miss) falls back to `cover` so the inline style stays safe and
// predictable.
$gatherpress_scale_candidate = (string) ( $attributes['scale'] ?? Map::DEFAULT_SCALE );
$gatherpress_scale           = in_array( $gatherpress_scale_candidate, Map::SCALE_OPTIONS, true )
	? $gatherpress_scale_candidate
	: Map::DEFAULT_SCALE;

$gatherpress_map_type = (string) ( $attributes['type'] ?? Map::DEFAULT_MAP_TYPE );

$gatherpress_static_map_descriptor = Map::get_instance()->get_descriptor_for_post(
	$gatherpress_post_id,
	$gatherpress_post_type,
	$gatherpress_zoom,
	0,
	$gatherpress_raw_height,
	$gatherpress_ratio,
	$gatherpress_map_type
);

$gatherpress_static_map_url    = null !== $gatherpress_static_map_descriptor
	? $gatherpress_static_map_descriptor['url']
	: '';
$gatherpress_static_map_url_2x = null !== $gatherpress_static_map_descriptor
	? $gatherpress_static_map_descriptor['url_2x']
	: '';

// Hydration data sits on the outer wrapper so view.js can replace the
// wrapper's children with a live Leaflet map (the `<img>` inside is a void
// element that React can't mount into directly).
$gatherpress_wrapper_attr_args = array(
	'class'            => sprintf( 'gatherpress-venue-map gatherpress-venue-map--%s', $gatherpress_render_mode ),
	'data-render-mode' => $gatherpress_render_mode,
);

// Sizing rules (mirroring `wrapperStyle` in edit.js): the wrapper always
// spans its container — an explicit height stamps inline (and wins over
// the ratio); no height → CSS `aspect-ratio` shapes the wrapper as its
// container width changes. Static <img> inside uses object-fit: cover
// so the raster stays crisp at any size.
//
// The height value passes through safecss_filter_attr() because core's
// dimensions support stores raw CSS strings and
// get_block_wrapper_attributes() doesn't sanitize individual values —
// without the filter an editor-role attacker with edit_posts could
// smuggle extra declarations in via a hand-edited attribute. A rejected
// value degrades to auto (the aspect-ratio arm below picks it up).
$gatherpress_styles         = array();
$gatherpress_has_height_css = false;

if ( null !== $gatherpress_height_value ) {
	$gatherpress_height_declaration = safecss_filter_attr(
		'height:' . Dimensions::to_css_dimension( $gatherpress_height_value )
	);

	if ( '' !== $gatherpress_height_declaration ) {
		$gatherpress_styles[]       = $gatherpress_height_declaration;
		$gatherpress_has_height_css = true;
	}
}

if ( ! $gatherpress_has_height_css ) {
	// The `aspectRatio` block attr lands directly in an inline style.
	// get_block_wrapper_attributes() doesn't sanitize individual CSS
	// values, so an editor-role attacker with edit_posts could otherwise
	// stamp a payload like `1/1;background:url(...)`. Accept only the
	// narrow `N/N` or `N:N` form the block ever emits; anything else
	// falls back to the server default. The pattern is shared with the
	// REST validator so the two can never drift apart.
	$gatherpress_ratio_candidate = '' !== $gatherpress_ratio
		? $gatherpress_ratio
		: Map::DEFAULT_ASPECT_RATIO;
	$gatherpress_ratio_is_valid  = (bool) preg_match(
		Map::ASPECT_RATIO_PATTERN,
		$gatherpress_ratio_candidate
	);
	$gatherpress_styles[]        = sprintf(
		'aspect-ratio:%s',
		$gatherpress_ratio_is_valid ? $gatherpress_ratio_candidate : Map::DEFAULT_ASPECT_RATIO
	);
}

if ( ! empty( $gatherpress_styles ) ) {
	$gatherpress_wrapper_attr_args['style'] = implode( ';', $gatherpress_styles ) . ';';
}

if ( 'interactive' === $gatherpress_render_mode ) {
	$gatherpress_block_attrs = array(
		'address'          => $gatherpress_address,
		'latitude'         => (string) ( $gatherpress_venue_meta['latitude'] ?? '' ),
		'longitude'        => (string) ( $gatherpress_venue_meta['longitude'] ?? '' ),
		'mapZoomLevel'     => $attributes['zoom'] ?? Map::DEFAULT_ZOOM,
		'mapType'          => $attributes['type'] ?? Map::DEFAULT_MAP_TYPE,
		'mapPlatform'      => (string) Settings::get_instance()->get( 'map_platform' ),
		'pluginUrl'        => GATHERPRESS_CORE_URL,
		'googleMapsApiKey' => (string) Settings::get_instance()->get( 'google_maps_api_key' ),
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

	// Emit `srcset` only when the retina variant is actually present —
	// pre-retina descriptors and sites that filtered the 2× off keep the
	// existing plain `src` output so the browser doesn't try to resolve
	// an empty 2× URL.
	if ( '' !== $gatherpress_static_map_url_2x ) {
		printf(
			'<img class="gatherpress-venue-map__image" src="%1$s" srcset="%1$s 1x, %2$s 2x" alt="%3$s" loading="lazy" style="object-fit:%4$s;" />',
			esc_url( $gatherpress_static_map_url ),
			esc_url( $gatherpress_static_map_url_2x ),
			esc_attr( $gatherpress_alt ),
			esc_attr( $gatherpress_scale )
		);
	} else {
		printf(
			'<img class="gatherpress-venue-map__image" src="%s" alt="%s" loading="lazy" style="object-fit:%s;" />',
			esc_url( $gatherpress_static_map_url ),
			esc_attr( $gatherpress_alt ),
			esc_attr( $gatherpress_scale )
		);
	}

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
