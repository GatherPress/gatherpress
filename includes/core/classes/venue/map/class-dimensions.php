<?php
/**
 * Dimension attribute helpers for the venue-map block.
 *
 * As of 0.35.0 the block uses core's dimensions support: width and height
 * live in `style.dimensions` as CSS strings, with the legacy numeric
 * attributes kept as read-only fallbacks for content saved before the
 * GatherPress Alpha migration runs (and as the carrier for site defaults
 * from Settings → Venues). These helpers centralize reading a dimension
 * from either shape and projecting it into the two consumers: raw CSS for
 * the wrapper, whole pixels for the static-map PNG pipeline. Each mirrors
 * a JS counterpart in the block's `helpers.js`/`edit.js` so the editor and
 * the server can never drift.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.35.0
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Dimensions.
 *
 * Stateless helpers for the venue-map block's dimension attributes.
 *
 * @since 0.35.0
 */
class Dimensions {

	/**
	 * Read a dimension for the venue map from its current home.
	 *
	 * Core's dimensions support stores values under `style.dimensions`;
	 * blocks saved before 0.35.0 carry the legacy numeric `width`/`height`
	 * attributes until the GatherPress Alpha migration rewrites them (site
	 * defaults from Settings → Venues also arrive through the legacy
	 * attributes via `Map::apply_block_attribute_defaults()`). The style
	 * value wins whenever present. Mirrors `getDimensionValue()` in the
	 * block's `helpers.js`.
	 *
	 * @since 0.35.0
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $dimension  Either `width` or `height`.
	 *
	 * @return int|float|string|null The dimension value, or null when unset.
	 */
	public static function get_dimension_value( array $attributes, string $dimension ) {
		$style_value = $attributes['style']['dimensions'][ $dimension ] ?? null;

		if ( null !== $style_value && '' !== $style_value ) {
			return $style_value;
		}

		$legacy_value = $attributes[ $dimension ] ?? null;

		if ( ( is_int( $legacy_value ) || is_float( $legacy_value ) ) && 0 < $legacy_value ) {
			return $legacy_value;
		}

		return null;
	}

	/**
	 * Extract a pixel integer from a dimension value.
	 *
	 * Accepts the raw numbers the legacy `width`/`height` attributes stored
	 * and the CSS strings core's dimensions support writes to
	 * `style.dimensions` (e.g. `"512px"`). Values in any other unit (`%`,
	 * `rem`, keywords like `fit-content`) cannot feed the static-map PNG
	 * pipeline and resolve to 0 ("auto") — the wrapper CSS still applies
	 * them; only the generated image falls back to derived dimensions.
	 * Mirrors `parsePxDimension()` in the block's `helpers.js`.
	 *
	 * @since 0.35.0
	 *
	 * @param int|float|string|null $value Dimension value from either attribute shape.
	 *
	 * @return int Whole pixels, or 0 when the value is unset or not px-expressible.
	 */
	public static function parse_px_dimension( $value ): int {
		if ( is_int( $value ) || is_float( $value ) ) {
			return max( 0, (int) round( (float) $value ) );
		}

		if ( is_string( $value ) && preg_match( '#\A\s*(\d+(?:\.\d+)?)\s*(?:px)?\s*\z#', $value, $matches ) ) {
			return max( 0, (int) round( (float) $matches[1] ) );
		}

		return 0;
	}

	/**
	 * Normalize a dimension value into a CSS-ready string.
	 *
	 * Legacy numeric attribute values are pixel counts, so they gain a `px`
	 * suffix; `style.dimensions` strings already carry their unit and pass
	 * through untouched. Mirrors `toCssDimension()` in the block's
	 * `edit.js`.
	 *
	 * @since 0.35.0
	 *
	 * @param int|float|string $value Dimension value from either attribute shape.
	 *
	 * @return string CSS dimension value (e.g. `300px`, `50%`).
	 */
	public static function to_css_dimension( $value ): string {
		if ( is_int( $value ) || is_float( $value ) ) {
			return sprintf( '%dpx', (int) round( (float) $value ) );
		}

		return trim( (string) $value );
	}
}
