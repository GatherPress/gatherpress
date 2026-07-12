<?php
/**
 * Google Static Maps venue map provider.
 *
 * Renders static maps by requesting a single PNG from the Google Static
 * Maps API (`maps.googleapis.com/maps/api/staticmap`) and decoding the
 * response into a GD image. Unlike the OSM provider (XYZ tile fetch +
 * compositing), one HTTP call returns a finished, marker-stamped image.
 *
 * @package GatherPress\Core\Venue\Map\Provider
 * @since 0.35.0
 */

namespace GatherPress\Core\Venue\Map\Provider;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Venue\Map;
use GdImage;
use Throwable;

/**
 * Class Google.
 *
 * Static Maps API provider. Reads the site-wide Google Maps API key from
 * settings; without one the API responds 403 and this provider returns
 * null so the orchestrator can fall back to another provider's PNG.
 *
 * @since 0.35.0
 */
class Google extends Base {

	/**
	 * Google Static Maps API endpoint.
	 *
	 * @since 0.35.0
	 * @var string
	 */
	const STATIC_MAP_API_URL = 'https://maps.googleapis.com/maps/api/staticmap';

	/**
	 * Default map type for static renders in the minimal #1528 scope.
	 *
	 * Map-type threading through the orchestrator is deferred; all static
	 * Google map renders request roadmap until block `type` flows into render().
	 *
	 * @since 0.35.0
	 * @var string
	 */
	const DEFAULT_MAP_TYPE = 'roadmap';

	/**
	 * Provider slug used in post meta keys, filenames, and the
	 * `map_platform` setting.
	 *
	 * @since 0.35.0
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'google';
	}

	/**
	 * Human-readable label shown in the Settings → Venues → Maps platform
	 * dropdown.
	 *
	 * @since 0.35.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Google Maps', 'gatherpress' );
	}

	/**
	 * Google Static Maps supports all four common map types.
	 *
	 * @since 0.35.0
	 *
	 * @return string[]
	 */
	public function supported_map_types(): array {
		return array( 'roadmap', 'satellite', 'hybrid', 'terrain' );
	}

	/**
	 * Fetch a finished static map PNG from Google and decode it into GD.
	 *
	 * Returns null when GD is unavailable, the API key is missing, the
	 * HTTP request fails, or the response body is not a valid PNG.
	 *
	 * Return type is intentionally untyped at the PHP signature level for
	 * PHP 7.4 compatibility (GD returns a `resource` there, not a
	 * `GdImage`).
	 *
	 * @since 0.35.0
	 *
	 * @param float $latitude  Venue latitude in decimal degrees.
	 * @param float $longitude Venue longitude in decimal degrees.
	 * @param int   $zoom      Map zoom level (already clamped by the orchestrator).
	 * @param int   $width     Logical pixel width (at density 1).
	 * @param int   $height    Logical pixel height (at density 1).
	 * @param int   $density   Pixel-density multiplier. 1 = standard, 2 = retina.
	 *
	 * @return GdImage|resource|null Finished image, or null on failure.
	 */
	public function render(
		float $latitude,
		float $longitude,
		int $zoom,
		int $width,
		int $height,
		int $density = 1
	) {
		// PHP built without the GD extension. Can't simulate in a unit test without making the runtime itself broken.
		if ( ! function_exists( 'imagecreatefromstring' ) ) { // @codeCoverageIgnore
			return null; // @codeCoverageIgnore
		}

		// Coerce unsupported densities back to 1. Google's `scale` only
		// accepts 1, 2, or 4 (premium); mirror the OSM provider guard.
		if ( ! in_array( $density, Map::SUPPORTED_DENSITIES, true ) ) {
			$density = 1;
		}

		$api_key = trim( (string) Settings::get_instance()->get( 'google_maps_api_key' ) );

		if ( '' === $api_key ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__(
					'Google Static Maps requires a Google Maps API key in GatherPress venue map settings.',
					'gatherpress'
				),
				'0.35.0'
			);
			return null;
		}

		$url       = $this->build_static_map_url(
			$latitude,
			$longitude,
			$zoom,
			$width,
			$height,
			$density,
			$api_key
		);
		$png_bytes = $this->fetch_static_map( $url );

		if ( null === $png_bytes ) {
			return null;
		}

		$image = $this->decode_png( $png_bytes );

		return false === $image ? null : $image;
	}

	/**
	 * Compose the Google Static Maps API request URL.
	 *
	 * @since 0.35.0
	 *
	 * @param float  $latitude  Venue latitude.
	 * @param float  $longitude Venue longitude.
	 * @param int    $zoom      Map zoom level.
	 * @param int    $width     Logical width in pixels.
	 * @param int    $height    Logical height in pixels.
	 * @param int    $density   Scale multiplier (`scale` query param).
	 * @param string $api_key   Google Maps API key.
	 *
	 * @return string
	 */
	protected function build_static_map_url(
		float $latitude,
		float $longitude,
		int $zoom,
		int $width,
		int $height,
		int $density,
		string $api_key
	): string {
		$marker = sprintf(
			'color:red|%F,%F',
			$latitude,
			$longitude
		);

		return add_query_arg(
			array(
				'center'  => sprintf( '%F,%F', $latitude, $longitude ),
				'zoom'    => (string) $zoom,
				'size'    => sprintf( '%dx%d', $width, $height ),
				'scale'   => (string) $density,
				'maptype' => self::DEFAULT_MAP_TYPE,
				'markers' => $marker,
				'key'     => $api_key,
			),
			self::STATIC_MAP_API_URL
		);
	}

	/**
	 * Fetch raw PNG bytes from the Static Maps API.
	 *
	 * Returns null on any HTTP failure so the orchestrator can keep an
	 * existing descriptor rather than overwriting with nothing.
	 *
	 * @since 0.35.0
	 *
	 * @param string $url Fully composed Static Maps API URL.
	 *
	 * @return string|null PNG bytes, or null on failure.
	 */
	protected function fetch_static_map( string $url ): ?string {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = (string) wp_remote_retrieve_body( $response );

		return '' !== $body ? $body : null;
	}

	/**
	 * Decode raw PNG bytes into a GD image.
	 *
	 * Wraps `imagecreatefromstring()` so a malformed body never fatals
	 * the static-map pipeline.
	 *
	 * @since 0.35.0
	 *
	 * @param string $bytes Raw PNG bytes from `fetch_static_map()`.
	 *
	 * @return GdImage|resource|false Decoded image, or false when the bytes don't decode.
	 */
	protected function decode_png( string $bytes ) {
		try {
			return imagecreatefromstring( $bytes );
		} catch ( Throwable $e ) {
			return false;
		}
	}
}
