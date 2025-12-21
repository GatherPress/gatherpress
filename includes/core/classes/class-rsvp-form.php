<?php
/**
 * Handles RSVP form submission processing for both traditional forms and AJAX requests.
 *
 * This class centralizes the RSVP creation logic that was duplicated between
 * the traditional form submission handler and the REST API AJAX handler.
 * This ensures consistency and makes maintenance easier.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Rsvp_Form as Rsvp_Form_Block;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Rsvp_Token;
use WP_Comment;
use WP_User;

/**
 * Class Rsvp_Form
 *
 * Centralizes RSVP submission processing logic for consistency across form and AJAX submissions.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */
class Rsvp_Form {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'initialize_rsvp_form_handling' ) );
	}

	/**
	 * Get the duplicate RSVP error message.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @param array $comment_data The comment data array.
	 * @return array Modified comment data array.
	 */
	public function preprocess_rsvp_comment( array $comment_data ): array {
		$author  = Utility::get_http_input( INPUT_POST, 'author' );
		$email   = Utility::get_http_input( INPUT_POST, 'email', 'sanitize_email' );
		$post_id = intval( $comment_data['comment_post_ID'] );

		// Validate that the post is an event.
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			wp_die(
				esc_html__( 'Invalid event ID.', 'gatherpress' ),
				esc_html__( 'Invalid Request', 'gatherpress' ),
				400
			);
		}

		// Check if event has passed - prevent RSVPs to past events.
		$event = new Event( $post_id );
		if ( $event->has_event_past() ) {
			wp_die(
				esc_html__( 'Registration for this event is now closed.', 'gatherpress' ),
				esc_html__( 'Event Has Passed', 'gatherpress' ),
				400
			);
		}

		// Check for duplicate RSVP.
		if ( $this->has_duplicate_rsvp( $post_id, $email ) ) {
			wp_die(
				esc_html( $this->get_duplicate_rsvp_message() ),
				esc_html__( 'Duplicate RSVP', 'gatherpress' ),
				409
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
	 * @since 1.0.0
	 *
	 * @param int $comment_id The comment ID.
	 * @return void
	 */
	public function handle_rsvp_comment_post( int $comment_id ): void {
		if ( Rsvp::COMMENT_TYPE === get_comment_type( $comment_id ) ) {
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
				if ( 0 === strpos( $key, 'gatherpress_custom_' ) ) {
					$data[ $key ] = $value;
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			// Set RSVP status to attending.
			wp_set_object_terms( $comment_id, 'attending', Rsvp::TAXONOMY );

			// Process all fields.
			$this->process_fields( $comment_id, $data );

			// Generate and send confirmation email.
			$rsvp_token = new Rsvp_Token( $comment_id );
			$rsvp_token->generate_token()->send_rsvp_confirmation_email();
		}
	}

	/**
	 * Handle redirection after RSVP comment submission.
	 *
	 * This method customizes the redirect URL to include success parameters
	 * and preserve form anchors for better user experience.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $location The original redirect location.
	 * @param WP_Comment $comment  The comment object.
	 * @return string The modified redirect location.
	 */
	public function handle_rsvp_comment_redirect( string $location, WP_Comment $comment ): string {
		if ( Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return $location;
		}

		$form_id = Utility::get_http_input( INPUT_POST, 'gatherpress_rsvp_form_id' );
		$referer = Utility::get_wp_referer();

		if ( ! $referer ) {
			return $location;
		}

		$redirect_url = add_query_arg(
			array(
				'gatherpress_rsvp_success' => 'true',
			),
			$referer
		);

		if ( ! empty( $form_id ) ) {
			$redirect_url .= '#' . esc_attr( $form_id );
		}

		return $redirect_url;
	}


	/**
	 * Process an RSVP submission with the given data.
	 *
	 * Handles user authentication, duplicate detection, comment creation,
	 * meta data processing, and confirmation email sending.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data RSVP submission data containing post_id, author, email, and optional fields.
	 * @return array{success: bool, message: string, comment_id: int, error_code?: int} Processing result.
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

		// Prepare comment data.
		$comment_data = $this->prepare_comment_data( $post_id, $author, $email );

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
	 * @since 1.0.0
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $email   The email address to check.
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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$count = $wpdb->get_var(
			$wpdb->prepare(
				$query,
				...$prepare_values
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		return (int) $count > 0;
	}

	/**
	 * Prepare comment data for insertion.
	 *
	 * Handles user authentication and sets appropriate author information
	 * based on whether the user is logged in and email matches.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id The event post ID.
	 * @param string $author  The author name.
	 * @param string $email   The email address.
	 * @return array Comment data array for wp_insert_comment().
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
	 * @since 1.0.0
	 *
	 * @param int   $comment_id The comment ID.
	 * @param array $data       Submission data containing field values.
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
	 * @since 1.0.0
	 *
	 * @param int   $comment_id The comment ID.
	 * @param array $data       Submission data containing meta field values.
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
			$guest_count     = intval( $data['gatherpress_rsvp_guests'] );
			$max_guest_limit = intval( get_post_meta( $post_id, 'gatherpress_max_guest_limit', true ) );

			// Cap guest count at the maximum allowed.
			if ( $max_guest_limit > 0 && $guest_count > $max_guest_limit ) {
				$guest_count = $max_guest_limit;
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
	 * @since 1.0.0
	 *
	 * @param int   $comment_id The comment ID.
	 * @param array $data       Submission data containing custom field values.
	 * @return void
	 */
	private function process_custom_fields( int $comment_id, array $data ): void {
		// Check if we have a form schema ID in the data.
		$form_schema_id = $data['gatherpress_form_schema_id'] ?? '';
		if ( empty( $form_schema_id ) ) {
			// For traditional form submissions, delegate to the blocks class.
			$rsvp_form = Rsvp_Form_Block::get_instance();
			$rsvp_form->process_custom_fields_for_form( $comment_id );
			return;
		}

		// For REST API submissions, process the custom fields directly.
		$comment = get_comment( $comment_id );
		if ( ! $comment || Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return;
		}

		$post_id = (int) $comment->comment_post_ID;

		// Get stored schemas for this post.
		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );
		if ( empty( $schemas ) || ! isset( $schemas[ $form_schema_id ] ) ) {
			return; // No schema found for this form.
		}

		$form_schema = $schemas[ $form_schema_id ];
		$fields      = $form_schema['fields'] ?? array();

		// Get the blocks Rsvp_Form instance for field sanitization.
		$rsvp_form_blocks = Rsvp_Form_Block::get_instance();

		// Process each custom field.
		foreach ( $fields as $field_name => $field_config ) {
			if ( ! isset( $data[ $field_name ] ) ) {
				continue;
			}

			$field_value = $data[ $field_name ];

			// Sanitize the field value.
			$sanitized_value = $rsvp_form_blocks->sanitize_custom_field_value( $field_value, $field_config );

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
	 * @since 1.0.0
	 *
	 * @param int|false $comment_id_result The result from wp_insert_comment (comment ID or false).
	 * @param array     $data              RSVP submission data.
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
		wp_set_object_terms( $comment_id, 'attending', Rsvp::TAXONOMY );

		// Process all fields.
		$this->process_fields( $comment_id, $data );

		// Generate and send confirmation email.
		$rsvp_token = new Rsvp_Token( $comment_id );
		$rsvp_token->generate_token()->send_rsvp_confirmation_email();

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
