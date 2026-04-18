<?php
/**
 * Unit tests for GatherPress\Core\Venue_Map.
 *
 * These tests cover the generator's hash logic, the save/regenerate/cleanup
 * lifecycle, and the hook wiring. The tile fetcher is stubbed via an HTTP
 * short-circuit filter so tests never make a real network call.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Venue;
use GatherPress\Core\Venue_Map;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Venue_Map.
 *
 * @group multisite
 * @coversDefaultClass \GatherPress\Core\Venue_Map
 */
class Test_Venue_Map extends Base {
	/**
	 * Minimal valid 1×1 PNG used as a stand-in for every tile fetch.
	 *
	 * Keeping the payload small keeps tests fast and makes it obvious when
	 * a code path accidentally hits the real network (which would time out).
	 *
	 * @var string
	 */
	private $tile_png;

	/**
	 * Installs the HTTP short-circuit on every test so no network is touched.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$png  = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42m';
		$png .= 'NkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fixed, trusted PNG payload for the tile-fetch stub.
		$this->tile_png = base64_decode( $png );

		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );
	}

	/**
	 * Removes the filter and any leftover files on tear-down.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );

		$dirs     = wp_get_upload_dir();
		$base_dir = trailingslashit( $dirs['basedir'] ) . Venue_Map::UPLOADS_SUBDIR;

		if ( is_dir( $base_dir ) ) {
			foreach ( (array) glob( $base_dir . '/*.png' ) as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
		}

		parent::tearDown();
	}

	/**
	 * Short-circuit every HTTP request by returning the canned tile response.
	 *
	 * @param mixed  $preempt Default false.
	 * @param array  $args    HTTP args (unused).
	 * @param string $url     Request URL (unused).
	 * @return array Mocked WP HTTP response.
	 */
	public function short_circuit_tile_requests( $preempt, $args, $url ): array {
		unset( $args, $url );

		return array(
			'headers'  => array(),
			'body'     => $this->tile_png,
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Venue_Map::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 11,
				'callback' => array( $instance, 'maybe_generate' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_register_delete_hook' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'block_type_metadata',
				'priority' => 10,
				'callback' => array( $instance, 'apply_block_attribute_defaults' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for apply_block_attribute_defaults — overrides the venue-map
	 * block.json defaults with user-chosen Settings values.
	 *
	 * @covers ::apply_block_attribute_defaults
	 *
	 * @return void
	 */
	public function test_apply_block_attribute_defaults_overrides_venue_map(): void {
		$instance = Venue_Map::get_instance();
		$settings = \GatherPress\Core\Settings::get_instance();

		$settings->set( 'venue_map_default_render_mode', 'static' );
		$settings->set( 'venue_map_default_zoom', 12 );
		$settings->set( 'venue_map_default_height', 450 );
		$settings->set( 'venue_map_default_type', 'satellite' );

		$metadata = array(
			'name'       => 'gatherpress/venue-map',
			'attributes' => array(
				'renderMode' => array(
					'type'    => 'string',
					'default' => 'interactive',
				),
				'zoom'       => array(
					'type'    => 'number',
					'default' => 18,
				),
				'height'     => array(
					'type'    => 'number',
					'default' => 300,
				),
				'type'       => array(
					'type'    => 'string',
					'default' => 'roadmap',
				),
			),
		);

		$result = $instance->apply_block_attribute_defaults( $metadata );

		$this->assertSame( 'static', $result['attributes']['renderMode']['default'] );
		$this->assertSame( 12, $result['attributes']['zoom']['default'] );
		$this->assertSame( 450, $result['attributes']['height']['default'] );
		$this->assertSame( 'satellite', $result['attributes']['type']['default'] );
	}

	/**
	 * Unrelated block metadata passes through untouched.
	 *
	 * @covers ::apply_block_attribute_defaults
	 *
	 * @return void
	 */
	public function test_apply_block_attribute_defaults_ignores_other_blocks(): void {
		$instance = Venue_Map::get_instance();
		$metadata = array(
			'name'       => 'core/paragraph',
			'attributes' => array(
				'content' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
		);

		$this->assertSame( $metadata, $instance->apply_block_attribute_defaults( $metadata ) );
	}

	/**
	 * Empty / zero settings values (e.g. a never-written row) must leave the
	 * block.json default alone rather than stamping on garbage.
	 *
	 * @covers ::apply_block_attribute_defaults
	 *
	 * @return void
	 */
	public function test_apply_block_attribute_defaults_skips_empty_settings(): void {
		$instance = Venue_Map::get_instance();
		$settings = \GatherPress\Core\Settings::get_instance();

		$settings->set( 'venue_map_default_render_mode', '' );
		$settings->set( 'venue_map_default_zoom', 0 );
		$settings->set( 'venue_map_default_height', 0 );
		$settings->set( 'venue_map_default_type', '' );

		$metadata = array(
			'name'       => 'gatherpress/venue-map',
			'attributes' => array(
				'renderMode' => array(
					'type'    => 'string',
					'default' => 'interactive',
				),
				'zoom'       => array(
					'type'    => 'number',
					'default' => 18,
				),
				'height'     => array(
					'type'    => 'number',
					'default' => 300,
				),
				'type'       => array(
					'type'    => 'string',
					'default' => 'roadmap',
				),
			),
		);

		$result = $instance->apply_block_attribute_defaults( $metadata );

		$this->assertSame( 'interactive', $result['attributes']['renderMode']['default'] );
		$this->assertSame( 18, $result['attributes']['zoom']['default'] );
		$this->assertSame( 300, $result['attributes']['height']['default'] );
		$this->assertSame( 'roadmap', $result['attributes']['type']['default'] );
	}

	/**
	 * Coverage for maybe_register_delete_hook when the post type is a venue.
	 *
	 * @covers ::maybe_register_delete_hook
	 *
	 * @return void
	 */
	public function test_maybe_register_delete_hook(): void {
		$instance = Venue_Map::get_instance();

		foreach ( get_post_types_by_support( 'gatherpress-venue-information' ) as $post_type ) {
			$instance->maybe_register_delete_hook( $post_type );

			$this->assertSame(
				10,
				has_action(
					sprintf( 'delete_post_%s', $post_type ),
					array( $instance, 'delete_stored_image' )
				),
				sprintf( 'delete_post_%s should be wired to delete_stored_image.', $post_type )
			);
		}
	}

	/**
	 * Bails silently when the post type does not support venue information.
	 *
	 * @covers ::maybe_register_delete_hook
	 *
	 * @return void
	 */
	public function test_maybe_register_delete_hook_skips_unsupported_post_type(): void {
		$instance = Venue_Map::get_instance();

		$instance->maybe_register_delete_hook( 'post' );

		$this->assertFalse(
			has_action( 'delete_post_post', array( $instance, 'delete_stored_image' ) ),
			'Expected no delete-cleanup hook to be registered for a non-venue post type.'
		);
	}

	/**
	 * The hash should change when inputs change and stay stable otherwise.
	 *
	 * @covers ::hash_for
	 *
	 * @return void
	 */
	public function test_hash_for_detects_relevant_input_changes(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$info     = array(
			'fullAddress' => '1 Infinite Loop',
			'latitude'    => '37.3318',
			'longitude'   => '-122.0312',
		);

		$baseline = $instance->hash_for( $post_id, $info, 15, 400, Venue_Map::DEFAULT_TILE_URL );

		$this->assertSame(
			$baseline,
			$instance->hash_for( $post_id, $info, 15, 400, Venue_Map::DEFAULT_TILE_URL ),
			'Hash should be stable when every input is identical.'
		);

		$moved_info              = $info;
		$moved_info['latitude']  = '37.3320';
		$moved_info['longitude'] = '-122.0314';

		$this->assertNotSame(
			$baseline,
			$instance->hash_for( $post_id, $moved_info, 15, 400, Venue_Map::DEFAULT_TILE_URL ),
			'Hash should change when coordinates change.'
		);

		$this->assertNotSame(
			$baseline,
			$instance->hash_for( $post_id, $info, 14, 400, Venue_Map::DEFAULT_TILE_URL ),
			'Hash should change when the zoom level changes.'
		);

		$this->assertNotSame(
			$baseline,
			$instance->hash_for( $post_id, $info, 15, 500, Venue_Map::DEFAULT_TILE_URL ),
			'Hash should change when the height changes.'
		);

		// Different venue → different salt → different hash even with
		// identical address/coords.
		$other_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$this->assertNotSame(
			$baseline,
			$instance->hash_for( $other_post_id, $info, 15, 400, Venue_Map::DEFAULT_TILE_URL ),
			'Hash should differ between venues because each has its own salt.'
		);
	}

	/**
	 * Writes a PNG to the uploads subdir and stores its URL in post meta.
	 *
	 * @covers ::maybe_generate
	 * @covers ::composite_image
	 * @covers ::save_image
	 * @covers ::stamp_marker
	 * @covers ::fetch_tile
	 * @covers ::get_stored_descriptor
	 *
	 * @return void
	 */
	public function test_maybe_generate_writes_image_and_descriptor(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop, Cupertino, CA',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);

		$instance->maybe_generate( $post_id );

		$descriptor = $instance->get_stored_descriptor( $post_id );

		$this->assertIsArray( $descriptor, 'Expected a descriptor array after generation.' );
		$this->assertNotEmpty( $descriptor['url'], 'Descriptor URL should be populated.' );
		$this->assertSame( 32, strlen( $descriptor['hash'] ), 'Descriptor hash should be an MD5 hex string.' );

		$path = $instance->url_to_path( $descriptor['url'] );

		$this->assertNotNull( $path, 'Saved URL should map back to a filesystem path.' );
		$this->assertFileExists( $path, 'The static-map PNG should exist on disk.' );
	}

	/**
	 * A second save with identical inputs should no-op (no file mtime change).
	 *
	 * @covers ::maybe_generate
	 *
	 * @return void
	 */
	public function test_maybe_generate_is_idempotent_when_inputs_unchanged(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);

		$instance->maybe_generate( $post_id );
		$first_descriptor = $instance->get_stored_descriptor( $post_id );
		$path             = $instance->url_to_path( $first_descriptor['url'] );
		$mtime_first      = filemtime( $path );

		// Force the filesystem mtime to change so a regeneration would be detectable.
		sleep( 1 );

		$instance->maybe_generate( $post_id );

		$this->assertSame(
			$mtime_first,
			filemtime( $path ),
			'Second save with unchanged inputs should not rewrite the PNG.'
		);
	}

	/**
	 * Changing the venue address regenerates the image with a new hash.
	 *
	 * @covers ::maybe_generate
	 *
	 * @return void
	 */
	public function test_maybe_generate_regenerates_when_coordinates_change(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);
		$instance->maybe_generate( $post_id );
		$first = $instance->get_stored_descriptor( $post_id );

		update_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '60 29th Street #343, San Francisco, CA 94110',
					'latitude'    => '37.7573',
					'longitude'   => '-122.4132',
				)
			)
		);
		$instance->maybe_generate( $post_id );
		$second = $instance->get_stored_descriptor( $post_id );

		$this->assertNotSame(
			$first['hash'],
			$second['hash'],
			'Hash should change with new coordinates.'
		);
		$this->assertNotSame(
			$first['url'],
			$second['url'],
			'URL should change because the filename includes the hash.'
		);

		$old_path = $instance->url_to_path( $first['url'] );

		$this->assertFileDoesNotExist(
			$old_path,
			'The previous static-map PNG should be cleaned up when regenerating.'
		);
	}

	/**
	 * A previously-geocoded venue whose address is edited to something
	 * un-geocodable must have its stored PNG files purged so stale images
	 * don't keep serving under the new (wrong) address.
	 *
	 * @covers ::maybe_generate
	 *
	 * @return void
	 */
	public function test_maybe_generate_purges_when_coordinates_disappear(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);
		$instance->maybe_generate( $post_id );

		$descriptor = $instance->get_stored_descriptor( $post_id );

		$this->assertIsArray( $descriptor, 'Sanity: initial save should have produced a descriptor.' );
		$path = $instance->url_to_path( $descriptor['url'] );
		$this->assertFileExists( $path );

		// Now clear the coordinates (e.g. address changed to something un-geocodable).
		update_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => 'Nonexistent Place',
					'latitude'    => '',
					'longitude'   => '',
				)
			)
		);
		$instance->maybe_generate( $post_id );

		$this->assertNull(
			$instance->get_stored_descriptor( $post_id ),
			'Descriptor meta should be cleared when coordinates become un-geocodable.'
		);
		$this->assertFileDoesNotExist(
			(string) $path,
			'Orphaned PNG should be removed when coordinates disappear.'
		);
	}

	/**
	 * No image is written when the venue lacks usable coordinates.
	 *
	 * @covers ::maybe_generate
	 *
	 * @return void
	 */
	public function test_maybe_generate_bails_without_coordinates(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => 'Somewhere, somewhere',
					'latitude'    => '',
					'longitude'   => '',
				)
			)
		);

		$instance->maybe_generate( $post_id );

		$this->assertNull(
			$instance->get_stored_descriptor( $post_id ),
			'Venues without numeric coords should not get a static map.'
		);
	}

	/**
	 * Short-circuits for revisions and autosaves.
	 *
	 * @covers ::maybe_generate
	 *
	 * @return void
	 */
	public function test_maybe_generate_skips_revisions_and_autosaves(): void {
		$instance = Venue_Map::get_instance();
		$venue_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta(
			$venue_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);

		$revision_id = wp_save_post_revision( $venue_id );

		if ( $revision_id ) {
			$instance->maybe_generate( (int) $revision_id );
			$this->assertNull(
				$instance->get_stored_descriptor( (int) $revision_id ),
				'Revisions should not receive a static map.'
			);
		}
	}

	/**
	 * Removes the PNG and the descriptor meta for the venue.
	 *
	 * @covers ::delete_stored_image
	 *
	 * @return void
	 */
	public function test_delete_stored_image_removes_file_and_meta(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);
		$instance->maybe_generate( $post_id );

		$descriptor = $instance->get_stored_descriptor( $post_id );
		$path       = $instance->url_to_path( $descriptor['url'] );

		$this->assertFileExists( $path );

		$instance->delete_stored_image( $post_id );

		$this->assertFileDoesNotExist( $path, 'PNG should be removed by delete_stored_image.' );
		$this->assertNull(
			$instance->get_stored_descriptor( $post_id ),
			'Descriptor meta should be cleared after delete_stored_image.'
		);
	}

	/**
	 * Rejects URLs that sit outside of the plugin's uploads subdir.
	 *
	 * @covers ::url_to_path
	 *
	 * @return void
	 */
	public function test_url_to_path_rejects_external_urls(): void {
		$instance = Venue_Map::get_instance();

		$this->assertNull(
			$instance->url_to_path( 'https://example.com/evil.png' ),
			'External URLs must not resolve to a filesystem path.'
		);
	}

	/**
	 * A URL whose origin matches the plugin uploads subdir but whose
	 * filename contains path-traversal segments (`..`) must not resolve —
	 * otherwise delete_file_by_url() could be steered at arbitrary files
	 * if the meta were ever writable.
	 *
	 * @covers ::url_to_path
	 *
	 * @return void
	 */
	public function test_url_to_path_rejects_traversal_in_plugin_subdir_url(): void {
		$instance = Venue_Map::get_instance();
		$dirs     = wp_get_upload_dir();
		$base_url = trailingslashit( $dirs['baseurl'] ) . Venue_Map::UPLOADS_SUBDIR . '/';

		$this->assertNull(
			$instance->url_to_path( $base_url . '../../../wp-config.php' ),
			'Traversal segments in the relative portion must not resolve.'
		);
		$this->assertNull(
			$instance->url_to_path( $base_url . '42-notahex.png' ),
			'Filenames not matching the `{post_id}-{md5}.png` shape must not resolve.'
		);
		$this->assertNull(
			$instance->url_to_path( $base_url . 'subdir/42-abcdef0123456789abcdef0123456789.png' ),
			'Nested filenames (with slashes) must not resolve.'
		);
	}

	/**
	 * Returns an empty array from get_all_descriptors for an unsaved venue.
	 *
	 * @covers ::get_all_descriptors
	 *
	 * @return void
	 */
	public function test_get_all_descriptors_empty_when_nothing_stored(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$this->assertSame( array(), $instance->get_all_descriptors( $post_id ) );
	}

	/**
	 * Silently drops malformed entries from get_all_descriptors output.
	 *
	 * @covers ::get_all_descriptors
	 *
	 * @return void
	 */
	public function test_get_all_descriptors_filters_malformed_entries(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta(
			$post_id,
			Venue_Map::META_KEY,
			array(
				'15x300'        => array(
					'url'    => 'https://example.test/a.png',
					'hash'   => 'abc',
					'zoom'   => 15,
					'height' => 300,
				),
				'18x400'        => 'not-an-array',
				'20x500'        => array(
					'url'    => 'https://example.test/b.png',
					'zoom'   => 20,
					'height' => 500,
				), // Missing hash.
				'missing-shape' => array(
					'url'  => 'https://example.test/c.png',
					'hash' => 'def',
				), // Missing zoom/height.
			)
		);

		$descriptors = $instance->get_all_descriptors( $post_id );

		$this->assertArrayHasKey( '15x300', $descriptors );
		$this->assertArrayNotHasKey( '18x400', $descriptors );
		$this->assertArrayNotHasKey( '20x500', $descriptors );
		$this->assertArrayNotHasKey( 'missing-shape', $descriptors );
		$this->assertSame( 15, $descriptors['15x300']['zoom'] );
		$this->assertSame( 300, $descriptors['15x300']['height'] );

		// Read path must not mutate the meta — writing on every render would
		// thrash the post-meta cache and churn the DB. Cleanup is deferred
		// to the next save path.
		$raw_after_read = get_post_meta( $post_id, Venue_Map::META_KEY, true );
		$this->assertCount(
			4,
			$raw_after_read,
			'get_all_descriptors() must not rewrite meta on read.'
		);
	}

	/**
	 * The next write through ensure_descriptor_for_combo() rebuilds meta
	 * from the filtered descriptor map, so any malformed entries that were
	 * silently skipped on read are dropped from storage at that point.
	 * Confirms the read-path's deferred-cleanup strategy actually cleans up.
	 *
	 * @covers ::ensure_descriptor_for_combo
	 * @covers ::get_all_descriptors
	 *
	 * @return void
	 */
	public function test_ensure_descriptor_for_combo_drops_malformed_entries_on_write(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);

		// Seed meta with a mix of good and malformed entries.
		update_post_meta(
			$post_id,
			Venue_Map::META_KEY,
			array(
				'junk-row' => 'not-an-array',
				'no-hash'  => array(
					'url'    => 'https://example.test/b.png',
					'zoom'   => 20,
					'height' => 500,
				),
			)
		);

		$instance->maybe_generate( $post_id );

		$stored = get_post_meta( $post_id, Venue_Map::META_KEY, true );

		$this->assertIsArray( $stored );
		$this->assertArrayNotHasKey( 'junk-row', $stored );
		$this->assertArrayNotHasKey( 'no-hash', $stored );

		$default_key = sprintf(
			'%dx%d',
			Venue_Map::DEFAULT_ZOOM,
			Venue_Map::DEFAULT_HEIGHT
		);
		$this->assertArrayHasKey( $default_key, $stored );
	}

	/**
	 * Requesting a previously-uncached (zoom, height) combo triggers
	 * synchronous generation and caches the result for next time.
	 *
	 * @covers ::get_url_for_post
	 * @covers ::ensure_descriptor_for_combo
	 *
	 * @return void
	 */
	public function test_get_url_for_post_lazily_generates_new_combo(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);
		$instance->maybe_generate( $post_id );

		$default_key = sprintf(
			'%dx%d',
			Venue_Map::DEFAULT_ZOOM,
			Venue_Map::DEFAULT_HEIGHT
		);

		$descriptors_before = $instance->get_all_descriptors( $post_id );

		$this->assertCount(
			1,
			$descriptors_before,
			'Initial save should seed only the default (zoom, height) combo.'
		);
		$this->assertArrayHasKey( $default_key, $descriptors_before );

		// Request a different zoom — simulates a block customized to zoom 14.
		$new_key = sprintf( '14x%d', Venue_Map::DEFAULT_HEIGHT );
		$url     = $instance->get_url_for_post( $post_id, Venue::POST_TYPE, 14 );

		$this->assertNotEmpty( $url, 'Lazy generation should return a non-empty URL.' );

		$descriptors_after = $instance->get_all_descriptors( $post_id );

		$this->assertCount( 2, $descriptors_after, 'New combo should have been cached.' );
		$this->assertArrayHasKey( $new_key, $descriptors_after );
		$this->assertSame( $url, $descriptors_after[ $new_key ]['url'] );
	}

	/**
	 * Requesting a combo with a different height from the default also caches
	 * a new descriptor — the PNG is rendered at exactly that block height.
	 *
	 * @covers ::get_url_for_post
	 * @covers ::ensure_descriptor_for_combo
	 *
	 * @return void
	 */
	public function test_get_url_for_post_lazily_generates_new_height(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);
		$instance->maybe_generate( $post_id );

		$tall_url = $instance->get_url_for_post(
			$post_id,
			Venue::POST_TYPE,
			Venue_Map::DEFAULT_ZOOM,
			500
		);

		$this->assertNotEmpty( $tall_url );

		$key = sprintf( '%dx500', Venue_Map::DEFAULT_ZOOM );
		$all = $instance->get_all_descriptors( $post_id );

		$this->assertArrayHasKey( $key, $all, 'Tall-height combo should be cached under its own key.' );
		$this->assertSame( 500, $all[ $key ]['height'] );
	}

	/**
	 * A content change (e.g. new coordinates) regenerates every cached
	 * (zoom, height) combo.
	 *
	 * @covers ::maybe_generate
	 * @covers ::ensure_descriptor_for_combo
	 * @covers ::get_cached_combos
	 *
	 * @return void
	 */
	public function test_maybe_generate_cascades_content_changes_to_all_cached_combos(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		update_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);
		$instance->maybe_generate( $post_id );
		// Warm a second combo (not the default seed) so we have two variants.
		$instance->get_url_for_post( $post_id, Venue::POST_TYPE, 14, 500 );

		$default_key = sprintf(
			'%dx%d',
			Venue_Map::DEFAULT_ZOOM,
			Venue_Map::DEFAULT_HEIGHT
		);
		$second_key  = '14x500';

		$before = $instance->get_all_descriptors( $post_id );

		$this->assertCount( 2, $before );

		// Change the address. maybe_generate should regenerate both combos.
		update_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '60 29th Street #343, San Francisco, CA 94110',
					'latitude'    => '37.7573',
					'longitude'   => '-122.4132',
				)
			)
		);
		$instance->maybe_generate( $post_id );

		$after = $instance->get_all_descriptors( $post_id );

		$this->assertCount( 2, $after, 'Both combos should still be present after regeneration.' );
		$this->assertNotSame(
			$before[ $default_key ]['hash'],
			$after[ $default_key ]['hash'],
			'Default-combo hash should change with new coordinates.'
		);
		$this->assertNotSame(
			$before[ $second_key ]['hash'],
			$after[ $second_key ]['hash'],
			'Second-combo hash should change with new coordinates.'
		);

		// Old files should be gone.
		$this->assertFileDoesNotExist(
			(string) $instance->url_to_path( $before[ $default_key ]['url'] )
		);
		$this->assertFileDoesNotExist(
			(string) $instance->url_to_path( $before[ $second_key ]['url'] )
		);
	}

	/**
	 * If the meta write after save_image() is dropped (e.g. under an
	 * object-cache outage or a filter that suppresses the update),
	 * ensure_descriptor_for_combo() must unlink the just-saved PNG and
	 * return null so we don't leave an orphan on disk.
	 *
	 * @covers ::ensure_descriptor_for_combo
	 *
	 * @return void
	 */
	public function test_ensure_descriptor_for_combo_unlinks_orphan_when_meta_write_dropped(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);

		// Short-circuit update_post_metadata so the map descriptor write
		// never reaches the DB. The filter returns a non-null value for
		// META_KEY only, leaving other meta writes alone.
		$suppress = static function ( $check, $object_id, $meta_key ) {
			if ( Venue_Map::META_KEY === $meta_key ) {
				return true; // Pretend the update happened.
			}
			return $check;
		};
		add_filter( 'update_post_metadata', $suppress, 10, 3 );

		$info = ( new Venue( $post_id ) )->get_information();

		$result = Utility::invoke_hidden_method(
			$instance,
			'ensure_descriptor_for_combo',
			array( $post_id, $info, Venue_Map::DEFAULT_ZOOM, Venue_Map::DEFAULT_HEIGHT )
		);

		remove_filter( 'update_post_metadata', $suppress, 10 );

		$this->assertNull(
			$result,
			'Orphan-guard must return null when the meta write was suppressed.'
		);

		$dirs     = wp_get_upload_dir();
		$base_dir = trailingslashit( $dirs['basedir'] ) . Venue_Map::UPLOADS_SUBDIR;

		$this->assertEmpty(
			(array) glob( $base_dir . sprintf( '/%d-*.png', $post_id ) ),
			'The orphan PNG should have been unlinked.'
		);
	}

	/**
	 * Direct coverage for salt_for — lazily generates a salt on first
	 * access and returns the same value on subsequent calls.
	 *
	 * @covers ::salt_for
	 *
	 * @return void
	 */
	public function test_salt_for_generates_and_caches(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$this->assertSame(
			'',
			(string) get_post_meta( $post_id, Venue_Map::SALT_META_KEY, true ),
			'Precondition: freshly created venue has no salt meta.'
		);

		$first = Utility::invoke_hidden_method( $instance, 'salt_for', array( $post_id ) );

		$this->assertIsString( $first );
		$this->assertSame( 32, strlen( $first ), 'Salt should be 32 characters.' );
		$this->assertSame(
			$first,
			(string) get_post_meta( $post_id, Venue_Map::SALT_META_KEY, true ),
			'Salt should be persisted to meta on first access.'
		);

		$second = Utility::invoke_hidden_method( $instance, 'salt_for', array( $post_id ) );

		$this->assertSame(
			$first,
			$second,
			'Subsequent calls should return the same cached salt.'
		);
	}

	/**
	 * Simulates two concurrent first-touch callers by forcing add_post_meta
	 * to fail for the losing request (meaning another request just wrote
	 * the salt between our read and write). salt_for() must then re-read
	 * and return the winner's value, not its own abandoned candidate —
	 * otherwise the loser's PNG filename won't match what any subsequent
	 * request computes.
	 *
	 * @covers ::salt_for
	 *
	 * @return void
	 */
	public function test_salt_for_recovers_from_lost_race(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$winner   = 'winnerwinnerwinnerwinnerwinner12';

		// Hook `add_post_metadata` to simulate another request winning the
		// race between our read and our write: right before our
		// add_post_meta() lands, the hook writes the winner value straight
		// to the DB and returns false to tell add_post_meta() "already
		// exists, didn't write". salt_for() must recover by re-reading the
		// winner. The write goes through $wpdb directly so it doesn't fire
		// add_post_metadata again and recurse.
		global $wpdb;
		$force_race_loss = static function ( $check, $object_id, $meta_key ) use ( $winner, $post_id, $wpdb ) {
			if ( Venue_Map::SALT_META_KEY !== $meta_key || $object_id !== $post_id ) {
				return $check;
			}
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Test fixture bypasses add_post_meta() to avoid recursing into the filter we're inside.
			$wpdb->insert(
				$wpdb->postmeta,
				array(
					'post_id'    => $post_id,
					'meta_key'   => Venue_Map::SALT_META_KEY,
					'meta_value' => $winner,
				)
			);
			// phpcs:enable
			wp_cache_delete( $post_id, 'post_meta' );
			return false;
		};
		add_filter( 'add_post_metadata', $force_race_loss, 10, 3 );

		$resolved = Utility::invoke_hidden_method( $instance, 'salt_for', array( $post_id ) );

		remove_filter( 'add_post_metadata', $force_race_loss, 10 );

		$this->assertSame(
			$winner,
			$resolved,
			'Losing request must re-read the winner salt, not return its own candidate.'
		);
	}

	/**
	 * Direct coverage for delete_file_by_url — unlinks only when the URL
	 * resolves to a path inside the plugin's uploads subdir.
	 *
	 * @covers ::delete_file_by_url
	 *
	 * @return void
	 */
	public function test_delete_file_by_url_unlinks_in_scope(): void {
		$instance = Venue_Map::get_instance();

		// Create a fixture file the method is allowed to delete.
		$dirs     = wp_get_upload_dir();
		$base_dir = trailingslashit( $dirs['basedir'] ) . Venue_Map::UPLOADS_SUBDIR;
		wp_mkdir_p( $base_dir );

		// Use a filename in the `{post_id}-{md5}.png` shape url_to_path()
		// accepts. A fake hex string stands in for a real MD5 digest.
		$filename = '99-abcdef0123456789abcdef0123456789.png';
		$path     = $base_dir . '/' . $filename;
		$url      = trailingslashit( $dirs['baseurl'] )
			. Venue_Map::UPLOADS_SUBDIR . '/' . $filename;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Fixture write inside the test-only uploads dir.
		file_put_contents( $path, 'stub' );

		$this->assertFileExists( $path );

		Utility::invoke_hidden_method( $instance, 'delete_file_by_url', array( $url ) );

		$this->assertFileDoesNotExist( $path, 'In-scope file should be removed.' );

		// Out-of-scope URL should no-op (url_to_path returns null).
		Utility::invoke_hidden_method(
			$instance,
			'delete_file_by_url',
			array( 'https://example.test/outside.png' )
		);
		// Terminal assertion to keep PHPUnit happy — the real guarantee is
		// that no exception is thrown and no unexpected side effects occur.
		$this->assertTrue( true );
	}

	/**
	 * Direct coverage for ensure_descriptor_for_combo when save_image fails.
	 *
	 * Exercising it through the public API (maybe_generate) leaves this
	 * branch uncredited by xdebug, so we invoke the protected method directly.
	 *
	 * @covers ::ensure_descriptor_for_combo
	 *
	 * @return void
	 */
	public function test_ensure_descriptor_for_combo_returns_null_when_save_fails(): void {
		$instance = Venue_Map::get_instance();

		$force_error = static function ( $dirs ) {
			$dirs['error'] = 'Simulated uploads failure.';
			return $dirs;
		};
		add_filter( 'upload_dir', $force_error );

		$result = Utility::invoke_hidden_method(
			$instance,
			'ensure_descriptor_for_combo',
			array(
				42,
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				),
				15,
				300,
			)
		);

		remove_filter( 'upload_dir', $force_error );

		$this->assertNull( $result, 'Null return when save_image cannot write the file.' );
	}

	/**
	 * Second call to get_url_for_post for the same combo hits the filesystem
	 * cache (no PNG rewrite).
	 *
	 * @covers ::ensure_descriptor_for_combo
	 *
	 * @return void
	 */
	public function test_get_url_for_post_is_cached_on_second_call(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);

		$first  = $instance->get_url_for_post( $post_id, Venue::POST_TYPE, 17 );
		$path   = (string) $instance->url_to_path( $first );
		$mtime1 = filemtime( $path );

		sleep( 1 );

		$second = $instance->get_url_for_post( $post_id, Venue::POST_TYPE, 17 );

		$this->assertSame( $first, $second, 'Second call should return the same URL.' );
		$this->assertSame( $mtime1, filemtime( $path ), 'Second call must not rewrite the PNG.' );
	}

	/**
	 * Returns the stored URL when given a venue post ID directly.
	 *
	 * @covers ::get_url_for_post
	 *
	 * @return void
	 */
	public function test_get_url_for_post_resolves_venue_directly(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);
		$instance->maybe_generate( $post_id );

		$descriptor = $instance->get_stored_descriptor( $post_id );

		$this->assertSame(
			$descriptor['url'],
			$instance->get_url_for_post( $post_id, Venue::POST_TYPE )
		);
	}

	/**
	 * Walks event → linked venue to resolve the static map URL.
	 *
	 * @covers ::get_url_for_post
	 *
	 * @return void
	 */
	public function test_get_url_for_post_resolves_event_via_linked_venue(): void {
		$instance    = Venue_Map::get_instance();
		$venue_setup = \GatherPress\Core\Venue_Setup::get_instance();

		$venue = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'venue-for-url-helper',
				'post_title' => 'Venue For URL Helper',
			)
		)->get();

		$event = $this->mock->post(
			array(
				'post_type' => \GatherPress\Core\Event::POST_TYPE,
				'post_name' => 'event-for-url-helper',
			)
		)->get();

		$term_slug = $venue_setup->term_slug_from_post_name( $venue->post_name );
		wp_set_post_terms( $event->ID, $term_slug, Venue::TAXONOMY );

		update_post_meta(
			$venue->ID,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);
		$instance->maybe_generate( $venue->ID );

		$descriptor = $instance->get_stored_descriptor( $venue->ID );

		$this->assertSame(
			$descriptor['url'],
			$instance->get_url_for_post( $event->ID, \GatherPress\Core\Event::POST_TYPE )
		);
	}

	/**
	 * Returns '' from get_url_for_post for posts unrelated to venues.
	 *
	 * @covers ::get_url_for_post
	 *
	 * @return void
	 */
	public function test_get_url_for_post_returns_empty_for_unrelated_post(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$this->assertSame( '', $instance->get_url_for_post( $post_id, 'post' ) );
	}

	/**
	 * Returns '' from get_url_for_post when a venue has no stored map yet.
	 *
	 * @covers ::get_url_for_post
	 *
	 * @return void
	 */
	public function test_get_url_for_post_returns_empty_when_no_map_generated(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$this->assertSame( '', $instance->get_url_for_post( $post_id, Venue::POST_TYPE ) );
	}

	/**
	 * Resolves a URL inside the plugin's uploads subdir back to a path.
	 *
	 * @covers ::url_to_path
	 *
	 * @return void
	 */
	public function test_url_to_path_resolves_plugin_subdir_url(): void {
		$instance = Venue_Map::get_instance();
		$dirs     = wp_get_upload_dir();
		$filename = '42-abcdef0123456789abcdef0123456789.png';
		$url      = trailingslashit( $dirs['baseurl'] )
			. Venue_Map::UPLOADS_SUBDIR . '/' . $filename;
		$expected = trailingslashit( $dirs['basedir'] )
			. Venue_Map::UPLOADS_SUBDIR . '/' . $filename;

		$this->assertSame( $expected, $instance->url_to_path( $url ) );
	}

	/**
	 * Bails silently when called on a non-venue post type.
	 *
	 * @covers ::maybe_generate
	 *
	 * @return void
	 */
	public function test_maybe_generate_skips_unsupported_post_type(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$instance->maybe_generate( $post_id );

		$this->assertNull(
			$instance->get_stored_descriptor( $post_id ),
			'Non-venue post types must not receive a static map.'
		);
	}

	/**
	 * Returns null from fetch_tile when the HTTP response is an error.
	 *
	 * @covers ::fetch_tile
	 *
	 * @return void
	 */
	public function test_fetch_tile_returns_null_on_http_error(): void {
		$instance = Venue_Map::get_instance();

		// Replace the success stub with a WP_Error short-circuit just for this test.
		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );
		$fail = static function () {
			return new \WP_Error( 'boom', 'tile fetch failed' );
		};
		add_filter( 'pre_http_request', $fail, 10 );

		$this->assertNull(
			$instance->fetch_tile( 15, 1, 1, Venue_Map::DEFAULT_TILE_URL )
		);

		remove_filter( 'pre_http_request', $fail, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );
	}

	/**
	 * Returns null from fetch_tile when the HTTP response is not 200 OK.
	 *
	 * @covers ::fetch_tile
	 *
	 * @return void
	 */
	public function test_fetch_tile_returns_null_on_non_200_response(): void {
		$instance = Venue_Map::get_instance();

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );
		$not_found = static function () {
			return array(
				'headers'  => array(),
				'body'     => '',
				'response' => array(
					'code'    => 404,
					'message' => 'Not Found',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $not_found, 10 );

		$this->assertNull(
			$instance->fetch_tile( 15, 1, 1, Venue_Map::DEFAULT_TILE_URL )
		);

		remove_filter( 'pre_http_request', $not_found, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );
	}

	/**
	 * Returns PNG bytes from fetch_tile when the request succeeds.
	 *
	 * @covers ::fetch_tile
	 *
	 * @return void
	 */
	public function test_fetch_tile_returns_png_bytes(): void {
		$instance = Venue_Map::get_instance();

		$this->assertSame(
			$this->tile_png,
			$instance->fetch_tile( 15, 1, 1, Venue_Map::DEFAULT_TILE_URL )
		);
	}

	/**
	 * Directly exercise composite_image so coverage credits the method entry.
	 *
	 * @covers ::composite_image
	 *
	 * @return void
	 */
	public function test_composite_image_returns_gd_image(): void {
		$instance = Venue_Map::get_instance();

		$image = $instance->composite_image(
			37.3318,
			-122.0312,
			15,
			256,
			Venue_Map::DEFAULT_TILE_URL
		);

		$this->assertInstanceOf( \GdImage::class, $image );
		// Width is derived from height via IMAGE_ASPECT_RATIO (2.0) → 512×256.
		$this->assertSame( 512, imagesx( $image ) );
		$this->assertSame( 256, imagesy( $image ) );

		imagedestroy( $image );
	}

	/**
	 * Draws a marker into the provided canvas.
	 *
	 * @covers ::stamp_marker
	 *
	 * @return void
	 */
	public function test_stamp_marker_draws_on_canvas(): void {
		$instance = Venue_Map::get_instance();
		$canvas   = imagecreatetruecolor( 40, 40 );
		$instance->stamp_marker( $canvas, 20, 20 );

		// The marker center should be white (inner dot).
		$index = imagecolorat( $canvas, 20, 20 );
		$rgb   = imagecolorsforindex( $canvas, $index );

		$this->assertSame( 255, $rgb['red'] );
		$this->assertSame( 255, $rgb['green'] );
		$this->assertSame( 255, $rgb['blue'] );

		imagedestroy( $canvas );
	}

	/**
	 * Writes the PNG and returns a URL inside the plugin uploads subdir.
	 *
	 * @covers ::save_image
	 *
	 * @return void
	 */
	public function test_save_image_writes_file_and_returns_url(): void {
		$instance = Venue_Map::get_instance();
		$canvas   = imagecreatetruecolor( 10, 10 );

		// Use a 32-char hex hash matching what hash_for() produces; the
		// url_to_path allow-list rejects anything else.
		$hash = str_repeat( 'a', 32 );
		$url  = $instance->save_image( $canvas, 99, $hash );
		imagedestroy( $canvas );

		$this->assertNotNull( $url, 'save_image should return a URL on success.' );
		$this->assertStringContainsString( Venue_Map::UPLOADS_SUBDIR, $url );

		$path = $instance->url_to_path( $url );
		$this->assertFileExists( $path );
	}

	/**
	 * Coverage for parse_coord — numeric input → float, anything else → null.
	 *
	 * @covers ::parse_coord
	 *
	 * @return void
	 */
	public function test_parse_coord(): void {
		$instance = Venue_Map::get_instance();

		$this->assertSame(
			37.3318,
			Utility::invoke_hidden_method( $instance, 'parse_coord', array( '37.3318' ) )
		);
		$this->assertNull(
			Utility::invoke_hidden_method( $instance, 'parse_coord', array( 'not a number' ) )
		);
		$this->assertNull(
			Utility::invoke_hidden_method( $instance, 'parse_coord', array( '' ) )
		);
	}

	/**
	 * Coverage for get_zoom — prefers Settings, falls back to constant,
	 * then passes through the filter.
	 *
	 * @covers ::get_zoom
	 *
	 * @return void
	 */
	public function test_get_zoom(): void {
		$instance = Venue_Map::get_instance();
		$settings = \GatherPress\Core\Settings::get_instance();

		$settings->set( 'venue_map_default_zoom', 13 );
		$this->assertSame(
			13,
			Utility::invoke_hidden_method( $instance, 'get_zoom' ),
			'Should prefer the stored Settings value.'
		);

		$settings->set( 'venue_map_default_zoom', 0 );
		$this->assertSame(
			Venue_Map::DEFAULT_ZOOM,
			Utility::invoke_hidden_method( $instance, 'get_zoom' ),
			'Should fall back to DEFAULT_ZOOM when the setting is unset/zero.'
		);
	}

	/**
	 * Clamps out-of-range zoom values from the Settings row or filter to
	 * the supported range. Otherwise a stale/hand-edited setting or filter
	 * override could drive the generator to render a useless world-view
	 * (zoom 0) or crash out on a value the tile provider won't serve
	 * (zoom 30+).
	 *
	 * @covers ::get_zoom
	 * @covers ::clamp_zoom
	 *
	 * @return void
	 */
	public function test_get_zoom_clamps_out_of_range_values(): void {
		$instance = Venue_Map::get_instance();
		$settings = \GatherPress\Core\Settings::get_instance();

		$settings->set( 'venue_map_default_zoom', 0 );
		$too_high = static function () {
			return 99;
		};
		add_filter( 'gatherpress_venue_map_zoom', $too_high );
		$this->assertSame(
			Venue_Map::ZOOM_MAX,
			Utility::invoke_hidden_method( $instance, 'get_zoom' ),
			'Zoom beyond ZOOM_MAX must clamp down.'
		);
		remove_filter( 'gatherpress_venue_map_zoom', $too_high );

		$too_low = static function () {
			return 0;
		};
		add_filter( 'gatherpress_venue_map_zoom', $too_low );
		$this->assertSame(
			Venue_Map::ZOOM_MIN,
			Utility::invoke_hidden_method( $instance, 'get_zoom' ),
			'Zoom below ZOOM_MIN must clamp up.'
		);
		remove_filter( 'gatherpress_venue_map_zoom', $too_low );
	}

	/**
	 * Same coverage for get_height — clamps Settings + filter overrides.
	 *
	 * @covers ::get_height
	 * @covers ::clamp_height
	 *
	 * @return void
	 */
	public function test_get_height_clamps_out_of_range_values(): void {
		$instance = Venue_Map::get_instance();
		$settings = \GatherPress\Core\Settings::get_instance();

		$settings->set( 'venue_map_default_height', 0 );
		$too_big = static function () {
			return 9999;
		};
		add_filter( 'gatherpress_venue_map_height', $too_big );
		$this->assertSame(
			Venue_Map::HEIGHT_MAX,
			Utility::invoke_hidden_method( $instance, 'get_height' ),
			'Height beyond HEIGHT_MAX must clamp down.'
		);
		remove_filter( 'gatherpress_venue_map_height', $too_big );
	}

	/**
	 * Coverage for get_height — prefers Settings, falls back to constant,
	 * then passes through the filter. Mirrors test_get_zoom.
	 *
	 * @covers ::get_height
	 *
	 * @return void
	 */
	public function test_get_height(): void {
		$instance = Venue_Map::get_instance();
		$settings = \GatherPress\Core\Settings::get_instance();

		$settings->set( 'venue_map_default_height', 450 );
		$this->assertSame(
			450,
			Utility::invoke_hidden_method( $instance, 'get_height' ),
			'Should prefer the stored Settings value.'
		);

		$settings->set( 'venue_map_default_height', 0 );
		$this->assertSame(
			Venue_Map::DEFAULT_HEIGHT,
			Utility::invoke_hidden_method( $instance, 'get_height' ),
			'Should fall back to DEFAULT_HEIGHT when the setting is unset/zero.'
		);
	}

	/**
	 * Coverage for the generator's tile URL getter.
	 *
	 * @covers ::get_tile_url_template
	 *
	 * @return void
	 */
	public function test_get_tile_url_template(): void {
		$instance = Venue_Map::get_instance();

		$this->assertSame(
			Venue_Map::DEFAULT_TILE_URL,
			Utility::invoke_hidden_method( $instance, 'get_tile_url_template' )
		);
	}

	/**
	 * Coverage for combo_key — formats `{zoom}x{height}`.
	 *
	 * @covers ::combo_key
	 *
	 * @return void
	 */
	public function test_combo_key_formats_zoom_and_height(): void {
		$instance = Venue_Map::get_instance();

		$this->assertSame(
			'14x500',
			Utility::invoke_hidden_method( $instance, 'combo_key', array( 14, 500 ) )
		);
	}

	/**
	 * Coverage for get_cached_combos — returns unique (zoom, height) combos.
	 *
	 * @covers ::get_cached_combos
	 *
	 * @return void
	 */
	public function test_get_cached_combos(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$this->assertSame(
			array(),
			$instance->get_cached_combos( $post_id ),
			'Empty meta should yield zero combos.'
		);

		update_post_meta(
			$post_id,
			Venue_Map::META_KEY,
			array(
				'15x300' => array(
					'url'    => 'https://example.test/a.png',
					'hash'   => 'abc',
					'zoom'   => 15,
					'height' => 300,
				),
				'18x500' => array(
					'url'    => 'https://example.test/b.png',
					'hash'   => 'def',
					'zoom'   => 18,
					'height' => 500,
				),
			)
		);

		$combos = $instance->get_cached_combos( $post_id );

		$this->assertCount( 2, $combos );
		$this->assertContains(
			array(
				'zoom'   => 15,
				'height' => 300,
			),
			$combos
		);
		$this->assertContains(
			array(
				'zoom'   => 18,
				'height' => 500,
			),
			$combos
		);
	}

	/**
	 * When the composite time budget is exhausted, the inner loop breaks
	 * out and the canvas is returned with the pre-painted gray background
	 * intact — no tiles fetched. Filter-driven so we don't have to wait
	 * COMPOSITE_TIME_BUDGET seconds to exercise the branch.
	 *
	 * @covers ::composite_image
	 *
	 * @return void
	 */
	public function test_composite_image_aborts_when_time_budget_exhausted(): void {
		$instance = Venue_Map::get_instance();

		$fetches = 0;
		$counter = static function () use ( &$fetches ) {
			++$fetches;
			return array(
				'headers'  => array(),
				'body'     => '',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		$budget  = static function () {
			// A negative budget makes the deadline sit in the past from
			// the first iteration, forcing break 2 before any fetch runs.
			return -1;
		};

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );
		add_filter( 'pre_http_request', $counter, 10 );
		add_filter( 'gatherpress_venue_map_composite_time_budget', $budget );

		$image = $instance->composite_image(
			37.3318,
			-122.0312,
			15,
			256,
			Venue_Map::DEFAULT_TILE_URL
		);

		remove_filter( 'gatherpress_venue_map_composite_time_budget', $budget );
		remove_filter( 'pre_http_request', $counter, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );

		$this->assertInstanceOf(
			\GdImage::class,
			$image,
			'Canvas should still be returned when the budget is exhausted.'
		);
		$this->assertSame( 0, $fetches, 'No tiles should be fetched when the deadline is already in the past.' );

		// Center pixel should remain the neutral-gray background color since
		// no tile was drawn.
		$index = imagecolorat( $image, 128, 64 );
		$rgb   = imagecolorsforindex( $image, $index );
		$this->assertSame( 238, $rgb['red'] );
		$this->assertSame( 238, $rgb['green'] );
		$this->assertSame( 238, $rgb['blue'] );

		imagedestroy( $image );
	}

	/**
	 * Survives a tile that fails to fetch (falls through, blank area).
	 *
	 * @covers ::composite_image
	 *
	 * @return void
	 */
	public function test_composite_image_continues_past_failed_fetch(): void {
		$instance = Venue_Map::get_instance();

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );
		$fail = static function () {
			return new \WP_Error( 'boom', 'tile fetch failed' );
		};
		add_filter( 'pre_http_request', $fail, 10 );

		$image = $instance->composite_image(
			37.3318,
			-122.0312,
			15,
			256,
			Venue_Map::DEFAULT_TILE_URL
		);

		remove_filter( 'pre_http_request', $fail, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );

		$this->assertInstanceOf(
			\GdImage::class,
			$image,
			'A failed tile fetch should leave the canvas intact rather than nulling the result.'
		);

		imagedestroy( $image );
	}

	/**
	 * Survives a tile whose response body is not a valid PNG.
	 *
	 * @covers ::composite_image
	 *
	 * @return void
	 */
	public function test_composite_image_continues_past_invalid_png(): void {
		$instance = Venue_Map::get_instance();

		remove_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10 );
		$garbage = static function () {
			return array(
				'headers'  => array(),
				'body'     => 'not a png',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $garbage, 10 );

		$image = $instance->composite_image(
			37.3318,
			-122.0312,
			15,
			256,
			Venue_Map::DEFAULT_TILE_URL
		);

		remove_filter( 'pre_http_request', $garbage, 10 );
		add_filter( 'pre_http_request', array( $this, 'short_circuit_tile_requests' ), 10, 3 );

		$this->assertInstanceOf( \GdImage::class, $image );

		imagedestroy( $image );
	}

	/**
	 * Returns null from save_image when wp_get_upload_dir reports an error.
	 *
	 * @covers ::save_image
	 *
	 * @return void
	 */
	public function test_save_image_returns_null_when_uploads_report_error(): void {
		$instance = Venue_Map::get_instance();
		$canvas   = imagecreatetruecolor( 10, 10 );

		$force_error = static function ( $dirs ) {
			$dirs['error'] = 'Simulated uploads failure.';
			return $dirs;
		};
		add_filter( 'upload_dir', $force_error );

		$url = $instance->save_image( $canvas, 99, 'abc' );

		remove_filter( 'upload_dir', $force_error );
		imagedestroy( $canvas );

		$this->assertNull(
			$url,
			'save_image must report failure when the uploads dir itself is unavailable.'
		);
	}

	/**
	 * Bails without writing meta when save_image fails.
	 *
	 * @covers ::maybe_generate
	 *
	 * @return void
	 */
	public function test_maybe_generate_bails_when_save_fails(): void {
		$instance = Venue_Map::get_instance();
		$post_id  = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '1 Infinite Loop',
					'latitude'    => '37.3318',
					'longitude'   => '-122.0312',
				)
			)
		);

		$force_error = static function ( $dirs ) {
			$dirs['error'] = 'Simulated uploads failure.';
			return $dirs;
		};
		add_filter( 'upload_dir', $force_error );

		$instance->maybe_generate( $post_id );

		remove_filter( 'upload_dir', $force_error );

		$this->assertNull(
			$instance->get_stored_descriptor( $post_id ),
			'No descriptor should be stored when save_image fails.'
		);
	}

	/**
	 * Coverage for the lng/lat → world-pixel conversions.
	 *
	 * Verifies the canonical slippy-map invariants: `lng = 0, zoom = 0` sits
	 * at the middle of the 256-pixel world, and `lat = 0` likewise.
	 *
	 * @covers ::lng_to_world_pixel
	 * @covers ::lat_to_world_pixel
	 *
	 * @return void
	 */
	public function test_world_pixel_conversions(): void {
		$instance = Venue_Map::get_instance();

		$this->assertSame(
			128.0,
			Utility::invoke_hidden_method( $instance, 'lng_to_world_pixel', array( 0.0, 0 ) )
		);
		$this->assertEqualsWithDelta(
			128.0,
			Utility::invoke_hidden_method( $instance, 'lat_to_world_pixel', array( 0.0, 0 ) ),
			0.000001
		);
	}
}
