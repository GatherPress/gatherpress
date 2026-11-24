<?php
/**
 * Detects changes to an event that affect the people who RSVP'd.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Venue\Setup as Venue_Setup;
use WP_Error;
use WP_Post;

/**
 * Compares a published event before and after a save.
 *
 * When the date, the time, the time zone, the venue, or the status changes
 * on a published event that has not started, the change is stored on the
 * event. The editor reads it from the save response and offers to tell the
 * attendees. Each save compares only the event before and after that save:
 * nothing tracks what attendees were told before.
 *
 * The stored change is also what lets the message skip the event updates
 * opt-in. Sending it uses the change up, so the opt-in can be skipped once
 * per detected change and never for a message that is not about one.
 *
 * @since TBD
 *
 * @phpstan-type Values array{
 *     datetime_start: string,
 *     datetime_end: string,
 *     timezone: string,
 *     venue: array<string, string>,
 *     status: array<int, string>
 * }
 * @phpstan-type StoredChange array{
 *     keys: array<int, string>,
 *     before: Values,
 *     after: Values,
 *     show_notice: bool
 * }
 * @phpstan-type Description array{
 *     keys: array<int, string>,
 *     headline: string,
 *     subject: string,
 *     message: string,
 *     attendees: int,
 *     offer_not_attending: bool,
 *     show_notice: bool
 * }
 */
final class Change {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Post meta key that holds the change found by the last save.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const META_KEY = '_gatherpress_change';

	/**
	 * REST field that hands the change to the editor.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const REST_FIELD = 'gatherpress_change';

	/**
	 * The values that are compared, in the order they are reported.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	const KEYS = array( 'datetime_start', 'datetime_end', 'timezone', 'venue', 'status' );

	/**
	 * The keys that are about the date or the time.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	const DATETIME_KEYS = array( 'datetime_start', 'datetime_end', 'timezone' );

	/**
	 * The post meta keys `Event::save_datetimes()` writes the dates to.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	const DATETIME_META_KEYS = array(
		'gatherpress_datetime_start',
		'gatherpress_datetime_end',
		'gatherpress_timezone',
	);

	/**
	 * The statuses that affect attendance, so a change to one of them counts.
	 *
	 * @since TBD
	 *
	 * @var string[]
	 */
	const NOTIFY_STATUSES = array( Status::STATUS_CANCELED, 'postponed', 'rescheduled', 'moved-online' );

	/**
	 * The event values before the first write of this request, keyed by post ID.
	 *
	 * @since TBD
	 *
	 * @var array<int, Values>
	 */
	private array $snapshots = array();

	/**
	 * Posts that were not eligible when this request first wrote to them.
	 *
	 * See `should_snapshot()`.
	 *
	 * @since TBD
	 *
	 * @var array<int, true>
	 */
	private array $skipped = array();

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
	 * A REST save runs `wp_update_post()` first, then the terms, the meta and
	 * the REST fields, then `wp_after_insert_post`, where the dates are
	 * written. So the snapshot is taken on the first write of the request and
	 * the compare runs after the dates are written, before the response is
	 * built. Writes that skip `wp_update_post()`, such as `Status::set()`
	 * or a direct `Event::save_datetimes()`, take the snapshot from the term
	 * or meta write itself, and are compared at shutdown.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'pre_post_update', array( $this, 'snapshot_before_update' ) );
		add_action( 'set_object_terms', array( $this, 'snapshot_before_terms' ), 10, 6 );
		add_filter( 'update_post_metadata', array( $this, 'snapshot_before_meta' ), 10, 3 );
		add_action( 'wp_after_insert_post', array( $this, 'compare' ), 20 );
		add_action( 'shutdown', array( $this, 'finish' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_field' ) );
	}

	/**
	 * Take the snapshot before `wp_update_post()` writes anything.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post about to be updated.
	 *
	 * @return void
	 */
	public function snapshot_before_update( int $post_id ): void {
		$this->maybe_snapshot( $post_id );
	}

	/**
	 * Take the snapshot when a venue or status term write comes first.
	 *
	 * The action fires after the terms are written, so the replaced terms
	 * come from `$old_tt_ids` and every other value is still the old one.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param int    $object_id  The object the terms were written to.
	 * @param mixed  $terms      The terms, required by the action signature.
	 * @param mixed  $tt_ids     The new term taxonomy IDs, required by the action signature.
	 * @param string $taxonomy   The taxonomy written to.
	 * @param bool   $append     Whether the terms were appended, required by the action signature.
	 * @param mixed  $old_tt_ids The term taxonomy IDs before the write.
	 *
	 * @return void
	 */
	public function snapshot_before_terms(
		int $object_id,
		$terms,
		$tt_ids,
		string $taxonomy,
		bool $append,
		$old_tt_ids
	): void {
		$post_type = (string) get_post_type( $object_id );
		$venue_tax = Venue_Setup::get_instance()->taxonomy_for_event_post_type( $post_type );

		// Terms are also written to RSVP comments, whose IDs are not post IDs,
		// so act only on the two taxonomies that belong to this post's type.
		if (
			! in_array( $taxonomy, array( Event::TAXONOMY_STATUS, $venue_tax ), true )
			|| ! is_object_in_taxonomy( $post_type, $taxonomy )
			|| ! $this->should_snapshot( $object_id )
		) {
			return;
		}

		$old_tt_ids = array_map( 'intval', (array) $old_tt_ids );
		$values     = $this->read_values( $object_id );

		// Every other value is still the old one.
		if ( Event::TAXONOMY_STATUS === $taxonomy ) {
			$values['status'] = $this->status_from_tt_ids( $object_id, $old_tt_ids );
		} else {
			$values['venue'] = $this->venue_from_tt_ids( $old_tt_ids, $taxonomy );
		}

		$this->snapshots[ $object_id ] = $values;
	}

	/**
	 * Take the snapshot when a date write comes first.
	 *
	 * Covers `Event::save_datetimes()` called without a post update. The
	 * filter fires before the meta is written and its return value is passed
	 * through unchanged.
	 *
	 * @since TBD
	 *
	 * @param mixed  $check     Short-circuit value from earlier callbacks.
	 * @param int    $object_id The post the meta belongs to.
	 * @param string $meta_key  The meta key about to be written.
	 *
	 * @return mixed The short-circuit value, unchanged.
	 */
	public function snapshot_before_meta( $check, int $object_id, string $meta_key ) {
		if ( in_array( $meta_key, self::DATETIME_META_KEYS, true ) ) {
			$this->maybe_snapshot( $object_id );
		}

		return $check;
	}

	/**
	 * Compare the event with its snapshot and store what changed.
	 *
	 * Runs after `Event\Setup::set_datetimes()` (priority 10) has written the
	 * dates. The snapshot stays until shutdown, so a later write in the same
	 * request is compared again.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post that was saved.
	 *
	 * @return void
	 */
	public function compare( int $post_id ): void {
		if ( isset( $this->snapshots[ $post_id ] ) ) {
			$this->store( $post_id );
		}
	}

	/**
	 * Compare every snapshot a last time, then tell the site what changed.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function finish(): void {
		$this->skipped = array();

		foreach ( array_keys( $this->snapshots ) as $post_id ) {
			$keys = $this->store( $post_id );

			unset( $this->snapshots[ $post_id ] );

			if ( empty( $keys ) ) {
				continue;
			}

			/**
			 * Fires after a save changes the date, venue, or status of a published event.
			 *
			 * Fires once per request, for saves through the editor, the REST API,
			 * WP-CLI, or code. A site can use it for its own rules, for example to
			 * wait a few minutes and combine several saves into one message.
			 *
			 * @since TBD
			 *
			 * @param int      $post_id The event post ID.
			 * @param string[] $keys    What changed: `datetime_start`, `datetime_end`, `timezone`,
			 *                          `venue`, `status`.
			 */
			do_action( 'gatherpress_event_changed', $post_id, $keys );

			$this->maybe_send_automatically( $post_id, $keys );
		}
	}

	/**
	 * Expose the stored change to the editor.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_rest_field(): void {
		foreach ( get_post_types_by_support( Rsvp::SUPPORT ) as $post_type ) {
			if ( ! post_type_supports( $post_type, Event::SUPPORT ) ) {
				continue;
			}

			register_rest_field(
				$post_type,
				self::REST_FIELD,
				array(
					'get_callback' => array( $this, 'get_rest_value' ),
					'schema'       => array(
						'description' => __(
							'What the last save changed for the people who RSVP\'d, if anything.',
							'gatherpress'
						),
						'type'        => array( 'object', 'null' ),
						'context'     => array( 'edit' ),
						'readonly'    => true,
					),
				)
			);
		}
	}

	/**
	 * Get the REST value of the stored change.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $post The post as prepared for the response.
	 *
	 * @return Description|null The change, or null when there is none or the user cannot edit the event.
	 */
	public function get_rest_value( array $post ): ?array {
		$post_id = (int) ( $post['id'] ?? 0 );

		if ( ! current_user_can( Event::edit_capability(), $post_id ) ) {
			return null;
		}

		return $this->describe( $post_id );
	}

	/**
	 * Get the stored change of an event.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return StoredChange|null The change, or null when the last save changed nothing that counts.
	 */
	public function get( int $post_id ): ?array {
		$change = get_post_meta( $post_id, self::META_KEY, true );

		if ( ! is_array( $change ) || empty( $change['keys'] ) ) {
			return null;
		}

		/**
		 * The meta that `store()` writes, in the shape it writes it.
		 *
		 * @phpstan-var StoredChange $change
		 */
		return $change;
	}

	/**
	 * Describe the stored change in words, for the notice and the message.
	 *
	 * Built in PHP so the editor and the automatic send use the same words,
	 * in the site's date and time formats.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return Description|null The description, or null when there is no stored change.
	 */
	public function describe( int $post_id ): ?array {
		$change = $this->get( $post_id );

		if ( null === $change ) {
			return null;
		}

		$responses = ( new Rsvp( $post_id ) )->responses();
		$attendees = (int) ( $responses['attending']['count'] ?? 0 )
			+ (int) ( $responses['waiting_list']['count'] ?? 0 );
		$headline  = $this->headline( $change );

		return array(
			'keys'                => $change['keys'],
			'headline'            => $headline,
			'subject'             => sprintf(
				/* translators: 1: Event title. 2: What changed, for example "The venue changed." */
				_x( '%1$s: %2$s', 'Event change email subject', 'gatherpress' ),
				get_the_title( $post_id ),
				$headline
			),
			'message'             => $this->message( $change ),
			'attendees'           => $attendees,
			'offer_not_attending' => $this->has_datetime_change( $change['keys'] ),
			'show_notice'         => $change['show_notice'] && 0 < $attendees,
		);
	}

	/**
	 * Send the message about the stored change, and use the change up.
	 *
	 * Attending and waiting list get the message whatever their event updates
	 * opt-in, because it is about their own RSVP. Not attending gets it only
	 * for a date, time, or time zone change, only when asked, and only if they
	 * opted in.
	 *
	 * @since TBD
	 *
	 * @param int    $post_id       The event post ID.
	 * @param bool   $not_attending Whether to also tell the people who said they are not attending.
	 * @param string $message       The message.
	 * @param string $subject       The subject line.
	 *
	 * @return bool|WP_Error True when the message is queued, a WP_Error when there is no change to send.
	 */
	public function send( int $post_id, bool $not_attending, string $message, string $subject ): bool|WP_Error {
		$change = $this->get( $post_id );

		// Without a stored change, or once the event can no longer change for
		// its attendees, nothing may skip the opt-in. Fail closed.
		if ( null === $change || ! $this->is_eligible( $post_id ) ) {
			return new WP_Error(
				'gatherpress_no_event_change',
				__( 'There is no change to tell attendees about. Save a change to the event first.', 'gatherpress' ),
				array( 'status' => 409 )
			);
		}

		$send = array(
			'attending'     => true,
			'waiting_list'  => true,
			'not_attending' => $not_attending && $this->has_datetime_change( $change['keys'] ),
		);

		/**
		 * Filters who gets the message about a change to an event.
		 *
		 * Attending and waiting list skip the event updates opt-in; not
		 * attending follows it. To change the opt-in rules themselves, use
		 * `gatherpress_email_recipient_consent`.
		 *
		 * @since TBD
		 *
		 * @param array{attending: bool, waiting_list: bool, not_attending: bool} $send    The groups to send to.
		 * @param string[]                                                         $keys    What changed.
		 * @param int                                                              $post_id The event post ID.
		 */
		$send = (array) apply_filters( 'gatherpress_event_change_recipients', $send, $change['keys'], $post_id );

		$queued = Email::get_instance()->send_change_emails(
			$post_id,
			array(
				'attending'     => ! empty( $send['attending'] ),
				'waiting_list'  => ! empty( $send['waiting_list'] ),
				'not_attending' => ! empty( $send['not_attending'] ),
			),
			$message,
			$subject
		);

		if ( $queued ) {
			delete_post_meta( $post_id, self::META_KEY );
		}

		return $queued;
	}

	/**
	 * Take a snapshot of an event the first time this request writes to it.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post about to be written.
	 *
	 * @return void
	 */
	private function maybe_snapshot( int $post_id ): void {
		if ( $this->should_snapshot( $post_id ) ) {
			$this->snapshots[ $post_id ] = $this->read_values( $post_id );
		}
	}

	/**
	 * Whether this is the first write of the request to an eligible post.
	 *
	 * The first write decides for the whole request. A post that is not
	 * eligible then is skipped until the request ends, so an event that this
	 * request creates or publishes never looks changed: its dates are written
	 * one key at a time, after it is already published.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True when a snapshot should be taken now.
	 */
	private function should_snapshot( int $post_id ): bool {
		if ( isset( $this->snapshots[ $post_id ] ) || isset( $this->skipped[ $post_id ] ) ) {
			return false;
		}

		if ( ! $this->is_eligible( $post_id ) ) {
			$this->skipped[ $post_id ] = true;

			return false;
		}

		return true;
	}

	/**
	 * Whether a change to this post can matter to people who RSVP'd.
	 *
	 * An event with no start date yet is one being created, so filling in
	 * its first date is not a change.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True for a published event that takes RSVPs, has a start date, and has not started.
	 */
	private function is_eligible( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof WP_Post
			&& post_type_supports( $post->post_type, Event::SUPPORT )
			&& post_type_supports( $post->post_type, Rsvp::SUPPORT )
			&& 'publish' === $post->post_status
			&& '' !== (string) get_post_meta( $post_id, 'gatherpress_datetime_start', true )
			&& ! ( new Event( $post_id ) )->has_event_started();
	}

	/**
	 * Read the values that are compared, as they are stored now.
	 *
	 * The dates are read from meta rather than through `Event::get_datetime()`,
	 * which caches and applies the viewer's time zone preference.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return Values The values.
	 */
	private function read_values( int $post_id ): array {
		$taxonomy = Venue_Setup::get_instance()->taxonomy_for_event_post_type( (string) get_post_type( $post_id ) );
		$terms    = get_the_terms( $post_id, $taxonomy );
		$venue    = array();

		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$venue[ $term->slug ] = $term->name;
		}

		ksort( $venue );

		return array(
			'datetime_start' => (string) get_post_meta( $post_id, 'gatherpress_datetime_start', true ),
			'datetime_end'   => (string) get_post_meta( $post_id, 'gatherpress_datetime_end', true ),
			'timezone'       => (string) get_post_meta( $post_id, 'gatherpress_timezone', true ),
			'venue'          => $venue,
			'status'         => ( new Status( $post_id ) )->get_all(),
		);
	}

	/**
	 * Get the status slugs an event had, from term taxonomy IDs.
	 *
	 * @since TBD
	 *
	 * @param int   $post_id The event post ID.
	 * @param int[] $tt_ids  The term taxonomy IDs.
	 *
	 * @return string[] The slugs, highest priority first, or the default status when there are none.
	 */
	private function status_from_tt_ids( int $post_id, array $tt_ids ): array {
		$post_type = (string) get_post_type( $post_id );
		$slugs     = array();

		foreach ( array_keys( $this->terms_from_tt_ids( $tt_ids, Event::TAXONOMY_STATUS ) ) as $slug ) {
			if ( Status::exists( (string) $slug, $post_type ) ) {
				$slugs[] = (string) $slug;
			}
		}

		if ( empty( $slugs ) ) {
			return array( Status::default_slug( $post_type ) );
		}

		usort(
			$slugs,
			static fn( string $a, string $b ): int => Status::priority( $b ) <=> Status::priority( $a )
		);

		return $slugs;
	}

	/**
	 * Get the venue terms an event had, from term taxonomy IDs.
	 *
	 * @since TBD
	 *
	 * @param int[]  $tt_ids   The term taxonomy IDs.
	 * @param string $taxonomy The venue taxonomy.
	 *
	 * @return array<string, string> Term names keyed by slug, sorted by slug.
	 */
	private function venue_from_tt_ids( array $tt_ids, string $taxonomy ): array {
		$venue = $this->terms_from_tt_ids( $tt_ids, $taxonomy );

		ksort( $venue );

		return $venue;
	}

	/**
	 * Get term names keyed by slug, from term taxonomy IDs.
	 *
	 * @since TBD
	 *
	 * @param int[]  $tt_ids   The term taxonomy IDs.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return array<string, string> Term names keyed by slug.
	 */
	private function terms_from_tt_ids( array $tt_ids, string $taxonomy ): array {
		if ( empty( $tt_ids ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'         => $taxonomy,
				'term_taxonomy_id' => $tt_ids,
				'hide_empty'       => false,
			)
		);
		$names = array();

		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$names[ $term->slug ] = $term->name;
		}

		return $names;
	}

	/**
	 * Compare the snapshot with the event now, and store or clear the change.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return string[] What changed and counts, or an empty array.
	 */
	private function store( int $post_id ): array {
		$before = $this->snapshots[ $post_id ];
		$after  = $this->read_values( $post_id );
		$found  = array();

		foreach ( self::KEYS as $key ) {
			// A venue is compared by its term slugs, so a renamed venue is not a change.
			$old = 'venue' === $key ? array_keys( $before['venue'] ) : $before[ $key ];
			$new = 'venue' === $key ? array_keys( $after['venue'] ) : $after[ $key ];

			if ( $old !== $new ) {
				$found[] = $key;
			}
		}

		// A status change counts only when the new status affects attendance.
		$keys = array_values(
			array_filter(
				$found,
				static fn( string $key ): bool => 'status' !== $key
					|| in_array( $after['status'][0] ?? '', self::NOTIFY_STATUSES, true )
			)
		);

		/**
		 * Filters which changes to an event count.
		 *
		 * Return fewer keys to ignore some changes. A key that was not found
		 * is ignored, so this filter cannot report a change that did not happen.
		 *
		 * @since TBD
		 *
		 * @param string[] $keys    The changes that count: `datetime_start`, `datetime_end`,
		 *                          `timezone`, `venue`, `status`.
		 * @param string[] $found   Every value that changed, including a status change
		 *                          that does not count by default.
		 * @param int      $post_id The event post ID.
		 */
		$keys = (array) apply_filters( 'gatherpress_event_change_fields', $keys, $found, $post_id );
		$keys = array_values( array_intersect( $found, $keys ) );

		// The post can stop being published later in the same request.
		if ( empty( $keys ) || 'publish' !== get_post_status( $post_id ) ) {
			delete_post_meta( $post_id, self::META_KEY );

			return array();
		}

		/**
		 * Filters whether the editor offers to tell attendees about a change.
		 *
		 * Return false to keep the notice off, for example on a site that sends
		 * the message itself through `gatherpress_event_change_send`.
		 *
		 * @since TBD
		 *
		 * @param bool     $show_notice Whether the editor shows the notice. Default true.
		 * @param string[] $keys        What changed.
		 * @param int      $post_id     The event post ID.
		 */
		$show_notice = (bool) apply_filters( 'gatherpress_event_change_show_notice', true, $keys, $post_id );

		update_post_meta(
			$post_id,
			self::META_KEY,
			array(
				'keys'        => $keys,
				'before'      => $before,
				'after'       => $after,
				'show_notice' => $show_notice,
			)
		);

		return $keys;
	}

	/**
	 * Send the change message without the organizer, when the site asks for it.
	 *
	 * @since TBD
	 *
	 * @param int      $post_id The event post ID.
	 * @param string[] $keys    What changed.
	 *
	 * @return void
	 */
	private function maybe_send_automatically( int $post_id, array $keys ): void {
		/**
		 * Filters whether the message about a change goes out without the organizer.
		 *
		 * Off by default: the organizer reviews the message in the editor and
		 * sends it. Turn it on for a site that changes events through the REST
		 * API or WP-CLI, or that turned the notice off and wants its own rules.
		 * The message is the default description of the change.
		 *
		 * @since TBD
		 *
		 * @param bool     $send    Whether to send now. Default false.
		 * @param string[] $keys    What changed.
		 * @param int      $post_id The event post ID.
		 */
		if ( ! apply_filters( 'gatherpress_event_change_send', false, $keys, $post_id ) ) {
			return;
		}

		$description = $this->describe( $post_id );

		if ( null !== $description ) {
			$this->send( $post_id, false, $description['message'], $description['subject'] );
		}
	}

	/**
	 * Whether the change includes the date, the time, or the time zone.
	 *
	 * @since TBD
	 *
	 * @param string[] $keys What changed.
	 *
	 * @return bool True when a date key changed.
	 */
	private function has_datetime_change( array $keys ): bool {
		return ! empty( array_intersect( $keys, self::DATETIME_KEYS ) );
	}

	/**
	 * One sentence that says what changed.
	 *
	 * @since TBD
	 *
	 * @param array $change The stored change.
	 * @phpstan-param StoredChange $change
	 *
	 * @return string The sentence.
	 */
	private function headline( array $change ): string {
		if ( 1 < count( $change['keys'] ) ) {
			return __( 'Several event details changed.', 'gatherpress' );
		}

		return match ( $change['keys'][0] ) {
			'datetime_start' => __( 'The start date or time changed.', 'gatherpress' ),
			'datetime_end'   => __( 'The end date or time changed.', 'gatherpress' ),
			'timezone'       => __( 'The time zone changed.', 'gatherpress' ),
			'venue'          => __( 'The venue changed.', 'gatherpress' ),
			default          => sprintf(
				/* translators: %s: Event status, for example "Canceled". */
				__( 'The event status changed to %s.', 'gatherpress' ),
				Status::label( $change['after']['status'][0] ?? '' )
			),
		};
	}

	/**
	 * The message that says what changed, with the old and the new value.
	 *
	 * @since TBD
	 *
	 * @param array $change The stored change.
	 * @phpstan-param StoredChange $change
	 *
	 * @return string The message, one change per line.
	 */
	private function message( array $change ): string {
		$lines = array();

		foreach ( $change['keys'] as $key ) {
			$lines[] = match ( $key ) {
				'datetime_start' => sprintf(
					/* translators: 1: Old start date and time. 2: New start date and time. */
					__( 'Start: was %1$s, now %2$s.', 'gatherpress' ),
					$this->format_datetime( $change['before']['datetime_start'], $change['before']['timezone'] ),
					$this->format_datetime( $change['after']['datetime_start'], $change['after']['timezone'] )
				),
				'datetime_end'   => sprintf(
					/* translators: 1: Old end date and time. 2: New end date and time. */
					__( 'End: was %1$s, now %2$s.', 'gatherpress' ),
					$this->format_datetime( $change['before']['datetime_end'], $change['before']['timezone'] ),
					$this->format_datetime( $change['after']['datetime_end'], $change['after']['timezone'] )
				),
				'timezone'       => sprintf(
					/* translators: 1: Old time zone. 2: New time zone. */
					__( 'Time zone: was %1$s, now %2$s.', 'gatherpress' ),
					$change['before']['timezone'],
					$change['after']['timezone']
				),
				'venue'          => sprintf(
					/* translators: 1: Old venue. 2: New venue. */
					__( 'Venue: was %1$s, now %2$s.', 'gatherpress' ),
					$this->format_venue( $change['before']['venue'] ),
					$this->format_venue( $change['after']['venue'] )
				),
				default          => sprintf(
					/* translators: 1: Old event status. 2: New event status. */
					__( 'Status: was %1$s, now %2$s.', 'gatherpress' ),
					Status::label( $change['before']['status'][0] ?? '' ),
					Status::label( $change['after']['status'][0] ?? '' )
				),
			};
		}

		return implode( "\n", $lines );
	}

	/**
	 * Format a stored local date and time in the site's formats.
	 *
	 * @since TBD
	 *
	 * @param string $datetime The local date and time, `Y-m-d H:i:s`.
	 * @param string $timezone The time zone it is in.
	 *
	 * @return string The formatted date and time, or "not set".
	 */
	private function format_datetime( string $datetime, string $timezone ): string {
		if ( '' === $datetime ) {
			return __( 'not set', 'gatherpress' );
		}

		try {
			$timezone = '' === $timezone ? wp_timezone_string() : $timezone;
			$zone     = new DateTimeZone( Utility::normalize_timezone_string( $timezone ) );
			$date     = new DateTimeImmutable( $datetime, $zone );
		} catch ( Exception ) {
			return $datetime;
		}

		return (string) wp_date(
			sprintf( '%s %s T', get_option( 'date_format' ), get_option( 'time_format' ) ),
			$date->getTimestamp(),
			$zone
		);
	}

	/**
	 * Format the venue terms of an event.
	 *
	 * @since TBD
	 *
	 * @param array<string, string> $venue Term names keyed by slug.
	 *
	 * @return string The names, or "no venue".
	 */
	private function format_venue( array $venue ): string {
		return empty( $venue ) ? __( 'no venue', 'gatherpress' ) : implode( ', ', $venue );
	}
}
