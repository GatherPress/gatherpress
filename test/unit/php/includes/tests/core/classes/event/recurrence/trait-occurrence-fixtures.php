<?php
/**
 * Shared fixtures for the recurring-events test suites.
 *
 * The filename deliberately does not match `class-test-*.php`, so PHPUnit does
 * not collect this file as a test case. It is required once from
 * `test/unit/php/bootstrap.php` and consumed with `use Occurrence_Fixtures;`
 * inside a test class.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Rule;
use GatherPress\Core\Event\Setup as Event_Setup;
use ReflectionClass;

/**
 * Trait Occurrence_Fixtures.
 *
 * Builds a real recurring event post and states the expected occurrence set for
 * its reference weekly rule, so the expander tests and the persistence
 * tests assert against one fixture rather than two hand-written lists.
 *
 * @since 0.36.0
 */
trait Occurrence_Fixtures {

	/**
	 * Local start of the reference series anchor, `Y-m-d H:i:s`.
	 *
	 * A **Thursday**, deliberately. The reference rule is bi-weekly Tue/Thu, and
	 * anchoring it on a Monday hides the WKST bug entirely: a day-delta walk only
	 * lands in the wrong week bucket when the anchor falls late in its
	 * Monday-start week.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	protected string $reference_anchor_start = '2026-09-03 18:00:00';

	/**
	 * Local end of the reference series anchor, `Y-m-d H:i:s`.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	protected string $reference_anchor_end = '2026-09-03 20:00:00';

	/**
	 * Create a real recurring event post.
	 *
	 * Writes the `gatherpress_datetime` blob the way the plugin already writes
	 * it, runs `Event\Setup::set_datetimes()` so the derived datetime mirrors and
	 * the `wp_gatherpress_events` row exist, then writes the
	 * `gatherpress_recurrence` blob for the passed rule.
	 *
	 * This works against the tree as it stands today, before any recurrence
	 * behavior exists. Writing the rule blob is a plain `add_post_meta()` call,
	 * so the fixture never depends on the code the tests are about to drive out.
	 *
	 * @since 0.36.0
	 *
	 * @param array  $rule     Recurrence rule values, stored as the `gatherpress_recurrence` JSON blob.
	 * @param string $timezone Named tz-database identifier for the series.
	 *
	 * @return int The created post ID.
	 */
	public function create_recurring_event( array $rule, string $timezone = 'America/New_York' ): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Downtown WordPress Meetup',
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $this->reference_anchor_start,
					'dateTimeEnd'   => $this->reference_anchor_end,
					'timezone'      => $timezone,
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );

		add_post_meta( $post_id, 'gatherpress_recurrence', wp_json_encode( $rule ) );

		return (int) $post_id;
	}

	/**
	 * Create and project a recurring event anchored relative to "now".
	 *
	 * The counterpart to `create_recurring_event()` for every test whose
	 * subject compares an occurrence against `current_time()` rather than
	 * against a stated calendar expectation. `select_upcoming()` /
	 * `select_past()` do it directly; so, less obviously, does anything driving
	 * an RSVP write, because `Rest_Api::update_rsvp()` gates on
	 * `! $event->has_event_past()` and `Rsvp\Form` bails 400 on the same check.
	 *
	 * Pinning a fixture date and comparing it against the wall clock is a date
	 * bomb: it passes until real time crosses the anchor and then fails for a
	 * reader with no context. The fixed-anchor
	 * `$reference_anchor_start` above is correct for the expander and
	 * persistence suites, which assert a *stated* occurrence set where the exact
	 * calendar dates are the specification and WKST week buckets depend on
	 * them. Those must not move, so anything time-relative uses this instead.
	 *
	 * @since 0.36.0
	 *
	 * @param array             $rule     Recurrence rule values.
	 * @param DateTimeImmutable $start    Anchor start, in `$timezone`.
	 * @param DateTimeImmutable $end      Anchor end, in `$timezone`.
	 * @param string            $timezone Named tz-database identifier for the series.
	 *
	 * @return int The projected post ID.
	 */
	public function create_relative_recurring_event(
		array $rule,
		DateTimeImmutable $start,
		DateTimeImmutable $end,
		string $timezone = 'America/New_York'
	): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $start->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $end->format( 'Y-m-d H:i:s' ),
					'timezone'      => $timezone,
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );
		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( $rule ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		return (int) $post_id;
	}

	/**
	 * Build a `Rule` directly through its private constructor via reflection.
	 *
	 * Used only to exercise `is_valid()` branches that `from_array()`'s boundary
	 * guards make unreachable through the public API, along with the expander's
	 * own defensive arms downstream of them (e.g. an `end_type` of
	 * `count` carrying a stray `until`, or an unrecognized frequency, both of
	 * which `from_array()` already rejects before a `Rule` is ever built).
	 * Shared between `Test_Rule`, which exercises `is_valid()` itself, and
	 * `Test_Expander`, which exercises what the expander does when it is
	 * handed one of these deliberately-invalid shapes directly.
	 *
	 * @since 0.36.0
	 *
	 * @param array $args Constructor arguments, in declaration order.
	 *
	 * @return Rule The directly constructed rule, bypassing `from_array()`.
	 */
	public function build_rule_directly( array $args ): Rule {
		$reflection  = new ReflectionClass( Rule::class );
		$constructor = $reflection->getConstructor();
		$constructor->setAccessible( true );
		$instance = $reflection->newInstanceWithoutConstructor();
		$constructor->invoke( $instance, ...$args );

		return $instance;
	}

	/**
	 * Get the canonical expected occurrence set for the reference weekly rule.
	 *
	 * The reference rule is the one the demo opens with, and the one that makes
	 * WKST load-bearing:
	 *
	 *     array(
	 *         'frequency' => 'weekly',
	 *         'interval'  => 2,
	 *         'weekdays'  => array( 2, 4 ),
	 *         'end_type'  => 'count',
	 *         'count'     => 5,
	 *     )
	 *
	 * anchored on Thursday 2026-09-03 18:00:00 in `America/New_York`. Against a
	 * Monday-start week index that gives week buckets 0, 2, 2, 4, 4, the exact
	 * sequence `week_index()` must produce. A day-delta implementation produces a
	 * different set, which is the point.
	 *
	 * Every entry is a datetime, never a date, and its
	 * `recurrence_id` is its **local** start in `Ymd\THis`. The GMT
	 * columns are four hours ahead because the whole set falls inside
	 * `America/New_York`'s 2026 daylight saving period.
	 *
	 * Note what is deliberately absent: Tuesday 2026-09-01 shares the anchor's
	 * Monday-start week bucket and satisfies the weekday list, but the walk
	 * begins at the anchor date, so it is not an occurrence. A six-entry result
	 * starting `20260901T180000` is a start-boundary bug, not a WKST bug.
	 *
	 * @since 0.36.0
	 *
	 * @return array<int, array<string, string>> Ordered ascending, keyed as the occurrence table's columns are.
	 */
	public function expected_weekly_set(): array {
		return array(
			array(
				'recurrence_id'      => '20260903T180000',
				'datetime_start'     => '2026-09-03 18:00:00',
				'datetime_start_gmt' => '2026-09-03 22:00:00',
				'datetime_end'       => '2026-09-03 20:00:00',
				'datetime_end_gmt'   => '2026-09-04 00:00:00',
			),
			array(
				'recurrence_id'      => '20260915T180000',
				'datetime_start'     => '2026-09-15 18:00:00',
				'datetime_start_gmt' => '2026-09-15 22:00:00',
				'datetime_end'       => '2026-09-15 20:00:00',
				'datetime_end_gmt'   => '2026-09-16 00:00:00',
			),
			array(
				'recurrence_id'      => '20260917T180000',
				'datetime_start'     => '2026-09-17 18:00:00',
				'datetime_start_gmt' => '2026-09-17 22:00:00',
				'datetime_end'       => '2026-09-17 20:00:00',
				'datetime_end_gmt'   => '2026-09-18 00:00:00',
			),
			array(
				'recurrence_id'      => '20260929T180000',
				'datetime_start'     => '2026-09-29 18:00:00',
				'datetime_start_gmt' => '2026-09-29 22:00:00',
				'datetime_end'       => '2026-09-29 20:00:00',
				'datetime_end_gmt'   => '2026-09-30 00:00:00',
			),
			array(
				'recurrence_id'      => '20261001T180000',
				'datetime_start'     => '2026-10-01 18:00:00',
				'datetime_start_gmt' => '2026-10-01 22:00:00',
				'datetime_end'       => '2026-10-01 20:00:00',
				'datetime_end_gmt'   => '2026-10-02 00:00:00',
			),
		);
	}

	/**
	 * Make the nth occurrence-table insert of the request fail at the database.
	 *
	 * The insert is redirected to a table name that does not exist, so
	 * `$wpdb->query()` genuinely returns `false` the way a missing table, a
	 * `max_allowed_packet` overflow or a read-only replica makes it return
	 * `false` in production. No DDL is involved, so the surrounding
	 * transaction is never committed and no fixture leaks into the rest of
	 * the run.
	 *
	 * Counting the matching statements is what lets a caller choose *which*
	 * projection of a multi-projection operation to break: a forward split
	 * projects the forward post's rule first and the capped origin's second,
	 * and its rollback projects the origin again third.
	 *
	 * The real table is left in place, so `maybe_install_missing_table()`
	 * finds it present, declines to heal, and the failure propagates instead
	 * of being retried.
	 *
	 * @since 0.36.0
	 *
	 * @param int $nth Which matching insert to break, counted from 1.
	 *
	 * @return callable The filter, for `remove_filter( 'query', ... )`.
	 */
	public function break_nth_occurrence_insert( int $nth ): callable {
		global $wpdb;

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );
		$seen  = 0;

		$filter = static function ( $query ) use ( $table, $nth, &$seen ) {
			if ( str_starts_with( (string) $query, 'INSERT' ) && str_contains( (string) $query, $table ) ) {
				++$seen;

				if ( $nth === $seen ) {
					return str_replace( $table, $table . '_gone', (string) $query );
				}
			}

			return $query;
		};

		add_filter( 'query', $filter );

		return $filter;
	}
}
