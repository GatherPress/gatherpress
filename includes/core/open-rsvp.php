<?php
/**
 * Handle RSVP form submissions for GatherPress
 *
 * This file sets up filters to modify the standard comment processing
 * for RSVPs and then includes the WordPress comment processor.
 */

$gatherpress_document_root = $_SERVER['DOCUMENT_ROOT'];

if ( ! file_exists( $gatherpress_document_root . '/wp-load.php' ) ) {
	exit;
}

// add_filter( 'comment_form_fields', function( $comment_fields ) {
// unset($comment_fields['comment']);
// unset($comment_fields['url']);
// unset($comment_fields['cookies']);
// return $comment_fields;
// });

// Sets up the WordPress Environment.
require_once $gatherpress_document_root . '/wp-load.php';

add_filter( 'allow_empty_comment', '__return_true' );

add_filter(
	'preprocess_comment',
	static function ( array $comment_data ): array {
		$comment_data['comment_content']  = '';
		$comment_data['comment_type']     = 'gatherpress_rsvp';
		$comment_data['comment_approved'] = 1;

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
require_once ABSPATH . 'wp-comments-post.php';
