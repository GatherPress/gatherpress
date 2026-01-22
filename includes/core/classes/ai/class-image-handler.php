<?php
/**
 * Handles image uploads and validation for AI conversations.
 *
 * @package GatherPress\Core\AI
 * @since 1.0.0
 */

namespace GatherPress\Core\AI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_Error;
use WordPress\AiClient\Files\DTO\File as AiClientFile;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessagePartChannelEnum;

/**
 * Class Image_Handler.
 *
 * Handles image file validation, upload to WordPress media library,
 * and retrieval of attachment data for AI message processing.
 *
 * @since 1.0.0
 */
class Image_Handler {
	/**
	 * Allowed image MIME types.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	const ALLOWED_MIME_TYPES = array(
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/gif',
		'image/webp',
	);

	/**
	 * Maximum file size in bytes (10MB).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_FILE_SIZE = 10485760; // 10MB

	/**
	 * Validate an uploaded image file.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $file File data from $_FILES array.
	 * @return array{valid: bool, error?: string} Validation result.
	 */
	public function validate_image_file( array $file ): array {
		// Check if file was uploaded.
		if ( ! isset( $file['tmp_name'] ) || empty( $file['tmp_name'] ) ) {
			return array(
				'valid' => false,
				'error' => __( 'No file was uploaded.', 'gatherpress' ),
			);
		}

		// Check for upload errors.
		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
			$error_message = $this->get_upload_error_message( $file['error'] );
			return array(
				'valid' => false,
				'error' => $error_message,
			);
		}

		// Check file size.
		if ( isset( $file['size'] ) && $file['size'] > self::MAX_FILE_SIZE ) {
			$max_size_mb = self::MAX_FILE_SIZE / 1048576;
			return array(
				'valid' => false,
				'error' => sprintf(
					/* translators: %s: Maximum file size in MB */
					__( 'File size exceeds maximum allowed size of %s MB.', 'gatherpress' ),
					$max_size_mb
				),
			);
		}

		// Check MIME type.
		if ( ! isset( $file['type'] ) || ! in_array( $file['type'], self::ALLOWED_MIME_TYPES, true ) ) {
			return array(
				'valid' => false,
				'error' => __( 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.', 'gatherpress' ),
			);
		}

		// Verify file is actually an image using getimagesize.
		if ( ! function_exists( 'getimagesize' ) ) {
			return array(
				'valid' => false,
				'error' => __( 'Image validation is not available on this server.', 'gatherpress' ),
			);
		}

		// Check file exists and is readable before attempting to get image size.
		if ( ! file_exists( $file['tmp_name'] ) || ! is_readable( $file['tmp_name'] ) ) {
			return array(
				'valid' => false,
				'error' => __( 'File is not accessible.', 'gatherpress' ),
			);
		}

		// Suppress warnings for non-image files (getimagesize generates warnings for invalid files).
		// Use temporary error handler to avoid @ operator.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Required to suppress getimagesize() warnings.
		set_error_handler(
			function () {
				return true; // Suppress warnings.
			},
			E_WARNING
		);
		/**
		 * Result of getimagesize() - array with image info or false on failure.
		 *
		 * @var array{0: int, 1: int, 2: int, 3: string, mime: string, channels?: int, bits?: int}|false $image_info
		 */
		$image_info = getimagesize( $file['tmp_name'] );
		restore_error_handler();

		if ( false === $image_info ) {
			return array(
				'valid' => false,
				'error' => __( 'File is not a valid image.', 'gatherpress' ),
			);
		}

		// Verify MIME type matches actual image type.
		// After false check, we know $image_info is an array with 'mime' key.
		$detected_mime = (string) $image_info['mime'];
		if ( ! in_array( $detected_mime, self::ALLOWED_MIME_TYPES, true ) ) {
			return array(
				'valid' => false,
				'error' => __( 'File MIME type does not match image type.', 'gatherpress' ),
			);
		}

		return array( 'valid' => true );
	}

	/**
	 * Upload image file to WordPress media library.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $file File data from $_FILES array.
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public function upload_to_media_library( array $file ) {
		// Validate file first.
		$validation = $this->validate_image_file( $file );
		if ( ! $validation['valid'] ) {
			return new WP_Error(
				'invalid_file',
				$validation['error'] ?? __( 'File validation failed.', 'gatherpress' )
			);
		}

		// Check user capabilities.
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'insufficient_permissions',
				__( 'You do not have permission to upload files.', 'gatherpress' )
			);
		}

		// Use WordPress media_handle_upload function.
		// WordPress core functions are usually already loaded in admin context.
		// If not available, we cannot proceed with upload.
		if ( ! function_exists( 'media_handle_upload' ) ) {
			return new WP_Error(
				'media_functions_unavailable',
				__( 'WordPress media functions are not available.', 'gatherpress' )
			);
		}

		// Prepare file for WordPress upload handler.
		$uploaded_file = array(
			'name'     => $file['name'] ?? '',
			'type'     => $file['type'] ?? '',
			'tmp_name' => $file['tmp_name'] ?? '',
			'error'    => $file['error'] ?? UPLOAD_ERR_OK,
			'size'     => $file['size'] ?? 0,
		);

		// Build MIME types array for WordPress (extension => mime type mapping).
		// WordPress handles case-insensitive matching internally.
		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		);

		// Use wp_handle_upload to move file to uploads directory.
		$upload_result = wp_handle_upload(
			$uploaded_file,
			array(
				'test_form' => false, // Skip form validation.
				'mimes'     => $allowed_mimes,
			)
		);

		if ( isset( $upload_result['error'] ) ) {
			return new WP_Error(
				'upload_failed',
				$upload_result['error']
			);
		}

		// Create attachment post.
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload_result['type'],
				'post_title'     => sanitize_file_name( pathinfo( $upload_result['file'], PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload_result['file']
		);

		/**
		 * Result of wp_insert_attachment - can be int (attachment ID) or WP_Error.
		 *
		 * @var int|WP_Error $attachment_id
		 */
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate attachment metadata.
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload_result['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_data );

		return $attachment_id;
	}

	/**
	 * Get image attachment data for AI processing.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array<string, mixed>|WP_Error Attachment data or error.
	 */
	public function get_image_attachment_data( int $attachment_id ) {
		// Verify attachment exists and is an image.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return new WP_Error(
				'invalid_attachment',
				__( 'Invalid attachment ID.', 'gatherpress' )
			);
		}

		// Check if it's an image.
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error(
				'not_an_image',
				__( 'Attachment is not an image.', 'gatherpress' )
			);
		}

		// Get attachment URL and metadata.
		$attachment_url = wp_get_attachment_url( $attachment_id );
		$mime_type      = get_post_mime_type( $attachment_id );
		$file_path      = get_attached_file( $attachment_id );

		if ( ! $attachment_url || ! $mime_type ) {
			return new WP_Error(
				'missing_data',
				__( 'Could not retrieve attachment data.', 'gatherpress' )
			);
		}

		return array(
			'attachment_id' => $attachment_id,
			'url'           => $attachment_url,
			'mime_type'     => $mime_type,
			'file_path'     => $file_path,
		);
	}

	/**
	 * Convert WordPress attachment ID to wp-ai-client File MessagePart.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment_id WordPress attachment ID.
	 * @return MessagePart|WP_Error File MessagePart or error.
	 */
	public function attachment_to_file_message_part( int $attachment_id ) {
		// Check if wp-ai-client classes are available.
		if ( ! class_exists( 'WordPress\AiClient\Files\DTO\File' ) ) {
			return new WP_Error(
				'wp_ai_client_not_available',
				__( 'wp-ai-client is not available.', 'gatherpress' )
			);
		}

		// Get attachment data.
		$attachment_data = $this->get_image_attachment_data( $attachment_id );
		if ( is_wp_error( $attachment_data ) ) {
			return $attachment_data;
		}

		// Create File DTO with attachment URL and MIME type.
		// File DTO accepts URL, base64 data, or local file path.
		try {
			$file = new AiClientFile(
				$attachment_data['url'],
				$attachment_data['mime_type']
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'file_creation_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to create File DTO: %s', 'gatherpress' ),
					$e->getMessage()
				)
			);
		}

		// Create MessagePart with File directly (MessagePart constructor accepts File instances).
		try {
			$message_part = new MessagePart( $file );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'message_part_creation_failed',
				sprintf(
					/* translators: %s: Error message */
					__( 'Failed to create MessagePart: %s', 'gatherpress' ),
					$e->getMessage()
				)
			);
		}

		return $message_part;
	}

	/**
	 * Filter WordPress allowed MIME types to ensure our image types are included.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $mimes Existing MIME types.
	 * @return array<string, string> Filtered MIME types.
	 */
	public function filter_allowed_mime_types( array $mimes ): array {
		// Ensure our allowed image types are always included.
		$image_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		);

		return array_merge( $mimes, $image_mimes );
	}

	/**
	 * Get human-readable upload error message.
	 *
	 * @since 1.0.0
	 *
	 * @param int $error_code PHP upload error code.
	 * @return string Error message.
	 */
	private function get_upload_error_message( int $error_code ): string {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
				return __( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'gatherpress' );
			case UPLOAD_ERR_FORM_SIZE:
				return __(
					'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
					'gatherpress'
				);
			case UPLOAD_ERR_PARTIAL:
				return __( 'The uploaded file was only partially uploaded.', 'gatherpress' );
			case UPLOAD_ERR_NO_FILE:
				return __( 'No file was uploaded.', 'gatherpress' );
			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'Missing a temporary folder.', 'gatherpress' );
			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Failed to write file to disk.', 'gatherpress' );
			case UPLOAD_ERR_EXTENSION:
				return __( 'A PHP extension stopped the file upload.', 'gatherpress' );
			default:
				return __( 'Unknown upload error occurred.', 'gatherpress' );
		}
	}
}
