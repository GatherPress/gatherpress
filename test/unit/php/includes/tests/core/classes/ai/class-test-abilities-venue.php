<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Abilities_Venue.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\Abilities_Venue;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Abilities_Venue.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Abilities_Venue
 */
class Test_Abilities_Venue extends Base {
	/**
	 * Returns a venue abilities handler instance.
	 *
	 * @return Abilities_Venue
	 */
	private function get_venue_instance(): Abilities_Venue {
		return new Abilities_Venue();
	}

	/**
	 * Set venue meta using the current GatherPress meta keys.
	 *
	 * @param int                   $venue_id Venue post ID.
	 * @param array<string, string> $fields   Field values keyed by unprefixed meta suffix.
	 * @return void
	 */
	private function set_venue_test_meta( int $venue_id, array $fields ): void {
		foreach ( $fields as $field => $value ) {
			update_post_meta( $venue_id, Utility::prefix_key( $field ), $value );
		}
	}

	/**
	 * Read venue meta using the current GatherPress Venue API.
	 *
	 * @param int $venue_id Venue post ID.
	 * @return array<string, string>
	 */
	private function get_venue_test_meta( int $venue_id ): array {
		return ( new Venue( $venue_id ) )->get_information();
	}

	/**
	 * Coverage for execute_create_venue method with valid parameters.
	 *
	 * @covers ::execute_create_venue
	 *
	 * @return void
	 */
	public function test_execute_create_venue_with_valid_params(): void {
		$venue  = $this->get_venue_instance();
		$params = array(
			'name'    => 'Test Venue',
			'address' => '456 Test St',
			'phone'   => '555-9999',
			'website' => 'https://test.com',
		);
		$result = $venue->execute_create_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsInt( $result['venue_id'], 'Failed to assert venue_id is an integer.' );
		$this->assertStringContainsString( 'Test Venue', $result['message'], 'Failed to assert message contains venue name.' ); // phpcs:ignore Generic.Files.LineLength.TooLong

		// Verify venue was created.
		$venue_post = get_post( $result['venue_id'] );
		$this->assertSame( Venue::POST_TYPE, $venue_post->post_type, 'Failed to assert post type is venue.' );
		$this->assertSame( 'Test Venue', $venue_post->post_title, 'Failed to assert venue title.' );
		$this->assertStringContainsString( 'gatherpress/venue-template', $venue_post->post_content, 'Failed to assert venue template pattern.' ); // phpcs:ignore Generic.Files.LineLength.TooLong

		$venue_info = $this->get_venue_test_meta( $result['venue_id'] );
		$this->assertSame( '456 Test St', $venue_info['address'], 'Failed to assert venue address saved.' );
		$this->assertSame( '555-9999', $venue_info['phone'], 'Failed to assert venue phone saved.' );
		$this->assertSame( 'https://test.com', $venue_info['website'], 'Failed to assert venue website saved.' );
	}
	/**
	 * Coverage for geocoding functionality in execute_create_venue.
	 *
	 * @covers ::execute_create_venue
	 * @covers ::geocode_address
	 *
	 * @return void
	 */
	public function test_execute_create_venue_geocodes_address(): void {
		$venue  = $this->get_venue_instance();
		$params = array(
			'name'    => 'Test Geocoded Venue',
			'address' => '1600 Amphitheater Parkway, Mountain View, CA',
		);
		$result = $venue->execute_create_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify venue information includes geocoded coordinates.
		$venue_info = $this->get_venue_test_meta( $result['venue_id'] );
		$this->assertArrayHasKey( 'latitude', $venue_info, 'Failed to assert latitude exists.' );
		$this->assertArrayHasKey( 'longitude', $venue_info, 'Failed to assert longitude exists.' );

		// Verify coordinates are not the default '0' values (which would indicate geocoding failed).
		// Note: We can't test for exact coordinates since the API might return slightly different values,
		// but we can verify they're numeric and not zero.
		$this->assertIsString( $venue_info['latitude'], 'Failed to assert latitude is a string.' );
		$this->assertIsString( $venue_info['longitude'], 'Failed to assert longitude is a string.' );

		// If geocoding succeeded, coordinates should be non-zero numeric strings.
		// If it failed, they should be '0'.
		if ( '0' !== $venue_info['latitude'] && '0' !== $venue_info['longitude'] ) {
			$this->assertIsNumeric( $venue_info['latitude'], 'Failed to assert latitude is numeric.' );
			$this->assertIsNumeric( $venue_info['longitude'], 'Failed to assert longitude is numeric.' );
		}
	}
	/**
	 * Coverage for execute_create_venue method without required name.
	 *
	 * @covers ::execute_create_venue
	 *
	 * @return void
	 */
	public function test_execute_create_venue_without_name(): void {
		$venue  = $this->get_venue_instance();
		$params = array(
			'address' => '456 Test St',
		);
		$result = $venue->execute_create_venue( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'name is required', $result['message'], 'Failed to assert error message.' );
	}
	/**
	 * Coverage for execute_create_venue method without required address.
	 *
	 * @covers ::execute_create_venue
	 *
	 * @return void
	 */
	public function test_execute_create_venue_without_address(): void {
		$venue  = $this->get_venue_instance();
		$params = array(
			'name' => 'Test Venue',
		);
		$result = $venue->execute_create_venue( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'address is required', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}
	/**
	 * Coverage for execute_update_venue method with valid parameters.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_valid_params(): void {
		// Create a venue first.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Original Name',
				'post_status' => 'publish',
			)
		);
		$this->set_venue_test_meta(
			$venue_id,
			array(
				'address' => 'Original Address',
				'phone'   => '555-1111',
			)
		);

		$venue  = $this->get_venue_instance();
		$params = array(
			'venue_id' => $venue_id,
			'name'     => 'Updated Name',
			'phone'    => '555-9999',
		);
		$result = $venue->execute_update_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertSame( $venue_id, $result['venue_id'], 'Failed to assert venue_id matches.' );

		// Verify title was updated.
		$venue_post = get_post( $venue_id );
		$this->assertSame( 'Updated Name', $venue_post->post_title, 'Failed to assert title updated.' );

		// Verify venue information was updated.
		$updated_info = $this->get_venue_test_meta( $venue_id );
		$this->assertSame( 'Original Address', $updated_info['address'], 'Failed to assert address unchanged.' );
		$this->assertSame( '555-9999', $updated_info['phone'], 'Failed to assert phone updated.' );
	}
	/**
	 * Coverage for execute_update_venue method without venue_id.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_without_venue_id(): void {
		$venue  = $this->get_venue_instance();
		$params = array(
			'name' => 'Updated Name',
		);
		$result = $venue->execute_update_venue( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Venue ID is required', $result['message'], 'Failed to assert error message.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}
	/**
	 * Coverage for execute_update_venue method with invalid venue_id.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_invalid_id(): void {
		$venue  = $this->get_venue_instance();
		$params = array(
			'venue_id' => 999999,
			'name'     => 'Updated Name',
		);
		$result = $venue->execute_update_venue( $params );

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Venue not found', $result['message'], 'Failed to assert error message.' );
	}
	/**
	 * Coverage for geocode_address method with valid address.
	 *
	 * @covers ::geocode_address
	 *
	 * @return void
	 */
	public function test_geocode_address_with_valid_address(): void {
		$venue = $this->get_venue_instance();

		// Mock wp_remote_get to return a successful response.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'nominatim.openstreetmap.org' ) !== false ) {
					return array(
						'body' => wp_json_encode(
							array(
								array(
									'lat' => '40.7128',
									'lon' => '-74.0060',
								),
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$venue,
			'geocode_address',
			array( '1600 Amphitheater Parkway, Mountain View, CA' )
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'latitude', $result );
		$this->assertArrayHasKey( 'longitude', $result );
	}
	/**
	 * Coverage for geocode_address method with error response.
	 *
	 * @covers ::geocode_address
	 *
	 * @return void
	 */
	public function test_geocode_address_with_error(): void {
		$venue = $this->get_venue_instance();

		// Mock wp_remote_get to return an error.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'nominatim.openstreetmap.org' ) !== false ) {
					return new \WP_Error( 'http_error', 'Connection failed' );
				}
				return $preempt;
			},
			10,
			3
		);

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$venue,
			'geocode_address',
			array( 'Invalid Address' )
		);

		$this->assertIsArray( $result );
		$this->assertSame( '0', $result['latitude'] );
		$this->assertSame( '0', $result['longitude'] );
	}
	/**
	 * Coverage for geocode_address method with empty response.
	 *
	 * @covers ::geocode_address
	 *
	 * @return void
	 */
	public function test_geocode_address_with_empty_response(): void {
		$venue = $this->get_venue_instance();

		// Mock wp_remote_get to return empty response.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'nominatim.openstreetmap.org' ) !== false ) {
					return array( 'body' => '[]' );
				}
				return $preempt;
			},
			10,
			3
		);

		$result = \PMC\Unit_Test\Utility::invoke_hidden_method(
			$venue,
			'geocode_address',
			array( 'Invalid Address' )
		);

		$this->assertIsArray( $result );
		$this->assertSame( '0', $result['latitude'] );
		$this->assertSame( '0', $result['longitude'] );
	}
	/**
	 * Coverage for execute_create_venue with geocoding.
	 *
	 * @covers ::execute_create_venue
	 * @covers ::geocode_address
	 *
	 * @return void
	 */
	public function test_execute_create_venue_with_geocoding(): void {
		$venue = $this->get_venue_instance();

		// Mock geocoding to return coordinates.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'nominatim.openstreetmap.org' ) !== false ) {
					return array(
						'body' => wp_json_encode(
							array(
								array(
									'lat' => '40.7128',
									'lon' => '-74.0060',
								),
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$params = array(
			'name'    => 'Geocoded Venue',
			'address' => '1600 Amphitheater Parkway, Mountain View, CA',
		);
		$result = $venue->execute_create_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify venue information includes coordinates.
		$venue_info = $this->get_venue_test_meta( $result['venue_id'] );
		$this->assertArrayHasKey( 'latitude', $venue_info );
		$this->assertArrayHasKey( 'longitude', $venue_info );
	}
	/**
	 * Coverage for execute_update_venue with address update and geocoding.
	 *
	 * @covers ::execute_update_venue
	 * @covers ::geocode_address
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_address_update(): void {
		// Create a venue first.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Original Venue',
				'post_status' => 'publish',
			)
		);

		// Mock geocoding.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'nominatim.openstreetmap.org' ) !== false ) {
					return array(
						'body' => wp_json_encode(
							array(
								array(
									'lat' => '40.7128',
									'lon' => '-74.0060',
								),
							)
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$venue  = $this->get_venue_instance();
		$params = array(
			'venue_id' => $venue_id,
			'address'  => '1600 Amphitheater Parkway, Mountain View, CA',
		);
		$result = $venue->execute_update_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify address was updated.
		$venue_info = $this->get_venue_test_meta( $venue_id );
		$this->assertSame( '1600 Amphitheater Parkway, Mountain View, CA', $venue_info['address'] );
	}
	/**
	 * Coverage for execute_update_venue with all fields.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_all_fields(): void {
		// Create a venue first.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Original Venue',
				'post_status' => 'publish',
			)
		);

		$venue  = $this->get_venue_instance();
		$params = array(
			'venue_id' => $venue_id,
			'name'     => 'Updated Venue Name',
			'address'  => 'New Address',
			'phone'    => '555-1234',
			'website'  => 'https://example.com',
		);
		$result = $venue->execute_update_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify all fields were updated.
		$venue_post = get_post( $venue_id );
		$this->assertSame( 'Updated Venue Name', $venue_post->post_title );

		$venue_info = $this->get_venue_test_meta( $venue_id );
		$this->assertSame( 'New Address', $venue_info['address'] );
		$this->assertSame( '555-1234', $venue_info['phone'] );
		$this->assertSame( 'https://example.com', $venue_info['website'] );
	}
	/**
	 * Coverage for execute_update_venue with empty venue_info.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_empty_venue_info(): void {
		// Create a venue without venue information.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Venue Without Info',
				'post_status' => 'publish',
			)
		);

		$venue  = $this->get_venue_instance();
		$params = array(
			'venue_id' => $venue_id,
			'phone'    => '555-9999',
		);
		$result = $venue->execute_update_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		$venue_info = $this->get_venue_test_meta( $venue_id );
		$this->assertSame( '555-9999', $venue_info['phone'] );
	}
	/**
	 * Coverage for execute_create_venue with website URL.
	 *
	 * @covers ::execute_create_venue
	 *
	 * @return void
	 */
	public function test_execute_create_venue_with_website(): void {
		$venue  = $this->get_venue_instance();
		$params = array(
			'name'    => 'Test Venue',
			'address' => '123 Test St',
			'website' => 'https://example.com',
		);
		$result = $venue->execute_create_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify website was saved.
		$venue_info = $this->get_venue_test_meta( $result['venue_id'] );
		$this->assertSame( 'https://example.com', $venue_info['website'] );
	}
	/**
	 * Coverage for execute_create_venue with phone number.
	 *
	 * @covers ::execute_create_venue
	 *
	 * @return void
	 */
	public function test_execute_create_venue_with_phone(): void {
		$venue  = $this->get_venue_instance();
		$params = array(
			'name'    => 'Test Venue',
			'address' => '123 Test St',
			'phone'   => '555-1234',
		);
		$result = $venue->execute_create_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify phone was saved.
		$venue_info = $this->get_venue_test_meta( $result['venue_id'] );
		$this->assertSame( '555-1234', $venue_info['phone'] );
	}
	/**
	 * Returns failure when wp_insert_post returns WP_Error.
	 *
	 * @covers ::execute_create_venue
	 *
	 * @return void
	 */
	public function test_execute_create_venue_with_wp_error(): void {
		add_filter(
			'wp_insert_post_data',
			function ( $data, $postarr ) {
				if ( isset( $postarr['post_type'] ) && Venue::POST_TYPE === $postarr['post_type'] ) {
					$data['post_status'] = str_repeat( 'x', 100 );
				}

				return $data;
			},
			10,
			2
		);

		$venue  = $this->get_venue_instance();
		$result = $venue->execute_create_venue(
			array(
				'name'    => 'Test Venue',
				'address' => '123 Test St',
			)
		);

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertNotEmpty( $result['message'], 'Failed to assert error message is present.' );
	}
	/**
	 * Coverage for execute_update_venue with empty venue_info.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_empty_venue_info_json(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Venue Without Info',
				'post_status' => 'publish',
			)
		);

		$venue  = $this->get_venue_instance();
		$params = array(
			'venue_id' => $venue_id,
			'phone'    => '555-9999',
		);
		$result = $venue->execute_update_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		$venue_info = $this->get_venue_test_meta( $venue_id );
		$this->assertSame( '555-9999', $venue_info['phone'] );
	}
	/**
	 * Coverage for execute_update_venue with empty venue_info JSON (line 1049).
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_invalid_json_venue_info(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Venue With Invalid JSON',
				'post_status' => 'publish',
			)
		);

		$venue  = $this->get_venue_instance();
		$params = array(
			'venue_id' => $venue_id,
			'phone'    => '555-9999',
		);
		$result = $venue->execute_update_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		$venue_info = $this->get_venue_test_meta( $venue_id );
		$this->assertSame( '555-9999', $venue_info['phone'] );
	}
	/**
	 * Coverage for execute_update_venue with thumbnail_id parameter.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_with_thumbnail_id(): void {
		// Create a venue first.
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Test Venue',
				'post_status' => 'publish',
			)
		);

		// Create an image attachment.
		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Test Image',
			)
		);

		// Get upload directory.
		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		// Get attachment file path.
		$attachment_file = get_attached_file( $attachment_id );
		if ( ! $attachment_file || ! file_exists( $attachment_file ) ) {
			// Create a minimal image file for the attachment.
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$temp_file = sys_get_temp_dir() . '/' . uniqid( 'gp_test_' ) . '.jpg';
			// phpcs:ignore Generic.Files.LineLength.TooLong -- Binary data cannot be split.
			$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xD2\xCF\x20\xFF\xD9";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
			file_put_contents( $temp_file, $jpeg_data );
			$file_path = $upload_dir['path'] . '/' . basename( $temp_file );
			if ( ! file_exists( $upload_dir['path'] ) ) {
				wp_mkdir_p( $upload_dir['path'] );
			}
			copy( $temp_file, $file_path );
			update_attached_file( $attachment_id, $file_path );
			// Clean up temp file.
			if ( file_exists( $temp_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test file cleanup.
				unlink( $temp_file );
			}
		}

		// Generate attachment metadata so wp_attachment_is_image() works.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_file = get_attached_file( $attachment_id );
		if ( $attach_file && file_exists( $attach_file ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $attach_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		$venue  = $this->get_venue_instance();
		$params = array(
			'venue_id'     => $venue_id,
			'thumbnail_id' => $attachment_id,
		);
		$result = $venue->execute_update_venue( $params );

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );

		// Verify thumbnail was set.
		$thumbnail_id = get_post_thumbnail_id( $venue_id );
		$this->assertSame( $attachment_id, $thumbnail_id, 'Failed to assert thumbnail was set.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Saves only the meta fields present in the fields array.
	 *
	 * @covers ::save_venue_editor_meta
	 *
	 * @return void
	 */
	public function test_save_venue_editor_meta_skips_absent_fields(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Meta Test Venue',
				'post_status' => 'publish',
			)
		);

		$this->set_venue_test_meta(
			$venue_id,
			array(
				'address' => 'Existing Address',
				'phone'   => '555-0000',
			)
		);

		$venue = $this->get_venue_instance();
		$venue->save_venue_editor_meta(
			$venue_id,
			array(
				'phone' => '555-1111',
			)
		);

		$venue_info = $this->get_venue_test_meta( $venue_id );
		$this->assertSame( 'Existing Address', $venue_info['address'], 'Failed to assert address was unchanged.' );
		$this->assertSame( '555-1111', $venue_info['phone'], 'Failed to assert phone was updated.' );
	}

	/**
	 * Continues when set_post_thumbnail fails for a valid attachment.
	 *
	 * @covers ::execute_update_venue
	 *
	 * @return void
	 */
	public function test_execute_update_venue_when_set_post_thumbnail_fails(): void {
		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Thumbnail Fail Venue',
				'post_status' => 'publish',
			)
		);

		$attachment_id = $this->factory->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Fail Thumbnail Image',
			)
		);

		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$temp_file = sys_get_temp_dir() . '/' . uniqid( 'gp_test_' ) . '.jpg';
		// phpcs:ignore Generic.Files.LineLength.TooLong -- Binary data cannot be split.
		$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xD2\xCF\x20\xFF\xD9";
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
		file_put_contents( $temp_file, $jpeg_data );
		$file_path = $upload_dir['path'] . '/' . basename( $temp_file );
		if ( ! file_exists( $upload_dir['path'] ) ) {
			wp_mkdir_p( $upload_dir['path'] );
		}
		copy( $temp_file, $file_path );
		update_attached_file( $attachment_id, $file_path );
		if ( file_exists( $temp_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test file cleanup.
			unlink( $temp_file );
		}

		$attach_file = get_attached_file( $attachment_id );
		if ( $attach_file && file_exists( $attach_file ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $attach_file );
			wp_update_attachment_metadata( $attachment_id, $attach_data );
		}

		add_filter(
			'update_post_metadata',
			static function ( $check, $object_id, $meta_key ) use ( $venue_id ) {
				if ( $venue_id === $object_id && '_thumbnail_id' === $meta_key ) {
					return false;
				}

				return $check;
			},
			10,
			3
		);

		$venue  = $this->get_venue_instance();
		$result = $venue->execute_update_venue(
			array(
				'venue_id'     => $venue_id,
				'thumbnail_id' => $attachment_id,
			)
		);

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertSame( 0, get_post_thumbnail_id( $venue_id ), 'Failed to assert thumbnail was not set.' );

		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for execute_list_venues method with no venues.
	 *
	 * @covers ::execute_list_venues
	 *
	 * @return void
	 */
	public function test_execute_list_venues_with_no_venues(): void {
		$venue  = $this->get_venue_instance();
		$result = $venue->execute_list_venues();

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertIsArray( $result['data'], 'Failed to assert data is an array.' );
		$this->assertEmpty( $result['data'], 'Failed to assert data is empty.' );
		$this->assertStringContainsString( 'Found 0 venue', $result['message'], 'Failed to assert message contains count.' ); // phpcs:ignore Generic.Files.LineLength.TooLong
	}

	/**
	 * Coverage for execute_list_venues method with venues.
	 *
	 * @covers ::execute_list_venues
	 *
	 * @return void
	 */
	public function test_execute_list_venues_with_venues(): void {
		$venue_id_1 = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Downtown Library',
				'post_status' => 'publish',
			)
		);
		$this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_title'  => 'Community Center',
				'post_status' => 'publish',
			)
		);

		$this->set_venue_test_meta(
			$venue_id_1,
			array(
				'address'   => '123 Main St',
				'phone'     => '555-1234',
				'website'   => 'https://example.com',
				'latitude'  => '40.7128',
				'longitude' => '-74.0060',
			)
		);

		$venue  = $this->get_venue_instance();
		$result = $venue->execute_list_venues();

		$this->assertTrue( $result['success'], 'Failed to assert success is true.' );
		$this->assertCount( 2, $result['data'], 'Failed to assert data has 2 venues.' );

		$library = null;
		foreach ( $result['data'] as $venue_data ) {
			if ( 'Downtown Library' === $venue_data['name'] ) {
				$library = $venue_data;
				break;
			}
		}

		$this->assertNotNull( $library, 'Failed to find Downtown Library in results.' );
		$this->assertSame( '123 Main St', $library['address'], 'Failed to assert venue address.' );
		$this->assertSame( '555-1234', $library['phone'], 'Failed to assert venue phone.' );
	}

	/**
	 * Coverage for execute_list_venues exception handling.
	 *
	 * @covers ::execute_list_venues
	 *
	 * @return void
	 */
	public function test_execute_list_venues_with_exception(): void {
		add_filter(
			'pre_get_posts',
			function ( $query ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( isset( $query->query_vars['post_type'] ) && 'gatherpress_venue' === $query->query_vars['post_type'] ) {
					throw new \Exception( 'Database error' );
				}
				return $query;
			}
		);

		$venue  = $this->get_venue_instance();
		$result = $venue->execute_list_venues();

		$this->assertFalse( $result['success'], 'Failed to assert success is false.' );
		$this->assertStringContainsString( 'Error retrieving venues', $result['message'] );
		$this->assertStringContainsString( 'Database error', $result['message'] );
	}
}
