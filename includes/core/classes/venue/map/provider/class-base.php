<?php
/**
 * Base contract for venue-map provider strategies.
 *
 * A provider is the swappable bit of the static-map pipeline: given a
 * coordinate plus dimensions, it returns a finished GD image (already
 * marker-stamped, retina-aware) that the Map orchestrator persists to
 * disk and tracks via post meta. Everything else — descriptor management,
 * filename composition, save-post lifecycle, hash invalidation — lives on
 * the orchestrator and works the same regardless of which provider is
 * active.
 *
 * @package GatherPress\Core\Venue\Map\Provider
 * @since 0.34.0
 */

namespace GatherPress\Core\Venue\Map\Provider;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GdImage;

/**
 * Class Base.
 *
 * Abstract parent of every venue-map provider. Concrete subclasses
 * implement `get_slug()` and `render()`; everything else has sensible
 * defaults so a minimal "roadmap-only, no attribution" provider compiles
 * with three method bodies.
 *
 * @since 0.34.0
 */
abstract class Base {

	/**
	 * Stable identifier used in post meta keys, filename slugs, and the
	 * `map_platform` setting.
	 *
	 * Lowercase ASCII; treat as URL-safe. Two providers MUST NOT share a
	 * slug — `Manager::register()` silently drops the second registration.
	 *
	 * @since 0.34.0
	 *
	 * @return string
	 */
	abstract public function get_slug(): string;

	/**
	 * Human-readable label shown in the Settings → Venues → Maps platform
	 * dropdown. Translator-facing — wrap in `__()`.
	 *
	 * @since 0.34.0
	 *
	 * @return string
	 */
	abstract public function get_label(): string;

	/**
	 * Render a finished, marker-stamped, possibly-retina static map.
	 *
	 * Implementations MUST return a GD image of exactly
	 * `($width * $density) × ($height * $density)` pixels with the venue
	 * marker already drawn, OR null when rendering fails (network error,
	 * tile-fetch deadline exceeded, GD missing, unsupported zoom × density
	 * combo, etc.). The caller treats null as "skip this combo" — it does
	 * NOT fall back to another provider.
	 *
	 * Return type is intentionally untyped at the PHP signature level so the
	 * abstract is loadable on PHP 7.4 (where the GD extension returns a
	 * `resource`, not a `GdImage` — the latter class is 8.0+). PHPStan reads
	 * the docblock instead.
	 *
	 * @since 0.34.0
	 *
	 * @param float  $latitude  Venue latitude in decimal degrees.
	 * @param float  $longitude Venue longitude in decimal degrees.
	 * @param int    $zoom      Map zoom level (already clamped by the orchestrator).
	 * @param int    $width     Logical pixel width (at density 1).
	 * @param int    $height    Logical pixel height (at density 1).
	 * @param int    $density   Pixel-density multiplier. 1 = standard, 2 = retina.
	 * @param string $map_type  Map type slug — one of `roadmap`, `satellite`, `hybrid`, `terrain`.
	 *
	 * @return GdImage|resource|null Finished image, or null on failure.
	 */
	abstract public function render(
		float $latitude,
		float $longitude,
		int $zoom,
		int $width,
		int $height,
		int $density = 1,
		string $map_type = 'roadmap'
	);

	/**
	 * Attribution markup the front end MUST display alongside the static
	 * map image. Required by OpenStreetMap; empty for providers (Google)
	 * that bake attribution directly into the rendered PNG.
	 *
	 * Output is treated as trusted HTML — implementations are responsible
	 * for escaping any dynamic values they substitute in.
	 *
	 * @since 0.34.0
	 *
	 * @return string Attribution HTML, or empty string when none required.
	 */
	public function attribution_html(): string {
		return '';
	}

	/**
	 * Whether this provider can render the requested map type.
	 *
	 * Drives the editor's Map Type dropdown — types not supported by the
	 * active provider are filtered out. OSM only ships `roadmap`; Google
	 * ships all four common types.
	 *
	 * @since 0.34.0
	 *
	 * @param string $map_type Map type slug — one of `roadmap`, `satellite`, `hybrid`, `terrain`.
	 *
	 * @return bool
	 */
	public function supports_map_type( string $map_type ): bool {
		return in_array( $map_type, $this->supported_map_types(), true );
	}

	/**
	 * The full list of map types this provider supports. Default-only
	 * implementation declares roadmap; override to add satellite/etc.
	 *
	 * @since 0.34.0
	 *
	 * @return string[]
	 */
	public function supported_map_types(): array {
		return array( 'roadmap' );
	}
}
