<?php
/**
 * The repeat rules of an event.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Utility;
use WP_Error;

/**
 * Class Rules.
 *
 * An event's repeat rules, from its `gatherpress_rule` meta, one row per rule.
 * An event without rules is a series of one, read from its saved dates.
 *
 * Rules are written only through `replace()`, `change_from()` and `stop()`,
 * which keep a published schedule's past dates and keep the event's saved
 * dates on the series' first date. Direct meta writes are refused.
 *
 * @since TBD
 */
final class Rules {

	/**
	 * Post meta key holding the rules, one row per rule.
	 *
	 * @since TBD
	 * @var string
	 */
	const META_KEY = 'gatherpress_rule';

	/**
	 * The `FREQ` values a rule supports.
	 *
	 * @since TBD
	 * @var string[]
	 */
	const FREQUENCIES = array( 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY' );

	/**
	 * The weekday codes, in week order from Monday.
	 *
	 * @since TBD
	 * @var string[]
	 */
	const WEEKDAYS = array( 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' );

	/**
	 * Post statuses whose dates people may already have seen or RSVPed to.
	 *
	 * @since TBD
	 * @var string[]
	 */
	const PUBLISHED_STATUSES = array( 'publish', 'private' );

	/**
	 * Posts whose rules are being written, which lets their meta writes through.
	 *
	 * @since TBD
	 * @var array<int, true>
	 */
	protected static array $writing = array();

	/**
	 * The valid rules, once read.
	 *
	 * @since TBD
	 * @var Rule[]|null
	 */
	protected ?array $rules = null;

	/**
	 * Build the rules of an event.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 */
	public function __construct( protected readonly Event $event ) {}

	/**
	 * The event's valid rules, in the order they were saved.
	 *
	 * A row that is not a valid rule is left out.
	 *
	 * @since TBD
	 *
	 * @return Rule[] The rules, empty when the event doesn't repeat.
	 */
	public function all(): array {
		if ( null !== $this->rules ) {
			return $this->rules;
		}

		// Not single: the key holds one row per rule.
		$rows        = $this->event->post ? get_post_meta( $this->event->post->ID, self::META_KEY, false ) : array();
		$this->rules = array_values( array_filter( array_map( array( Rule::class, 'from_meta' ), (array) $rows ) ) );

		return $this->rules;
	}

	/**
	 * Replace all of the event's rules.
	 *
	 * For drafts and events whose dates haven't started. A published event
	 * with past dates changes its schedule with `change_from()` instead.
	 *
	 * @since TBD
	 *
	 * @param array<int, mixed> $rows The new rules, as meta rows. An empty list removes every rule.
	 *
	 * @return bool|WP_Error True once saved, or why the rules were refused.
	 */
	public function replace( array $rows ): bool|WP_Error {
		$rows = $this->prepare( $rows, wp_generate_uuid4() );

		if ( ! is_wp_error( $rows ) && $this->is_published() && $this->event->has_event_started() ) {
			$rows = new WP_Error(
				'gatherpress_rules_past_dates',
				__( 'This event has dates in the past. Change its schedule from a future date instead.', 'gatherpress' )
			);
		}

		return is_wp_error( $rows ) ? $rows : $this->write( $rows );
	}

	/**
	 * Change the event's schedule from a date on.
	 *
	 * Ends the current rules the day before `$date` and adds the new ones, so
	 * dates before it stay as they were. A rule that starts on or after
	 * `$date` has no past dates, so it is removed rather than ended. An event
	 * without rules keeps its saved date as a rule of one date. The new rules
	 * and the rules this change ends share one new change ID.
	 *
	 * @since TBD
	 *
	 * @param string            $date The first local date of the new schedule, as `Y-m-d`. On a published
	 *                                event it must be after today in the event's timezone and in each
	 *                                current and new rule's, so nothing that has happened, or is happening
	 *                                today, changes.
	 * @param array<int, mixed> $rows The new rules, as meta rows, each starting on or after `$date`.
	 *
	 * @return bool|WP_Error True once saved, or why the change was refused.
	 */
	public function change_from( string $date, array $rows ): bool|WP_Error {
		$from = self::parse_date( $date );

		if ( ! $from ) {
			return new WP_Error(
				'gatherpress_rules_invalid_date',
				__( 'The change date is not a valid date.', 'gatherpress' )
			);
		}

		$change_id = wp_generate_uuid4();
		$rows      = $this->prepare( $rows, $change_id, $from );

		// Read the persisted rules, not ones another `Rules` object has since rewritten.
		$this->rules = null;

		if ( ! is_wp_error( $rows ) && $this->is_published() && ! $this->is_after_today( $from, $rows ) ) {
			$rows = new WP_Error(
				'gatherpress_rules_past_change',
				__( 'A published event can only change its schedule from tomorrow on.', 'gatherpress' )
			);
		}

		if ( is_wp_error( $rows ) ) {
			return $rows;
		}

		$kept = $this->end_before( $from, $change_id );

		return is_wp_error( $kept ) ? $kept : $this->write( array_merge( $kept, $rows ) );
	}

	/**
	 * Stop the event repeating from a date on.
	 *
	 * @since TBD
	 *
	 * @param string $date The first local date without occurrences, as `Y-m-d`.
	 *
	 * @return bool|WP_Error True once saved, or why the change was refused.
	 */
	public function stop( string $date ): bool|WP_Error {
		return $this->change_from( $date, array() );
	}

	/**
	 * Refuse an add or update of the rule meta from outside the write methods.
	 *
	 * Hooked to `add_post_metadata` and `update_post_metadata`.
	 *
	 * @since TBD
	 *
	 * @param mixed  $check     Null to let the write run, or a value to short-circuit it.
	 * @param int    $object_id The post ID.
	 * @param string $meta_key  The meta key.
	 *
	 * @return mixed False to refuse the write, otherwise `$check` unchanged.
	 */
	public static function guard_write( mixed $check, int $object_id, string $meta_key ): mixed {
		return self::META_KEY === $meta_key && ! self::may_write( $object_id ) ? false : $check;
	}

	/**
	 * Refuse a delete of the rule meta from outside the write methods.
	 *
	 * Hooked to `delete_post_metadata`. Deleting the key from every post, as
	 * `delete_post_meta_by_key()` does, is allowed.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param mixed  $check      Null to let the delete run, or a value to short-circuit it.
	 * @param int    $object_id  The post ID.
	 * @param string $meta_key   The meta key.
	 * @param mixed  $meta_value The value to delete. Required by the filter's signature.
	 * @param bool   $delete_all Whether the key is deleted from every post.
	 *
	 * @return mixed False to refuse the delete, otherwise `$check` unchanged.
	 */
	public static function guard_delete(
		mixed $check,
		int $object_id,
		string $meta_key,
		mixed $meta_value,
		bool $delete_all
	): mixed {
		return $delete_all ? $check : self::guard_write( $check, $object_id, $meta_key );
	}

	/**
	 * Whether the rule meta of a post may be written directly.
	 *
	 * @since TBD
	 *
	 * @param int $object_id The post ID.
	 *
	 * @return bool True while this post's rules are written, on a revision, or while importing.
	 */
	protected static function may_write( int $object_id ): bool {
		if ( isset( self::$writing[ $object_id ] ) || 'revision' === get_post_type( $object_id ) ) {
			return true;
		}

		/**
		 * Filters whether rule meta may be written directly, outside the `Rules` write methods.
		 *
		 * Rules should change through `Rules::replace()`, `change_from()` and
		 * `stop()`, which keep past dates. Importers that copy meta as it was
		 * exported, such as the WordPress Importer, are let through by default.
		 *
		 * @since TBD
		 *
		 * @param bool $allowed   Whether the write is allowed. Defaults to true while `WP_IMPORTING` is set.
		 * @param int  $object_id The post ID.
		 *
		 * @return bool Whether the write is allowed.
		 */
		return (bool) apply_filters(
			'gatherpress_rule_meta_write_allowed',
			defined( 'WP_IMPORTING' ) && WP_IMPORTING,
			$object_id
		);
	}

	/**
	 * Validate new rules and give them the change's ID.
	 *
	 * @since TBD
	 *
	 * @param array<int, mixed>      $rows      The new rules, as meta rows.
	 * @param string                 $change_id The change ID the rows take.
	 * @param DateTimeImmutable|null $from      The date each rule must start on or after, if any.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error The rows in canonical form, or the first problem found.
	 */
	protected function prepare( array $rows, string $change_id, ?DateTimeImmutable $from = null ): array|WP_Error {
		if ( ! $this->event->post ) {
			return new WP_Error(
				'gatherpress_rules_not_an_event',
				__( 'Repeat rules can only be saved on an event.', 'gatherpress' )
			);
		}

		$prepared = array();

		foreach ( array_values( $rows ) as $index => $row ) {
			$rule = is_array( $row )
				? Rule::from_meta( array_merge( $row, array( 'change_id' => $change_id ) ) )
				: null;

			if ( ! $rule || ( $from && $rule->starts_on < $from ) ) {
				$message = $rule
					/* translators: %d: The rule's position in the list, from 1. */
					? __( 'Rule %d starts before the date the change takes effect.', 'gatherpress' )
					/* translators: %d: The rule's position in the list, from 1. */
					: __( 'Rule %d is not a valid repeat rule.', 'gatherpress' );

				return new WP_Error(
					'gatherpress_invalid_rule',
					sprintf( $message, $index + 1 ),
					array( 'index' => $index )
				);
			}

			$prepared[] = $rule->to_meta();
		}

		return $prepared;
	}

	/**
	 * The current rules, each ended before a date, leaving out those that start on or after it.
	 *
	 * A rule this ends, and an event's saved date kept as a rule, take the
	 * change's ID, so the change records what it ended.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $from      The first date of the new schedule.
	 * @param string            $change_id The change's ID.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error The rows to keep, or an error if the event's saved
	 *                                                   date can't be kept as a rule.
	 */
	protected function end_before( DateTimeImmutable $from, string $change_id ): array|WP_Error {
		$rules = $this->all() ? $this->all() : $this->seed( $change_id );

		if ( is_wp_error( $rules ) ) {
			return $rules;
		}

		$rows = array();

		foreach ( $rules as $rule ) {
			if ( $rule->starts_on >= $from ) {
				continue;
			}

			$row = $rule->to_meta();

			// Only a rule with dates from the change on needs ending.
			if ( null !== $rule->next_date( $from ) ) {
				$row['ends_on']   = $from->modify( '-1 day' )->format( Rule::DATE_FORMAT );
				$row['change_id'] = $change_id;
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * The event's saved date as a rule of one date, for an event without rules.
	 *
	 * @since TBD
	 *
	 * @param string $change_id The change's ID.
	 *
	 * @return Rule[]|WP_Error The rule, none when the event has no saved date, or an error if the saved
	 *                         date can't be a rule, such as one lasting longer than `Rule::MAX_DURATION`
	 *                         or one with seconds.
	 */
	protected function seed( string $change_id ): array|WP_Error {
		$data = $this->event->get_saved_datetime();

		if ( empty( $data['datetime_start'] ) || empty( $data['datetime_end'] ) ) {
			return array();
		}

		$timezone   = new DateTimeZone( Utility::normalize_timezone_string( $this->event->get_timezone() ) );
		$start      = new DateTimeImmutable( $data['datetime_start'], $timezone );
		$end        = new DateTimeImmutable( $data['datetime_end'], $timezone );
		$is_all_day = $this->event->is_all_day();
		$rule       = Rule::from_meta(
			array(
				'change_id'  => $change_id,
				'starts_on'  => $start->format( Rule::DATE_FORMAT ),
				'rrule'      => null,
				'is_all_day' => $is_all_day,
				'times'      => $is_all_day ? array() : array( $start->format( 'H:i' ) ),
				'timezone'   => $timezone->getName(),
				'duration'   => $is_all_day
					? 1440 * ( (int) $start->diff( $end )->days + 1 )
					: intdiv( $end->getTimestamp() - $start->getTimestamp(), 60 ),
			)
		);

		// A rule keeps whole minutes, so a timed date with seconds would change.
		$is_exact = $is_all_day || '0000' === $start->format( 's' ) . $end->format( 's' );

		return $rule && $is_exact ? array( $rule ) : new WP_Error(
			'gatherpress_rules_saved_date',
			__( "The event's saved date can't be kept as a repeat rule, so its schedule can't change.", 'gatherpress' )
		);
	}

	/**
	 * Save rows as the event's rules, then move its saved dates to the series' first date.
	 *
	 * Rows that aren't valid rules, which a later version may have written,
	 * are kept as they were. If a row can't be saved, the rows from before
	 * are put back, so a failed write never leaves the schedule half written.
	 *
	 * @since TBD
	 *
	 * @param array<int, array<string, mixed>> $rows The rows, already validated.
	 *
	 * @return bool|WP_Error True once saved, or an error if the rows couldn't be saved.
	 */
	protected function write( array $rows ): bool|WP_Error {
		$post_id  = $this->event->post->ID ?? 0;
		$previous = (array) get_post_meta( $post_id, self::META_KEY, false );
		$unknown  = array_filter( $previous, static fn( mixed $row ): bool => null === Rule::from_meta( $row ) );

		self::$writing[ $post_id ] = true;

		try {
			$saved = $this->replace_rows( $post_id, array_merge( array_values( $unknown ), $rows ) );

			if ( ! $saved ) {
				$this->replace_rows( $post_id, $previous );
			}
		} finally {
			unset( self::$writing[ $post_id ] );
		}

		$this->rules = null;

		if ( ! $saved ) {
			return new WP_Error(
				'gatherpress_rules_not_saved',
				__( 'The repeat rules could not be saved, so the schedule was left as it was.', 'gatherpress' )
			);
		}

		$this->sync_saved_dates( $post_id );

		return true;
	}

	/**
	 * Replace the stored rows of the rule key.
	 *
	 * @since TBD
	 *
	 * @param int               $post_id The event's post ID.
	 * @param array<int, mixed> $rows    The rows to store.
	 *
	 * @return bool True if every row was saved.
	 */
	protected function replace_rows( int $post_id, array $rows ): bool {
		delete_post_meta( $post_id, self::META_KEY );

		foreach ( $rows as $row ) {
			// Slashed, since `add_post_meta()` unslashes.
			if ( false === add_post_meta( $post_id, self::META_KEY, wp_slash( $row ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Move the event's saved dates to the first occurrence of its rules.
	 *
	 * Lists, the event page and the editor still read the saved dates, so
	 * they show the series' first date. The editor's `gatherpress_datetime`
	 * moves too, since every save of the event rewrites the dates from it.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event's post ID.
	 *
	 * @return void
	 */
	protected function sync_saved_dates( int $post_id ): void {
		$first = null;

		foreach ( $this->all() as $rule ) {
			$occurrence = $rule->first_occurrence( $post_id, Status::STATUS_SCHEDULED );

			if ( ! $first || $occurrence->start < $first->start ) {
				$first = $occurrence;
			}
		}

		if ( ! $first ) {
			return;
		}

		$start    = $first->start->format( 'Y-m-d H:i:s' );
		$end      = $first->end->format( 'Y-m-d H:i:s' );
		$timezone = $first->start->getTimezone()->getName();

		update_post_meta( $post_id, 'gatherpress_is_all_day', $first->is_all_day );
		update_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_slash(
				(string) wp_json_encode(
					array(
						'dateTimeStart' => $start,
						'dateTimeEnd'   => $end,
						'timezone'      => $timezone,
					),
					JSON_UNESCAPED_SLASHES
				)
			)
		);

		$this->event->save_datetimes(
			array(
				'datetime_start' => $start,
				'datetime_end'   => $end,
				'timezone'       => $timezone,
			)
		);
	}

	/**
	 * Whether the event is published, so people may have seen its dates.
	 *
	 * @since TBD
	 *
	 * @return bool True for a published or private event.
	 */
	protected function is_published(): bool {
		// By ID, since the event's post object keeps the status it was read with.
		return in_array( get_post_status( $this->event->post->ID ?? 0 ), self::PUBLISHED_STATUSES, true );
	}

	/**
	 * Whether a local date is after today in the event's timezone and in each current and new rule's.
	 *
	 * Today can be a day further on in one rule's timezone than in another,
	 * so the date must be after the latest of them.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable                $date The local date.
	 * @param array<int, array<string, mixed>> $rows The new rules, as validated meta rows.
	 *
	 * @return bool True if the date is tomorrow or later everywhere the event happens.
	 */
	protected function is_after_today( DateTimeImmutable $date, array $rows ): bool {
		$timezones = array( new DateTimeZone( Utility::normalize_timezone_string( $this->event->get_timezone() ) ) );
		$new       = array_filter( array_map( array( Rule::class, 'from_meta' ), $rows ) );

		foreach ( array_merge( $this->all(), $new ) as $rule ) {
			$timezones[] = $rule->timezone;
		}

		$latest = '';

		foreach ( $timezones as $timezone ) {
			$latest = max( $latest, ( new DateTimeImmutable( 'now', $timezone ) )->format( Rule::DATE_FORMAT ) );
		}

		return $date->format( Rule::DATE_FORMAT ) > $latest;
	}

	/**
	 * Parse a `Y-m-d` date, as rules store their dates.
	 *
	 * @since TBD
	 *
	 * @param string $date The date.
	 *
	 * @return DateTimeImmutable|null The date at midnight UTC, or null if it isn't a valid date.
	 */
	protected static function parse_date( string $date ): ?DateTimeImmutable {
		$parsed = DateTimeImmutable::createFromFormat( '!' . Rule::DATE_FORMAT, $date, new DateTimeZone( 'UTC' ) );

		return $parsed && $parsed->format( Rule::DATE_FORMAT ) === $date ? $parsed : null;
	}
}
