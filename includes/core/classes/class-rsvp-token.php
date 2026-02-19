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
use WP_Post;

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
	 * The length of the generated token.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const TOKEN_LENGTH = 32;

	/**
	 * The meta key prefix for storing tokens.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_PREFIX = '_';

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
		if ( $comment_id <= 0 ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( ! $this->is_valid_rsvp_comment( $comment, $comment_id ) ) {
			return;
		}

		$this->comment = $comment;
	}

	/**
	 * Validates if a comment is a valid RSVP comment.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Comment|null $comment The comment object to validate.
	 * @param int             $comment_id The comment ID for fallback validation.
	 *
	 * @return bool True if valid RSVP comment, false otherwise.
	 */
	private function is_valid_rsvp_comment( ?WP_Comment $comment, int $comment_id ): bool {
		return $comment instanceof WP_Comment && Rsvp::COMMENT_TYPE === get_comment_type( $comment_id );
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

		// Reject token if the comment is more than 24 hours old.
		$diff = strtotime( 'now' ) - strtotime( $this->comment->comment_date );
		if ( $diff >= HOUR_IN_SECONDS * 24 ) {
			return '';
		}

		$token = (string) get_comment_meta(
			(int) $this->comment->comment_ID,
			$this->get_meta_key(),
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
	 * Gets the meta key for storing the token.
	 *
	 * @since 1.0.0
	 *
	 * @return string The formatted meta key.
	 */
	private function get_meta_key(): string {
		return sprintf( '%s%s', self::META_KEY_PREFIX, static::NAME );
	}

	/**
	 * Generates a new secure token for this RSVP comment.
	 *
	 * Creates a secure random token and saves it to comment meta.
	 *
	 * @since 1.0.0
	 *
	 * @return self Returns the current instance for method chaining.
	 */
	public function generate_token(): self {
		if ( ! $this->comment ) {
			return $this;
		}

		$this->token = $this->create_secure_token();
		$this->save_token_to_meta();

		return $this;
	}

	/**
	 * Creates a secure random token.
	 *
	 * @since 1.0.0
	 *
	 * @return string The generated token.
	 */
	private function create_secure_token(): string {
		return wp_generate_password( self::TOKEN_LENGTH, false );
	}

	/**
	 * Saves the current token to comment meta.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function save_token_to_meta(): void {
		update_comment_meta(
			(int) $this->comment->comment_ID,
			$this->get_meta_key(),
			$this->token
		);
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
	 * Retrieves the event post object associated with this token.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Post|null The event post object, or null if not set.
	 */
	public function get_post(): ?WP_Post {
		$comment = $this->get_comment();

		if ( ! $comment ) {
			return null;
		}

		$post = get_post( (int) $comment->comment_post_ID );

		if ( ! $post || Event::POST_TYPE !== get_post_type( $post ) ) {
			return null;
		}

		return $post;
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
	 * Creates an Rsvp_Token instance from a token string.
	 *
	 * Parses the token string and validates it against the stored token.
	 * Returns null if the token string is invalid or doesn't match.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $token_string Token in format "commentId_token".
	 * @return self|null Instance if valid, null otherwise.
	 */
	public static function from_token_string( ?string $token_string ): ?self {
		$parts = self::parse_token_string( $token_string );

		if ( empty( $parts ) ) {
			return null;
		}

		$instance = new self( $parts['comment_id'] );

		// Validate the token matches.
		if ( ! $instance->is_valid( $parts['token'] ) ) {
			return null;
		}

		return $instance;
	}

	/**
	 * Parse token string into component parts.
	 *
	 * Splits a token string in the format "commentId_token" into its
	 * component parts for validation and instantiation.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $token_string Raw token string in format "commentId_token".
	 * @return array Array with 'comment_id' and 'token' keys, or empty array if invalid.
	 */
	public static function parse_token_string( ?string $token_string ): array {
		if ( empty( $token_string ) ) {
			return array();
		}

		$parts = explode( '_', $token_string, 2 );

		if ( 2 !== count( $parts ) || ! is_numeric( $parts[0] ) ) {
			return array();
		}

		return array(
			'comment_id' => (int) $parts[0],
			'token'      => $parts[1],
		);
	}

	/**
	 * Creates an Rsvp_Token instance from a URL parameter.
	 *
	 * Retrieves the token from GET request and attempts to create
	 * a valid instance. Useful for handling magic link clicks.
	 *
	 * @since 1.0.0
	 *
	 * @return self|null Instance if valid token found, null otherwise.
	 */
	public static function from_url_parameter(): ?self {
		$token_param = Utility::get_http_input( INPUT_GET, self::NAME );

		return self::from_token_string( $token_param );
	}

	/**
	 * Generates the confirmation URL with the event URL and token parameter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The confirmation URL, or empty string if post not found.
	 */
	public function generate_url(): string {
		$post    = $this->get_post();
		$comment = $this->get_comment();
		$token   = $this->get_token();

		if ( ! $this->has_required_url_components( $post, $comment, $token ) ) {
			return '';
		}

		$event_url = get_permalink( $post );

		if ( ! $event_url ) {
			return '';
		}

		$token_value = $this->format_token_value( (int) $comment->comment_ID, $token );

		return add_query_arg( static::NAME, $token_value, $event_url );
	}

	/**
	 * Checks if all required components for URL generation are available.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post|null    $post The post object.
	 * @param WP_Comment|null $comment The comment object.
	 * @param string          $token The token string.
	 *
	 * @return bool True if all components are available, false otherwise.
	 */
	private function has_required_url_components( ?WP_Post $post, ?WP_Comment $comment, string $token ): bool {
		return $post instanceof WP_Post && $comment instanceof WP_Comment && ! empty( $token );
	}

	/**
	 * Formats the token value for URL inclusion.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $comment_id The comment ID.
	 * @param string $token The token string.
	 *
	 * @return string The formatted token value.
	 */
	private function format_token_value( int $comment_id, string $token ): string {
		return sprintf( '%d_%s', $comment_id, $token );
	}

	/**
	 * Send RSVP confirmation email with magic link.
	 *
	 * Sends an email containing a magic link that allows the user to confirm
	 * their RSVP and change their response if needed.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if email was sent successfully, false otherwise.
	 */
	public function send_rsvp_confirmation_email(): bool {
		$to = $this->get_email();

		if ( empty( $to ) ) {
			return false;
		}

		$post       = $this->get_post();
		$email_data = $this->prepare_email_data( $post );

		return wp_mail(
			$to,
			$email_data['subject'],
			$email_data['body'],
			$email_data['headers']
		);
	}

	/**
	 * Prepares email data for the confirmation email.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post|null $post The event post object.
	 *
	 * @return array Email data with subject, body, and headers.
	 */
	private function prepare_email_data( ?WP_Post $post ): array {
		$title = $post ? get_the_title( $post ) : __( 'this event', 'gatherpress' );

		return array(
			'subject' => sprintf(
				/* translators: %s: Event title. */
				__( 'Confirm your RSVP for %s', 'gatherpress' ),
				$title
			),
			'body'    => $this->render_email_template( $post ),
			'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
		);
	}

	/**
	 * Renders the email template for the confirmation email.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post|null $post The event post object.
	 *
	 * @return string The rendered email body.
	 */
	private function render_email_template( ?WP_Post $post ): string {
		return Utility::render_template(
			sprintf( '%s/includes/templates/admin/emails/rsvp-token-confirmation.php', GATHERPRESS_CORE_PATH ),
			array(
				'event_id'  => $post ? $post->ID : 0,
				'token_url' => $this->generate_url(),
			)
		);
	}
}
