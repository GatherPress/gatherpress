<?php
/**
 * Handles RSVP form submission processing for both traditional forms and AJAX requests.
 *
 * This class centralizes the RSVP creation logic that was duplicated between
 * the traditional form submission handler and the REST API AJAX handler.
 * This ensures consistency and makes maintenance easier.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.33.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Rsvp_Form;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Comment;
use WP_User;

/**
 * Class Form.
 *
 * Centralizes RSVP submission processing logic for consistency across form and AJAX submissions.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.34.0
 *
 * @phpstan-type ReturnedSubmission array{
 *     token: string,
 *     post_id: int,
 *     form_schema_id: string,
 *     errors: array<string, string>,
 *     form_error: string,
 *     values: array<string, string>
 * }
 */
final class Form {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Query arg carrying the token of a rejected submission back to its form.
	 *
	 * @since TBD
	 * @var string
	 */
	const RETURN_QUERY_ARG = 'gatherpress_rsvp_form_error';

	/**
	 * Prefix of the transient holding a rejected submission's errors and values.
	 *
	 * @since TBD
	 * @var string
	 */
	const RETURN_TRANSIENT_PREFIX = 'gatherpress_rsvp_form_return_';

	/**
	 * Seconds a rejected submission waits for its form to load again.
	 *
	 * @since TBD
	 * @var int
	 */
	const RETURN_EXPIRATION = 10 * MINUTE_IN_SECONDS;

	/**
	 * Characters a returned value keeps, unless its field allows more.
	 *
	 * Matches the default length limit of a text field.
	 *
	 * @since TBD
	 * @var int
	 */
	const RETURN_VALUE_MAX_LENGTH = 1000;

	/**
	 * The rejected submission this request returned to its form.
	 *
	 * @since TBD
	 * @var ReturnedSubmission|null
	 */
	private ?array $returned_submission = null;

	/**
	 * Whether the token in the URL has been looked up yet.
	 *
	 * @since TBD
	 * @var bool
	 */
	private bool $returned_submission_read = false;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.34.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'initialize_rsvp_form_handling' ) );
		add_filter( 'wp_headers', array( $this, 'prevent_caching_returned_submission' ) );
	}

	/**
	 * Get the duplicate RSVP error message.
	 *
	 * @since 0.34.0
	 *
	 * @return string The translated error message.
	 */
	private function get_duplicate_rsvp_message(): string {
		return __( "You've already RSVP'd to this event.", 'gatherpress' );
	}

	/**
	 * Check if this is an RSVP form submission.
	 *
	 * This method determines if the current request is an RSVP form submission
	 * by checking for the presence of required form fields in the POST data.
	 *
	 * @since 0.34.0
	 *
	 * @return bool True if this is an RSVP form submission, false otherwise.
	 */
	public function is_rsvp_form_submission(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
			: '';
		return (
			'POST' === $request_method &&
			! empty( Utility::get_http_input( INPUT_POST, 'comment_post_ID' ) ) &&
			! empty( Utility::get_http_input( INPUT_POST, 'gatherpress_rsvp_form_id' ) )
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Initializes RSVP form handling.
	 *
	 * This method detects RSVP form submissions and configures the necessary WordPress
	 * filters and actions to process them correctly as specialized comment objects.
	 *
	 * @since 0.34.0
	 * @return void
	 */
	public function initialize_rsvp_form_handling(): void {
		// Only proceed if this is an RSVP form submission.
		if ( ! $this->is_rsvp_form_submission() ) {
			return;
		}

		add_filter( 'allow_empty_comment', '__return_true', PHP_INT_MAX );

		add_filter( 'comments_open', '__return_true', PHP_INT_MAX );

		add_filter(
			'preprocess_comment',
			array( $this, 'preprocess_rsvp_comment' )
		);

		add_action(
			'comment_post',
			array( $this, 'handle_rsvp_comment_post' )
		);

		add_filter(
			'comment_duplicate_message',
			array( $this, 'get_duplicate_rsvp_message' )
		);

		add_filter(
			'comment_post_redirect',
			array( $this, 'handle_rsvp_comment_redirect' ),
			10,
			2
		);
	}

	/**
	 * Process RSVP comment data during preprocessing.
	 *
	 * This method handles duplicate detection and prepares comment data
	 * for WordPress's comment processing system.
	 *
	 * @since 0.34.0
	 *
	 * @param array<string, mixed> $comment_data The comment data array.
	 *
	 * @return array<string, mixed> Modified comment data array.
	 */
	public function preprocess_rsvp_comment( array $comment_data ): array {
		$author  = Utility::get_http_input( INPUT_POST, 'author' );
		$email   = Utility::get_http_input( INPUT_POST, 'email', 'sanitize_email' );
		$post_id = intval( $comment_data['comment_post_ID'] );

		// is_enabled() is false for any post type without gatherpress-rsvp
		// support, so a non-event ID answers 403 here like a disabled event.
		if ( ! ( new Rsvp( $post_id ) )->is_enabled() ) {
			wp_die(
				esc_html__( 'RSVP is disabled for this event.', 'gatherpress' ),
				esc_html__( 'RSVP Disabled', 'gatherpress' ),
				403
			);
		}

		if ( ! ( new Rsvp( $post_id ) )->allows_open_rsvp() ) {
			wp_die(
				esc_html__( 'Open RSVP is disabled for this site.', 'gatherpress' ),
				esc_html__( 'Open RSVP Disabled', 'gatherpress' ),
				403
			);
		}

		// Check if event has passed - prevent RSVPs to past events.
		$event = new Event( $post_id );
		if ( $event->has_event_past() ) {
			$singular = Utility::post_type_label( 'singular_name', (string) get_post_type( $post_id ) );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: Singular post type label, e.g. "event". */
						__( 'Registration for this %s is now closed.', 'gatherpress' ),
						strtolower( $singular )
					)
				),
				esc_html(
					sprintf(
						/* translators: %s: Singular post type label, e.g. "Event". */
						__( '%s Has Passed', 'gatherpress' ),
						$singular
					)
				),
				400
			);
		}

		// Check for duplicate RSVP. The form is still on the page, so the
		// submitter goes back to it rather than to an error page.
		if ( $this->has_duplicate_rsvp( $post_id, $email ) ) {
			$this->return_to_form( $post_id, array(), $this->get_duplicate_rsvp_message() );

			wp_die(
				esc_html( $this->get_duplicate_rsvp_message() ),
				esc_html__( 'Duplicate RSVP', 'gatherpress' ),
				409
			);
		}

		// Validate custom fields before the comment is created. The `required`
		// attribute in the markup is browser-side only, so anything submitting
		// past the browser reaches this gate instead of storing an RSVP with
		// answers missing.
		$field_errors = Rsvp_Form::get_instance()->validate_custom_fields_from_post(
			$post_id,
			Utility::get_http_input( INPUT_POST, 'gatherpress_form_schema_id' )
		);

		// No form on the page has an unknown schema id, so that one stays an error page.
		if ( ! empty( $field_errors ) && ! isset( $field_errors[ Rsvp_Form::SCHEMA_ID_FIELD ] ) ) {
			$this->return_to_form( $post_id, $field_errors, '' );
		}

		if ( ! empty( $field_errors ) ) {
			wp_die(
				esc_html( implode( ' ', $field_errors ) ),
				esc_html__( 'RSVP Incomplete', 'gatherpress' ),
				400
			);
		}

		// Prepare comment data for WordPress processing.
		$comment_data['comment_content'] = '';
		$comment_data['comment_type']    = Rsvp::COMMENT_TYPE;
		$comment_data['comment_parent']  = 0;

		// Handle user authentication.
		$user = get_user_by( 'ID', get_current_user_id() );
		if (
			! $user instanceof WP_User ||
			$user->user_email !== $email
		) {
			add_filter( 'pre_comment_approved', '__return_zero' );

			$comment_data['user_id']              = 0;
			$comment_data['comment_author_url']   = '';
			$comment_data['comment_author']       = $author;
			$comment_data['comment_author_email'] = $email;
		}

		return $comment_data;
	}

	/**
	 * Handle actions after an RSVP comment is posted.
	 *
	 * This method processes meta fields and sends confirmation emails
	 * after a successful RSVP comment creation.
	 *
	 * @since 0.34.0
	 *
	 * @param int $comment_id The comment ID.
	 *
	 * @return void
	 */
	public function handle_rsvp_comment_post( int $comment_id ): void {
		if ( Rsvp::is_comment_type( $comment_id ) ) {
			// Prepare data for meta processing.
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$data = array(
				'gatherpress_event_updates_opt_in' => Utility::get_http_input(
					INPUT_POST,
					'gatherpress_event_updates_opt_in'
				),
				'gatherpress_rsvp_guests'          => Utility::get_http_input(
					INPUT_POST,
					'gatherpress_rsvp_form_guests'
				),
				'gatherpress_rsvp_anonymous'       => Utility::get_http_input(
					INPUT_POST,
					'gatherpress_rsvp_form_anonymous'
				),
			);

			// Add custom fields to data.
			foreach ( $_POST as $key => $value ) {
				if ( str_starts_with( $key, 'gatherpress_custom_' ) ) {
					$data[ $key ] = $value;
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			// Set RSVP status to attending.
			wp_set_object_terms( $comment_id, Status::ATTENDING->value, Status::TAXONOMY );

			// Process all fields.
			$this->process_fields( $comment_id, $data );

			// The email creates the token when it goes out.
			Email::get_instance()->queue_confirmation( $comment_id );
		}
	}

	/**
	 * Handle redirection after RSVP comment submission.
	 *
	 * This method customizes the redirect URL to include success parameters
	 * and preserve form anchors for better user experience.
	 *
	 * @since 0.34.0
	 *
	 * @param string     $location The original redirect location.
	 * @param WP_Comment $comment  The comment object.
	 *
	 * @return string The modified redirect location.
	 */
	public function handle_rsvp_comment_redirect( string $location, WP_Comment $comment ): string {
		if ( ! Rsvp::is_comment_type( $comment ) ) {
			return $location;
		}

		$form_id = Utility::get_http_input( INPUT_POST, 'gatherpress_rsvp_form_id' );
		$referer = Utility::get_wp_referer();

		if ( ! $referer ) {
			return $location;
		}

		// A submission that succeeds after being sent back carries the earlier
		// token, which has nothing left to show.
		$redirect_url = add_query_arg(
			array(
				'gatherpress_rsvp_success' => 'true',
			),
			remove_query_arg( self::RETURN_QUERY_ARG, $referer )
		);

		if ( ! empty( $form_id ) ) {
			$redirect_url .= '#' . esc_attr( $form_id );
		}

		return $redirect_url;
	}

	/**
	 * Sends a rejected submission back to its form.
	 *
	 * Returns without redirecting when the page the form was on is unknown,
	 * so the caller can fall back to an error page.
	 *
	 * @since TBD
	 *
	 * @param int                   $post_id      The event post ID.
	 * @param array<string, string> $field_errors Error messages keyed by field name.
	 * @param string                $form_error   A message for the whole form, or ''.
	 *
	 * @return void
	 */
	private function return_to_form( int $post_id, array $field_errors, string $form_error ): void {
		$url = $this->get_return_url( $post_id, $field_errors, $form_error );

		if ( '' === $url ) {
			return;
		}

		// A wp_redirect filter can cancel the redirect, which leaves the
		// caller's error page. Tests stop inside that filter.
		if ( wp_safe_redirect( $url, 303 ) ) {
			// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation.
			// @codeCoverageIgnoreStart
			exit;
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * Stores a rejected submission and returns the URL that brings it back.
	 *
	 * The errors and the submitted values go in a transient under a random
	 * token, and only the token goes in the URL, so a name or email address
	 * never ends up in browser history or server logs.
	 *
	 * @since TBD
	 *
	 * @param int                   $post_id      The event post ID.
	 * @param array<string, string> $field_errors Error messages keyed by field name.
	 * @param string                $form_error   A message for the whole form, or ''.
	 *
	 * @return string The form's URL with the token, or '' when the page the form was on is unknown.
	 */
	public function get_return_url( int $post_id, array $field_errors, string $form_error ): string {
		$referer = Utility::get_wp_referer();

		if ( ! $referer ) {
			return '';
		}

		$form_schema_id = Utility::get_http_input( INPUT_POST, 'gatherpress_form_schema_id' );
		$token          = wp_generate_password( 20, false );

		set_transient(
			self::RETURN_TRANSIENT_PREFIX . $token,
			array(
				'post_id'        => $post_id,
				'form_schema_id' => $form_schema_id,
				'errors'         => $field_errors,
				'form_error'     => $form_error,
				'values'         => $this->get_return_values( $post_id, $form_schema_id ),
			),
			self::RETURN_EXPIRATION
		);

		// No #anchor: a URL fragment stops browsers honoring autofocus, which
		// already scrolls the field to fix into view.
		return add_query_arg(
			self::RETURN_QUERY_ARG,
			$token,
			remove_query_arg( array( self::RETURN_QUERY_ARG, 'gatherpress_rsvp_success' ), $referer )
		);
	}

	/**
	 * Returns the submitted values a rejected submission keeps for its form.
	 *
	 * These are the fields the form's schema names, hidden fields aside, and
	 * the built-in fields, which come back even for an event with no stored
	 * schema. Each value is cut to its field's length limit, 1000 characters
	 * unless the field allows more, so a refused submission stays small.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id        The event post ID.
	 * @param string $form_schema_id The submitted form-schema id.
	 *
	 * @return array<string, string> Submitted values keyed by field name.
	 */
	private function get_return_values( int $post_id, string $form_schema_id ): array {
		$fields = array_fill_keys(
			Rsvp_Form::BUILT_IN_FIELDS,
			array(
				'type'       => 'text',
				'max_length' => self::RETURN_VALUE_MAX_LENGTH,
			)
		);

		foreach ( Rsvp_Form::get_instance()->get_schema_fields( $post_id, $form_schema_id ) as $field_name => $field ) {
			$fields[ (string) $field_name ] = array(
				'type'       => $field['type'],
				'max_length' => max( self::RETURN_VALUE_MAX_LENGTH, (int) ( $field['max_length'] ?? 0 ) ),
			);
		}

		$values = array();

		foreach ( $fields as $field_name => $field ) {
			if ( 'hidden' !== $field['type'] ) {
				$value = Utility::get_http_input(
					INPUT_POST,
					$field_name,
					'textarea' === $field['type'] ? 'sanitize_textarea_field' : null
				);

				$values[ $field_name ] = mb_substr( $value, 0, $field['max_length'] );
			}
		}

		return $values;
	}

	/**
	 * Returns the rejected submission whose token is in the URL.
	 *
	 * Looked up once per request, since the headers need it before the form
	 * renders and deletes its transient.
	 *
	 * @since TBD
	 *
	 * @return ReturnedSubmission|null The submission, or null when there is none.
	 */
	public function get_returned_submission(): ?array {
		if ( ! $this->returned_submission_read ) {
			$this->returned_submission_read = true;
			$this->returned_submission      = $this->read_returned_submission();
		}

		return $this->returned_submission;
	}

	/**
	 * Reads the rejected submission whose token is in the URL.
	 *
	 * @since TBD
	 *
	 * @return ReturnedSubmission|null The submission, or null when the token is missing, malformed or expired.
	 */
	private function read_returned_submission(): ?array {
		$token      = Utility::get_http_input( INPUT_GET, self::RETURN_QUERY_ARG );
		$submission = preg_match( '/^[A-Za-z0-9]{20}$/', $token )
			? get_transient( self::RETURN_TRANSIENT_PREFIX . $token )
			: false;

		if ( ! is_array( $submission ) ) {
			return null;
		}

		return array(
			'token'          => $token,
			'post_id'        => (int) ( $submission['post_id'] ?? 0 ),
			'form_schema_id' => (string) ( $submission['form_schema_id'] ?? '' ),
			'errors'         => array_map( 'strval', (array) ( $submission['errors'] ?? array() ) ),
			'form_error'     => (string) ( $submission['form_error'] ?? '' ),
			'values'         => array_map( 'strval', (array) ( $submission['values'] ?? array() ) ),
		);
	}

	/**
	 * Deletes the returned submission once its form has picked it up.
	 *
	 * It is shown once, so a later visit to the same URL, from history or a
	 * shared link, finds nothing.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function consume_returned_submission(): void {
		if ( null !== $this->returned_submission ) {
			delete_transient( self::RETURN_TRANSIENT_PREFIX . $this->returned_submission['token'] );
		}
	}

	/**
	 * Keeps a page showing a rejected submission out of page caches.
	 *
	 * The page holds what the submitter typed, their name and email
	 * included, so no cache may keep a copy of it. Page cache plugins
	 * look for `DONOTCACHEPAGE` rather than the headers.
	 *
	 * @since TBD
	 *
	 * @param array<string, string|false> $headers The headers about to be sent.
	 *
	 * @return array<string, string|false> The headers, with no-cache headers when a submission is shown.
	 */
	public function prevent_caching_returned_submission( array $headers ): array {
		if ( null !== $this->get_returned_submission() ) {
			$headers = array_merge( $headers, wp_get_nocache_headers() );

			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Read by page cache plugins.
				define( 'DONOTCACHEPAGE', true );
			}
		}

		return $headers;
	}


	/**
	 * Process an RSVP submission with the given data.
	 *
	 * Handles user authentication, duplicate detection, comment creation,
	 * meta data processing, and confirmation email sending.
	 *
	 * @since 0.34.0
	 *
	 * @param array<string, mixed> $data RSVP submission data containing post_id, author, email, and optional fields.
	 *
	 * @return array{success: bool, message: string, comment_id: int, error_code?: int, errors?: array<string, string>}
	 *               Processing result. On a custom-field rejection, `errors` carries one message per failing field.
	 */
	public function process_rsvp( array $data ): array {
		$post_id = intval( $data['post_id'] );
		$author  = sanitize_text_field( $data['author'] ?? '' );
		$email   = sanitize_email( $data['email'] ?? '' );

		// Validate required fields.
		if ( ! $post_id || ! $email || ! $author ) {
			return array(
				'success'    => false,
				'message'    => __( 'Missing required fields.', 'gatherpress' ),
				'comment_id' => 0,
				'error_code' => 400,
			);
		}

		// Check for duplicate RSVP.
		if ( $this->has_duplicate_rsvp( $post_id, $email ) ) {
			return array(
				'success'    => false,
				'message'    => $this->get_duplicate_rsvp_message(),
				'comment_id' => 0,
				'error_code' => 409,
			);
		}

		// Validate custom fields before the comment exists, so a rejected
		// submission is rejected outright rather than stored with answers
		// missing and confirmed by email.
		$field_errors = Rsvp_Form::get_instance()->validate_custom_fields(
			$post_id,
			(string) ( $data['gatherpress_form_schema_id'] ?? '' ),
			$data
		);

		if ( ! empty( $field_errors ) ) {
			return array(
				'success'    => false,
				'message'    => implode( ' ', $field_errors ),
				'comment_id' => 0,
				'error_code' => 400,
				'errors'     => $field_errors,
			);
		}

		// Prepare comment data.
		$comment_data = $this->prepare_comment_data( $post_id, $author, $email );

		// Run WordPress-native comment filters so sites can honor
		// pre_comment_user_ip, pre_comment_user_agent, etc. for privacy.
		$comment_data = wp_filter_comment( $comment_data );

		// Insert the comment.
		$comment_id_result = wp_insert_comment( $comment_data );

		return $this->handle_rsvp_creation( $comment_id_result, $data );
	}

	/**
	 * Check for duplicate RSVP by email or user ID.
	 *
	 * Uses direct SQL instead of WP_Comment_Query because we need OR logic
	 * across different fields (email OR user_id) which isn't supported natively.
	 * This prevents duplicate RSVPs when someone submits with an email that
	 * belongs to an existing user who already RSVP'd.
	 *
	 * @since 0.34.0
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $email   The email address to check.
	 *
	 * @return bool True if a duplicate RSVP exists, false otherwise.
	 */
	public function has_duplicate_rsvp( int $post_id, string $email ): bool {
		global $wpdb;

		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user instanceof WP_User ) {
			$query          = "SELECT COUNT(*) FROM {$wpdb->comments}
				WHERE comment_post_ID = %d AND comment_type = %s
				AND (comment_author_email = %s OR user_id = %d)";
			$prepare_values = array( $post_id, Rsvp::COMMENT_TYPE, $email, $existing_user->ID );
		} else {
			$query          = "SELECT COUNT(*) FROM {$wpdb->comments}
				WHERE comment_post_ID = %d AND comment_type = %s AND comment_author_email = %s";
			$prepare_values = array( $post_id, Rsvp::COMMENT_TYPE, $email );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare(
				$query,
				...$prepare_values
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return (int) $count > 0;
	}

	/**
	 * Prepare comment data for insertion.
	 *
	 * Handles user authentication and sets appropriate author information
	 * based on whether the user is logged in and email matches.
	 *
	 * @since 0.34.0
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $author  The author name.
	 * @param string $email   The email address.
	 *
	 * @return array{
	 *     comment_post_ID: int,
	 *     comment_author_IP: string,
	 *     comment_type: string,
	 *     comment_content: string,
	 *     comment_parent: int,
	 *     user_id: int,
	 *     comment_approved: int,
	 *     comment_author: string,
	 *     comment_author_email: string,
	 *     comment_author_url: string
	 * } Comment data array for wp_insert_comment().
	 */
	private function prepare_comment_data( int $post_id, string $author, string $email ): array {
		$user = get_user_by( 'ID', get_current_user_id() );

		$comment_data = array(
			'comment_post_ID'   => $post_id,
			'comment_author_IP' => '127.0.0.1',
			'comment_type'      => Rsvp::COMMENT_TYPE,
			'comment_content'   => '',
			'comment_parent'    => 0,
			'user_id'           => 0,
			'comment_approved'  => 0,
		);

		// Set remote IP if available.
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

			if ( rest_is_ip_address( $remote_ip ) ) {
				$comment_data['comment_author_IP'] = $remote_ip;
			}
		}

		// Handle user authentication and author data.
		if ( $user instanceof WP_User && $user->user_email === $email ) {
			// Current logged-in user matches the email.
			$comment_data['user_id']              = $user->ID;
			$comment_data['comment_author']       = $user->display_name;
			$comment_data['comment_author_email'] = $user->user_email;
			$comment_data['comment_author_url']   = get_author_posts_url( $user->ID );
		} else {
			// Check if any user exists with this email.
			$existing_user = get_user_by( 'email', $email );

			if ( $existing_user instanceof WP_User ) {
				// Associate with existing user account.
				$comment_data['user_id']              = $existing_user->ID;
				$comment_data['comment_author']       = $existing_user->display_name;
				$comment_data['comment_author_email'] = $existing_user->user_email;
				$comment_data['comment_author_url']   = get_author_posts_url( $existing_user->ID );
			} else {
				// No user found, create anonymous RSVP.
				$comment_data['user_id']              = 0;
				$comment_data['comment_author_url']   = '';
				$comment_data['comment_author']       = $author;
				$comment_data['comment_author_email'] = $email;
			}
		}

		return $comment_data;
	}

	/**
	 * Process all fields for the RSVP comment.
	 *
	 * Handles both meta fields (email updates, guest count, anonymous flag)
	 * and custom fields based on form schema.
	 *
	 * @since 0.34.0
	 *
	 * @param int                  $comment_id The comment ID.
	 * @param array<string, mixed> $data       Submission data containing field values.
	 *
	 * @return void
	 */
	public function process_fields( int $comment_id, array $data ): void {
		$this->process_meta_fields( $comment_id, $data );
		$this->process_custom_fields( $comment_id, $data );
	}

	/**
	 * Process meta fields for the RSVP comment.
	 *
	 * Handles email updates preference, guest count, and anonymous flag
	 * with proper validation and limits.
	 *
	 * @since 0.34.0
	 *
	 * @param int                  $comment_id The comment ID.
	 * @param array<string, mixed> $data       Submission data containing meta field values.
	 *
	 * @return void
	 */
	private function process_meta_fields( int $comment_id, array $data ): void {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}
		$post_id = (int) $comment->comment_post_ID;
		// Handle email updates preference.
		if ( isset( $data['gatherpress_event_updates_opt_in'] ) ) {
			$email_updates = (bool) $data['gatherpress_event_updates_opt_in'];
			update_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', $email_updates ? 1 : 0 );
		}

		// Handle guest count field.
		if ( isset( $data['gatherpress_rsvp_guests'] ) && is_numeric( $data['gatherpress_rsvp_guests'] ) ) {
			$guest_count = intval( $data['gatherpress_rsvp_guests'] );
			$guest_limit = intval( get_post_meta( $post_id, 'gatherpress_guest_limit', true ) );

			// Cap guest count at the maximum allowed.
			if ( $guest_limit > 0 && $guest_count > $guest_limit ) {
				$guest_count = $guest_limit;
			}

			update_comment_meta( $comment_id, 'gatherpress_rsvp_guests', $guest_count );
		}

		// Handle anonymous field.
		if ( isset( $data['gatherpress_rsvp_anonymous'] ) ) {
			$anonymous             = (bool) $data['gatherpress_rsvp_anonymous'];
			$enable_anonymous_rsvp = get_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', true );

			// Only set anonymous if it's enabled for the event.
			if ( $anonymous && ! empty( $enable_anonymous_rsvp ) ) {
				update_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', 1 );
			}
		}
	}

	/**
	 * Process custom fields for the RSVP comment.
	 *
	 * Validates and saves custom fields based on the form schema.
	 * For form submissions, this uses the existing method that reads from $_POST.
	 * For REST API submissions, this processes the data directly.
	 *
	 * @since 0.34.0
	 *
	 * @param int                  $comment_id The comment ID.
	 * @param array<string, mixed> $data       Submission data containing custom field values.
	 *
	 * @return void
	 */
	private function process_custom_fields( int $comment_id, array $data ): void {
		// Check if we have a form schema ID in the data.
		$form_schema_id = $data['gatherpress_form_schema_id'] ?? '';
		if ( empty( $form_schema_id ) ) {
			// For traditional form submissions, delegate to the blocks class.
			$rsvp_form = Rsvp_Form::get_instance();
			$rsvp_form->process_custom_fields_for_form( $comment_id );
			return;
		}

		// For REST API submissions, process the custom fields directly.
		$comment = get_comment( $comment_id );
		if ( ! $comment instanceof WP_Comment || ! Rsvp::is_comment_type( $comment ) ) {
			return;
		}

		$post_id = (int) $comment->comment_post_ID;

		// Get the blocks Rsvp_Form instance for the schema and field sanitization.
		$rsvp_form_blocks = Rsvp_Form::get_instance();

		// Read the schema through the shared accessor, which type-checks the
		// stored meta rather than trusting its shape.
		$fields = $rsvp_form_blocks->get_schema_fields( $post_id, (string) $form_schema_id );

		// Process each custom field.
		foreach ( $fields as $field_name => $field_config ) {
			// Skip built-in fields - they are handled by process_meta_fields().
			if ( in_array( $field_name, Rsvp_Form::BUILT_IN_FIELDS, true ) ) {
				continue;
			}

			if ( ! isset( $data[ $field_name ] ) ) {
				continue;
			}

			$field_value = $data[ $field_name ];

			// Sanitize the field value. A rejected value is not stored at all,
			// rather than stored as the sanitizer's `false`.
			$sanitized_value = $rsvp_form_blocks->sanitize_custom_field_value( $field_value, $field_config );

			if ( false === $sanitized_value ) {
				continue;
			}

			// Save the sanitized field value with prefix to avoid conflicts.
			$meta_key = 'gatherpress_custom_' . sanitize_key( $field_name );
			update_comment_meta( $comment_id, $meta_key, $sanitized_value );
		}
	}

	/**
	 * Handle the result of RSVP comment creation.
	 *
	 * Processes the result of wp_insert_comment, handling both success and failure cases.
	 * On success, sets the RSVP status, processes custom fields, and sends confirmation email.
	 * On failure, returns an error response.
	 *
	 * @since 0.34.0
	 *
	 * @param int|false            $comment_id_result The result from wp_insert_comment (comment ID or false).
	 * @param array<string, mixed> $data              RSVP submission data.
	 *
	 * @return array{success: bool, message: string, comment_id: int, error_code?: int} Processing result.
	 */
	private function handle_rsvp_creation( $comment_id_result, array $data ): array {
		// Handle failure case.
		if ( ! $comment_id_result ) {
			return array(
				'success'    => false,
				'message'    => __( 'Failed to create RSVP.', 'gatherpress' ),
				'comment_id' => 0,
				'error_code' => 500,
			);
		}

		$comment_id = (int) $comment_id_result;

		// Set RSVP status to attending.
		wp_set_object_terms( $comment_id, Status::ATTENDING->value, Status::TAXONOMY );

		// Process all fields.
		$this->process_fields( $comment_id, $data );

		// The email creates the token when it goes out.
		Email::get_instance()->queue_confirmation( $comment_id );

		return array(
			'success'    => true,
			'message'    => __(
				'Your RSVP has been submitted successfully! Please check your email for a confirmation link.',
				'gatherpress'
			),
			'comment_id' => $comment_id,
		);
	}
}
