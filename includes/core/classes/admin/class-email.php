<?php
/**
 * Email page for GatherPress.
 *
 * This class handles the Email page in the Events menu, which lets an
 * administrator send a message to every member of the site who has opted in
 * to GatherPress emails, without going through a single event.
 *
 * @package GatherPress\Core\Admin
 * @since TBD
 */

namespace GatherPress\Core\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Assets;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Query as Event_Query;
use GatherPress\Core\Mailer;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User_Query;

/**
 * Class Email.
 *
 * Handles the Email page in the Events menu.
 *
 * @since TBD
 *
 * @phpstan-import-type Recipient from Mailer
 * @phpstan-type RecipientBatch array{recipients: array<int, Recipient>, complete: bool, fetched: int, last_id: int}
 * @phpstan-type Audience array{type: string, roles?: list<string>, months?: int, event_id?: int}
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
	 * Who a message can be sent to: every member, members with one of the
	 * chosen roles, members who RSVP'd recently, or members who have not
	 * RSVP'd to a chosen event.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	const AUDIENCES = array( 'all', 'role', 'active', 'no_rsvp' );

	/**
	 * How far back, in months, an active member's last RSVP can be.
	 *
	 * @since TBD
	 *
	 * @var int[]
	 */
	const ACTIVE_MONTHS = array( 1, 3, 6, 12 );

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
		add_action( 'gatherpress_site_message_send', array( $this, 'process_message' ), 10, 4 );

		add_action( 'added_user_meta', array( $this, 'maybe_clear_count_cache' ), 10, 3 );
		add_action( 'updated_user_meta', array( $this, 'maybe_clear_count_cache' ), 10, 3 );
		add_action( 'deleted_user_meta', array( $this, 'maybe_clear_count_cache' ), 10, 3 );

		// Membership changes move the count too, so the Email page does not
		// keep showing a stale number until the transient expires.
		add_action( 'user_register', array( $this, 'clear_count_cache' ) );
		add_action( 'deleted_user', array( $this, 'clear_count_cache' ) );
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
		$event_query = Event_Query::get_instance();

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/email.php', GATHERPRESS_CORE_PATH ),
			array(
				'recipient_count' => $this->get_cached_recipient_count(),
				'roles'           => array_map( 'translate_user_role', wp_roles()->get_names() ),
				'active_months'   => self::ACTIVE_MONTHS,
				'upcoming_events' => $this->get_event_choices( $event_query->get_upcoming_events( 50 )->posts ),
				'past_events'     => $this->get_event_choices( $event_query->get_past_events( 20 )->posts ),
			),
			true
		);
	}

	/**
	 * Label events for the "didn't RSVP" audience picker.
	 *
	 * @since TBD
	 *
	 * @param array<int, int|WP_Post> $events Events, as post IDs or posts.
	 *
	 * @return array<int, string> Event labels, keyed by post ID.
	 */
	private function get_event_choices( array $events ): array {
		$choices = array();

		foreach ( $events as $event ) {
			$event_id = (int) get_post_field( 'ID', $event );

			$choices[ $event_id ] = sprintf(
				/* translators: 1: Event title, 2: Event start date. */
				__( '%1$s (%2$s)', 'gatherpress' ),
				get_the_title( $event_id ),
				( new Event( $event_id ) )->get_datetime_start( get_option( 'date_format' ) )
			);
		}

		return $choices;
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
	 * Clear the cached recipient count when a member changes their opt-in.
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
	 * Counting walks every member on the site, so the Email page reads a
	 * cached count instead of paying that cost on each load.
	 *
	 * @since TBD
	 *
	 * @return int Number of eligible members.
	 */
	protected function get_cached_recipient_count(): int {
		$count = get_transient( self::COUNT_CACHE_PREFIX . get_current_blog_id() );

		if ( false === $count ) {
			$count = $this->count_recipients();
			set_transient( self::COUNT_CACHE_PREFIX . get_current_blog_id(), $count, self::COUNT_CACHE_TTL );
		}

		return (int) $count;
	}

	/**
	 * Get one page of members a site-wide message would go out to.
	 *
	 * @since TBD
	 *
	 * @param int   $last_id  Last user ID processed before this batch.
	 * @param array $audience Who the message goes to, from `resolve_audience()`.
	 * @phpstan-param Audience $audience
	 *
	 * @return array{recipients: array<int, Recipient>, complete: bool, fetched: int, last_id: int} Recipient batch.
	 */
	protected function get_recipient_batch( int $last_id = 0, array $audience = array( 'type' => 'all' ) ): array {
		$batch_size   = $this->get_batch_size();
		$after_cursor = static function ( WP_User_Query $query ) use ( $last_id ): WP_User_Query {
			global $wpdb;

			$query->query_where .= $wpdb->prepare(
				" AND {$wpdb->users}.ID > %d",
				$last_id
			);

			return $query;
		};
		add_filter( 'pre_user_query', $after_cursor );
		$users = get_users(
			array_merge(
				array(
					'number'  => $batch_size,
					'fields'  => array( 'ID', 'user_email', 'display_name' ),
					'orderby' => 'ID',
					'order'   => 'ASC',
				),
				$this->get_audience_query_args( $audience )
			)
		);
		remove_filter( 'pre_user_query', $after_cursor );
		update_meta_cache(
			'user',
			array_map(
				static function ( $user ): int {
					return (int) $user->ID;
				},
				$users
			)
		);
		$mailer     = Mailer::get_instance();
		$recipients = array();

		foreach ( $users as $user ) {
			$recipient = array(
				'is_user'    => true,
				'user_id'    => (int) $user->ID,
				'comment_id' => 0,
				'email'      => $user->user_email,
				'name'       => $user->display_name,
			);

			if ( $mailer->has_consent( $recipient, 'site_message' ) ) {
				$recipients[] = $recipient;
			}
		}

		/**
		 * Filters the recipients of a site-wide member message batch.
		 *
		 * Lets a site narrow or extend the audience for the current page of
		 * opted-in members.
		 *
		 * @since TBD
		 *
		 * @param array<int, Recipient> $recipients Recipient rows to email.
		 * @param int                  $last_id    Last user ID processed before this batch.
		 * @param array                $audience   Who the message goes to: a `type` of `all`, `role`
		 *                                         (with `roles`), `active` (with `months`) or
		 *                                         `no_rsvp` (with `event_id`).
		 */
		$recipients = apply_filters( 'gatherpress_site_message_recipients', $recipients, $last_id, $audience );

		$last_user_id = empty( $users ) ? $last_id : (int) $users[ array_key_last( $users ) ]->ID;

		return array(
			'recipients' => $recipients,
			'complete'   => count( $users ) < $batch_size,
			'fetched'    => count( $users ),
			'last_id'    => $last_user_id,
		);
	}

	/**
	 * Get the user query arguments that narrow members down to an audience.
	 *
	 * @since TBD
	 *
	 * @param array $audience Who the message goes to, from `resolve_audience()`.
	 * @phpstan-param Audience $audience
	 *
	 * @return array<string, mixed> Arguments for `get_users()`.
	 */
	private function get_audience_query_args( array $audience ): array {
		return match ( $audience['type'] ) {
			'role'    => array( 'role__in' => $audience['roles'] ?? array() ),
			// An empty include list would match every member. The union only
			// adds an ID no member has when the list is empty.
			'active'  => array( 'include' => $this->get_active_member_ids( $audience['months'] ?? 0 ) + array( 0 ) ),
			'no_rsvp' => array( 'exclude' => $this->get_event_responder_ids( $audience['event_id'] ?? 0 ) ),
			default   => array(),
		};
	}

	/**
	 * Get the members who RSVP'd to any event in the past few months.
	 *
	 * @since TBD
	 *
	 * @param int $months How many months back to look.
	 *
	 * @return int[] User IDs.
	 */
	private function get_active_member_ids( int $months ): array {
		return $this->get_rsvp_user_ids(
			array(
				'status'     => 'approve',
				'date_query' => array(
					array(
						'after'  => sprintf( '%d months ago', $months ),
						'column' => 'comment_date_gmt',
					),
				),
			)
		);
	}

	/**
	 * Get the members who have RSVP'd to an event, whatever their response.
	 *
	 * @since TBD
	 *
	 * @param int $event_id Event post ID.
	 *
	 * @return int[] User IDs.
	 */
	private function get_event_responder_ids( int $event_id ): array {
		return $this->get_rsvp_user_ids(
			array(
				'post_id' => $event_id,
				'status'  => 'all',
			)
		);
	}

	/**
	 * Get the IDs of the members behind a set of RSVPs.
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
	 * Get the number of members currently eligible for a site-wide message.
	 *
	 * Walks the full member list, so callers that run on every page load
	 * should use the cached variant instead.
	 *
	 * @since TBD
	 *
	 * @param array $audience Who the message goes to, from `resolve_audience()`.
	 * @phpstan-param Audience $audience
	 *
	 * @return int Number of eligible members.
	 */
	public function count_recipients( array $audience = array( 'type' => 'all' ) ): int {
		$count   = 0;
		$last_id = 0;

		do {
			$batch   = $this->get_recipient_batch( $last_id, $audience );
			$count  += count( $batch['recipients'] );
			$last_id = $batch['last_id'];
		} while ( ! $batch['complete'] );

		return $count;
	}

	/**
	 * Get the configured number of members processed by one cron job.
	 *
	 * @since TBD
	 *
	 * @return int Number of users in one batch.
	 */
	protected function get_batch_size(): int {
		/**
		 * Filters the number of users processed by one site-message cron job.
		 *
		 * @since TBD
		 *
		 * @param int $batch_size Number of users in one batch. Default 50.
		 */
		return max( 1, (int) apply_filters( 'gatherpress_site_message_batch_size', 50 ) );
	}

	/**
	 * Register the REST routes that count and queue a site-wide member message.
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
			'months'   => array(
				'type'    => 'integer',
				'enum'    => self::ACTIVE_MONTHS,
				'default' => self::ACTIVE_MONTHS[1],
			),
			'event_id' => array(
				'type'    => 'integer',
				'default' => 0,
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
			$audience['months'] = (int) $request->get_param( 'months' );
		} elseif ( 'no_rsvp' === $audience['type'] ) {
			$audience['event_id'] = (int) $request->get_param( 'event_id' );
			$error                = post_type_supports( (string) get_post_type( $audience['event_id'] ), Rsvp::SUPPORT )
				? ''
				: __( 'Choose an event.', 'gatherpress' );
		}

		return '' === $error
			? $audience
			: new WP_Error( 'gatherpress_site_message_audience', $error, array( 'status' => 400 ) );
	}

	/**
	 * Count the members a site-wide message would go to, from a REST request.
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

		$count = $this->count_recipients( $audience );

		return new WP_REST_Response(
			array(
				'count'   => $count,
				'message' => sprintf(
					/* translators: %d: Number of members the message will be sent to. */
					_n( 'This will email %d member.', 'This will email %d members.', $count, 'gatherpress' ),
					$count
				),
			)
		);
	}

	/**
	 * Queue a site-wide member message from a REST request.
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

		// Count fresh at send time rather than trusting the count the Email
		// page showed, so the queued count reflects who is eligible now.
		$recipient_count = $this->count_recipients( $audience );
		$error           = null;

		if ( 0 === $recipient_count ) {
			$error = new WP_Error(
				'gatherpress_site_message_no_recipients',
				__( 'There are no members to send this message to.', 'gatherpress' ),
				array( 'status' => 400 )
			);
		} elseif ( ! $this->schedule_message_batch( $subject, $message, 0, $audience ) ) {
			$error = new WP_Error(
				'gatherpress_site_message_not_queued',
				__( 'The message could not be queued. Please try again.', 'gatherpress' ),
				array( 'status' => 500 )
			);
		}

		return $error ?? new WP_REST_Response(
			array(
				'message' => sprintf(
					/* translators: %d: Number of members the message was queued for. */
					_n(
						'Queued for %d member.',
						'Queued for %d members.',
						$recipient_count,
						'gatherpress'
					),
					$recipient_count
				),
			)
		);
	}

	/**
	 * Queue the cron job for one batch of a site-wide member message.
	 *
	 * The batches are chained rather than sent in one request: each job sends a
	 * bounded page of recipients and queues the next cursor. A fatal error part
	 * way through a page therefore leaves the next page scheduled, so the
	 * remaining members still receive the message.
	 *
	 * @since TBD
	 *
	 * @param string $subject  Message subject.
	 * @param string $message  Message body.
	 * @param int    $last_id  Last user ID processed before this batch.
	 * @param array  $audience Who the message goes to, from `resolve_audience()`.
	 * @phpstan-param Audience $audience
	 *
	 * @return bool True when the batch is queued, false when it is not.
	 */
	protected function schedule_message_batch(
		string $subject,
		string $message,
		int $last_id,
		array $audience = array( 'type' => 'all' )
	): bool {
		// A short delay keeps each batch from running in the same cron pass as
		// the batch that queued it.
		return Mailer::get_instance()->schedule(
			'gatherpress_site_message_send',
			array( $subject, $message, $last_id, $audience ),
			1
		);
	}

	/**
	 * Deliver one queued batch of a site-wide message.
	 *
	 * Runs from cron so a large membership does not hold up the admin request
	 * that queued it, one bounded page per job, with the next page queued
	 * before this one is sent. Recipients are resolved again here, so anyone
	 * who opted out between queuing and delivery is skipped.
	 *
	 * @since TBD
	 *
	 * @param string $subject Optional subject line. Empty falls back to a
	 *                        translatable default built in the recipient's locale.
	 * @param string $message  Message body.
	 * @param int    $last_id  Last user ID processed before this batch.
	 * @param array  $audience Who the message goes to, from `resolve_audience()`.
	 * @phpstan-param Audience $audience
	 *
	 * @return void
	 */
	public function process_message(
		string $subject,
		string $message,
		int $last_id = 0,
		array $audience = array( 'type' => 'all' )
	): void {
		$batch = $this->get_recipient_batch( $last_id, $audience );

		// Queue the next page before sending this one, so an error part way
		// through the batch cannot strand recipients after this cursor. This
		// runs even when the filtered page is empty, since a filter can narrow
		// one page without ending the chain.
		if ( ! $batch['complete'] ) {
			$this->schedule_message_batch( $subject, $message, $batch['last_id'], $audience );
		} else {
			// The last page is done, so the cached count the Email page
			// showed is stale. Drop it so the next page load recounts.
			$this->clear_count_cache();
		}

		$template = sprintf( '%s/includes/templates/admin/emails/site-email.php', GATHERPRESS_CORE_PATH );

		// Build the email inside the callback so the default subject is
		// translated, and the template rendered, in the recipient's context.
		$compose = fn(): array => array(
			'subject' => $this->resolve_subject( $subject ),
			'body'    => Utility::render_template( $template, array( 'message' => $message ) ),
			'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
		);

		$mailer = Mailer::get_instance();

		foreach ( $batch['recipients'] as $recipient ) {
			// Mailer re-checks consent at delivery time, so a member who opted
			// out after queuing is skipped here.
			$mailer->send( $recipient, $compose, 'site_message' );
		}
	}

	/**
	 * Resolve the subject line for a site-wide member message.
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
		 * Filters the subject line of a site-wide member message.
		 *
		 * @since TBD
		 *
		 * @param string $subject Email subject line.
		 */
		return apply_filters( 'gatherpress_site_message_subject', $subject );
	}
}
