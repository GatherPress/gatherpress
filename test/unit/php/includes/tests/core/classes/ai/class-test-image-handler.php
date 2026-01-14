<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\Image_Handler.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\Image_Handler;
use GatherPress\Tests\Base;
use WP_Error;

/**
 * Class Test_Image_Handler.
 *
 * @coversDefaultClass \GatherPress\Core\AI\Image_Handler
 */
class Test_Image_Handler extends Base {
	/**
	 * Coverage for ALLOWED_MIME_TYPES constant.
	 *
	 * @coversDefaultClass \GatherPress\Core\AI\Image_Handler
	 *
	 * @return void
	 */
	public function test_allowed_mime_types_constant(): void {
		$this->assertIsArray(
			Image_Handler::ALLOWED_MIME_TYPES,
			'Failed to assert ALLOWED_MIME_TYPES is an array.'
		);
		$this->assertContains(
			'image/jpeg',
			Image_Handler::ALLOWED_MIME_TYPES,
			'Failed to assert JPEG is allowed.'
		);
		$this->assertContains(
			'image/png',
			Image_Handler::ALLOWED_MIME_TYPES,
			'Failed to assert PNG is allowed.'
		);
		$this->assertContains(
			'image/gif',
			Image_Handler::ALLOWED_MIME_TYPES,
			'Failed to assert GIF is allowed.'
		);
		$this->assertContains(
			'image/webp',
			Image_Handler::ALLOWED_MIME_TYPES,
			'Failed to assert WebP is allowed.'
		);
	}

	/**
	 * Coverage for MAX_FILE_SIZE constant.
	 *
	 * @coversDefaultClass \GatherPress\Core\AI\Image_Handler
	 *
	 * @return void
	 */
	public function test_max_file_size_constant(): void {
		$this->assertSame(
			10485760,
			Image_Handler::MAX_FILE_SIZE,
			'Failed to assert MAX_FILE_SIZE is 10MB (10485760 bytes).'
		);
	}

	/**
	 * Coverage for validate_image_file with valid file.
	 *
	 * @covers ::validate_image_file
	 *
	 * @return void
	 */
	public function test_validate_image_file_valid(): void {
		$handler = new Image_Handler();

		// Create a temporary test image file.
		$temp_file = $this->create_temp_image_file( 'test.jpg', 'image/jpeg' );

		$file = array(
			'name'     => 'test.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $temp_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $temp_file ),
		);

		$result = $handler->validate_image_file( $file );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'valid', $result );
		$this->assertTrue( $result['valid'], 'Failed to assert valid file passes validation.' );

		// Clean up.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
		@unlink( $temp_file );
	}

	/**
	 * Coverage for validate_image_file with missing file.
	 *
	 * @covers ::validate_image_file
	 *
	 * @return void
	 */
	public function test_validate_image_file_missing(): void {
		$handler = new Image_Handler();

		$file = array();

		$result = $handler->validate_image_file( $file );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'valid', $result );
		$this->assertFalse( $result['valid'], 'Failed to assert missing file fails validation.' );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Coverage for validate_image_file with invalid MIME type.
	 *
	 * @covers ::validate_image_file
	 *
	 * @return void
	 */
	public function test_validate_image_file_invalid_mime(): void {
		$handler = new Image_Handler();

		// Create a temporary file (doesn't matter what type since MIME check happens first).
		$temp_file = sys_get_temp_dir() . '/' . uniqid( 'gp_test_' ) . '.pdf';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
		file_put_contents( $temp_file, 'fake pdf content' );

		$file = array(
			'name'     => 'test.pdf',
			'type'     => 'application/pdf',
			'tmp_name' => $temp_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $temp_file ),
		);

		$result = $handler->validate_image_file( $file );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['valid'], 'Failed to assert invalid MIME type fails validation.' );
		$this->assertArrayHasKey( 'error', $result );
		// Verify the error message mentions invalid file type.
		$this->assertStringContainsString(
			'invalid file type',
			strtolower( $result['error'] ),
			'Failed to assert error message mentions invalid file type.'
		);

		// Clean up.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
		@unlink( $temp_file );
	}

	/**
	 * Coverage for validate_image_file with file too large.
	 *
	 * @covers ::validate_image_file
	 *
	 * @return void
	 */
	public function test_validate_image_file_too_large(): void {
		$handler = new Image_Handler();

		// Create a temp file first, then report it as oversized.
		// The validation checks size BEFORE getimagesize(), so this should work.
		$temp_file = $this->create_temp_image_file( 'test_large.jpg', 'image/jpeg' );
		$file_size = filesize( $temp_file );

		$file = array(
			'name'     => 'test.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $temp_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => Image_Handler::MAX_FILE_SIZE + 1, // Exceeds limit (fake size).
		);

		$result = $handler->validate_image_file( $file );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['valid'], 'Failed to assert oversized file fails validation.' );
		$this->assertArrayHasKey( 'error', $result );
		// Verify the error message mentions file size.
		$this->assertStringContainsString(
			'file size',
			strtolower( $result['error'] ),
			'Failed to assert error message mentions file size.'
		);

		// Clean up.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
		@unlink( $temp_file );
	}

	/**
	 * Coverage for validate_image_file with upload error.
	 *
	 * @covers ::validate_image_file
	 *
	 * @return void
	 */
	public function test_validate_image_file_upload_error(): void {
		$handler = new Image_Handler();

		$file = array(
			'name'     => 'test.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '',
			'error'    => UPLOAD_ERR_INI_SIZE,
			'size'     => 1000,
		);

		$result = $handler->validate_image_file( $file );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['valid'], 'Failed to assert upload error fails validation.' );
		$this->assertArrayHasKey( 'error', $result );
	}

	/**
	 * Coverage for upload_to_media_library with invalid file.
	 *
	 * @covers ::upload_to_media_library
	 * @covers ::validate_image_file
	 *
	 * @return void
	 */
	public function test_upload_to_media_library_invalid_file(): void {
		$handler = new Image_Handler();

		$file = array(
			'name'     => 'test.pdf',
			'type'     => 'application/pdf',
			'tmp_name' => '/tmp/test.pdf',
			'error'    => UPLOAD_ERR_OK,
			'size'     => 1000,
		);

		$result = $handler->upload_to_media_library( $file );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert invalid file returns WP_Error.'
		);
	}

	/**
	 * Coverage for get_image_attachment_data with invalid attachment ID.
	 *
	 * @covers ::get_image_attachment_data
	 *
	 * @return void
	 */
	public function test_get_image_attachment_data_invalid_id(): void {
		$handler = new Image_Handler();

		$result = $handler->get_image_attachment_data( 999999 );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert invalid attachment ID returns WP_Error.'
		);
	}

	/**
	 * Coverage for get_image_attachment_data with non-image attachment.
	 *
	 * @covers ::get_image_attachment_data
	 *
	 * @return void
	 */
	public function test_get_image_attachment_data_not_image(): void {
		$handler = new Image_Handler();

		// Create a non-image attachment post (PDF).
		// wp_attachment_is_image() will return false for non-image MIME types.
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'test.pdf',
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			false // No file path needed for this test.
		);

		$result = $handler->get_image_attachment_data( $attachment_id );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert non-image attachment returns WP_Error.'
		);
		$this->assertSame(
			'not_an_image',
			$result->get_error_code(),
			'Failed to assert error code is not_an_image.'
		);

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for get_image_attachment_data with valid image attachment.
	 *
	 * @covers ::get_image_attachment_data
	 *
	 * @return void
	 */
	public function test_get_image_attachment_data_valid(): void {
		$handler = new Image_Handler();

		// Get upload directory.
		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			$this->markTestSkipped( 'Upload directory is not writable.' );
		}

		// Create a temporary image file.
		$temp_file = $this->create_temp_image_file( 'test_attachment_' . time() . '.jpg', 'image/jpeg' );
		$filename  = basename( $temp_file );
		$file_path = $upload_dir['path'] . '/' . $filename;

		// Ensure upload directory exists.
		if ( ! file_exists( $upload_dir['path'] ) ) {
			wp_mkdir_p( $upload_dir['path'] );
		}

		// Copy temp file to upload directory.
		if ( ! copy( $temp_file, $file_path ) ) {
			$this->markTestSkipped( 'Could not copy test file to upload directory.' );
		}

		// Create attachment post.
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$file_path
		);

		if ( is_wp_error( $attachment_id ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
			@unlink( $temp_file );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
			@unlink( $file_path );
			$this->markTestSkipped( 'Could not create test attachment: ' . $attachment_id->get_error_message() );
		}

		// Generate attachment metadata so wp_attachment_is_image() works.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		$result = $handler->get_image_attachment_data( $attachment_id );

		$this->assertIsArray( $result, 'Failed to assert valid attachment returns array.' );
		$this->assertArrayHasKey( 'attachment_id', $result );
		$this->assertArrayHasKey( 'url', $result );
		$this->assertArrayHasKey( 'mime_type', $result );
		$this->assertArrayHasKey( 'file_path', $result );
		$this->assertSame( $attachment_id, $result['attachment_id'] );
		$this->assertSame( 'image/jpeg', $result['mime_type'] );
		$this->assertNotEmpty( $result['url'], 'Failed to assert URL is not empty.' );
		$this->assertNotEmpty( $result['file_path'], 'Failed to assert file_path is not empty.' );

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
		@unlink( $temp_file );
		if ( file_exists( $file_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
			@unlink( $file_path );
		}
	}

	/**
	 * Create a temporary image file for testing.
	 *
	 * @param string $filename Filename for the temp file.
	 * @param string $mime_type MIME type of the image.
	 * @return string Path to temporary file.
	 */
	private function create_temp_image_file( string $filename, string $mime_type ): string {
		// Use unique filename to avoid conflicts in parallel test runs.
		$unique_filename = uniqid( 'gp_test_' ) . '_' . $filename;
		$temp_file       = sys_get_temp_dir() . '/' . $unique_filename;

		// Check if GD extension is available.
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			// Fallback: create a minimal valid JPEG file manually.
			// This is a 1x1 pixel JPEG in binary format.
			if ( 'image/jpeg' === $mime_type || 'image/jpg' === $mime_type ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong -- Binary data cannot be split.
				$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xD2\xCF\x20\xFF\xD9";
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
				file_put_contents( $temp_file, $jpeg_data );
				return $temp_file;
			}
			// For other types without GD, create a simple text file (validation will catch it).
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
			file_put_contents( $temp_file, 'fake image data' );
			return $temp_file;
		}

		// Create a minimal valid image file based on type using GD.
		if ( 'image/jpeg' === $mime_type || 'image/jpg' === $mime_type ) {
			// Minimal valid JPEG (1x1 pixel).
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test file creation, errors handled.
			$image = @imagecreatetruecolor( 1, 1 );
			if ( false === $image ) {
				// Fallback if image creation fails.
				// phpcs:ignore Generic.Files.LineLength.TooLong -- Binary data cannot be split.
				$jpeg_data = "\xFF\xD8\xFF\xE0\x00\x10\x4A\x46\x49\x46\x00\x01\x01\x01\x00\x48\x00\x48\x00\x00\xFF\xDB\x00\x43\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C\x20\x24\x2E\x27\x20\x22\x2C\x23\x1C\x1C\x28\x37\x29\x2C\x30\x31\x34\x34\x34\x1F\x27\x39\x3D\x38\x32\x3C\x2E\x33\x34\x32\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00\xFF\xC4\x00\x14\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\xFF\xC4\x00\x14\x10\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00\xD2\xCF\x20\xFF\xD9";
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
				file_put_contents( $temp_file, $jpeg_data );
			} else {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test file creation, errors handled.
				@imagejpeg( $image, $temp_file, 100 );
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test file creation, errors handled.
				@imagedestroy( $image );
			}
		} elseif ( 'image/png' === $mime_type ) {
			// Minimal valid PNG (1x1 pixel).
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test file creation, errors handled.
			$image = @imagecreatetruecolor( 1, 1 );
			if ( false !== $image ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test file creation, errors handled.
				@imagepng( $image, $temp_file );
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test file creation, errors handled.
				@imagedestroy( $image );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
				file_put_contents( $temp_file, 'fake png data' );
			}
		} elseif ( 'image/gif' === $mime_type ) {
			// Minimal valid GIF (1x1 pixel).
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test file creation, errors handled.
			$image = @imagecreatetruecolor( 1, 1 );
			if ( false !== $image ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test file creation, errors handled.
				@imagegif( $image, $temp_file );
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Test file creation, errors handled.
				@imagedestroy( $image );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
				file_put_contents( $temp_file, 'fake gif data' );
			}
		} else {
			// For WebP or unknown, create a simple text file (test will fail validation).
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
			file_put_contents( $temp_file, 'fake image data' );
		}

		return $temp_file;
	}

	/**
	 * Coverage for attachment_to_file_message_part with valid attachment.
	 *
	 * @covers ::attachment_to_file_message_part
	 *
	 * @return void
	 */
	public function test_attachment_to_file_message_part_valid(): void {
		// Skip if wp-ai-client is not available.
		if ( ! class_exists( 'WordPress\AiClient\Files\DTO\File' ) ) {
			$this->markTestSkipped( 'wp-ai-client is not available in test environment.' );
		}

		$handler = new Image_Handler();

		// Create a test attachment.
		$attachment_id = $this->create_and_upload_attachment( 'test-image.jpg', 'image/jpeg' );
		$this->assertIsInt( $attachment_id, 'Failed to create test attachment.' );

		// Convert to File MessagePart.
		$result = $handler->attachment_to_file_message_part( $attachment_id );

		// Verify result is MessagePart.
		$this->assertInstanceOf(
			'WordPress\AiClient\Messages\DTO\MessagePart',
			$result,
			'Failed to assert result is MessagePart.'
		);

		// Verify MessagePart has file property.
		$reflection    = new \ReflectionClass( $result );
		$file_property = $reflection->getProperty( 'file' );
		$file_property->setAccessible( true );
		$file = $file_property->getValue( $result );

		$this->assertInstanceOf(
			'WordPress\AiClient\Files\DTO\File',
			$file,
			'Failed to assert MessagePart has File DTO.'
		);

		// Clean up.
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Coverage for attachment_to_file_message_part with invalid attachment ID.
	 *
	 * @covers ::attachment_to_file_message_part
	 *
	 * @return void
	 */
	public function test_attachment_to_file_message_part_invalid_attachment(): void {
		// Skip if wp-ai-client is not available.
		if ( ! class_exists( 'WordPress\AiClient\Files\DTO\File' ) ) {
			$this->markTestSkipped( 'wp-ai-client is not available in test environment.' );
		}

		$handler = new Image_Handler();

		// Try with non-existent attachment ID.
		$result = $handler->attachment_to_file_message_part( 99999 );

		// Verify result is WP_Error.
		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert result is WP_Error for invalid attachment.'
		);

		// Verify error code.
		$this->assertSame(
			'invalid_attachment',
			$result->get_error_code(),
			'Failed to assert error code is invalid_attachment.'
		);
	}


	/**
	 * Create and upload an attachment to the media library for testing.
	 *
	 * @param string $filename Filename for the attachment.
	 * @param string $mime_type MIME type of the image.
	 * @return int|false Attachment ID on success, false on failure.
	 */
	private function create_and_upload_attachment( string $filename, string $mime_type ) {
		// Get upload directory.
		$upload_dir = wp_upload_dir();
		if ( isset( $upload_dir['error'] ) && $upload_dir['error'] ) {
			return false;
		}

		// Create a temporary image file.
		$temp_file = $this->create_temp_image_file( $filename, $mime_type );
		$file_path = $upload_dir['path'] . '/' . basename( $temp_file );

		// Ensure upload directory exists.
		if ( ! file_exists( $upload_dir['path'] ) ) {
			wp_mkdir_p( $upload_dir['path'] );
		}

		// Copy temp file to upload directory.
		if ( ! copy( $temp_file, $file_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
			@unlink( $temp_file );
			return false;
		}

		// Create attachment post.
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $mime_type,
				'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$file_path
		);

		if ( is_wp_error( $attachment_id ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
			@unlink( $temp_file );
			if ( file_exists( $file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
				@unlink( $file_path );
			}
			return false;
		}

		// Generate attachment metadata so wp_attachment_is_image() works.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Clean up temp file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.PHP.NoSilencedErrors.Discouraged -- Test file cleanup.
		@unlink( $temp_file );

		return $attachment_id;
	}
}
