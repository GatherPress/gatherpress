<?php
/**
 * Manages RSVP token functionality for event comments.
 *
 * This class handles the creation, validation, and management of secure tokens
 * associated with RSVP comments. Tokens are used for email verification and
 * allowing anonymous users to modify their RSVP status via email links.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use WP_Comment;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Handles RSVP token operations for event comments.
 *
 * The Rsvp_Token class provides secure token generation and validation for RSVP comments.
 * It enables email-based RSVP management for both logged-in and anonymous users.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */
class Rsvp_Token {
	/**
	 * The parameter name used for RSVP tokens in URLs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const NAME = 'gatherpress_rsvp_token';

	/**
	 * The comment object associated with this token.
	 *
	 * @since 1.0.0
	 * @var WP_Comment|null
	 */
	private ?WP_Comment $comment = null;

	/**
	 * The cached token string.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $token = '';

	/**
	 * Class constructor.
	 *
	 * Initializes the token object with a comment ID and validates that it's an RSVP comment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id The ID of the RSVP comment.
	 */
	public function __construct( int $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment || Rsvp::COMMENT_TYPE !== get_comment_type( $comment_id ) ) {
			return;
		}

		$this->comment = $comment;
	}

	/**
	 * Retrieves the token string for this RSVP comment.
	 *
	 * Returns the cached token if available, otherwise retrieves it from comment meta.
	 *
	 * @since 1.0.0
	 *
	 * @return string The token string, or empty string if no token exists.
	 */
	public function get_token(): string {
		if ( $this->token ) {
			return $this->token;
		}

		if ( ! $this->comment ) {
			return '';
		}

		$token = (string) get_comment_meta(
			(int) $this->comment->comment_ID,
			sprintf( '_%s', static::NAME ),
			true
		);

		$this->token = $token;

		return $this->token;
	}

	/**
	 * Approves the RSVP comment.
	 *
	 * Changes the comment status from pending to approved, typically used
	 * when a user clicks a verification link in their email.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function approve_comment(): void {
		if ( ! $this->comment ) {
			return;
		}

		wp_set_comment_status( (int) $this->comment->comment_ID, 'approve' );
	}

	/**
	 * Generates a new secure token for this RSVP comment.
	 *
	 * Creates a 32-character random token and saves it to comment meta.
	 *
	 * @since 1.0.0
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function generate_token(): self {
		if ( ! $this->comment ) {
			return $this;
		}

		$this->token = wp_generate_password( 32, false );

		update_comment_meta(
			(int) $this->comment->comment_ID,
			sprintf( '_%s', static::NAME ),
			$this->token
		);

		return $this;
	}

	/**
	 * Retrieves the comment object associated with this token.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Comment|null The comment object, or null if not set.
	 */
	public function get_comment(): ?WP_Comment {
		return $this->comment;
	}

	/**
	 * Retrieves the email address from the RSVP comment.
	 *
	 * @since 1.0.0
	 *
	 * @return string The comment author's email address, or empty string if not available.
	 */
	public function get_email(): string {
		if ( ! $this->comment ) {
			return '';
		}

		return $this->comment->comment_author_email;
	}

	/**
	 * Validates a token against the stored token for this comment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token The token to validate.
	 *
	 * @return bool True if the token is valid, false otherwise.
	 */
	public function is_valid( string $token ): bool {
		return ! empty( $token ) && $this->get_token() === $token;
	}

	/**
	 * Send RSVP confirmation email with magic link.
	 *
	 * Sends an email containing a magic link that allows the user to confirm
	 * their RSVP and change their response if needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function send_rsvp_confirmation_email(): void {
		// @todo add email logic.
	}
}
