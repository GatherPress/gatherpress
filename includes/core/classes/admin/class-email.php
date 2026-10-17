<?php
/**
 * Email page for GatherPress.
 *
 * This class handles the Email page in the Events menu, which lets an
 * administrator send a message to every user of the site who has opted in
 * to GatherPress emails, without going through a single event.
 *
 * @package GatherPress\Core\Admin
 * @since TBD
 */

namespace GatherPress\Core\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Assets;
use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Email.
 *
 * Handles the Email page in the Events menu.
 *
 * @since TBD
 *
 * @phpstan-import-type Recipient from Mailer
 * @phpstan-import-type Payload from Mailer
 * @phpstan-type Audience array{type: string, roles?: list<string>, days?: int}
 */
final class Email {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The Email page slug.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'gatherpress_email';

	/**
	 * The Mailer type that sends the Email page's messages.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const MAIL_TYPE = 'site_message';

	/**
	 * Who a message can be sent to: every user, users with one of the
	 * chosen roles, or users who RSVP'd recently.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	const AUDIENCES = array( 'all', 'role', 'active' );

	/**
	 * How far back, in days, an active user's last RSVP can be, unless filtered.
	 *
	 * @since TBD
	 *
	 * @var int[]
	 */
	const ACTIVE_DAYS = array( 30, 90, 180, 365 );

	/**
	 * The active window picked by default, when it is offered.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const DEFAULT_ACTIVE_DAYS = 90;

	/**
	 * Transient key prefix for the cached eligible recipient count.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	private const COUNT_CACHE_PREFIX = 'gatherpress_site_message_count_';

	/**
	 * How long the cached eligible recipient count stays valid.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	private const COUNT_CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
		$this->register_mail_type();
	}

	/**
	 * Tell Mailer who the Email page's messages go to and what they say.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	private function register_mail_type(): void {
		Mailer::get_instance()->register(
			self::MAIL_TYPE,
			array(
				'context' => 'site_message',
				'users'   => $this->get_audience_query_args( ... ),
				'compose' => $this->compose_message( ... ),
			)
		);
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu_page' ) );
		add_filter( 'submenu_file', array( $this, 'select_submenu' ) );
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );

		add_action( 'added_user_meta', array( $this, 'maybe_clear_count_cache' ), 10, 3 );
		add_action( 'updated_user_meta', array( $this, 'maybe_clear_count_cache' ), 10, 3 );
		add_action( 'deleted_user_meta', array( $this, 'maybe_clear_count_cache' ), 10, 3 );

		// Users joining or leaving move the count too, so the Email page does not
		// keep showing a stale number until the transient expires.
		add_action( 'user_register', array( $this, 'clear_count_cache' ) );
		add_action( 'deleted_user', array( $this, 'clear_count_cache' ) );
		add_action( 'add_user_to_blog', array( $this, 'clear_count_cache' ) );
		add_action( 'remove_user_from_blog', array( $this, 'clear_count_cache' ) );
	}

	/**
	 * Add the Email page to the Events menu, between Venues and Settings.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function add_submenu_page(): void {
		$hook = add_submenu_page(
			Settings::PARENT_SLUG,
			__( 'Email', 'gatherpress' ),
			__( 'Email', 'gatherpress' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			6
		);

		if ( false !== $hook ) {
			add_action( sprintf( 'load-%s', $hook ), array( $this, 'enqueue_assets' ) );
		}
	}

	/**
	 * Highlight the Email item in the Events menu while the Email page is open.
	 *
	 * @since TBD
	 *
	 * @param string|null $submenu_file The submenu item to highlight.
	 *
	 * @return string|null The Email page slug on the Email page, otherwise unchanged.
	 */
	public function select_submenu( ?string $submenu_file ): ?string {
		global $plugin_page;

		return self::PAGE_SLUG === $plugin_page ? self::PAGE_SLUG : $submenu_file;
	}

	/**
	 * Render the Email page.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function render_page(): void {
		Utility::render_template(
			sprintf( '%s/includes/templates/admin/email.php', GATHERPRESS_CORE_PATH ),
			array(
				'recipient_count' => $this->get_cached_recipient_count(),
				'audiences'       => $this->get_audience_choices(),
				'roles'           => array_map( 'translate_user_role', wp_roles()->get_names() ),
				'active_days'     => $this->get_active_days(),
				'default_days'    => $this->get_default_active_days(),
			),
			true
		);
	}

	/**
	 * Get the audiences the site can send to, with their labels.
	 *
	 * Active users are picked by their RSVPs, so they are only offered
	 * while RSVPs are turned on.
	 *
	 * @since TBD
	 *
	 * @return array<string, string> Audience labels, keyed by audience.
	 */
	private function get_audience_choices(): array {
		$choices = array(
			'all'    => __( 'All users', 'gatherpress' ),
			'role'   => __( 'Users with a role', 'gatherpress' ),
			'active' => __( 'Active users', 'gatherpress' ),
		);

		if ( ! $this->is_rsvp_enabled() ) {
			unset( $choices['active'] );
		}

		return $choices;
	}

	/**
	 * Get the choices for how far back an active user's last RSVP can be.
	 *
	 * @since TBD
	 *
	 * @return int[] Numbers of days to offer.
	 */
	private function get_active_days(): array {
		/**
		 * Filters the choices for how far back an active user's last RSVP can be.
		 *
		 * Lets a site offer windows that suit how often it holds events, for
		 * example 7 days for a weekly meetup.
		 *
		 * @since TBD
		 *
		 * @param int[] $days Numbers of days to offer. Default 30, 90, 180 and 365.
		 *
		 * @return int[] Numbers of days to offer.
		 */
		$days = (array) apply_filters( 'gatherpress_site_message_active_days', self::ACTIVE_DAYS );
		$days = array_filter( array_map( 'intval', $days ), static fn( int $day ): bool => $day > 0 );
		$days = array_values( array_unique( $days ) );

		return empty( $days ) ? self::ACTIVE_DAYS : $days;
	}

	/**
	 * Get the active window picked by default.
	 *
	 * @since TBD
	 *
	 * @return int 90 days when offered, otherwise the first choice.
	 */
	private function get_default_active_days(): int {
		$days = $this->get_active_days();

		return in_array( self::DEFAULT_ACTIVE_DAYS, $days, true ) ? self::DEFAULT_ACTIVE_DAYS : $days[0];
	}

	/**
	 * Check whether RSVPs are turned on for the site.
	 *
	 * @since TBD
	 *
	 * @return bool True unless the RSVP mode setting is disabled.
	 */
	private function is_rsvp_enabled(): bool {
		return 'disabled' !== Settings::get_instance()->get( 'rsvp_mode' );
	}

	/**
	 * Load the Email page's script and the utility styles it relies on.
	 *
	 * Runs on the page's own `load-` action, so nothing loads on other screens.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$assets  = Assets::get_instance();
		$script  = $assets->get_asset_data( 'email' );
		$utility = $assets->get_asset_data( 'utility_style' );

		wp_enqueue_script(
			'gatherpress-email',
			GATHERPRESS_CORE_URL . 'build/email.js',
			$script['dependencies'],
			$script['version'],
			true
		);

		wp_set_script_translations( 'gatherpress-email', 'gatherpress' );

		wp_enqueue_style(
			'gatherpress-utility-style',
			GATHERPRESS_CORE_URL . 'build/utility_style.css',
			$utility['dependencies'],
			$utility['version']
		);
	}

	/**
	 * Clear the cached recipient count.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function clear_count_cache(): void {
		delete_transient( self::COUNT_CACHE_PREFIX . get_current_blog_id() );
	}

	/**
	 * Clear the cached recipient count when a user changes their opt-in.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param int|int[] $meta_id  ID of the meta row, or the list of IDs for a delete.
	 * @param int       $user_id  User the meta belongs to.
	 * @param string    $meta_key Meta key being written.
	 *
	 * @return void
	 */
	public function maybe_clear_count_cache( $meta_id, int $user_id, string $meta_key ): void {
		// $meta_id and $user_id are required by the user meta action signature.
		if ( 'gatherpress_event_updates_opt_in' === $meta_key ) {
			$this->clear_count_cache();
		}
	}

	/**
	 * Get the eligible recipient count from the cache, filling it when empty.
	 *
	 * Counting walks every user on the site, so the Email page reads a
	 * cached count instead of paying that cost on each load.
	 *
	 * @since TBD
	 *
	 * @return int Number of eligible users.
	 */
	protected function get_cached_recipient_count(): int {
		$count = get_transient( self::COUNT_CACHE_PREFIX . get_current_blog_id() );

		if ( false === $count ) {
			$count = Mailer::get_instance()->count( self::MAIL_TYPE, array( 'audience' => array( 'type' => 'all' ) ) );
			set_transient( self::COUNT_CACHE_PREFIX . get_current_blog_id(), $count, self::COUNT_CACHE_TTL );
		}

		return (int) $count;
	}

	/**
	 * Get the user query arguments for the audience a message was queued for.
	 *
	 * @since TBD
	 *
	 * @param array $payload The queued message, with its `audience` from `resolve_audience()`.
	 * @phpstan-param Payload $payload
	 *
	 * @return array<string, mixed> Arguments for `get_users()`.
	 */
	private function get_audience_query_args( array $payload ): array {
		$audience = $payload['audience'] ?? array( 'type' => 'all' );

		return match ( $audience['type'] ) {
			'role'    => array( 'role__in' => $audience['roles'] ?? array() ),
			// An empty include list would match every user. The union only
			// adds an ID no user has when the list is empty.
			'active'  => array( 'include' => $this->get_active_user_ids( $audience['days'] ?? 0 ) + array( 0 ) ),
			default   => array(),
		};
	}

	/**
	 * Get the users who RSVP'd to any event in the past few days.
	 *
	 * @since TBD
	 *
	 * @param int $days How many days back to look.
	 *
	 * @return int[] User IDs.
	 */
	private function get_active_user_ids( int $days ): array {
		return $this->get_rsvp_user_ids(
			array(
				'status'     => 'approve',
				'date_query' => array(
					array(
						'after'  => sprintf( '%d days ago', $days ),
						'column' => 'comment_date_gmt',
					),
				),
			)
		);
	}

	/**
	 * Get the IDs of the users behind a set of RSVPs.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $args Arguments for `Rsvp\Query::get_rsvps()`.
	 *
	 * @return int[] User IDs, without RSVPs from people without an account.
	 */
	private function get_rsvp_user_ids( array $args ): array {
		$rsvps = Rsvp_Query::get_instance()->get_rsvps(
			array_merge(
				$args,
				array(
					'author__not_in'            => array( 0 ),
					'update_comment_meta_cache' => false,
				)
			)
		);

		return array_values( array_unique( array_map( 'intval', wp_list_pluck( $rsvps, 'user_id' ) ) ) );
	}

	/**
	 * Register the REST routes that count and queue a site-wide message.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_endpoints(): void {
		$permission = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/site-message',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'queue_message' ),
				'permission_callback' => $permission,
				'args'                => array_merge(
					array(
						'subject' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'message' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
					$this->get_audience_args()
				),
			)
		);

		register_rest_route(
			GATHERPRESS_REST_NAMESPACE,
			'/site-message/count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'count_message_recipients' ),
				'permission_callback' => $permission,
				'args'                => $this->get_audience_args(),
			)
		);
	}

	/**
	 * Get the REST arguments that pick a message's audience.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<string, mixed>> Argument definitions.
	 */
	private function get_audience_args(): array {
		return array(
			'audience' => array(
				'type'    => 'string',
				'enum'    => self::AUDIENCES,
				'default' => 'all',
			),
			'roles'    => array(
				'type'    => 'array',
				'items'   => array( 'type' => 'string' ),
				'default' => array(),
			),
			'days'     => array(
				'type'    => 'integer',
				'enum'    => $this->get_active_days(),
				'default' => $this->get_default_active_days(),
			),
		);
	}

	/**
	 * Read the audience a REST request picked, and check it is complete.
	 *
	 * @since TBD
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array|WP_Error The audience, or why it cannot be used.
	 * @phpstan-return Audience|WP_Error
	 */
	private function resolve_audience( WP_REST_Request $request ): array|WP_Error {
		$type     = (string) $request->get_param( 'audience' );
		$audience = array( 'type' => in_array( $type, self::AUDIENCES, true ) ? $type : 'all' );
		$error    = '';

		if ( 'role' === $audience['type'] ) {
			// Only roles the site has, so a stale or made-up role cannot widen the query.
			$audience['roles'] = array_values(
				array_intersect( (array) $request->get_param( 'roles' ), array_keys( wp_roles()->get_names() ) )
			);
			$error             = empty( $audience['roles'] ) ? __( 'Choose at least one role.', 'gatherpress' ) : '';
		} elseif ( 'active' === $audience['type'] ) {
			$audience['days'] = (int) $request->get_param( 'days' );
			$error            = $this->is_rsvp_enabled()
				? ''
				: __( 'RSVPs are turned off, so users cannot be picked by their RSVPs.', 'gatherpress' );
		}

		return '' === $error
			? $audience
			: new WP_Error( 'gatherpress_site_message_audience', $error, array( 'status' => 400 ) );
	}

	/**
	 * Count the users a site-wide message would go to, from a REST request.
	 *
	 * @since TBD
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response|WP_Error The count, or why the audience cannot be used.
	 */
	public function count_message_recipients( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$audience = $this->resolve_audience( $request );

		if ( is_wp_error( $audience ) ) {
			return $audience;
		}

		$count = Mailer::get_instance()->count( self::MAIL_TYPE, array( 'audience' => $audience ) );

		return new WP_REST_Response(
			array(
				'count'   => $count,
				'message' => sprintf(
					/* translators: %d: Number of users the message will be sent to. */
					_n( 'This will email %d user.', 'This will email %d users.', $count, 'gatherpress' ),
					$count
				),
			)
		);
	}

	/**
	 * Queue a site-wide message from a REST request.
	 *
	 * Schedules the message for delivery on the next cron pass, so the request
	 * returns without waiting on the mail server.
	 *
	 * @since TBD
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return WP_REST_Response|WP_Error The queued confirmation, or why the message was not queued.
	 */
	public function queue_message( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$subject  = (string) $request->get_param( 'subject' );
		$message  = (string) $request->get_param( 'message' );
		$audience = $this->resolve_audience( $request );

		if ( '' === trim( $message ) ) {
			return new WP_Error(
				'gatherpress_site_message_empty',
				__( 'Please write a message before sending.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		}

		if ( is_wp_error( $audience ) ) {
			return $audience;
		}

		$payload = array(
			'subject'  => $subject,
			'message'  => $message,
			'audience' => $audience,
		);

		// Count fresh at send time rather than trusting the count the Email
		// page showed, so the queued count reflects who is eligible now.
		$recipient_count = Mailer::get_instance()->count( self::MAIL_TYPE, $payload );
		$error           = null;

		if ( 0 === $recipient_count ) {
			$error = new WP_Error(
				'gatherpress_site_message_no_recipients',
				__( 'There are no users to send this message to.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		} elseif ( ! Mailer::get_instance()->queue( self::MAIL_TYPE, $payload ) ) {
			$error = new WP_Error(
				'gatherpress_site_message_not_queued',
				__( 'The message could not be queued. Please try again.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		return $error ?? new WP_REST_Response(
			array(
				'message' => sprintf(
					/* translators: %d: Number of users the message was queued for. */
					_n(
						'Queued for %d user.',
						'Queued for %d users.',
						$recipient_count,
						'gatherpress'
					),
					$recipient_count
				),
			)
		);
	}

	/**
	 * Build a queued message for one recipient.
	 *
	 * Mailer calls this while the recipient's context is active, so the
	 * default subject is translated, and the template rendered, in their
	 * language.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param array $recipient The recipient, required by Mailer's compose signature.
	 * @param array $payload   The queued message: its `subject` and `message`.
	 * @phpstan-param Recipient $recipient
	 * @phpstan-param Payload $payload
	 *
	 * @return array{subject: string, body: string, headers: array<int, string>} The email.
	 */
	private function compose_message( array $recipient, array $payload ): array {
		return array(
			'subject' => $this->resolve_subject( (string) ( $payload['subject'] ?? '' ) ),
			'body'    => Utility::render_template(
				sprintf( '%s/includes/templates/admin/emails/site-email.php', GATHERPRESS_CORE_PATH ),
				array( 'message' => (string) ( $payload['message'] ?? '' ) )
			),
			'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
		);
	}

	/**
	 * Resolve the subject line for a site-wide message.
	 *
	 * The default is built here rather than up front so it is translated in the
	 * recipient's locale, matching how the event update email builds its own
	 * default subject.
	 *
	 * @since TBD
	 *
	 * @param string $subject Optional subject line supplied by the sender.
	 *
	 * @return string The subject line to send.
	 */
	protected function resolve_subject( string $subject ): string {
		if ( '' === trim( $subject ) ) {
			$subject = sprintf(
				/* translators: %s: Site name. */
				__( 'Message from %s', 'gatherpress' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			);
		}

		/**
		 * Filters the subject line of a site-wide message.
		 *
		 * Lets a site brand the subject, for example with a prefix, including
		 * the "Message from" default used when the sender leaves it blank.
		 *
		 * @since TBD
		 *
		 * @param string $subject Email subject line.
		 *
		 * @return string Email subject line.
		 */
		return apply_filters( 'gatherpress_site_message_subject', $subject );
	}
}
