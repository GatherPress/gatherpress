<?php
/**
 * Shared email delivery for GatherPress.
 *
 * This file contains the Mailer class, which owns everything about sending
 * GatherPress emails: queuing and batching the jobs, opt-in eligibility,
 * recipient context switching, and the wp_mail call itself.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_User;
use WP_User_Query;

/**
 * Class Mailer.
 *
 * Owns how GatherPress emails are queued, batched and delivered. A caller
 * registers a mail type that says who an email goes to and what it says,
 * then queues it with plain data. Mailer runs the jobs: one batch of
 * recipients per job, each job queuing the next before it sends.
 *
 * @since TBD
 *
 * @phpstan-type Recipient array{is_user: bool, user_id: int, comment_id: int, email: string, name: string}
 * @phpstan-type ComposedEmail array{subject: string, body: string, headers?: array<int, string>}
 * @phpstan-type Payload array<string, mixed>
 * @phpstan-type MailType array{
 *     context: string,
 *     compose: callable(Recipient, Payload): ComposedEmail,
 *     users?: callable(Payload): (array<string, mixed>|null),
 *     recipients?: callable(Payload): array<int, Recipient>
 * }
 * @phpstan-type Cursor array{source: string, last_id: int}
 * @phpstan-type Batch array{recipients: array<int, Recipient>, next: Cursor|null}
 */
final class Mailer {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Action hook that runs one batch of a queued mail job.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const JOB_HOOK = 'gatherpress_email_send';

	/**
	 * Consent context for emails about an event, which follow the opt-in.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const CONTEXT_EVENT = 'event';

	/**
	 * Consent context for site-wide messages, which follow the opt-in.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const CONTEXT_SITE_MESSAGE = 'site_message';

	/**
	 * Consent context for event reminders, which follow the reminder opt-in.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const CONTEXT_REMINDER = 'reminder';

	/**
	 * Consent context for emails about the recipient's own RSVP, which skip the opt-in.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const CONTEXT_RSVP = 'rsvp';

	/**
	 * Where a job starts: the first page of users, then the recipient list.
	 *
	 * Each batch carries the last user ID, or the last RSVP comment ID, it
	 * sent to, and the next batch starts after it.
	 *
	 * @since TBD
	 *
	 * @var array{source: string, last_id: int}
	 */
	const FIRST_BATCH = array(
		'source'  => 'users',
		'last_id' => 0,
	);

	/**
	 * Registered mail types, keyed by name.
	 *
	 * @since TBD
	 *
	 * @var array<string, MailType>
	 */
	private array $types = array();

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
		add_action( self::JOB_HOOK, array( $this, 'process_job' ), 10, 3 );
	}

	/**
	 * Check whether a recipient should receive an email.
	 *
	 * Honors the recipient's opt-in preference: user meta for WordPress users,
	 * RSVP comment meta for non-user recipients. Users have a separate opt-in
	 * for reminders; an RSVP without an account has one opt-in for both.
	 *
	 * @since TBD
	 *
	 * @param array  $recipient Recipient row from a mail path.
	 * @param string $context   Mail path asking for consent.
	 * @phpstan-param Recipient $recipient
	 *
	 * @return bool True when the recipient can be emailed.
	 */
	private function is_eligible( array $recipient, string $context ): bool {
		if ( $recipient['is_user'] ) {
			$user = User::get_instance();

			return self::CONTEXT_REMINDER === $context
				? $user->has_event_reminders_opt_in( $recipient['user_id'] )
				: $user->has_event_updates_opt_in( $recipient['user_id'] );
		}

		return '0' !== get_comment_meta(
			$recipient['comment_id'],
			'gatherpress_event_updates_opt_in',
			true
		);
	}

	/**
	 * Check whether a recipient consents to the kind of email being sent.
	 *
	 * Defaults to the recipient's opt-in, which covers the emails a recipient
	 * can do without. Reminders follow the user's reminder opt-in. Emails
	 * about the recipient's own RSVP, sent with the `rsvp` context, default
	 * to consent instead, since the confirmation link
	 * is what approves an open RSVP. The filter lets a site change the rule for
	 * a specific context, for example a site-wide message that is not about
	 * events, without touching the other mail paths. A recipient without a
	 * valid email address never consents, whatever the filter returns.
	 *
	 * @since TBD
	 *
	 * @param array  $recipient Recipient row from a mail path.
	 * @param string $context   Mail path asking for consent, e.g. `event`, `site_message`, `reminder` or `rsvp`.
	 * @phpstan-param Recipient $recipient
	 *
	 * @return bool True when the recipient consents to this email.
	 */
	private function has_consent( array $recipient, string $context ): bool {
		// A missing or malformed address is not a consent question, so no filter can override it.
		if ( ! is_email( $recipient['email'] ) ) {
			return false;
		}

		/**
		 * Filters whether a recipient consents to a GatherPress email.
		 *
		 * Lets a site set its own consent rule per kind of email, for example
		 * requiring an explicit opt-in before a site-wide message goes out.
		 *
		 * @since TBD
		 *
		 * @param bool   $consent   Whether the recipient consents. Defaults to the opt-in, or to
		 *                          true for the `rsvp` context.
		 * @param array  $recipient Recipient row from a mail path.
		 * @param string $context   Mail path asking for consent.
		 * @phpstan-param Recipient $recipient
		 *
		 * @return bool Whether the recipient consents.
		 */
		return (bool) apply_filters(
			'gatherpress_email_recipient_consent',
			self::CONTEXT_RSVP === $context || $this->is_eligible( $recipient, $context ),
			$recipient,
			$context
		);
	}

	/**
	 * Switch the request context over to the recipient.
	 *
	 * A WordPress user gets their locale and user context applied, so date and
	 * time formats, the user's timezone, and translations inside the rendered
	 * template match the recipient. Non-user recipients keep the current
	 * context.
	 *
	 * @since TBD
	 *
	 * @param array $recipient Recipient row from a mail path.
	 * @phpstan-param Recipient $recipient
	 *
	 * @return bool Whether the locale was switched, for `restore_context()`.
	 */
	private function switch_context( array $recipient ): bool {
		if ( ! $recipient['is_user'] ) {
			return false;
		}

		$switched_locale = switch_to_user_locale( $recipient['user_id'] );

		// Set the current user to the user being mailed, so the GatherPress
		// filters for date and time format, as well as the user's timezone, are
		// recognized by the functions inside the template render.
		wp_set_current_user( $recipient['user_id'] );

		return $switched_locale;
	}

	/**
	 * Restore the context that was active before a recipient switch.
	 *
	 * @since TBD
	 *
	 * @param bool    $switched_locale Whether `switch_context()` switched the locale.
	 * @param WP_User $sender          User to make current again.
	 *
	 * @return void
	 */
	private function restore_context( bool $switched_locale, WP_User $sender ): void {
		// Reset the current user to the sender of the email.
		wp_set_current_user( $sender->ID );

		// Cleanup branch only fires when `switch_to_user_locale()` actually
		// switched, which requires a non-stub `WP_Locale_Switcher` and is not
		// reachable from the test runner.
		if ( $switched_locale ) { // @codeCoverageIgnore
			restore_previous_locale(); // @codeCoverageIgnore
		}
	}

	/**
	 * Send an email to a single recipient.
	 *
	 * @since TBD
	 *
	 * @param string             $email   Recipient email address.
	 * @param string             $subject Email subject line.
	 * @param string             $body    Rendered email body.
	 * @param array<int, string> $headers Optional wp_mail headers.
	 *
	 * @return bool True when WordPress accepted the email for sending.
	 */
	private function deliver( string $email, string $subject, string $body, array $headers = array() ): bool {
		// Subject lines arrive from translators and filters, so decode entities
		// and strip the slashes WordPress adds on input before handing them off.
		$subject = stripslashes_deep( html_entity_decode( $subject, ENT_QUOTES, 'UTF-8' ) );

		return wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * Send one email to one recipient while their context is active.
	 *
	 * Runs the whole per-recipient sequence in one place: consent check,
	 * context switch, compose, deliver, and context restore. Callers only own
	 * who the recipient is and what the email says, which they return from the
	 * `$compose` callback so the subject and body are built in the recipient's
	 * locale and user context.
	 *
	 * @since TBD
	 *
	 * @param array    $recipient Recipient row from a mail path.
	 * @param callable $compose   Returns `array{subject: string, body: string, headers?: array<int, string>}`
	 *                            built for the recipient.
	 * @param string   $context   Mail path asking for consent, e.g. `event`, `site_message` or `rsvp`.
	 * @phpstan-param Recipient $recipient
	 * @phpstan-param callable(Recipient): ComposedEmail $compose
	 *
	 * @return bool True when the email was delivered, false when consent was withheld.
	 */
	public function send( array $recipient, callable $compose, string $context = self::CONTEXT_EVENT ): bool {
		if ( ! $this->has_consent( $recipient, $context ) ) {
			return false;
		}

		// Cron runs without a logged-in user, so the sender to restore to is
		// whoever is current now: the editor for a request, the anonymous user
		// for a cron job.
		$sender          = wp_get_current_user();
		$switched_locale = $this->switch_context( $recipient );

		// Deliver while still in the recipient's context, so wp_mail filters
		// run with the recipient's locale active. A filter or the compose
		// callback that throws still has to hand the context back, or the next
		// recipient would be mailed in the wrong locale.
		try {
			$email = $compose( $recipient );

			return $this->deliver(
				$recipient['email'],
				$email['subject'],
				$email['body'],
				$email['headers'] ?? array()
			);
		} finally {
			$this->restore_context( $switched_locale, $sender );
		}
	}

	/**
	 * Send one email to each of a list of recipients.
	 *
	 * Runs `send()` for every recipient, so each one gets their own consent
	 * check and context, and `$compose` builds their copy.
	 *
	 * @since TBD
	 *
	 * @param array    $recipients Recipient rows from a mail path.
	 * @param callable $compose    Returns `array{subject: string, body: string, headers?: array<int, string>}`
	 *                             built for the recipient it receives.
	 * @param string   $context    Mail path asking for consent, e.g. `event`, `site_message` or `rsvp`.
	 * @phpstan-param array<int, Recipient> $recipients
	 * @phpstan-param callable(Recipient): ComposedEmail $compose
	 *
	 * @return int How many emails were delivered.
	 */
	public function bulk_send( array $recipients, callable $compose, string $context = self::CONTEXT_EVENT ): int {
		$sent = 0;

		foreach ( $recipients as $recipient ) {
			$sent += (int) $this->send( $recipient, $compose, $context );
		}

		return $sent;
	}

	/**
	 * Register a mail type, so its emails can be queued by name.
	 *
	 * A queued job only holds plain data, so the type is how the job finds who
	 * an email goes to and what it says. Register on every request, since
	 * cron runs in a request of its own.
	 *
	 * @since TBD
	 *
	 * @param string $type       Mail type name, e.g. `event` or `site_message`.
	 * @param array  $definition {
	 *     How the type sends.
	 *
	 *     @type string   $context    Mail path asking for consent, e.g. `event`, `site_message` or `rsvp`.
	 *     @type callable $compose    Receives one recipient and the payload, returns the email for them.
	 *     @type callable $users      Optional. Receives the payload, returns `get_users()` arguments for the
	 *                                users to email, or null for none. Mailer pages through them by ID.
	 *     @type callable $recipients Optional. Receives the payload, returns recipient rows to email after the
	 *                                users, for people a user query cannot reach. Each row needs a unique
	 *                                `comment_id` above 0, which the job pages through the rows by.
	 * }
	 * @phpstan-param MailType $definition
	 *
	 * @return void
	 */
	public function register( string $type, array $definition ): void {
		$this->types[ $type ] = $definition;
	}

	/**
	 * Queue an email of a registered type.
	 *
	 * Only schedules the first batch, so the request that queues it returns
	 * without waiting on recipients or the mail server.
	 *
	 * @since TBD
	 *
	 * @param string $type    A registered mail type.
	 * @param array  $payload Plain data the type needs, stored with the job.
	 * @phpstan-param Payload $payload
	 *
	 * @return bool True when the first batch is queued, false for an unknown type or a failed enqueue.
	 */
	public function queue( string $type, array $payload ): bool {
		return isset( $this->types[ $type ] )
			&& $this->schedule( self::JOB_HOOK, array( $type, $payload, self::FIRST_BATCH ) );
	}

	/**
	 * Count who an email of a registered type would reach.
	 *
	 * Walks every batch and counts the recipients who consent. The type's
	 * users and recipient list are looked up once for the whole walk, rather
	 * than once per batch the way a job looks them up.
	 *
	 * @since TBD
	 *
	 * @param string $type    A registered mail type.
	 * @param array  $payload Plain data the type needs.
	 * @phpstan-param Payload $payload
	 *
	 * @return int Number of recipients the email would go to.
	 */
	public function count( string $type, array $payload ): int {
		if ( ! isset( $this->types[ $type ] ) ) {
			return 0;
		}

		$count      = 0;
		$cursor     = self::FIRST_BATCH;
		$definition = $this->resolve_sources( $this->types[ $type ], $payload );

		while ( null !== $cursor ) {
			$batch  = $this->get_batch( $type, $definition, $payload, $cursor );
			$count += count(
				array_filter(
					$batch['recipients'],
					fn( array $recipient ): bool => $this->has_consent( $recipient, $definition['context'] )
				)
			);
			$cursor = $batch['next'];
		}

		return $count;
	}

	/**
	 * Look a type's users and recipient list up once, for a walk over every batch.
	 *
	 * @since TBD
	 *
	 * @param array $definition A registered mail type.
	 * @param array $payload    Plain data the type needs.
	 * @phpstan-param MailType $definition
	 * @phpstan-param Payload $payload
	 *
	 * @return array The type, with its sources answering from the one lookup.
	 * @phpstan-return MailType
	 */
	private function resolve_sources( array $definition, array $payload ): array {
		if ( isset( $definition['users'] ) ) {
			$users               = $definition['users']( $payload );
			$definition['users'] = static fn(): ?array => $users;
		}

		if ( isset( $definition['recipients'] ) ) {
			$recipients               = $definition['recipients']( $payload );
			$definition['recipients'] = static fn(): array => $recipients;
		}

		return $definition;
	}

	/**
	 * Send one batch of a queued email, and queue the batch after it.
	 *
	 * The next batch is queued before this one is sent, so an error part way
	 * through cannot strand the recipients after it. Recipients are looked up
	 * here rather than when the email was queued, so anyone who opted out in
	 * between is skipped.
	 *
	 * @since TBD
	 *
	 * @param string $type    A registered mail type.
	 * @param array  $payload Plain data the type needs.
	 * @param array  $cursor  Where this batch starts.
	 * @phpstan-param Payload $payload
	 * @phpstan-param Cursor $cursor
	 *
	 * @return void
	 */
	public function process_job( string $type, array $payload, array $cursor = self::FIRST_BATCH ): void {
		if ( ! isset( $this->types[ $type ] ) ) {
			return;
		}

		$definition = $this->types[ $type ];
		$batch      = $this->get_batch( $type, $definition, $payload, $cursor );

		if ( null !== $batch['next'] ) {
			// A short delay keeps each batch out of the cron pass that queued it.
			$this->schedule( self::JOB_HOOK, array( $type, $payload, $batch['next'] ), 1 );
		}

		$this->bulk_send(
			$batch['recipients'],
			fn( array $recipient ): array => $definition['compose']( $recipient, $payload ),
			$definition['context']
		);
	}

	/**
	 * Get one batch of recipients for a mail type, and where the next starts.
	 *
	 * Users come first, paged by user ID, then the type's recipient list,
	 * paged by comment ID. Paging by ID rather than position keeps a row that
	 * is added or removed between batches from shifting the rest, which would
	 * skip one recipient or mail another twice.
	 *
	 * @since TBD
	 *
	 * @param string $type       The mail type's name.
	 * @param array  $definition The mail type.
	 * @param array  $payload    Plain data the type needs.
	 * @param array  $cursor     Where this batch starts.
	 * @phpstan-param MailType $definition
	 * @phpstan-param Payload $payload
	 * @phpstan-param Cursor $cursor
	 *
	 * @return array{recipients: array<int, Recipient>, next: array{source: string, last_id: int}|null} The batch.
	 */
	private function get_batch( string $type, array $definition, array $payload, array $cursor ): array {
		$size  = $this->get_batch_size( $type );
		$users = 'users' === $cursor['source'] && isset( $definition['users'] )
			? $definition['users']( $payload )
			: null;

		if ( null !== $users ) {
			$page  = $this->get_user_batch( $users, $cursor['last_id'], $size );
			$batch = array(
				'recipients' => $page['recipients'],
				'next'       => $page['complete']
					? $this->get_list_start( $definition )
					: array(
						'source'  => 'users',
						'last_id' => $page['last_id'],
					),
			);
		} else {
			$after = 'list' === $cursor['source'] ? $cursor['last_id'] : 0;
			$rest  = array_filter(
				isset( $definition['recipients'] ) ? $definition['recipients']( $payload ) : array(),
				static fn( array $row ): bool => $row['comment_id'] > $after
			);

			usort( $rest, static fn( array $a, array $b ): int => $a['comment_id'] <=> $b['comment_id'] );

			$rows  = array_slice( $rest, 0, $size );
			$batch = array(
				'recipients' => $rows,
				'next'       => count( $rest ) > $size
					? array(
						'source'  => 'list',
						'last_id' => $rows[ $size - 1 ]['comment_id'],
					)
					: null,
			);
		}

		/**
		 * Filters one batch of recipients of a GatherPress email.
		 *
		 * Lets a site narrow or extend who a batch goes to. Mailer still checks
		 * each recipient's consent before sending.
		 *
		 * @since TBD
		 *
		 * @param array  $recipients Recipient rows in this batch.
		 * @param string $type       The mail type, e.g. `event` or `site_message`.
		 * @param array  $payload    Plain data the email was queued with.
		 * @phpstan-param array<int, Recipient> $recipients
		 * @phpstan-param Payload $payload
		 *
		 * @return array Recipient rows to send this batch to.
		 */
		$batch['recipients'] = (array) apply_filters(
			'gatherpress_email_recipients',
			$batch['recipients'],
			$type,
			$payload
		);

		return $batch;
	}

	/**
	 * Get where a type's recipient list starts, or null when it has none.
	 *
	 * @since TBD
	 *
	 * @param array $definition A registered mail type.
	 * @phpstan-param MailType $definition
	 *
	 * @return array{source: string, last_id: int}|null The list's first batch, or null.
	 */
	private function get_list_start( array $definition ): ?array {
		return isset( $definition['recipients'] )
			? array(
				'source'  => 'list',
				'last_id' => 0,
			)
			: null;
	}

	/**
	 * Get one page of users, after the last user ID of the page before.
	 *
	 * Paging by ID, rather than by offset, keeps a user who joins or leaves
	 * between batches from shifting the pages.
	 *
	 * @since TBD
	 *
	 * @param array $query_args `get_users()` arguments that pick the users.
	 * @param int   $last_id    Last user ID of the page before.
	 * @param int   $size       Users per page.
	 * @phpstan-param array<string, mixed> $query_args
	 *
	 * @return array{recipients: array<int, Recipient>, last_id: int, complete: bool} The page.
	 */
	private function get_user_batch( array $query_args, int $last_id, int $size ): array {
		$after_cursor = static function ( WP_User_Query $query ) use ( $last_id ): WP_User_Query {
			global $wpdb;

			$query->query_where .= $wpdb->prepare( " AND {$wpdb->users}.ID > %d", $last_id );

			return $query;
		};

		add_filter( 'pre_user_query', $after_cursor );
		$users = get_users(
			array_merge(
				$query_args,
				array(
					'number'  => $size,
					'fields'  => array( 'ID', 'user_email', 'display_name' ),
					'orderby' => 'ID',
					'order'   => 'ASC',
				)
			)
		);
		remove_filter( 'pre_user_query', $after_cursor );

		// Opt-ins live in user meta, so load the page's meta in one query.
		update_meta_cache( 'user', wp_list_pluck( $users, 'ID' ) );

		return array(
			'recipients' => array_map(
				static fn( $user ): array => array(
					'is_user'    => true,
					'user_id'    => (int) $user->ID,
					'comment_id' => 0,
					'email'      => $user->user_email,
					'name'       => $user->display_name,
				),
				$users
			),
			'last_id'    => empty( $users ) ? $last_id : (int) $users[ array_key_last( $users ) ]->ID,
			'complete'   => count( $users ) < $size,
		);
	}

	/**
	 * Get how many recipients one job sends to.
	 *
	 * @since TBD
	 *
	 * @param string $type The mail type.
	 *
	 * @return int Recipients per batch.
	 */
	private function get_batch_size( string $type ): int {
		/**
		 * Filters how many recipients one GatherPress mail job sends to.
		 *
		 * Lower it on hosts with a short PHP time limit or a strict mail rate
		 * limit, so each cron job finishes in time.
		 *
		 * @since TBD
		 *
		 * @param int    $batch_size Recipients per batch. Default 50.
		 * @param string $type       The mail type, e.g. `event` or `site_message`.
		 *
		 * @return int Recipients per batch.
		 */
		return max( 1, (int) apply_filters( 'gatherpress_email_batch_size', 50, $type ) );
	}

	/**
	 * Queue a GatherPress mail job on cron.
	 *
	 * Every mail path queues through here so delivery is scheduled the same
	 * way. A companion plugin can take over scheduling by hooking the
	 * `gatherpress_email_pre_enqueue_job` filter; the native WP-Cron path
	 * deduplicates on the hook and args, and retries once before giving up.
	 *
	 * @since TBD
	 *
	 * @param string $hook  Action hook fired when the job runs.
	 * @param array  $args  Args passed to the action hook.
	 * @param int    $delay Seconds to wait before the job may run. Default 0.
	 * @phpstan-param list<mixed> $args
	 *
	 * @return bool True when the job is queued or owned by an external scheduler.
	 */
	private function schedule( string $hook, array $args, int $delay = 0 ): bool {
		/**
		 * Filters the mail enqueue call to take over scheduling.
		 *
		 * Return any non-null value to suppress both the WP-Cron dedup check
		 * and the `wp_schedule_single_event()` call. A companion plugin that
		 * hooks this filter owns the full scheduling path end-to-end,
		 * including its own dedup since the jobs by-pass `wp_next_scheduled()`.
		 * Mirrors the core `pre_*` filter convention: `null` means "pass
		 * through to the default"; everything else, including falsy values
		 * like `false`, `0`, and `''`, short-circuits.
		 *
		 * @since TBD
		 *
		 * @param mixed  $short_circuit Non-null to suppress the default enqueue.
		 * @param string $hook          Action hook name fired when the job runs.
		 * @param array  $args          Args passed to the action hook.
		 * @phpstan-param list<mixed> $args
		 *
		 * @return mixed Null to let WP-Cron queue the job, anything else when another scheduler took it.
		 */
		$short_circuit = apply_filters( 'gatherpress_email_pre_enqueue_job', null, $hook, $args );

		if ( null !== $short_circuit ) {
			return true;
		}

		// A job already queued for these args owns them, so do not stack a
		// duplicate.
		if ( false !== wp_next_scheduled( $hook, $args ) ) {
			return true;
		}

		$timestamp = time() + max( 0, $delay );
		$scheduled = wp_schedule_single_event( $timestamp, $hook, $args );

		// WP-Cron can fail transiently, so retry once before reporting that the
		// job was not queued.
		if ( false === $scheduled ) {
			$scheduled = wp_schedule_single_event( $timestamp, $hook, $args );
		}

		return false !== $scheduled;
	}
}
