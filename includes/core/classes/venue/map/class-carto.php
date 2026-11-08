<?php
/**
 * CARTO basemap host helpers.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.36.2
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Carto.
 *
 * Stateless helpers for tile URLs on CARTO's basemap hosts.
 *
 * @since 0.36.2
 */
final class Carto {

	/**
	 * Highest zoom CARTO serves raster tiles at.
	 *
	 * @since 0.36.2
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
	 * @since 0.36.2
	 * @var string[]
	 */
	private const TILE_HOSTS = array(
		'basemaps.cartocdn.com',
	);

	/**
	 * Whether a tile URL goes to one of CARTO's basemap hosts.
	 *
	 * @since 0.36.2
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
}
