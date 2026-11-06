<?php
/**
 * Saved static map image helpers.
 *
 * @package GatherPress\Core\Venue\Map
 * @since TBD
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Saved_Image.
 *
 * Stateless helpers for the file names, URLs and locations of saved
 * static map PNG files.
 *
 * @since TBD
 *
 * @phpstan-import-type Descriptor from Map
 * @phpstan-import-type ProviderDescriptorMap from Map
 */
final class Saved_Image {

	/**
	 * Compose a filesystem-safe filename from the address, coordinates and dimensions.
	 *
	 * The coordinates keep two venues at one address apart when their
	 * coordinates differ, for example when one sets them by hand.
	 *
	 * Address gets slugified via `sanitize_title()` and capped at 150 chars
	 * so the full filename stays comfortably under the 255-byte cap common
	 * to most filesystems. An empty or all-special-character address falls
	 * back to `venue` so there's always something before the dimension
	 * suffix. `$density > 1` appends the `@{density}x` suffix used by the
	 * retina variants, e.g. `venue-52.358000_4.881000-osm-roadmap-12-800-300@2x.png`.
	 *
	 * @since TBD
	 *
	 * @param string $address   Venue address.
	 * @param float  $latitude  Venue latitude.
	 * @param float  $longitude Venue longitude.
	 * @param int    $zoom      Map zoom level.
	 * @param int    $width     Output width (at density 1).
	 * @param int    $height    Output height (at density 1).
	 * @param string $provider  Provider slug (e.g. `osm`) — namespaces the file
	 *                          so OSM and Google PNG files can coexist on disk.
	 * @param int    $density   Pixel-density multiplier. 1 = standard, 2 = retina.
	 * @param string $map_type  Normalized map type slug.
	 *
	 * @return string Filename including the `.png` extension.
	 */
	public static function filename(
		string $address,
		float $latitude,
		float $longitude,
		int $zoom,
		int $width,
		int $height,
		string $provider,
		int $density,
		string $map_type
	): string {
		// `sanitize_title()` URL-slugifies; pass the result through
		// `sanitize_file_name()` as defense-in-depth so any path-sensitive
		// characters that slip past (or future changes to sanitize_title's
		// allowed set) can't escape the filename segment.
		$slug = sanitize_file_name( sanitize_title( $address ) );

		if ( '' === $slug ) {
			$slug = 'venue';
		}

		if ( 150 < strlen( $slug ) ) {
			$slug = substr( $slug, 0, 150 );
		}

		$suffix = $density > 1 ? sprintf( '@%dx', $density ) : '';

		return sprintf(
			'%s-%.6F_%.6F-%s-%s-%d-%d-%d%s.png',
			$slug,
			$latitude,
			$longitude,
			$provider,
			$map_type,
			$zoom,
			$width,
			$height,
			$suffix
		);
	}

	/**
	 * Add the first eight characters of the map hash to a file name or URL.
	 *
	 * A map that renders again gets a new URL, so a browser or CDN does
	 * not keep serving the old image. The hash goes before the retina
	 * suffix, e.g. `...-800-300-1a2b3c4d@2x.png`.
	 *
	 * @since TBD
	 *
	 * @param string $file File name or URL from {@see self::filename()}.
	 * @param string $hash Map hash.
	 *
	 * @return string The file name or URL with the hash.
	 */
	public static function versioned( string $file, string $hash ): string {
		return (string) preg_replace( '/(@\d+x)?\.png$/', '-' . substr( $hash, 0, 8 ) . '$1.png', $file );
	}

	/**
	 * Public URL of a file in the static maps folder.
	 *
	 * @since TBD
	 *
	 * @param string $filename File name from {@see self::filename()}.
	 *
	 * @return string Full public URL.
	 */
	public static function url( string $filename ): string {
		$dirs = wp_get_upload_dir();

		return trailingslashit( $dirs['baseurl'] ) . Map::UPLOADS_SUBDIR . '/' . $filename;
	}

	/**
	 * Whether a saved PNG is still on disk.
	 *
	 * A URL outside the static maps folder, for example one a filter moved
	 * to a CDN, or one that leaves the folder with `..`, is not checked
	 * here and counts as present. A query string a filter added is ignored.
	 *
	 * @since TBD
	 *
	 * @param string $url Public URL of the PNG.
	 *
	 * @return bool False only when a PNG in the static maps folder is missing.
	 */
	public static function exists( string $url ): bool {
		$path = self::path( (string) strtok( $url, '?' ) );

		return null === $path || is_file( $path );
	}

	/**
	 * Path on disk of a PNG in the static maps folder.
	 *
	 * @since TBD
	 *
	 * @param string $url Public URL of the PNG.
	 *
	 * @return string|null The path, or null when the URL is not a file directly in the static maps folder.
	 */
	private static function path( string $url ): ?string {
		$dirs     = wp_get_upload_dir();
		$base_url = trailingslashit( $dirs['baseurl'] ) . Map::UPLOADS_SUBDIR . '/';
		$file     = substr( $url, strlen( $base_url ) );

		// A filtered URL with `..` or a subfolder must not point outside the folder.
		if ( ! str_starts_with( $url, $base_url ) || wp_basename( $file ) !== $file || '..' === $file ) {
			return null;
		}

		return trailingslashit( $dirs['basedir'] ) . Map::UPLOADS_SUBDIR . '/' . $file;
	}

	/**
	 * Delete the PNG files of saved maps that no venue uses any longer.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $descriptors Saved maps, by provider and combo.
	 * @phpstan-param ProviderDescriptorMap $descriptors
	 *
	 * @return void
	 */
	public static function delete_all_unused( array $descriptors ): void {
		foreach ( $descriptors as $combos ) {
			array_map( array( self::class, 'delete_unused' ), $combos );
		}
	}

	/**
	 * Delete a saved entry's PNG files that no venue uses any longer.
	 *
	 * Venues at the same address and coordinates can share a file, so a
	 * file is kept while any venue's saved maps still point at it, with or
	 * without a query string.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $entry Saved descriptor whose files may be unused.
	 *
	 * @return void
	 */
	public static function delete_unused( array $entry ): void {
		global $wpdb;

		foreach ( array( $entry['url'] ?? '', $entry['url_2x'] ?? '' ) as $url ) {
			$path = self::path( (string) strtok( (string) $url, '?' ) );

			if ( null === $path || ! is_file( $path ) ) {
				continue;
			}

			$file = $wpdb->esc_like( '/' . Map::UPLOADS_SUBDIR . '/' . wp_basename( $path ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
			$used = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->postmeta}
					WHERE meta_key = %s AND ( meta_value LIKE %s OR meta_value LIKE %s ) LIMIT 1",
					Map::META_KEY,
					'%' . $file . '"%',
					'%' . $file . '?%'
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( null === $used ) {
				wp_delete_file( $path );
			}
		}
	}

	/**
	 * Identify the address and coordinates a static map shows.
	 *
	 * @since TBD
	 *
	 * @param array<string, string> $info Parsed venue information.
	 *
	 * @return string MD5 hex digest.
	 */
	public static function location( array $info ): string {
		// md5() here is a non-cryptographic key, matching Map::hash_for().
		return md5( // NOSONAR.
			implode(
				'|',
				array(
					(string) ( $info['address'] ?? '' ),
					(string) ( $info['latitude'] ?? '' ),
					(string) ( $info['longitude'] ?? '' ),
				)
			)
		);
	}

	/**
	 * Get a saved image that can stand in for a map that could not render.
	 *
	 * It must show the venue's current location and its PNG must exist.
	 * An entry saved before `location` was stored is matched on its URL,
	 * which carries the location in the file name. A missing 2x PNG is
	 * left out, so the page doesn't offer it in `srcset`.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed>                     $entry Saved descriptor.
	 * @phpstan-param Descriptor $entry
	 * @param array<string, string>                    $info  Parsed venue information.
	 * @param string                                   $slug  Provider slug the entry was saved under.
	 * @param array{0: int, 1: int, 2: int, 3: string} $combo Zoom, width, height and normalized map type.
	 *
	 * @return Descriptor|null The entry, or null when it can't be shown.
	 */
	public static function usable( array $entry, array $info, string $slug, array $combo ): ?array {
		$url      = $entry['url'];
		$location = $entry['location'] ?? '';
		$matches  = '' !== $location
			? self::location( $info ) === $location
			: self::url(
				self::filename(
					(string) ( $info['address'] ?? '' ),
					(float) $info['latitude'],
					(float) $info['longitude'],
					$combo[0],
					$combo[1],
					$combo[2],
					$slug,
					1,
					$combo[3]
				)
			) === $url;

		if ( ! $matches || ! self::exists( $url ) ) {
			return null;
		}

		if ( ! self::exists( $entry['url_2x'] ) ) {
			$entry['url_2x'] = '';
		}

		return $entry;
	}

	/**
	 * Put back a provider's saved image for a combo that failed to render again.
	 *
	 * Used by {@see Map::regenerate()}, which clears the meta before it
	 * renders. The image is put back only when it can still be shown.
	 *
	 * @since TBD
	 *
	 * @param int                                      $post_id  Venue post ID.
	 * @param array<string, mixed>                     $previous Descriptors from before the meta was cleared.
	 * @param array<string, string>                    $info     Parsed venue information.
	 * @param string                                   $slug     Provider slug.
	 * @param string                                   $key      Combo key from `Map::combo_key()`.
	 * @param array{0: int, 1: int, 2: int, 3: string} $combo    Zoom, width, height and normalized map type.
	 * @phpstan-param ProviderDescriptorMap $previous
	 *
	 * @return void
	 */
	public static function restore(
		int $post_id,
		array $previous,
		array $info,
		string $slug,
		string $key,
		array $combo
	): void {
		$entry = $previous[ $slug ][ $key ] ?? null;
		$entry = null === $entry ? null : self::usable( $entry, $info, $slug, $combo );

		if ( null === $entry ) {
			return;
		}

		$stored = get_post_meta( $post_id, Map::META_KEY, true );
		$stored = is_array( $stored ) ? $stored : array();

		$stored[ $slug ][ $key ] = $entry;

		update_post_meta( $post_id, Map::META_KEY, $stored );
	}
}
