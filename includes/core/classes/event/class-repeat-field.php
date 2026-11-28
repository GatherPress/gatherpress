<?php
/**
 * The REST field the editor reads and writes an event's repeating schedule through.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use GatherPress\Core\Validate;
use stdClass;
use WP_Error;
use WP_Post;
use WP_REST_Request;

/**
 * Class Repeat_Field.
 *
 * Adds `gatherpress_repeat` to every event post type's REST responses, so
 * the editor reads the schedule with the post and saves it with the Save
 * button. It shows one schedule at a time: the rule that applies now, a
 * change scheduled for a later date, and whether the series has ended.
 *
 * A save is checked on `rest_pre_insert_{post_type}`, before anything is
 * saved, so a bad schedule never leaves a post half saved. The rule is
 * written once the post's dates are saved in the same request, through the
 * `Rules` methods, which keep a published schedule's past:
 *
 * - An event that hasn't started gets its rules replaced.
 * - A published event that has started changes from a date after today,
 *   tomorrow unless the request says otherwise.
 * - A pattern equal to the current one changes nothing, and one that only
 *   moves the end date ends the current rule there.
 *
 * Dates in a request come from its `gatherpress_datetime` meta when it has
 * one, so a save that moves the start and adds a pattern uses the new start.
 *
 * @since TBD
 */
final class Repeat_Field {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The REST field name.
	 *
	 * @since TBD
	 * @var string
	 */
	const FIELD = 'gatherpress_repeat';

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Register the field once REST starts.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Add the field and its check to every event post type.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register(): void {
		foreach ( get_post_types_by_support( Event::SUPPORT ) as $post_type ) {
			register_rest_field(
				$post_type,
				self::FIELD,
				array(
					'get_callback'    => array( $this, 'get_value' ),
					'update_callback' => array( $this, 'update_value' ),
					'schema'          => array(
						'description' => __( 'The event\'s repeating schedule.', 'gatherpress' ),
						'type'        => array( 'object', 'null' ),
						'context'     => array( 'view', 'edit' ),
					),
				)
			);
			add_filter( "rest_pre_insert_{$post_type}", array( $this, 'validate' ), 10, 2 );
		}
	}

	/**
	 * The event's schedule, as the editor shows it.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param array<string, mixed> $data    The post's REST data.
	 * @param string               $field   The field name. Required by the callback's signature.
	 * @param WP_REST_Request      $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array<string, mixed>|null The schedule, or null for a post whose dates are hidden by a password.
	 */
	public function get_value( array $data, string $field, WP_REST_Request $request ): ?array {
		$post = get_post( (int) ( $data['id'] ?? 0 ) );

		if ( ! $post instanceof WP_Post || Event::is_hidden_by_password( $post, $request ) ) {
			return null;
		}

		return $this->describe_schedule( new Event( $post->ID ) );
	}

	/**
	 * Refuse a save whose schedule can't be written, before anything is saved.
	 *
	 * Hooked to `rest_pre_insert_{post_type}`.
	 *
	 * @since TBD
	 *
	 * @param stdClass        $prepared_post The post about to be saved.
	 * @param WP_REST_Request $request       The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return stdClass|WP_Error The post unchanged, or why the schedule was refused.
	 */
	public function validate( stdClass $prepared_post, WP_REST_Request $request ): stdClass|WP_Error {
		if ( ! $request->has_param( self::FIELD ) ) {
			return $prepared_post;
		}

		$plan = $this->plan( (int) ( $prepared_post->ID ?? 0 ), $request );

		return is_wp_error( $plan ) ? $plan : $prepared_post;
	}

	/**
	 * Write the schedule once the post and its dates are saved.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param mixed           $value   The field's value. Read again from the request.
	 * @param WP_Post         $post    The saved post.
	 * @param string          $field   The field name. Required by the callback's signature.
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return bool|WP_Error True once written, or why it couldn't be.
	 */
	public function update_value(
		mixed $value,
		WP_Post $post,
		string $field,
		WP_REST_Request $request
	): bool|WP_Error {
		$plan = $this->plan( $post->ID, $request );

		return is_wp_error( $plan ) ? $plan : $this->apply( $post->ID, $plan );
	}

	/**
	 * Describe an event's rules as one current schedule.
	 *
	 * Rules that ended before today are history. Of the rest, the one that
	 * applies today, or the first to come, is the pattern, and one starting
	 * after it is a scheduled change. When every rule has ended, the last is
	 * the pattern and the series is marked ended. Anything the editor can't
	 * show as one pattern is marked not showable.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 *
	 * @return array{pattern: array<string, mixed>|null, scheduled: array<string, mixed>|null, ended: bool,
	 *     showable: bool} The schedule.
	 */
	protected function describe_schedule( Event $event ): array {
		$today  = $this->today( $this->event_timezone( $event ) );
		$groups = array(
			'active' => array(),
			'future' => array(),
			'ended'  => array(),
		);

		foreach ( $event->rules()->all() as $rule ) {
			$groups[ $this->group_of( $rule, $today ) ][] = $rule;
		}

		$current   = $groups['active'][0] ?? $groups['future'][0] ?? null;
		$scheduled = $groups['active'] ? ( $groups['future'][0] ?? null ) : null;
		$last      = $groups['ended'] ? end( $groups['ended'] ) : null;
		$shown     = array_filter( array( $current, $scheduled ) );
		$showable  = count( $groups['active'] ) <= 1 && count( $groups['future'] ) <= 1;

		foreach ( $shown as $rule ) {
			$showable = $showable && $this->is_showable( $rule, $event );
		}

		return array(
			'pattern'   => $this->describe( $current ?? $last ),
			'scheduled' => $this->describe( $scheduled ),
			'ended'     => null === $current && null !== $last,
			'showable'  => $showable,
		);
	}

	/**
	 * Whether a rule is history, applies today, or is still to come.
	 *
	 * @since TBD
	 *
	 * @param Rule              $rule  The rule.
	 * @param DateTimeImmutable $today Today, as a rule date.
	 *
	 * @return string `ended`, `active` or `future`.
	 */
	protected function group_of( Rule $rule, DateTimeImmutable $today ): string {
		if ( null !== $rule->ends_on && $rule->ends_on < $today ) {
			return 'ended';
		}

		return $rule->starts_on > $today ? 'future' : 'active';
	}

	/**
	 * Whether the editor can show a rule: one time of day, in the event's timezone.
	 *
	 * @since TBD
	 *
	 * @param Rule  $rule  The rule.
	 * @param Event $event The event.
	 *
	 * @return bool True if the editor can show it.
	 */
	protected function is_showable( Rule $rule, Event $event ): bool {
		return count( $rule->times ) <= 1 && $rule->timezone->getName() === $this->event_timezone( $event )->getName();
	}

	/**
	 * A rule's fields, as the editor reads them.
	 *
	 * @since TBD
	 *
	 * @param Rule|null $rule The rule.
	 *
	 * @return array<string, mixed>|null The fields, or null without a rule.
	 */
	protected function describe( ?Rule $rule ): ?array {
		if ( null === $rule ) {
			return null;
		}

		return array(
			'starts_on'       => $rule->starts_on->format( Rule::DATE_FORMAT ),
			'ends_on'         => $rule->ends_on?->format( Rule::DATE_FORMAT ),
			'frequency'       => $rule->frequency,
			'interval'        => $rule->interval,
			'weekdays'        => $rule->weekdays,
			'month_day'       => $rule->month_day,
			'ordinal_weekday' => $rule->ordinal_weekday,
			'times'           => $rule->times,
			'duration'        => $rule->duration,
			'is_all_day'      => $rule->is_all_day,
			'timezone'        => $rule->timezone->getName(),
		);
	}

	/**
	 * Work out what a save does with the schedule, without writing anything.
	 *
	 * @since TBD
	 *
	 * @param int             $post_id The event's post ID, or 0 for a post not created yet.
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array{action: string, rows?: array<int, array<string, mixed>>, from?: string}|WP_Error The plan,
	 *     or why the schedule can't be written.
	 */
	protected function plan( int $post_id, WP_REST_Request $request ): array|WP_Error {
		$value   = $request->get_param( self::FIELD );
		$pattern = is_array( $value ) ? ( $value['pattern'] ?? null ) : null;
		$dates   = $this->request_dates( $post_id, $request );

		if ( ! is_array( $value ) || ( null !== $pattern && ! is_array( $pattern ) ) ) {
			return $this->error( __( 'The repeating schedule is not in the expected form.', 'gatherpress' ) );
		}

		if ( null === $dates ) {
			return $this->error( __( 'Set the event\'s date before making it repeat.', 'gatherpress' ) );
		}

		return $this->has_started( $post_id, $request, $dates )
			? $this->plan_change( $pattern, $value['from'] ?? null, $dates )
			: $this->plan_replace( $pattern, $dates );
	}

	/**
	 * Plan replacing the rules of an event that hasn't started.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed>|null $pattern The pattern, or null to stop repeating.
	 * @param array<string, mixed>      $dates   The event's dates in the request.
	 *
	 * @return array{action: string, rows: array<int, array<string, mixed>>}|WP_Error The plan, or why the
	 *     pattern is invalid.
	 */
	protected function plan_replace( ?array $pattern, array $dates ): array|WP_Error {
		$rows = array();

		if ( null !== $pattern ) {
			$row = $this->valid_row( $this->row( $pattern, $dates, $dates['start']->format( Rule::DATE_FORMAT ) ) );

			if ( is_wp_error( $row ) ) {
				return $row;
			}

			$rows[] = $row;
		}

		return array(
			'action' => 'replace',
			'rows'   => $rows,
		);
	}

	/**
	 * Plan changing the schedule of a published event that has started.
	 *
	 * The new rule starts on its first date on or after the change date, so
	 * the change date itself isn't added as a date.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed>|null $pattern The pattern, or null to stop repeating.
	 * @param mixed                     $from    The change date from the request, if any.
	 * @param array<string, mixed>      $dates   The event's dates in the request.
	 *
	 * @return array{action: string, from: string, rows?: array<int, array<string, mixed>>}|WP_Error The plan,
	 *     or why the change can't be made.
	 */
	protected function plan_change( ?array $pattern, mixed $from, array $dates ): array|WP_Error {
		$from = $this->change_date( $from, $dates['timezone'] );

		if ( is_wp_error( $from ) ) {
			return $from;
		}

		if ( null === $pattern ) {
			return array(
				'action' => 'stop',
				'from'   => $from->format( Rule::DATE_FORMAT ),
			);
		}

		$first = $this->first_date( $pattern, $dates, $from );
		$row   = $first ? $this->valid_row( $this->row( $pattern, $dates, $first ) ) : $this->no_dates();

		return is_wp_error( $row ) ? $row : array(
			'action' => 'change',
			'from'   => $from->format( Rule::DATE_FORMAT ),
			'rows'   => array( $row ),
		);
	}

	/**
	 * Carry out a plan, unless it would change nothing.
	 *
	 * @since TBD
	 *
	 * @param int                  $post_id The event's post ID.
	 * @param array<string, mixed> $plan    The plan.
	 *
	 * @return bool|WP_Error True once written, or why it couldn't be.
	 */
	protected function apply( int $post_id, array $plan ): bool|WP_Error {
		$rules   = ( new Event( $post_id ) )->rules();
		$current = $this->active_row( $rules );
		$next    = $plan['rows'][0] ?? null;

		if ( 'change' === $plan['action'] && $current && $next && $this->same_pattern( $current, $next ) ) {
			return $this->move_end( $rules, $current, $next );
		}

		$unchanged = 'replace' === $plan['action']
			&& $this->rows_of( $rules ) === $this->without_change_ids( $plan['rows'] );

		if ( $unchanged ) {
			return true;
		}

		return match ( $plan['action'] ) {
			'stop'   => $rules->stop( $plan['from'] ),
			'change' => $rules->change_from( $plan['from'], $plan['rows'] ),
			default  => $rules->replace( $plan['rows'] ),
		};
	}

	/**
	 * End the current rule where an otherwise identical pattern ends, or leave it.
	 *
	 * @since TBD
	 *
	 * @param Rules                $rules   The event's rules.
	 * @param array<string, mixed> $current The current rule's row.
	 * @param array<string, mixed> $next    The new row.
	 *
	 * @return bool|WP_Error True once written or when nothing changes, or why it couldn't be.
	 */
	protected function move_end( Rules $rules, array $current, array $next ): bool|WP_Error {
		if ( $current['ends_on'] === $next['ends_on'] || null === $next['ends_on'] ) {
			return true;
		}

		$day_after = ( new DateTimeImmutable( $next['ends_on'], new DateTimeZone( 'UTC' ) ) )->modify( '+1 day' );

		return $rules->stop( $day_after->format( Rule::DATE_FORMAT ) );
	}

	/**
	 * The row of the rule that applies today, if any.
	 *
	 * @since TBD
	 *
	 * @param Rules $rules The event's rules.
	 *
	 * @return array<string, mixed>|null The row.
	 */
	protected function active_row( Rules $rules ): ?array {
		foreach ( $rules->all() as $rule ) {
			if ( 'active' === $this->group_of( $rule, $this->today( $rule->timezone ) ) ) {
				return $rule->to_meta();
			}
		}

		return null;
	}

	/**
	 * Whether two rows repeat the same way, apart from when they start and end.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $current A row.
	 * @param array<string, mixed> $next    Another row.
	 *
	 * @return bool True if only the dates differ.
	 */
	protected function same_pattern( array $current, array $next ): bool {
		$ignore = array(
			'change_id' => '',
			'starts_on' => '',
			'ends_on'   => '',
		);

		return array_diff_key( $current, $ignore ) === array_diff_key( $next, $ignore );
	}

	/**
	 * An event's current rows, without change IDs, for comparison.
	 *
	 * @since TBD
	 *
	 * @param Rules $rules The event's rules.
	 *
	 * @return array<int, array<string, mixed>> The rows.
	 */
	protected function rows_of( Rules $rules ): array {
		$rows = array_map( static fn( Rule $rule ): array => $rule->to_meta(), $rules->all() );

		return $this->without_change_ids( $rows );
	}

	/**
	 * Rows without their change IDs.
	 *
	 * @since TBD
	 *
	 * @param array<int, array<string, mixed>> $rows The rows.
	 *
	 * @return array<int, array<string, mixed>> The rows.
	 */
	protected function without_change_ids( array $rows ): array {
		return array_map( static fn( array $row ): array => array_diff_key( $row, array( 'change_id' => '' ) ), $rows );
	}

	/**
	 * A rule row from a pattern and the event's dates.
	 *
	 * The time of day and the length come from the pattern when it gives
	 * them, as a schedule change can move them, and from the event's dates
	 * otherwise.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $pattern   The pattern.
	 * @param array<string, mixed> $dates     The event's dates in the request.
	 * @param string               $starts_on The rule's first date.
	 *
	 * @return array<string, mixed> The row.
	 */
	protected function row( array $pattern, array $dates, string $starts_on ): array {
		$times = $dates['is_all_day'] ? array() : array( $dates['start']->format( 'H:i' ) );

		return array(
			'starts_on'  => $starts_on,
			'ends_on'    => $pattern['ends_on'] ?? null,
			'rrule'      => $this->rrule( $pattern ),
			'is_all_day' => $dates['is_all_day'],
			'times'      => $dates['is_all_day'] ? array() : ( $pattern['times'] ?? $times ),
			'timezone'   => $dates['timezone'],
			'duration'   => $pattern['duration'] ?? $dates['duration'],
		);
	}

	/**
	 * A row in canonical form, or why it isn't a valid rule.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $row The row.
	 *
	 * @return array<string, mixed>|WP_Error The canonical row, without a change ID, or the error.
	 */
	protected function valid_row( array $row ): array|WP_Error {
		$rule = Rule::from_meta( $row );

		if ( null === $rule ) {
			return $this->error( __( 'The repeating schedule is not valid.', 'gatherpress' ) );
		}

		return array_diff_key( $rule->to_meta(), array( 'change_id' => '' ) );
	}

	/**
	 * The `RRULE` a pattern describes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $pattern The pattern.
	 *
	 * @return string The pattern as an `RRULE` string, which `Rule` validates.
	 */
	protected function rrule( array $pattern ): string {
		$parts    = array( 'FREQ=' . strtoupper( (string) ( $pattern['frequency'] ?? '' ) ) );
		$interval = (int) ( $pattern['interval'] ?? 1 );
		$ordinal  = $pattern['ordinal_weekday'] ?? null;

		if ( 1 < $interval ) {
			$parts[] = 'INTERVAL=' . $interval;
		}

		if ( ! empty( $pattern['weekdays'] ) && is_array( $pattern['weekdays'] ) ) {
			$parts[] = 'BYDAY=' . implode( ',', array_map( 'strval', $pattern['weekdays'] ) );
		} elseif ( is_array( $ordinal ) && 2 === count( $ordinal ) ) {
			$parts[] = 'BYDAY=' . (int) $ordinal[0] . strtoupper( (string) $ordinal[1] );
		} elseif ( ! empty( $pattern['month_day'] ) ) {
			$parts[] = 'BYMONTHDAY=' . (int) $pattern['month_day'];
		}

		return implode( ';', $parts );
	}

	/**
	 * The pattern's first date on or after the change date.
	 *
	 * The new rule starts there, so its interval counts from that date: every
	 * other Thursday from a Saturday starts on the next Thursday, as an
	 * organizer means it, rather than skipping a week as counting from the
	 * change date's week would.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $pattern The pattern.
	 * @param array<string, mixed> $dates   The event's dates in the request.
	 * @param DateTimeImmutable    $from    The change date.
	 *
	 * @return string|null The date, or null if the pattern has none.
	 */
	protected function first_date( array $pattern, array $dates, DateTimeImmutable $from ): ?string {
		// A rule's start always counts as a date, so ask one starting the day before, without the interval.
		$day_before = $from->modify( '-1 day' )->format( Rule::DATE_FORMAT );
		$every      = Rule::from_meta( $this->row( array( 'interval' => 1 ) + $pattern, $dates, $day_before ) );

		return $every?->next_date( $from )?->format( Rule::DATE_FORMAT );
	}

	/**
	 * The change date for a started series: the one requested, or tomorrow.
	 *
	 * @since TBD
	 *
	 * @param mixed  $from     The requested date, as `Y-m-d`, if any.
	 * @param string $timezone The event's timezone.
	 *
	 * @return DateTimeImmutable|WP_Error The date at midnight UTC, or why it can't be used.
	 */
	protected function change_date( mixed $from, string $timezone ): DateTimeImmutable|WP_Error {
		$today = $this->today( new DateTimeZone( $timezone ) );
		$date  = null === $from ? $today->modify( '+1 day' ) : null;

		if ( is_string( $from ) ) {
			$parsed = DateTimeImmutable::createFromFormat( '!' . Rule::DATE_FORMAT, $from, new DateTimeZone( 'UTC' ) );
			$date   = $parsed && $parsed->format( Rule::DATE_FORMAT ) === $from ? $parsed : null;
		}

		if ( null === $date || $date <= $today ) {
			return $this->error(
				__( 'A schedule change on a started event applies from tomorrow or later.', 'gatherpress' )
			);
		}

		return $date;
	}

	/**
	 * The event's dates as this request leaves them.
	 *
	 * @since TBD
	 *
	 * @param int             $post_id The event's post ID, or 0.
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array{start: DateTimeImmutable, end: DateTimeImmutable, timezone: string, is_all_day: bool,
	 *     duration: int}|null The dates, or null if the event has none.
	 */
	protected function request_dates( int $post_id, WP_REST_Request $request ): ?array {
		$meta  = $request->get_param( 'meta' );
		$meta  = is_array( $meta ) ? $meta : array();
		$saved = $post_id ? get_post_meta( $post_id, 'gatherpress_datetime', true ) : '';
		$json  = json_decode( (string) ( $meta['gatherpress_datetime'] ?? $saved ), true );
		$start = is_array( $json ) ? (string) ( $json['dateTimeStart'] ?? '' ) : '';
		$end   = is_array( $json ) ? (string) ( $json['dateTimeEnd'] ?? '' ) : '';

		if ( ! Validate::datetime( $start ) || ! Validate::datetime( $end ) ) {
			return null;
		}

		$timezone   = Utility::normalize_timezone_string( (string) ( $json['timezone'] ?? wp_timezone_string() ) );
		$zone       = new DateTimeZone( $timezone );
		$start      = new DateTimeImmutable( $start, $zone );
		$end        = new DateTimeImmutable( $end, $zone );
		$is_all_day = (bool) ( $meta['gatherpress_is_all_day']
			?? get_post_meta( $post_id, 'gatherpress_is_all_day', true ) );

		return array(
			'start'      => $start,
			'end'        => $end,
			'timezone'   => $timezone,
			'is_all_day' => $is_all_day,
			'duration'   => $is_all_day
				? 1440 * ( (int) $start->diff( $end )->days + 1 )
				: max( 0, intdiv( $end->getTimestamp() - $start->getTimestamp(), 60 ) ),
		);
	}

	/**
	 * Whether the event is published and its first date has started, as saved or as the request moves it.
	 *
	 * Either counts, matching `Rules::replace()`, which refuses an event whose saved start has passed.
	 *
	 * @since TBD
	 *
	 * @param int                  $post_id The event's post ID, or 0.
	 * @param WP_REST_Request      $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @param array<string, mixed> $dates   The event's dates in the request.
	 *
	 * @return bool True for a started, published event.
	 */
	protected function has_started( int $post_id, WP_REST_Request $request, array $dates ): bool {
		$status    = $request->get_param( 'status' );
		$status    = is_string( $status ) ? $status : get_post_status( $post_id );
		$published = in_array( $status, Rules::PUBLISHED_STATUSES, true );
		$now       = time();

		$saved = $post_id && ( new Event( $post_id ) )->has_event_started();

		return $published && ( $dates['start']->getTimestamp() <= $now || $saved );
	}

	/**
	 * The event's timezone.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 *
	 * @return DateTimeZone The timezone.
	 */
	protected function event_timezone( Event $event ): DateTimeZone {
		return new DateTimeZone( Utility::normalize_timezone_string( $event->get_timezone() ) );
	}

	/**
	 * Today in a timezone, as a rule date at midnight UTC.
	 *
	 * @since TBD
	 *
	 * @param DateTimeZone $timezone The timezone.
	 *
	 * @return DateTimeImmutable Today.
	 */
	protected function today( DateTimeZone $timezone ): DateTimeImmutable {
		$local = ( new DateTimeImmutable( 'now', $timezone ) )->format( Rule::DATE_FORMAT );

		return new DateTimeImmutable( $local, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * The error a pattern with no dates after the change date gives.
	 *
	 * @since TBD
	 *
	 * @return WP_Error The error.
	 */
	protected function no_dates(): WP_Error {
		return $this->error( __( 'The repeating schedule has no dates on or after the change date.', 'gatherpress' ) );
	}

	/**
	 * A schedule error for the editor to show.
	 *
	 * @since TBD
	 *
	 * @param string $message The message.
	 *
	 * @return WP_Error The error, with a 400 status.
	 */
	protected function error( string $message ): WP_Error {
		return new WP_Error( 'gatherpress_invalid_repeat', $message, array( 'status' => 400 ) );
	}
}
