<?php
/**
 * CARTO basemap host helpers.
 *
 * @package GatherPress\Core\Venue\Map
 * @since TBD
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Carto.
 *
 * Stateless helpers for tile URLs on CARTO's basemap hosts.
 *
 * @since TBD
 */
final class Carto {

	/**
	 * Highest zoom CARTO serves raster tiles at.
	 *
	 * @since TBD
	 * @var int
	 */
	const ZOOM_MAX = 18;

	/**
	 * Hosts the CARTO key may be sent to.
	 *
	 * The tile URL is filterable, so the key is only ever attached to hosts
	 * known to be CARTO's. A site pointing its tiles elsewhere does not hand
	 * its key to that host.
	 *
	 * @since TBD
	 * @var string[]
	 */
	private const TILE_HOSTS = array(
		'basemaps.cartocdn.com',
	);

	/**
	 * CARTO's old `fastly.net` tile hosts, with the subdomain letter captured.
	 *
	 * @since TBD
	 * @var string
	 */
	private const LEGACY_HOST_PATTERN =
		'#^https?://cartodb-basemaps-([a-d]|\{s\})\.global\.ssl\.fastly\.net(?::443)?/#i';

	/**
	 * Whether a tile URL goes to one of CARTO's basemap hosts.
	 *
	 * @since TBD
	 *
	 * @param string $url Tile URL or URL template.
	 *
	 * @return bool True when the host is CARTO's.
	 */
	public static function is_tile_url( string $url ): bool {
		// `{s}` stands in for a subdomain, so it is resolved before the host
		// is read; a bare placeholder does not parse as one.
		$host = strtolower( (string) wp_parse_url( str_replace( '{s}', 'a', $url ), PHP_URL_HOST ) );

		return in_array( $host, self::TILE_HOSTS, true )
			|| str_ends_with( $host, '.basemaps.cartocdn.com' );
	}

	/**
	 * Move a tile URL on CARTO's old `fastly.net` hosts to `basemaps.cartocdn.com`.
	 *
	 * The old hosts reject some keys, and CARTO's key page puts the key on
	 * `basemaps.cartocdn.com` URLs. The rest of the URL is kept as it is.
	 *
	 * @since TBD
	 *
	 * @param string $url Tile URL or URL template.
	 *
	 * @return string The URL, on `basemaps.cartocdn.com` when it was on an old host.
	 */
	public static function use_basemaps_host( string $url ): string {
		return (string) preg_replace( self::LEGACY_HOST_PATTERN, 'https://$1.basemaps.cartocdn.com/', $url );
	}
}
