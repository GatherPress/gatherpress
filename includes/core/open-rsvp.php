<?php
/**
 * Handle RSVP form submissions for GatherPress
 *
 * This file sets up filters to modify the standard comment processing
 * for RSVPs and then includes the WordPress comment processor.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

$gatherpress_document_root = '';

if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
	// We can't use wp_unslash() yet because WordPress isn't loaded.
	$gatherpress_document_root = preg_replace(
		'/[^A-Za-z0-9\/\\\._-]/',
		'',
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$_SERVER['DOCUMENT_ROOT']
	);

	// Prevent directory traversal.
	$gatherpress_document_root = str_replace(
		array( "\0", '..' ),
		'',
		$gatherpress_document_root
	);
}

if ( ! file_exists( $gatherpress_document_root . '/wp-load.php' ) ) {
	exit;
}

// Sets up the WordPress Environment.
require_once $gatherpress_document_root . '/wp-load.php';

add_filter( 'allow_empty_comment', '__return_true' );

add_filter(
	'preprocess_comment',
	static function ( array $comment_data ): array {
		$comment_data['comment_content']  = '';
		$comment_data['comment_type']     = 'gatherpress_rsvp';
		$comment_data['comment_approved'] = 0;

		return $comment_data;
	}
);

add_action(
	'comment_post',
	static function ( int $comment_id ): void {
		wp_set_object_terms( $comment_id, 'attending', GatherPress\Core\Rsvp::TAXONOMY );
	}
);

add_filter(
	'comment_duplicate_message',
	function (): string {
		return __( "You've already RSVP'd to this event.", 'gatherpress' );
	}
);

// Include the WordPress comment processing file.
// @phpstan-ignore-next-line
require_once ABSPATH . 'wp-comments-post.php';
