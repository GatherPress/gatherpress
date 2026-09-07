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
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Occurrence_Identity;
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
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
 */
final class Form {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Posted field naming the occurrence a classic form submission belongs to.
	 *
	 * The no-JavaScript fallback posts to `wp-comments-post.php`, which never
	 * fires `wp`, so `Event\Recurrence\Context::sync()` never runs and there is
	 * no ambient occurrence to read. The occurrence therefore travels as a
	 * server-rendered hidden field, the same way `comment_post_ID` does, and is
	 * validated here against the event's own series before anything is written.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const RECURRENCE_ID_FIELD = 'gatherpress_recurrence_id';

	/**
	 * Posted marker value that means the whole series, deliberately.
	 *
	 * A recurring event's form always posts a scope: an occurrence identifier
	 * inside occurrence context, and this value everywhere else. That is what
	 * lets `posted_occurrence()` tell an intentional series-wide submission
	 * apart from a stale pre-upgrade page posting with no field at all, which
	 * is refused rather than silently widened. The value cannot collide with a
	 * real identifier, which is always in `Ymd\THis` form.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const RECURRENCE_SCOPE_SERIES = 'series';

	/**
	 * The occurrence context standing before this submission entered one.
	 *
	 * `wp-comments-post.php` ends in a redirect rather than in a teardown hook,
	 * so the restore is explicit. Holding the previous value rather than a bare
	 * flag means the classic path composes with anything that already held
	 * context instead of clearing the process.
	 *
	 * @since 0.36.0
	 * @var array|null
	 */
	protected ?array $previous_occurrence = null;

	/**
	 * Whether this submission entered occurrence context.
	 *
	 * Distinct from `$previous_occurrence` being null, which is also the
	 * ordinary state of a submission that entered one from no context at all.
	 *
	 * @since 0.36.0
	 * @var bool
	 */
	protected bool $entered_occurrence_context = false;

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
	}

	/**
	 * Get the duplicate RSVP error message.
	 *
	 * Public because it is registered on the `comment_duplicate_message` filter,
	 * which WordPress invokes through `call_user_func_array()`. As a private
	 * method the registration was a fatal `TypeError` the moment core actually
	 * reached that filter, which only happens on the classic submission path.
	 *
	 * @since 0.34.0
	 *
	 * @return string The translated error message.
	 */
	public function get_duplicate_rsvp_message(): string {
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
			'duplicate_comment_id',
			array( $this, 'bypass_core_duplicate_check' ),
			10,
			2
		);

		add_filter(
			'comment_post_redirect',
			array( $this, 'handle_rsvp_comment_redirect' ),
			10,
			2
		);
	}

	/**
	 * Let WordPress's content-identity duplicate check pass an RSVP through.
	 *
	 * `wp_allow_comment()` refuses a comment whose author and **content** match
	 * an existing one on the same post. Every RSVP carries empty content by
	 * design, so for this comment type the check reduces to "this person has
	 * already commented on this post". A recurring event makes precisely that
	 * legitimate. Without this, a responder who booked one date was
	 * refused on every other date of the same series by core, after this class's
	 * own occurrence-scoped check had already allowed them.
	 *
	 * Bypassing it is safe because it is not the only duplicate check in the
	 * path: `preprocess_rsvp_comment()` runs first and refuses a genuine
	 * duplicate with a 409, scoped to the occurrence when the submission names
	 * one and series-wide when it does not.
	 *
	 * @since 0.36.0
	 *
	 * @param int|string $dupe_id     The comment ID core matched, or 0 for none.
	 * @param array      $commentdata The comment being inserted.
	 *
	 * @return int|string 0 for an RSVP, otherwise the match core found.
	 */
	public function bypass_core_duplicate_check( $dupe_id, array $commentdata ) {
		$comment_type = (string) ( $commentdata['comment_type'] ?? '' );

		return Rsvp::COMMENT_TYPE === $comment_type ? 0 : $dupe_id;
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

		// Resolve, then authorize, then use, which is the same sequence the REST
		// routes follow. This has to happen before every check below, because the
		// past-event gate and the duplicate gate both mean different things per
		// date: an occurrence in the future on a series whose anchor has passed
		// is bookable, and a responder who took September 3rd has not already
		// taken September 10th.
		$identity = $this->posted_occurrence( $post_id );

		if ( null !== $identity ) {
			// Step 2 of resolve-authorize-use, mirroring the REST twin in
			// `Rest_Api::handle_rsvp_form_submission()`: the response is written
			// against the post that owns the occurrence, so that owner must pass
			// the same viewability gate `wp-comments-post.php` already ran
			// against the named post. Deliberately viewability only, keeping the
			// two paths on one contract. `comments_open()` is not re-run against
			// the owner because this path force-opens comments for RSVP
			// submissions and the real write gates, `Rsvp::is_enabled()` and
			// `allows_open_rsvp()`, run against the owner below.
			// `post_password_required()` is not re-run either: a password gates
			// reading content, the REST twin accepts the same submission without
			// it, and core keeps checking it against the named post. The refusal
			// is the exact refusal a fabricated identifier gets, so it discloses
			// nothing about the unviewable owner's schedule.
			if (
				$identity->owner_post_id !== $post_id
				&& ! Event::is_viewable( $identity->owner_post_id )
			) {
				wp_die(
					esc_html__( 'The requested occurrence no longer exists.', 'gatherpress' ),
					esc_html__( 'Invalid Occurrence', 'gatherpress' ),
					400
				);
			}

			// The response is stored on the post that owns the occurrence.
			// Identical to the posted post on every unsplit series, and
			// deliberately not identical once a forward split has moved the
			// occurrence onto a sibling: `Rsvp\Storage` narrows reads by
			// `comment_post_ID` *and* by the occurrence term, so a row whose two
			// owners disagree is readable through neither.
			$post_id                          = $identity->owner_post_id;
			$comment_data['comment_post_ID']  = $post_id;
			$this->previous_occurrence        = Context::get_instance()->current();
			$this->entered_occurrence_context = true;

			Context::get_instance()->set_for_series( $post_id, $identity->recurrence_id );
		}

		// Check sitewide/per-event RSVP setting before any post-type check so that
		// globally-disabled mode returns the correct 403 rather than a misleading 400.
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
	 * @since 0.34.0
	 *
	 * @param int $comment_id The comment ID.
	 *
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
				if ( str_starts_with( $key, 'gatherpress_custom_' ) ) {
					$data[ $key ] = $value;
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			// Set RSVP status to attending.
			wp_set_object_terms( $comment_id, Status::ATTENDING->value, Status::TAXONOMY );

			$post_id = (int) get_comment( $comment_id )->comment_post_ID;

			// Bind the response to the occurrence the submission named. This is
			// the whole reason the classic fallback used to write series-wide:
			// only the REST path stamped the term, so a response made from an
			// occurrence page with JavaScript unavailable showed on every date
			// of the series and made every other date unbookable for the same
			// responder.
			$this->assign_occurrence( $comment_id, $post_id );

			// Drop the warm counts this insertion just changed. This path never
			// reaches `Rsvp\Storage::save()` or `handle_rsvp_creation()`, which
			// is where every other write invalidates, so a classic submission
			// used to leave both totals stale for the length of
			// `Cache::CACHE_EXPIRATION`, shared across every visitor under a
			// persistent object cache. Called after `assign_occurrence()` so the
			// occurrence-scoped key resolves and is dropped alongside the
			// series-wide one.
			Cache::delete( $post_id );

			// Process all fields.
			$this->process_fields( $comment_id, $data );

			// Generate and send confirmation email. The link resolves from the
			// comment's own occurrence term, so it names the date that was
			// actually booked now that the term above exists.
			$rsvp_token = new Token( $comment_id );
			$rsvp_token->generate_token()->send_rsvp_confirmation_email();

			$this->leave_posted_occurrence();
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
	 * @since 0.34.0
	 *
	 * @param array<string, mixed> $data RSVP submission data containing post_id, author, email, and optional fields.
	 *
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
	 * When the request is scoped to one occurrence of a series, so is the
	 * check: a responder who took the September 3rd date has not already
	 * RSVPd to the September 10th one, and refusing them would make every
	 * occurrence after the first unbookable.
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

		$occurrence = $this->duplicate_occurrence_clause( $post_id );

		if ( null !== $occurrence ) {
			$query         .= $occurrence['clause'];
			$prepare_values = array_merge( $prepare_values, $occurrence['values'] );
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
	 * Resolve the scope a classic form submission names, or refuse it.
	 *
	 * The hidden field is user-controllable, so it is validated against the
	 * event's own series exactly as the REST layer validates its argument, and
	 * a value that does not resolve is refused rather than silently treated as
	 * the series. Silent widening is the failure this whole subsystem exists to
	 * avoid: the visitor believes they booked one date, the response lands on
	 * all of them, and nothing afterwards can tell that apart from a deliberate
	 * series RSVP.
	 *
	 * A recurring event's form always carries the field, with an occurrence
	 * identifier or with the explicit `series` value, so on such an event a
	 * submission with no field at all can only come from markup rendered
	 * before the field existed: a reverse proxy or page cache holding
	 * pre-upgrade occurrence-page HTML, posted natively with JavaScript
	 * unavailable. That submission is refused with a reload-oriented error
	 * rather than widened, for the same reason an unresolvable identifier is.
	 * A fresh render carries the marker and goes through.
	 *
	 * `HTTP_REFERER` is deliberately not consulted. It is attacker-controlled
	 * and routinely stripped by privacy tooling, so scoping a write by it would
	 * be a worse failure mode than the honest series-wide behavior.
	 *
	 * On a site with no recurring events no field is rendered, the missing
	 * field is legitimate, and `requires_explicit_scope()` short-circuits on
	 * the autoloaded option before touching occurrence storage, so this
	 * returns null and the submission runs the SQL it always ran.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The event post ID the submission names.
	 *
	 * @return Occurrence_Identity|null The resolved occurrence, or null for a series-wide submission.
	 */
	private function posted_occurrence( int $post_id ): ?Occurrence_Identity {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$recurrence_id = (string) Utility::get_http_input( INPUT_POST, self::RECURRENCE_ID_FIELD );

		if ( self::RECURRENCE_SCOPE_SERIES === $recurrence_id ) {
			return null;
		}

		if ( '' === $recurrence_id ) {
			if ( Rsvp_Occurrence::requires_explicit_scope( $post_id ) ) {
				wp_die(
					esc_html__(
						'This RSVP form is out of date. Please reload the page and try again.',
						'gatherpress'
					),
					esc_html__( 'Reload Required', 'gatherpress' ),
					409
				);
			}

			return null;
		}

		$identity = Occurrence_Identity::resolve( $post_id, $recurrence_id );

		if ( null === $identity ) {
			wp_die(
				esc_html__( 'The requested occurrence no longer exists.', 'gatherpress' ),
				esc_html__( 'Invalid Occurrence', 'gatherpress' ),
				400
			);
		}

		return $identity;
	}

	/**
	 * Put back whatever occurrence context this submission displaced.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	private function leave_posted_occurrence(): void {
		if ( ! $this->entered_occurrence_context ) {
			return;
		}

		Context::get_instance()->restore( $this->previous_occurrence );

		$this->previous_occurrence        = null;
		$this->entered_occurrence_context = false;
	}

	/**
	 * Bind a freshly inserted RSVP comment to the occurrence in play.
	 *
	 * A no-op outside occurrence context, and on every site with no recurring
	 * events, since `current_occurrence()` short-circuits on the autoloaded
	 * `gatherpress_has_recurring_events` option.
	 *
	 * The term is keyed on the occurrence's own `series_post_id`, not on the
	 * post the request named. See `Rsvp_Occurrence::current_occurrence()`.
	 *
	 * @since 0.36.0
	 *
	 * @param int $comment_id The RSVP comment that was just created.
	 * @param int $post_id    The event post ID.
	 *
	 * @return void
	 */
	private function assign_occurrence( int $comment_id, int $post_id ): void {
		$occurrence = Rsvp_Occurrence::current_occurrence( $post_id );

		if ( null === $occurrence ) {
			return;
		}

		Rsvp_Occurrence::get_instance()->assign(
			$comment_id,
			$occurrence['series_post_id'],
			$occurrence['recurrence_id']
		);
	}

	/**
	 * Build the clause narrowing the duplicate check to one occurrence.
	 *
	 * Returns null outside occurrence context. It also returns null on every
	 * site with no recurring events at all, since `current_occurrence()`
	 * short-circuits on the `gatherpress_has_recurring_events` option. The query
	 * string is then byte-identical to the one this method's caller has always
	 * built.
	 *
	 * The slug is keyed on the occurrence's own `series_post_id` so the check
	 * matches the term `assign_occurrence()` writes.
	 *
	 * The occurrence link is the `_gatherpress_occurrence` term on the comment,
	 * so the narrowing is a term-relationship subquery rather than a new
	 * column, a meta key, or a table.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return array{clause: string, values: array<int, string>}|null The clause and its
	 *               prepare values, or null when the request is not scoped to an occurrence.
	 */
	private function duplicate_occurrence_clause( int $post_id ): ?array {
		global $wpdb;

		$occurrence = Rsvp_Occurrence::current_occurrence( $post_id );

		if ( null === $occurrence ) {
			return null;
		}

		return array(
			'clause' => " AND comment_ID IN (
				SELECT tr.object_id FROM {$wpdb->term_relationships} AS tr
				INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->terms} AS t ON t.term_id = tt.term_id
				WHERE tt.taxonomy = %s AND t.slug = %s )",
			'values' => array(
				Rsvp_Occurrence::TAXONOMY,
				Rsvp_Occurrence::term_slug( $occurrence['series_post_id'], $occurrence['recurrence_id'] ),
			),
		);
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
		$rsvp_form_blocks = Rsvp_Form::get_instance();

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

		$comment_id      = (int) $comment_id_result;
		$comment_post_id = intval( $data['post_id'] );

		// Set RSVP status to attending.
		wp_set_object_terms( $comment_id, Status::ATTENDING->value, Status::TAXONOMY );

		// Bind the response to the occurrence the request is scoped to. This
		// path inserts its comment directly rather than through
		// `Rsvp\Storage::save()`, so it carries its own stamping.
		$this->assign_occurrence( $comment_id, $comment_post_id );

		// Process all fields.
		$this->process_fields( $comment_id, $data );

		// Drop the warm counts this insertion just invalidated. This path never
		// reaches `Rsvp\Storage::save()`, which is where every other write does
		// its invalidation, so an open-form submission used to leave the cached
		// totals reading zero until the transient expired. That lasts for
		// `Cache::CACHE_EXPIRATION` and is shared across every visitor under a
		// persistent object cache. Called after `assign_occurrence()` so the
		// occurrence-scoped key is resolvable and gets dropped alongside the
		// series-wide one.
		Cache::delete( $comment_post_id );

		// Generate and send confirmation email.
		$rsvp_token = new Token( $comment_id );
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
