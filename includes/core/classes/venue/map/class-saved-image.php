<?php
/**
 * Saved static map image helpers.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.36.2
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Saved_Image.
 *
 * A static map PNG saved in a venue's meta, with the helpers that name
 * its files.
 *
 * @since 0.36.2
 *
 * @phpstan-import-type Descriptor from Map
 * @phpstan-import-type ProviderDescriptorMap from Map
 */
final class Saved_Image {

	/**
	 * Saved descriptor.
	 *
	 * @since 0.36.2
	 * @var array<string, mixed>
	 * @phpstan-var Descriptor
	 */
	private readonly array $entry;

	/**
	 * Provider slug the image was saved under.
	 *
	 * @since 0.36.2
	 * @var string
	 */
	private readonly string $slug;

	/**
	 * Combo key from `Map::combo_key()` the image was saved under.
	 *
	 * @since 0.36.2
	 * @var string
	 */
	private readonly string $key;

	/**
	 * Wrap a saved descriptor.
	 *
	 * @since 0.36.2
	 *
	 * @param array<string, mixed> $entry Saved descriptor.
	 * @phpstan-param Descriptor $entry
	 * @param string               $slug  Provider slug the image was saved under.
	 * @param string               $key   Combo key from `Map::combo_key()`.
	 */
	public function __construct( array $entry, string $slug, string $key ) {
		$this->entry = $entry;
		$this->slug  = $slug;
		$this->key   = $key;
	}

	/**
	 * Find the saved image for a provider and combo in a descriptor map.
	 *
	 * @since 0.36.2
	 *
	 * @param array<string, mixed> $descriptors Saved maps, by provider and combo.
	 * @phpstan-param ProviderDescriptorMap $descriptors
	 * @param string               $slug        Provider slug.
	 * @param string               $key         Combo key from `Map::combo_key()`.
	 *
	 * @return self|null The saved image, or null when there is none.
	 */
	public static function find( array $descriptors, string $slug, string $key ): ?self {
		return isset( $descriptors[ $slug ][ $key ] ) ? new self( $descriptors[ $slug ][ $key ], $slug, $key ) : null;
	}

	/**
	 * Delete the PNG files of saved maps that no venue uses any longer.
	 *
	 * @since 0.36.2
	 *
	 * @param array<string, mixed> $descriptors Saved maps, by provider and combo.
	 * @phpstan-param ProviderDescriptorMap $descriptors
	 * @param string[]             $keep        URLs to keep without a lookup, such as the files a render saved again.
	 *
	 * @return void
	 */
	public static function delete_all_unused( array $descriptors, array $keep = array() ): void {
		foreach ( $descriptors as $slug => $combos ) {
			foreach ( $combos as $key => $entry ) {
				( new self( $entry, (string) $slug, (string) $key ) )->delete_unused( $keep );
			}
		}
	}

	/**
	 * Get the PNG URLs a venue's stored maps point at.
	 *
	 * Reads the meta as stored, so a URL a filter rewrote is not included.
	 *
	 * @since 0.36.2
	 *
	 * @param int $post_id Venue post ID.
	 *
	 * @return string[] The 1x and 2x URLs.
	 */
	public static function stored_urls( int $post_id ): array {
		return self::urls( get_post_meta( $post_id, Map::META_KEY, true ) );
	}

	/**
	 * Delete the PNG files of a venue's maps that core deleted with the venue.
	 *
	 * Hooked on `deleted_post_meta` only while a venue is deleted, for a map
	 * another request, such as a prewarm job, wrote after the venue's saved
	 * maps were cleaned up.
	 *
	 * @since 0.36.2
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) -- $meta_ids and $post_id are required by
	 *                                                    the deleted_post_meta signature.
	 *
	 * @param string[] $meta_ids   Deleted meta IDs.
	 * @param int      $post_id    Venue post ID.
	 * @param string   $meta_key   Meta key.
	 * @param mixed    $meta_value Deleted meta value.
	 *
	 * @return void
	 */
	public static function delete_deleted( array $meta_ids, int $post_id, string $meta_key, mixed $meta_value ): void {
		if ( Map::META_KEY === $meta_key ) {
			self::delete_unused_files( self::urls( $meta_value ) );
		}
	}

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
	 * @since 0.36.2
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
	 * @since 0.36.2
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
	 * Identify the address and coordinates a static map shows.
	 *
	 * @since 0.36.2
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
	 * Whether the image is the current render for a map hash.
	 *
	 * A map saved before file names carried the hash is matched on its
	 * plain URL. A query string a filter added is ignored.
	 *
	 * @since 0.36.2
	 *
	 * @param string $hash      Map hash.
	 * @param string $plain_url URL of the 1x PNG without the hash.
	 *
	 * @return bool True when the hash and URL match and the 1x PNG exists.
	 */
	public function is_current( string $hash, string $plain_url ): bool {
		return $this->entry['hash'] === $hash
			&& in_array(
				self::without_query( $this->entry['url'] ),
				array( self::versioned( $plain_url, $hash ), $plain_url ),
				true
			)
			&& self::file_exists( $this->entry['url'] );
	}

	/**
	 * Whether the image has a 2x PNG on disk.
	 *
	 * @since 0.36.2
	 *
	 * @return bool False when there is no 2x URL or its PNG is missing.
	 */
	public function has_2x(): bool {
		return '' !== $this->entry['url_2x'] && self::file_exists( $this->entry['url_2x'] );
	}

	/**
	 * Get the image when it can stand in for a map that could not render.
	 *
	 * It must show the venue's current location and its PNG must exist.
	 * An entry saved before `location` was stored is matched on its URL,
	 * which carries the location in the file name, ignoring a query string
	 * a filter added. A missing 2x PNG is left out, so the page doesn't
	 * offer it in `srcset`.
	 *
	 * @since TBD
	 *
	 * @param array<string, string>                    $info  Parsed venue information.
	 * @param array{0: int, 1: int, 2: int, 3: string} $combo Zoom, width, height and normalized map type.
	 *
	 * @return Descriptor|null The descriptor, or null when it can't be shown.
	 */
	public function usable( array $info, array $combo ): ?array {
		$entry    = $this->entry;
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
					$this->slug,
					1,
					$combo[3]
				)
			) === self::without_query( $entry['url'] );

		if ( ! $matches || ! self::file_exists( $entry['url'] ) ) {
			return null;
		}

		if ( ! self::file_exists( $entry['url_2x'] ) ) {
			$entry['url_2x'] = '';
		}

		return $entry;
	}

	/**
	 * Put the image back into a venue's meta when it can still be shown.
	 *
	 * Used by {@see Map::regenerate()}, which clears the meta before it
	 * renders, for a combo that failed to render again.
	 *
	 * @since 0.36.2
	 *
	 * @param int                                      $post_id Venue post ID.
	 * @param array<string, string>                    $info    Parsed venue information.
	 * @param array{0: int, 1: int, 2: int, 3: string} $combo   Zoom, width, height and normalized map type.
	 *
	 * @return void
	 */
	public function restore( int $post_id, array $info, array $combo ): void {
		$entry = $this->usable( $info, $combo );

		if ( null === $entry ) {
			return;
		}

		$stored = get_post_meta( $post_id, Map::META_KEY, true );
		$stored = is_array( $stored ) ? $stored : array();

		$stored[ $this->slug ][ $this->key ] = $entry;

		update_post_meta( $post_id, Map::META_KEY, $stored );
	}

	/**
	 * Delete the image's PNG files that no venue uses any longer.
	 *
	 * Venues at the same address and coordinates can share a file, so a
	 * file is kept while any venue's saved maps still point at it, with or
	 * without a query string.
	 *
	 * @since 0.36.2
	 *
	 * @param string[] $keep URLs to keep without a lookup, such as the files a render saved again.
	 *
	 * @return void
	 */
	public function delete_unused( array $keep = array() ): void {
		self::delete_unused_files( array( $this->entry['url'], $this->entry['url_2x'] ), $keep );
	}

	/**
	 * Get the PNG URLs in a stored map value.
	 *
	 * @since 0.36.2
	 *
	 * @param mixed $stored Stored map meta.
	 *
	 * @return string[] The 1x and 2x URLs.
	 */
	private static function urls( mixed $stored ): array {
		$urls = array();

		foreach ( is_array( $stored ) ? $stored : array() as $combos ) {
			foreach ( is_array( $combos ) ? $combos : array() as $entry ) {
				$urls[] = (string) ( $entry['url'] ?? '' );
				$urls[] = (string) ( $entry['url_2x'] ?? '' );
			}
		}

		return array_values( array_filter( $urls ) );
	}

	/**
	 * Delete the PNG files at these URLs that no venue uses any longer.
	 *
	 * @since 0.36.2
	 *
	 * @param string[] $urls PNG URLs.
	 * @param string[] $keep URLs to keep without a lookup.
	 *
	 * @return void
	 */
	private static function delete_unused_files( array $urls, array $keep = array() ): void {
		global $wpdb;

		$keep = array_map( array( self::class, 'without_query' ), $keep );

		foreach ( $urls as $url ) {
			$url  = self::without_query( (string) $url );
			$path = self::path( $url );

			if ( null === $path || in_array( $url, $keep, true ) || ! is_file( $path ) ) {
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
	 * Whether a saved PNG is still on disk.
	 *
	 * A URL outside the static maps folder, for example one a filter moved
	 * to a CDN, or one that leaves the folder with `..`, is not checked
	 * here and counts as present. A query string a filter added is ignored.
	 *
	 * @since 0.36.2
	 *
	 * @param string $url Public URL of the PNG.
	 *
	 * @return bool False only when a PNG in the static maps folder is missing.
	 */
	private static function file_exists( string $url ): bool {
		$path = self::path( self::without_query( $url ) );

		return null === $path || is_file( $path );
	}

	/**
	 * Drop a query string a filter added to a saved map URL.
	 *
	 * @since 0.36.2
	 *
	 * @param string $url Public URL of the PNG.
	 *
	 * @return string The URL without its query string.
	 */
	private static function without_query( string $url ): string {
		return explode( '?', $url, 2 )[0];
	}

	/**
	 * Path on disk of a PNG in the static maps folder.
	 *
	 * @since 0.36.2
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
}
