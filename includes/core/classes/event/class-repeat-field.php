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
 * A save is planned on `rest_pre_insert_{post_type}`, before anything is
 * saved, so a schedule that can't be written fails the request and leaves
 * the post as it was. The same plan is carried out once the post and its
 * dates are saved, through the `Rules` methods, which keep a published
 * schedule's past:
 *
 * - An event that wasn't both published and started before the request
 *   gets its rules replaced, so a first publish keeps the dates entered.
 * - A started, published event changes from tomorrow or a later date. A
 *   change to an unchanged pattern continues the current rule's cycle, so
 *   extending or clearing its end keeps every other week aligned.
 * - An unchanged pattern writes nothing, and one that only brings the end
 *   date earlier ends the current rule there.
 * - A change already scheduled for a later date is kept unless the request
 *   sets `scheduled` to null.
 *
 * Two saves at once write in turn, and the later one wins.
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
	 * Plans made before a request saved anything, by request.
	 *
	 * @since TBD
	 * @var array<int, array<string, mixed>>
	 */
	protected array $plans = array();

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
					'schema'          => $this->schema(),
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
	 * Plan a save's schedule before anything is saved, refusing one that can't be written.
	 *
	 * Hooked to `rest_pre_insert_{post_type}`. A `Rules` method can still
	 * refuse after the post is saved, such as when the database fails a
	 * write, and then the post is saved without the schedule change.
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

		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$this->plans[ spl_object_id( $request ) ] = $plan;

		return $prepared_post;
	}

	/**
	 * Carry out the save's plan once the post and its dates are saved.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param mixed           $value   The field's value. Read from the plan.
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
		$key  = spl_object_id( $request );
		$plan = $this->plans[ $key ] ?? $this->plan( $post->ID, $request );

		unset( $this->plans[ $key ] );

		return is_wp_error( $plan ) ? $plan : $this->apply( $post->ID, $plan );
	}

	/**
	 * The field's schema.
	 *
	 * @since TBD
	 *
	 * @return array<string, mixed> The schema.
	 */
	protected function schema(): array {
		return array(
			'description' => __( 'The event\'s repeating schedule.', 'gatherpress' ),
			'type'        => array( 'object', 'null' ),
			'context'     => array( 'view', 'edit' ),
			'properties'  => array(
				'pattern'   => array(
					'description' => __( 'The rule that applies today, or null to stop repeating.', 'gatherpress' ),
					'type'        => array( 'object', 'null' ),
				),
				'scheduled' => array(
					'description' => __(
						'A change starting later. Left out of a save, it is kept; null removes it.',
						'gatherpress'
					),
					'type'        => array( 'object', 'null' ),
				),
				'from'      => array(
					'description' => __( 'The first date a change to a started event applies.', 'gatherpress' ),
					'type'        => array( 'string', 'null' ),
				),
				'ended'     => array(
					'description' => __( 'Whether every rule has ended.', 'gatherpress' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'showable'  => array(
					'description' => __( 'Whether one pattern can show the schedule.', 'gatherpress' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
			),
		);
	}

	/**
	 * Describe an event's rules as one current schedule.
	 *
	 * Tomorrow is the first date an edit can change, so a rule with no dates
	 * from tomorrow on is history. Of the rest, in start order, the first is
	 * the pattern and a second is a scheduled change. When every rule is
	 * history, the last is the pattern and the series is marked ended.
	 * Anything the editor can't show as one pattern is marked not showable.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 *
	 * @return array{pattern: array<string, mixed>|null, scheduled: array<string, mixed>|null, ended: bool,
	 *     showable: bool} The schedule.
	 */
	protected function describe_schedule( Event $event ): array {
		$groups    = $this->grouped( $event );
		$current   = $groups['live'][0] ?? null;
		$scheduled = $groups['live'][1] ?? null;
		$last      = $groups['ended'] ? end( $groups['ended'] ) : null;
		// A scheduled change follows the current rule; two rules running at once can't show as one pattern.
		$follows  = null === $scheduled || ( null !== $current?->ends_on && $current->ends_on < $scheduled->starts_on );
		$showable = count( $groups['live'] ) <= 2 && $follows;

		foreach ( $groups['live'] as $rule ) {
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
	 * An event's rules as live ones, in start order, and history, from tomorrow in the event's timezone.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 *
	 * @return array{live: Rule[], ended: Rule[]} The rules.
	 */
	protected function grouped( Event $event ): array {
		$tomorrow = $this->today( $this->event_timezone( $event ) )->modify( '+1 day' );
		$live     = array();
		$ended    = array();

		foreach ( $event->rules()->all() as $rule ) {
			if ( null === $rule->next_date( $tomorrow ) ) {
				$ended[] = $rule;
			} else {
				$live[] = $rule;
			}
		}

		usort( $live, static fn( Rule $a, Rule $b ): int => $a->starts_on <=> $b->starts_on );

		return array(
			'live'  => $live,
			'ended' => $ended,
		);
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
	 * @return array<string, mixed>|WP_Error The plan, with its `action`, or why the schedule can't be written.
	 */
	protected function plan( int $post_id, WP_REST_Request $request ): array|WP_Error {
		$value   = $request->get_param( self::FIELD );
		$pattern = is_array( $value ) ? ( $value['pattern'] ?? null ) : null;
		$dates   = $this->request_dates( $post_id, $request );

		if ( ! is_array( $value ) || ( null !== $pattern && ! is_array( $pattern ) ) ) {
			return $this->error( __( 'The repeating schedule is not in the expected form.', 'gatherpress' ) );
		}

		if ( null === $dates ) {
			return $this->error(
				__( 'The event needs a valid date and timezone before it can repeat.', 'gatherpress' )
			);
		}

		return $this->has_started( $post_id )
			? $this->plan_change( new Event( $post_id ), $value, $dates )
			: $this->plan_replace( $post_id, $pattern, $dates );
	}

	/**
	 * Plan replacing the rules of an event that hasn't started.
	 *
	 * @since TBD
	 *
	 * @param int                       $post_id The event's post ID, or 0.
	 * @param array<string, mixed>|null $pattern The pattern, or null to stop repeating.
	 * @param array<string, mixed>      $dates   The event's dates in the request.
	 *
	 * @return array<string, mixed>|WP_Error The plan, or why the pattern is invalid.
	 */
	protected function plan_replace( int $post_id, ?array $pattern, array $dates ): array|WP_Error {
		$rows = array();

		if ( null !== $pattern ) {
			$row = $this->valid_row( $this->row( $pattern, $dates, $dates['start']->format( Rule::DATE_FORMAT ) ) );

			if ( is_wp_error( $row ) ) {
				return $row;
			}

			$rows[] = $row;
		}

		$unchanged = $post_id && $this->rows_of( ( new Event( $post_id ) )->rules()->all() ) === $rows;

		return array(
			'action' => $unchanged ? 'none' : 'replace',
			'rows'   => $rows,
		);
	}

	/**
	 * Plan changing the schedule of a published event that has started.
	 *
	 * @since TBD
	 *
	 * @param Event                $event The event.
	 * @param array<string, mixed> $value The field's value.
	 * @param array<string, mixed> $dates The event's dates in the request.
	 *
	 * @return array<string, mixed>|WP_Error The plan, or why the change can't be made.
	 */
	protected function plan_change( Event $event, array $value, array $dates ): array|WP_Error {
		$from = $this->change_date( $value['from'] ?? null, $dates['timezone'] );

		if ( is_wp_error( $from ) || null === ( $value['pattern'] ?? null ) ) {
			return is_wp_error( $from ) ? $from : array(
				'action' => 'stop',
				'from'   => $from->format( Rule::DATE_FORMAT ),
			);
		}

		$groups    = $this->grouped( $event );
		$current   = $groups['live'][0] ?? null;
		$scheduled = array_key_exists( 'scheduled', $value ) ? null : ( $groups['live'][1] ?? null );
		$row       = $this->change_row( $value['pattern'], $dates, $from, $current );

		if ( is_wp_error( $row ) ) {
			return $row;
		}

		return $this->change_or_less( $row, $from, $current, $scheduled );
	}

	/**
	 * The plan for a change: nothing, ending the current rule earlier, or a change from a date.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $row       The new rule's row.
	 * @param DateTimeImmutable    $from      The change date.
	 * @param Rule|null            $current   The rule that applies today, if any.
	 * @param Rule|null            $scheduled A scheduled change to keep, if any.
	 *
	 * @return array<string, mixed> The plan.
	 */
	protected function change_or_less( array $row, DateTimeImmutable $from, ?Rule $current, ?Rule $scheduled ): array {
		$same  = $current && $this->same_pattern( $this->without_change_id( $current->to_meta() ), $row );
		$ends  = $current?->ends_on?->format( Rule::DATE_FORMAT );
		$plain = $same && null === $scheduled;

		if ( $plain && $ends === $row['ends_on'] ) {
			return array( 'action' => 'none' );
		}

		// Bringing the end earlier only ends the current rule; extending or clearing it continues the cycle.
		if ( $plain && null !== $row['ends_on'] && ( null === $ends || $row['ends_on'] < $ends ) ) {
			return array(
				'action' => 'stop',
				'from'   => $this->day_after( $row['ends_on'] ),
			);
		}

		return array(
			'action' => 'change',
			'from'   => $from->format( Rule::DATE_FORMAT ),
			'rows'   => $this->with_scheduled( $row, $scheduled ),
		);
	}

	/**
	 * The rows of a change, keeping a scheduled change after the new rule.
	 *
	 * The new rule ends the day before the scheduled one starts, or is left
	 * out when it would start no earlier.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $row       The new rule's row.
	 * @param Rule|null            $scheduled The scheduled change to keep, if any.
	 *
	 * @return array<int, array<string, mixed>> The rows.
	 */
	protected function with_scheduled( array $row, ?Rule $scheduled ): array {
		if ( null === $scheduled ) {
			return array( $row );
		}

		$before = $scheduled->starts_on->modify( '-1 day' )->format( Rule::DATE_FORMAT );
		$kept   = $this->without_change_id( $scheduled->to_meta() );

		if ( $row['starts_on'] > $before ) {
			return array( $kept );
		}

		$row['ends_on'] = null === $row['ends_on'] ? $before : min( $row['ends_on'], $before );

		return array( $row, $kept );
	}

	/**
	 * The new rule of a change, starting on its first date on or after the change date.
	 *
	 * An unchanged pattern continues the current rule's cycle. A new weekly
	 * or monthly pattern must name its days, since the change date is no
	 * guide to them, and a yearly one keeps the series' anniversary.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $pattern The pattern.
	 * @param array<string, mixed> $dates   The event's dates in the request.
	 * @param DateTimeImmutable    $from    The change date.
	 * @param Rule|null            $current The rule that applies today, if any.
	 *
	 * @return array<string, mixed>|WP_Error The row, or why the change can't be made.
	 */
	protected function change_row(
		array $pattern,
		array $dates,
		DateTimeImmutable $from,
		?Rule $current
	): array|WP_Error {
		$candidate = $this->valid_row( $this->row( $pattern, $dates, $from->format( Rule::DATE_FORMAT ) ) );

		if ( is_wp_error( $candidate ) ) {
			return $candidate;
		}

		if ( ! $this->names_its_days( $pattern ) ) {
			return $this->error( __( 'Choose which days the new schedule repeats on.', 'gatherpress' ) );
		}

		$same  = $current && $this->same_pattern( $this->without_change_id( $current->to_meta() ), $candidate );
		$first = $same
			? Rule::from_meta( array_merge( $current->to_meta(), array( 'ends_on' => null ) ) )?->next_date( $from )
			: $this->first_date( $pattern, $dates, $from );

		return null === $first
			? $this->error( __( 'The repeating schedule has no dates on or after the change date.', 'gatherpress' ) )
			: $this->valid_row( $this->row( $pattern, $dates, $first->format( Rule::DATE_FORMAT ) ) );
	}

	/**
	 * Whether a pattern names the days it repeats on, where its frequency needs them.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $pattern The pattern.
	 *
	 * @return bool True for a daily or yearly pattern, or a weekly or monthly one with its days.
	 */
	protected function names_its_days( array $pattern ): bool {
		return match ( strtoupper( (string) ( $pattern['frequency'] ?? '' ) ) ) {
			'WEEKLY'  => ! empty( $pattern['weekdays'] ),
			'MONTHLY' => ! empty( $pattern['month_day'] ) || ! empty( $pattern['ordinal_weekday'] ),
			default   => true,
		};
	}

	/**
	 * The pattern's first date on or after the change date.
	 *
	 * The new rule starts there, so its interval counts from that date: every
	 * other Thursday from a Saturday starts on the next Thursday, as an
	 * organizer means it, rather than skipping a week as counting from the
	 * change date's week would. A yearly pattern counts from the series'
	 * first date, so it keeps its anniversary.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $pattern The pattern.
	 * @param array<string, mixed> $dates   The event's dates in the request.
	 * @param DateTimeImmutable    $from    The change date.
	 *
	 * @return DateTimeImmutable|null The date, or null if the pattern has none.
	 */
	protected function first_date( array $pattern, array $dates, DateTimeImmutable $from ): ?DateTimeImmutable {
		$yearly = 'YEARLY' === strtoupper( (string) ( $pattern['frequency'] ?? '' ) );
		// A rule's start always counts as a date, so ask one starting the day before, without the interval.
		$start = $yearly ? $dates['start'] : $from->modify( '-1 day' );
		$shape = $yearly ? $pattern : array( 'interval' => 1 ) + $pattern;
		$probe = Rule::from_meta( $this->row( $shape, $dates, $start->format( Rule::DATE_FORMAT ) ) );

		return $probe?->next_date( $from );
	}

	/**
	 * Carry out a plan.
	 *
	 * @since TBD
	 *
	 * @param int                  $post_id The event's post ID.
	 * @param array<string, mixed> $plan    The plan.
	 *
	 * @return bool|WP_Error True once written, or why it couldn't be.
	 */
	protected function apply( int $post_id, array $plan ): bool|WP_Error {
		$rules = ( new Event( $post_id ) )->rules();

		return match ( $plan['action'] ) {
			'none'   => true,
			'stop'   => $rules->stop( $plan['from'] ),
			'change' => $rules->change_from( $plan['from'], $plan['rows'] ),
			default  => $rules->replace( $plan['rows'] ),
		};
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
			'starts_on' => '',
			'ends_on'   => '',
		);

		return array_diff_key( $current, $ignore ) === array_diff_key( $next, $ignore );
	}

	/**
	 * Rules as rows without change IDs, for comparison.
	 *
	 * @since TBD
	 *
	 * @param Rule[] $rules The rules.
	 *
	 * @return array<int, array<string, mixed>> The rows.
	 */
	protected function rows_of( array $rules ): array {
		return array_map( fn( Rule $rule ): array => $this->without_change_id( $rule->to_meta() ), $rules );
	}

	/**
	 * A row without its change ID.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $row The row.
	 *
	 * @return array<string, mixed> The row.
	 */
	protected function without_change_id( array $row ): array {
		return array_diff_key( $row, array( 'change_id' => '' ) );
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

		return $this->without_change_id( $rule->to_meta() );
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
	 * The request's `gatherpress_datetime` meta, or the saved one, or for an
	 * event without it, such as one imported, its saved dates.
	 *
	 * @since TBD
	 *
	 * @param int             $post_id The event's post ID, or 0.
	 * @param WP_REST_Request $request The request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @return array{start: DateTimeImmutable, end: DateTimeImmutable, timezone: string, is_all_day: bool,
	 *     duration: int}|null The dates, or null without a valid date and timezone.
	 */
	protected function request_dates( int $post_id, WP_REST_Request $request ): ?array {
		$meta     = $request->get_param( 'meta' );
		$meta     = is_array( $meta ) ? $meta : array();
		$payload  = $this->datetime_payload( $post_id, $meta );
		$timezone = Utility::normalize_timezone_string( $payload['timezone'] );

		$valid = Validate::datetime( $payload['start'] )
			&& Validate::datetime( $payload['end'] )
			&& Validate::timezone( $timezone );

		if ( ! $valid ) {
			return null;
		}

		$zone       = new DateTimeZone( $timezone );
		$start      = new DateTimeImmutable( $payload['start'], $zone );
		$end        = new DateTimeImmutable( $payload['end'], $zone );
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
	 * The start, end and timezone a request leaves an event with, as strings.
	 *
	 * @since TBD
	 *
	 * @param int                  $post_id The event's post ID, or 0.
	 * @param array<string, mixed> $meta    The request's meta.
	 *
	 * @return array{start: string, end: string, timezone: string} The strings, empty when unknown.
	 */
	protected function datetime_payload( int $post_id, array $meta ): array {
		$saved = $post_id ? get_post_meta( $post_id, 'gatherpress_datetime', true ) : '';
		$json  = json_decode( (string) ( $meta['gatherpress_datetime'] ?? $saved ), true );

		if ( is_array( $json ) && ! empty( $json['dateTimeStart'] ) ) {
			return array(
				'start'    => (string) $json['dateTimeStart'],
				'end'      => (string) ( $json['dateTimeEnd'] ?? '' ),
				'timezone' => (string) ( $json['timezone'] ?? wp_timezone_string() ),
			);
		}

		$event = new Event( $post_id );
		$dates = $event->get_saved_datetime();

		return array(
			'start'    => $dates['datetime_start'],
			'end'      => $dates['datetime_end'],
			'timezone' => $event->get_timezone(),
		);
	}

	/**
	 * Whether the event was published and its first date had started before this request.
	 *
	 * The state before the request decides, so an event's first publish,
	 * which nobody has seen yet, replaces its rules with the dates entered.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event's post ID, or 0.
	 *
	 * @return bool True for a started, published event.
	 */
	protected function has_started( int $post_id ): bool {
		return $post_id
			&& in_array( get_post_status( $post_id ), Rules::PUBLISHED_STATUSES, true )
			&& ( new Event( $post_id ) )->has_event_started();
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
	 * The day after a rule date.
	 *
	 * @since TBD
	 *
	 * @param string $date The date, as `Y-m-d`.
	 *
	 * @return string The next day, as `Y-m-d`.
	 */
	protected function day_after( string $date ): string {
		$day = new DateTimeImmutable( $date, new DateTimeZone( 'UTC' ) );

		return $day->modify( '+1 day' )->format( Rule::DATE_FORMAT );
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
