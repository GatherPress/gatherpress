<?php
/**
 * Date calculations for the Calendar blocks.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use WP_Block;

/**
 * Class Date_Calculator.
 *
 * Single source of truth for calendar date ranges, week calculations,
 * localized headings, and column layouts.
 *
 * @since TBD
 *
 * @phpstan-type DateRange array{
 *     view_type: string,
 *     unit_count: int,
 *     start_date: string,
 *     end_date: string,
 *     start_date_obj: DateTimeImmutable,
 *     end_date_obj: DateTimeImmutable,
 *     raw_week_start: DateTimeImmutable,
 *     target_date: DateTimeImmutable,
 *     year: int,
 *     month: int,
 *     heading: string
 * }
 */
final class Date_Calculator {

	/**
	 * The date format used throughout the calendar.
	 *
	 * @since TBD
	 * @var string
	 */
	const DATE_FORMAT = 'Y-m-d';

	/**
	 * A Sunday at noon UTC, used to name the days of the week.
	 *
	 * 2024-01-07 12:00:00 UTC.
	 *
	 * @since TBD
	 * @var int
	 */
	const REFERENCE_SUNDAY = 1704628800;

	/**
	 * Minutes in a day, the end of the last time-grid track.
	 *
	 * @since TBD
	 * @var int
	 */
	const MINUTES_PER_DAY = 1440;

	/**
	 * Retrieves the separator for date ranges in calendar headings.
	 *
	 * Uses the same `gatherpress_datetime_separator` filter as the Event Date block, with an
	 * en dash as the default.
	 *
	 * @since TBD
	 *
	 * @return string Filtered datetime separator.
	 */
	public static function get_datetime_separator(): string {
		$default_separator = _x( '–', 'Calendar heading date range separator', 'gatherpress' );
		$separator         = trim( Event\Event::get_datetime_separator( null, $default_separator ) );

		return '' !== $separator ? $separator : $default_separator;
	}

	/**
	 * Resolves the baseline target date applying date modifiers.
	 *
	 * @since TBD
	 *
	 * @param string $selected_date Selected date string.
	 * @param string $view_type     View type.
	 * @param int    $offset        Offset count.
	 *
	 * @return DateTimeImmutable
	 */
	private static function resolve_base_date(
		string $selected_date,
		string $view_type,
		int $offset
	): DateTimeImmutable {
		$tz        = wp_timezone();
		$base_date = null;

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
			$base_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $selected_date, $tz );
		} elseif ( preg_match( '/^\d{4}-\d{2}$/', $selected_date ) ) {
			$base_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $selected_date . '-01', $tz );
		}

		if ( ! $base_date instanceof DateTimeImmutable ) {
			$base_date = current_datetime();
		}

		// Normalize to the 1st of the month in month view to prevent overflow (e.g. Jan 31 + 1 month).
		if ( 'month' === $view_type ) {
			$base_date = $base_date->modify( 'first day of this month' );
		}

		if ( 0 !== $offset ) {
			$step_unit = 'month';
			if ( 'week' === $view_type ) {
				$step_unit = 'week';
			} elseif ( 'day' === $view_type ) {
				$step_unit = 'day';
			}
			$base_date = $base_date->modify( sprintf( '%+d %s', $offset, $step_unit ) );
		}

		return $base_date;
	}

	/**
	 * Computes visible week bounds for 1 or more consecutive weeks.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $base_date     Target base date.
	 * @param int               $start_of_week Start of week setting (0-6).
	 * @param bool              $show_weekends Weekend visibility.
	 * @param int               $unit_count    Number of weeks.
	 *
	 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: DateTimeImmutable} [start, end, raw_start].
	 */
	private static function calculate_week_bounds(
		DateTimeImmutable $base_date,
		int $start_of_week,
		bool $show_weekends,
		int $unit_count
	): array {
		$raw_week_start = self::get_week_start( $base_date, $start_of_week );
		$total_days     = $unit_count * 7;
		$visible_days   = array();

		for ( $i = 0; $i < $total_days; $i++ ) {
			$day_obj = $raw_week_start->modify( "+{$i} days" );

			if ( $show_weekends || ! self::is_weekend_day( (int) $day_obj->format( 'w' ) ) ) {
				$visible_days[] = $day_obj;
			}
		}

		if ( ! empty( $visible_days ) ) {
			$start_date_obj = $visible_days[0]->setTime( 0, 0, 0 );
			$end_date_obj   = $visible_days[ count( $visible_days ) - 1 ]->setTime( 23, 59, 59 );
		} else {
			$start_date_obj = $raw_week_start->setTime( 0, 0, 0 );
			$end_date_obj   = $raw_week_start->modify( sprintf( '+%d days', $total_days - 1 ) )->setTime( 23, 59, 59 );
		}

		return array( $start_date_obj, $end_date_obj, $raw_week_start );
	}

	/**
	 * Computes the start date, end date, and raw week start for a given view type.
	 *
	 * @since TBD
	 *
	 * @param string            $view_type     View type ('month', 'week', 'day').
	 * @param DateTimeImmutable $base_date     Baseline target date.
	 * @param int               $unit_count    Number of units.
	 * @param int               $start_of_week Start of week (0-6).
	 * @param bool              $show_weekends Whether weekends are visible.
	 *
	 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: DateTimeImmutable} [start, end, raw_start].
	 */
	private static function calculate_view_bounds(
		string $view_type,
		DateTimeImmutable $base_date,
		int $unit_count,
		int $start_of_week,
		bool $show_weekends
	): array {
		if ( 'week' === $view_type ) {
			return self::calculate_week_bounds( $base_date, $start_of_week, $show_weekends, $unit_count );
		}

		if ( 'day' === $view_type ) {
			$start = $base_date->setTime( 0, 0, 0 );
			$end   = $base_date->modify( sprintf( '+%d days', $unit_count - 1 ) )->setTime( 23, 59, 59 );

			return array( $start, $end, $start );
		}

		// Month view: spans from Month 1's first day to Month N's last day.
		$start = $base_date->modify( 'first day of this month' )->setTime( 0, 0, 0 );
		$end   = $base_date
			->modify( sprintf( '+%d months', $unit_count - 1 ) )
			->modify( 'last day of this month' )
			->setTime( 23, 59, 59 );

		return array( $start, $end, $start );
	}

	/**
	 * Builds a standardized date range result array.
	 *
	 * @since TBD
	 *
	 * @param string            $view_type      View type ('month', 'week', 'day').
	 * @param int               $unit_count     Number of units.
	 * @param DateTimeImmutable $start_date_obj Start date object.
	 * @param DateTimeImmutable $end_date_obj   End date object.
	 * @param DateTimeImmutable $raw_week_start Raw week start date object.
	 * @param DateTimeImmutable $target_date    Baseline target date object.
	 * @param string|null       $heading        Optional formatted heading override.
	 *
	 * @return DateRange
	 */
	private static function build_range_payload(
		string $view_type,
		int $unit_count,
		DateTimeImmutable $start_date_obj,
		DateTimeImmutable $end_date_obj,
		DateTimeImmutable $raw_week_start,
		DateTimeImmutable $target_date,
		?string $heading = null
	): array {
		return array(
			'view_type'      => $view_type,
			'unit_count'     => $unit_count,
			'start_date'     => $start_date_obj->format( self::DATE_FORMAT ),
			'end_date'       => $end_date_obj->format( self::DATE_FORMAT ),
			'start_date_obj' => $start_date_obj,
			'end_date_obj'   => $end_date_obj,
			'raw_week_start' => $raw_week_start,
			'target_date'    => $target_date,
			'year'           => (int) $target_date->format( 'Y' ),
			'month'          => (int) $target_date->format( 'n' ),
			'heading'        => $heading ?? self::format_heading( $view_type, $start_date_obj, $end_date_obj ),
		);
	}

	/**
	 * Resolves the last calendar day of an event.
	 *
	 * An event that ends at 00:00 on a later day does not run on that day, so it ends on the
	 * day before. An end before the start is treated as no end.
	 *
	 * @since TBD
	 *
	 * @param string $start_raw Start datetime (Y-m-d H:i:s).
	 * @param string $end_raw   End datetime (Y-m-d H:i:s), or an empty string.
	 *
	 * @return string Last day of the event (Y-m-d).
	 */
	private static function resolve_end_date( string $start_raw, string $end_raw ): string {
		$start_date = substr( $start_raw, 0, 10 );
		$end_date   = '' !== $end_raw ? substr( $end_raw, 0, 10 ) : $start_date;

		if ( $end_date > $start_date && '00:00:00' === substr( $end_raw, 11, 8 ) ) {
			$end_date = gmdate( self::DATE_FORMAT, (int) strtotime( $end_date . ' -1 day' ) );
		}

		return $end_date < $start_date ? $start_date : $end_date;
	}

	/**
	 * Retrieves the raw start and end datetimes of a post that supports event dates.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID to inspect.
	 *
	 * @return array{start: string, end: string}|null Start and end datetimes (Y-m-d H:i:s) or null.
	 */
	private static function get_post_event_raw_datetimes( int $post_id ): ?array {
		if ( $post_id <= 0 ) {
			return null;
		}

		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || ! post_type_supports( $post_type, Event\Event::SUPPORT ) ) {
			return null;
		}

		$datetimes = ( new Event\Event( $post_id ) )->get_datetime();

		if ( '' === $datetimes['datetime_start'] ) {
			return null;
		}

		return array(
			'start' => $datetimes['datetime_start'],
			'end'   => $datetimes['datetime_end'],
		);
	}

	/**
	 * Retrieves start and end date strings from a post supporting gatherpress-event-date.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID to inspect.
	 *
	 * @return array{start: string, end: string}|null Start and end Y-m-d date strings or null.
	 */
	public static function get_post_event_dates( int $post_id ): ?array {
		$raw = self::get_post_event_raw_datetimes( $post_id );

		if ( null === $raw ) {
			return null;
		}

		return array(
			'start' => substr( $raw['start'], 0, 10 ),
			'end'   => self::resolve_end_date( $raw['start'], $raw['end'] ),
		);
	}

	/**
	 * Calculates the number of calendar units spanned by an event's date range.
	 *
	 * @since TBD
	 *
	 * @param string $view_type      View type ('month', 'week', 'day').
	 * @param string $start_date_str Start date string (Y-m-d).
	 * @param string $end_date_str   End date string (Y-m-d).
	 *
	 * @return int Number of units.
	 */
	public static function calculate_post_span_units(
		string $view_type,
		string $start_date_str,
		string $end_date_str
	): int {
		$start_obj = self::parse_date_or_current( $start_date_str );
		$end_obj   = self::parse_date_or_current( $end_date_str );

		if ( 'day' === $view_type ) {
			$days_diff = (int) $start_obj->diff( $end_obj )->days + 1;

			return max( 1, min( self::get_max_unit_count( 'day' ), $days_diff ) );
		}

		if ( 'week' === $view_type ) {
			$raw_start  = self::get_week_start( $start_obj, self::get_start_of_week() );
			$raw_end    = self::get_week_start( $end_obj, self::get_start_of_week() );
			$seconds    = abs( $raw_end->getTimestamp() - $raw_start->getTimestamp() );
			$weeks_diff = (int) round( $seconds / WEEK_IN_SECONDS ) + 1;

			return max( 1, min( self::get_max_unit_count( 'week' ), $weeks_diff ) );
		}

		$years_diff  = (int) $end_obj->format( 'Y' ) - (int) $start_obj->format( 'Y' );
		$months_diff = ( $years_diff * 12 ) + (int) $end_obj->format( 'n' ) - (int) $start_obj->format( 'n' ) + 1;

		return max( 1, min( self::get_max_unit_count( 'month' ), $months_diff ) );
	}

	/**
	 * Resolves the effective source post ID based on dateRangeSource and context.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param WP_Block|null        $block      Optional block instance.
	 *
	 * @return int Target post ID or 0 if default source.
	 */
	public static function resolve_source_post_id( array $attributes, ?WP_Block $block = null ): int {
		$source = $attributes['dateRangeSource'] ?? 'default';

		if ( 'selected' === $source ) {
			return isset( $attributes['postId'] ) && is_numeric( $attributes['postId'] )
				? (int) $attributes['postId']
				: 0;
		}

		if ( 'context' === $source ) {
			$context_post_id = $block instanceof WP_Block ? ( $block->context['postId'] ?? null ) : null;

			if ( is_numeric( $context_post_id ) && (int) $context_post_id > 0 ) {
				return (int) $context_post_id;
			}

			return max( 0, (int) get_the_ID() );
		}

		return 0;
	}

	/**
	 * Resolves and validates the view type from attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Attributes array.
	 * @param string               $fallback   Default view type.
	 *
	 * @return string Validated view type ('month', 'week', 'day').
	 */
	public static function resolve_view_type( array $attributes, string $fallback = 'month' ): string {
		$view_type = $attributes['viewType'] ?? $fallback;

		return in_array( $view_type, array( 'month', 'week', 'day' ), true ) ? $view_type : 'month';
	}

	/**
	 * Resolves clamped unit count for a given view type and attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $view_type  View type.
	 *
	 * @return int Clamped unit count.
	 */
	public static function resolve_unit_count( array $attributes, string $view_type ): int {
		$unit_count = isset( $attributes['unitCount'] ) && is_numeric( $attributes['unitCount'] )
			? (int) $attributes['unitCount']
			: 1;

		return min( self::get_max_unit_count( $view_type ), max( 1, $unit_count ) );
	}

	/**
	 * Determines whether weekends should be shown based on attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 *
	 * @return bool True if weekends should be shown.
	 */
	public static function show_weekends( array $attributes ): bool {
		if ( ! isset( $attributes['showWeekends'] ) ) {
			return true;
		}

		return false !== $attributes['showWeekends'] && 'false' !== $attributes['showWeekends'];
	}

	/**
	 * Retrieves the configured start of week setting as an integer.
	 *
	 * @since TBD
	 *
	 * @return int Start of week (0 = Sunday, 1 = Monday, etc.).
	 */
	public static function get_start_of_week(): int {
		$start_of_week = get_option( 'start_of_week', 0 );

		return is_numeric( $start_of_week ) ? (int) $start_of_week : 0;
	}

	/**
	 * Parses a Y-m-d date string into a DateTimeImmutable, falling back to current datetime.
	 *
	 * @since TBD
	 *
	 * @param string $date_str Date string in Y-m-d format.
	 *
	 * @return DateTimeImmutable Parsed date object.
	 */
	private static function parse_date_or_current( string $date_str ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date_str, wp_timezone() );

		return ( $date instanceof DateTimeImmutable ) ? $date : current_datetime();
	}

	/**
	 * Calculate the target date range for the calendar.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes     Block attributes.
	 * @param int                  $page           Pagination page offset.
	 * @param int                  $source_post_id Optional explicit source post ID.
	 *
	 * @return DateRange
	 */
	public static function calculate_date_range( array $attributes, int $page = 1, int $source_post_id = 0 ): array {
		$view_type      = self::resolve_view_type( $attributes );
		$target_post_id = $source_post_id > 0 ? $source_post_id : self::resolve_source_post_id( $attributes );
		$post_dates     = self::get_post_event_dates( $target_post_id );

		if ( null !== $post_dates ) {
			$unit_count    = self::calculate_post_span_units( $view_type, $post_dates['start'], $post_dates['end'] );
			$selected_date = $post_dates['start'];
		} else {
			$unit_count    = self::resolve_unit_count( $attributes, $view_type );
			$selected_date = is_string( $attributes['selectedDate'] ?? null ) ? $attributes['selectedDate'] : '';
		}

		$date_modifier = is_numeric( $attributes['dateModifier'] ?? null ) ? (int) $attributes['dateModifier'] : 0;
		// Step offset multiplies pagination by unitCount.
		$offset    = $date_modifier + ( max( 0, $page - 1 ) * $unit_count );
		$base_date = self::resolve_base_date( $selected_date, $view_type, $offset );

		list( $start_date_obj, $end_date_obj, $raw_week_start ) = self::calculate_view_bounds(
			$view_type,
			$base_date,
			$unit_count,
			self::get_start_of_week(),
			self::show_weekends( $attributes )
		);

		return self::build_range_payload(
			$view_type,
			$unit_count,
			$start_date_obj,
			$end_date_obj,
			$raw_week_start,
			$base_date
		);
	}

	/**
	 * Resolves a date range from query block context, falling back to calculation.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $query               The query array from context.
	 * @param array<string, mixed> $fallback_attributes Block attributes if context is incomplete.
	 * @param int                  $page                Page number.
	 * @param int                  $source_post_id      Optional source post ID.
	 *
	 * @return DateRange
	 */
	public static function get_range_from_query(
		array $query,
		array $fallback_attributes = array(),
		int $page = 1,
		int $source_post_id = 0
	): array {
		$start_date_raw = $query[ Query_Integration::CALENDAR_QUERY_START_DATE ] ?? '';
		$end_date_raw   = $query[ Query_Integration::CALENDAR_QUERY_END_DATE ] ?? '';

		if (
			! is_string( $start_date_raw ) || '' === $start_date_raw ||
			! is_string( $end_date_raw ) || '' === $end_date_raw
		) {
			return self::calculate_date_range( $fallback_attributes, $page, $source_post_id );
		}

		$start_date_obj = self::parse_date_or_current( $start_date_raw );
		$end_date_obj   = self::parse_date_or_current( $end_date_raw );
		$view_type      = self::resolve_view_type(
			array( 'viewType' => $query[ Query_Integration::CALENDAR_QUERY_VIEW_TYPE ] ?? null ),
			self::resolve_view_type( $fallback_attributes )
		);

		$target_post_id = $source_post_id > 0 ? $source_post_id : self::resolve_source_post_id( $fallback_attributes );
		$post_dates     = self::get_post_event_dates( $target_post_id );

		if ( null !== $post_dates ) {
			$unit_count = self::calculate_post_span_units( $view_type, $post_dates['start'], $post_dates['end'] );
		} else {
			$unit_count = self::resolve_unit_count( $fallback_attributes, $view_type );
		}

		$raw_week_start = 'week' === $view_type
			? self::get_week_start( $start_date_obj, self::get_start_of_week() )
			: $start_date_obj;
		$heading        = $query[ Query_Integration::CALENDAR_QUERY_HEADING ] ?? null;

		return self::build_range_payload(
			$view_type,
			$unit_count,
			$start_date_obj,
			$end_date_obj,
			$raw_week_start,
			$start_date_obj,
			is_string( $heading ) ? $heading : null
		);
	}

	/**
	 * Compute the grid column count based on viewType, weekend visibility, and time grid gutter.
	 *
	 * @since TBD
	 *
	 * @param string $view_type       View type ('month', 'week', 'day').
	 * @param bool   $show_weekends   Weekend visibility.
	 * @param int    $unit_count      Number of units to show.
	 * @param bool   $has_time_gutter Whether the time gutter column is active.
	 *
	 * @return int Grid column count.
	 */
	public static function get_columns_count(
		string $view_type,
		bool $show_weekends,
		int $unit_count = 1,
		bool $has_time_gutter = false
	): int {
		if ( 'day' === $view_type ) {
			$columns = max( 1, $unit_count );
		} else {
			$columns = $show_weekends ? 7 : 7 - count( self::get_weekend_days() );
		}

		if ( $has_time_gutter && ( 'day' === $view_type || 'week' === $view_type ) ) {
			++$columns;
		}

		return $columns;
	}

	/**
	 * Resolve localized header day names based on the active view.
	 *
	 * @since TBD
	 *
	 * @param string            $view_type      View type ('month', 'week', 'day').
	 * @param DateTimeImmutable $start_date_obj Starting date.
	 * @param int               $start_of_week  Start of week setting.
	 * @param bool              $show_weekends  Weekend visibility.
	 * @param int               $unit_count     Number of units to show.
	 *
	 * @return list<string> Localized day name labels.
	 */
	public static function get_view_day_names(
		string $view_type,
		DateTimeImmutable $start_date_obj,
		int $start_of_week,
		bool $show_weekends,
		int $unit_count = 1
	): array {
		if ( 'day' !== $view_type ) {
			return self::get_day_names( $start_of_week, $show_weekends );
		}

		$day_names = array();

		for ( $i = 0; $i < $unit_count; $i++ ) {
			$day_names[] = (string) wp_date( 'D', $start_date_obj->modify( "+{$i} days" )->getTimestamp() );
		}

		return $day_names;
	}

	/**
	 * Get start of the week for a given date.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $date          Target date.
	 * @param int               $start_of_week Start of week (0 = Sunday, 1 = Monday).
	 *
	 * @return DateTimeImmutable Start of the week date at 00:00:00.
	 */
	public static function get_week_start( DateTimeImmutable $date, int $start_of_week ): DateTimeImmutable {
		$dow  = (int) $date->format( 'w' ); // 0 = Sunday, 6 = Saturday.
		$diff = ( $dow - $start_of_week + 7 ) % 7;

		return $date->modify( "-{$diff} days" )->setTime( 0, 0, 0 );
	}

	/**
	 * Get the ISO 8601 week number of a calendar row.
	 *
	 * A row that does not start on Monday spans two ISO weeks. Its fourth day
	 * is always in the ISO week that holds most of the row, so that one is used.
	 *
	 * @since TBD
	 *
	 * @param array<mixed> $week_days     Day entries of the row.
	 * @param int          $start_of_week Start of week (0 = Sunday, 1 = Monday).
	 *
	 * @return int Week number, or 0 when the row has no dated day.
	 */
	public static function get_week_number( array $week_days, int $start_of_week ): int {
		foreach ( $week_days as $day ) {
			if ( ! is_array( $day ) || ! is_string( $day['date'] ?? null ) || '' === $day['date'] ) {
				continue;
			}

			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $day['date'], wp_timezone() );

			if ( ! $date instanceof DateTimeImmutable ) {
				return 0;
			}

			return (int) self::get_week_start( $date, $start_of_week )->modify( '+3 days' )->format( 'W' );
		}

		return 0;
	}

	/**
	 * Formats heading for day or week view spans.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $start_date Start date.
	 * @param DateTimeImmutable $end_date   End date.
	 * @param string            $separator  Datetime separator.
	 *
	 * @return string Formatted heading string.
	 */
	private static function format_day_or_week_heading(
		DateTimeImmutable $start_date,
		DateTimeImmutable $end_date,
		string $separator
	): string {
		if ( $start_date->format( 'Y' ) !== $end_date->format( 'Y' ) ) {
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$start_format = _x( 'M j, Y', 'Calendar heading: start of date range across years', 'gatherpress' );
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$end_format = _x( 'M j, Y', 'Calendar heading: end of date range', 'gatherpress' );
		} elseif ( $start_date->format( 'n' ) !== $end_date->format( 'n' ) ) {
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$start_format = _x( 'M j', 'Calendar heading: start of date range across months', 'gatherpress' );
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$end_format = _x( 'M j, Y', 'Calendar heading: end of date range', 'gatherpress' );
		} else {
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$start_format = _x( 'M j', 'Calendar heading: start of date range within a month', 'gatherpress' );
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$end_format = _x( 'j, Y', 'Calendar heading: end of date range within a month', 'gatherpress' );
		}

		return sprintf(
			/* translators: %1$s: start date, %2$s: separator, %3$s: end date. */
			_x( '%1$s %2$s %3$s', 'Calendar heading: date range', 'gatherpress' ),
			wp_date( $start_format, $start_date->getTimestamp() ),
			$separator,
			wp_date( $end_format, $end_date->getTimestamp() )
		);
	}

	/**
	 * Formats heading for month view spans.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $start_date Start date.
	 * @param DateTimeImmutable $end_date   End date.
	 * @param string            $separator  Datetime separator.
	 *
	 * @return string Formatted heading string.
	 */
	private static function format_month_heading(
		DateTimeImmutable $start_date,
		DateTimeImmutable $end_date,
		string $separator
	): string {
		if ( $start_date->format( 'Y-m' ) === $end_date->format( 'Y-m' ) ) {
			return (string) wp_date(
				/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'F Y', 'Calendar heading: single month', 'gatherpress' ),
				$start_date->getTimestamp()
			);
		}

		if ( $start_date->format( 'Y' ) !== $end_date->format( 'Y' ) ) {
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$start_format = _x( 'M Y', 'Calendar heading: month range across years', 'gatherpress' );
			$end_format   = $start_format;
		} else {
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$start_format = _x( 'F', 'Calendar heading: start of month range within a year', 'gatherpress' );
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$end_format = _x( 'F Y', 'Calendar heading: end of month range within a year', 'gatherpress' );
		}

		return sprintf(
			/* translators: %1$s: start date, %2$s: separator, %3$s: end date. */
			_x( '%1$s %2$s %3$s', 'Calendar heading: date range', 'gatherpress' ),
			wp_date( $start_format, $start_date->getTimestamp() ),
			$separator,
			wp_date( $end_format, $end_date->getTimestamp() )
		);
	}

	/**
	 * Format calendar heading based on view type and date range.
	 *
	 * @since TBD
	 *
	 * @param string            $view_type  View type ('month', 'week', 'day').
	 * @param DateTimeImmutable $start_date Start date.
	 * @param DateTimeImmutable $end_date   End date.
	 *
	 * @return string Formatted heading.
	 */
	public static function format_heading(
		string $view_type,
		DateTimeImmutable $start_date,
		DateTimeImmutable $end_date
	): string {
		if ( 'day' === $view_type && $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) {
			return (string) wp_date(
				/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'l, F j, Y', 'Calendar heading: single day', 'gatherpress' ),
				$start_date->getTimestamp()
			);
		}

		$separator = self::get_datetime_separator();

		if ( 'day' === $view_type || 'week' === $view_type ) {
			return self::format_day_or_week_heading( $start_date, $end_date, $separator );
		}

		return self::format_month_heading( $start_date, $end_date, $separator );
	}

	/**
	 * Retrieves the days of the week considered weekend days.
	 *
	 * This filter allows sites to adapt the calendar grid, classes, and weekend-hiding toggle
	 * to match local cultural and regional norms. Filtered values that are not days 0 to 6, and
	 * repeated days, are dropped, so the column count matches the days that are hidden.
	 *
	 * @since TBD
	 *
	 * @return list<int> Day-of-week integers (0 = Sunday, 1 = Monday, ..., 6 = Saturday).
	 */
	public static function get_weekend_days(): array {
		/**
		 * Filters which days of the week are weekend days, for a site in a region with a different weekend.
		 *
		 * Defaults to Sunday (0) and Saturday (6). Weekend days get the `is-weekend` class, and the
		 * calendar hides them when Show Weekends is off.
		 *
		 * Across different cultures, religions, and regions, the weekend days vary significantly:
		 * - Western standard: Saturday (6) and Sunday (0).
		 * - Middle East & North Africa (e.g., Egypt, Saudi Arabia, Jordan): Friday (5) and Saturday (6).
		 * - Israel: Friday (5) and Saturday (6) (observing Shabbat).
		 * - Iran: Friday (5) as the sole official weekend day.
		 * - Nepal: Saturday (6) as the sole official weekend day.
		 *
		 * @since TBD
		 *
		 * @param int[] $weekend_days Array of day numbers where 0 = Sunday, 1 = Monday, ..., 6 = Saturday.
		 *
		 * @return int[] Day numbers. Values that are not 0 to 6, and repeated days, are dropped.
		 */
		$weekend_days = apply_filters( 'gatherpress_calendar_weekend_days', array( 0, 6 ) );
		$days         = array();
		$day_range    = array(
			'options' => array(
				'min_range' => 0,
				'max_range' => 6,
			),
		);

		foreach ( (array) $weekend_days as $day ) {
			$valid_day = filter_var( $day, FILTER_VALIDATE_INT, $day_range );

			if ( false !== $valid_day ) {
				$days[] = $valid_day;
			}
		}

		return array_values( array_unique( $days ) );
	}

	/**
	 * Checks whether a given day of the week is considered a weekend.
	 *
	 * @since TBD
	 *
	 * @param int $day_of_week Day of week (0 = Sunday through 6 = Saturday).
	 *
	 * @return bool True if weekend, false otherwise.
	 */
	public static function is_weekend_day( int $day_of_week ): bool {
		return in_array( $day_of_week, self::get_weekend_days(), true );
	}

	/**
	 * Converts a day-of-week integer (0-6) into a lowercase English slug.
	 *
	 * @since TBD
	 *
	 * @param int $day_of_week Day of week (0 = Sunday through 6 = Saturday).
	 *
	 * @return string Lowercase weekday slug (e.g. 'monday', 'saturday').
	 */
	public static function get_weekday_slug( int $day_of_week ): string {
		$slugs = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );

		return $slugs[ $day_of_week ] ?? '';
	}

	/**
	 * Get translated day names based on start_of_week setting and weekend visibility.
	 *
	 * @since TBD
	 *
	 * @param int  $start_of_week Start of week (0=Sunday, 1=Monday, etc.).
	 * @param bool $show_weekends Whether to include weekend days.
	 *
	 * @return list<string> Array of translated day names.
	 */
	public static function get_day_names( int $start_of_week, bool $show_weekends = true ): array {
		$day_names = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$day_of_week = ( $start_of_week + $i ) % 7;

			if ( ! $show_weekends && self::is_weekend_day( $day_of_week ) ) {
				continue;
			}

			$day_timestamp = self::REFERENCE_SUNDAY + ( $day_of_week * DAY_IN_SECONDS );
			$day_names[]   = (string) wp_date( 'D', $day_timestamp, new DateTimeZone( 'UTC' ) );
		}

		return $day_names;
	}

	/**
	 * Get today's date in standard format.
	 *
	 * @since TBD
	 *
	 * @return string Today's date (Y-m-d format).
	 */
	public static function get_today(): string {
		return (string) wp_date( self::DATE_FORMAT );
	}

	/**
	 * Get maximum allowed unit count for a given view type.
	 *
	 * @since TBD
	 *
	 * @param string $view_type View type ('month', 'week', 'day').
	 *
	 * @return int Maximum units allowed.
	 */
	public static function get_max_unit_count( string $view_type ): int {
		$max_units = array(
			'month' => 12,
			'week'  => 5,
			'day'   => 7,
		);

		return $max_units[ $view_type ] ?? 12;
	}

	/**
	 * Retrieves the start and end minutes of an event post, for the time grid.
	 *
	 * An event that ends at 00:00 on the next day runs to the end of its day. An event with
	 * no end, or an end before its start, lasts one hour.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array{start_date: string, end_date: string, start_min: int, end_min: int}|null Datetime data or null.
	 */
	public static function get_post_event_datetimes( int $post_id ): ?array {
		$raw = self::get_post_event_raw_datetimes( $post_id );

		if ( null === $raw ) {
			return null;
		}

		// Event::get_datetime() returns only valid datetimes.
		$start_ts = (int) strtotime( $raw['start'] );
		$end_ts   = '' !== $raw['end'] ? strtotime( $raw['end'] ) : false;

		if ( false === $end_ts || $end_ts <= $start_ts ) {
			$end_ts = $start_ts + HOUR_IN_SECONDS;
		}

		$start_date = substr( $raw['start'], 0, 10 );
		$end_date   = self::resolve_end_date( $raw['start'], $raw['end'] );
		$start_min  = ( (int) gmdate( 'G', $start_ts ) * 60 ) + (int) gmdate( 'i', $start_ts );
		$end_min    = ( (int) gmdate( 'G', $end_ts ) * 60 ) + (int) gmdate( 'i', $end_ts );

		// An end at 00:00 on the next day is the end of the start day.
		if ( $start_date === $end_date && gmdate( self::DATE_FORMAT, $end_ts ) > $start_date ) {
			$end_min = self::MINUTES_PER_DAY;
		}

		return array(
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'start_min'  => $start_min,
			'end_min'    => max( $start_min + 15, $end_min ),
		);
	}

	/**
	 * Retrieves the hours in each labeled row of the time grid.
	 *
	 * The editor preview reads the same value from the editor settings.
	 *
	 * @since TBD
	 *
	 * @return int Hours per row, 1 or more.
	 */
	public static function get_time_slot_duration(): int {
		/**
		 * Filters the hours in each labeled row of the time grid, to make a long day take less height.
		 *
		 * Defaults to 1. Each row has four steps. An event starts at the step at or before its start
		 * time and ends at the step at or after its end time: 15-minute steps for 1-hour rows, 30-minute
		 * steps for 2-hour rows. Values below 1 become 1.
		 *
		 * @since TBD
		 *
		 * @param int $slot_hours Hours per slot.
		 *
		 * @return int Hours per slot.
		 */
		return max( 1, (int) apply_filters( 'gatherpress_calendar_time_slot_duration', 1 ) );
	}

	/**
	 * Retrieve time grid configuration respecting attributes, query sorting, and filters.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Calendar block attributes.
	 * @param array<string, mixed> $query      Query block context.
	 *
	 * @return array{
	 *     enabled: bool,
	 *     start_hour: int,
	 *     end_hour: int,
	 *     slot_hours: int,
	 *     quarter_mins: int,
	 *     total_quarters: int,
	 *     slots: list<string>
	 * } Time grid configuration.
	 */
	public static function get_time_grid_config( array $attributes, array $query ): array {
		$view_type     = self::resolve_view_type( $attributes );
		$is_valid_view = in_array( $view_type, array( 'day', 'week' ), true );
		$order_by      = $query['orderBy'] ?? $query['orderby'] ?? '';
		$is_datetime   = 'datetime' === $order_by;
		$user_enabled  = ! empty( $attributes['enableTimeGrid'] );

		if ( ! $is_valid_view || ! $is_datetime || ! $user_enabled ) {
			return array(
				'enabled'        => false,
				'start_hour'     => 8,
				'end_hour'       => 20,
				'slot_hours'     => 1,
				'quarter_mins'   => 15,
				'total_quarters' => 48,
				'slots'          => array(),
			);
		}

		$start_hour_raw = $attributes['dayStartHour'] ?? 8;
		$end_hour_raw   = $attributes['dayEndHour'] ?? 20;
		$start_hour     = is_numeric( $start_hour_raw ) ? max( 0, min( 23, (int) $start_hour_raw ) ) : 8;
		$end_hour       = is_numeric( $end_hour_raw ) ? max( $start_hour + 1, min( 24, (int) $end_hour_raw ) ) : 20;

		$slot_hours     = self::get_time_slot_duration();
		$quarter_mins   = max( 1, (int) round( ( $slot_hours * 60 ) / 4 ) );
		$total_minutes  = ( $end_hour - $start_hour ) * 60;
		$total_quarters = (int) ceil( $total_minutes / $quarter_mins );
		$slots          = array();

		for ( $h = $start_hour; $h < $end_hour; $h += $slot_hours ) {
			$slots[] = sprintf( '%02d:00', $h );
		}

		return array(
			'enabled'        => true,
			'start_hour'     => $start_hour,
			'end_hour'       => $end_hour,
			'slot_hours'     => $slot_hours,
			'quarter_mins'   => $quarter_mins,
			'total_quarters' => $total_quarters,
			'slots'          => $slots,
		);
	}

	/**
	 * Round minute timestamp offset to the nearest 1/4 slot duration.
	 *
	 * @since TBD
	 *
	 * @param int  $minutes      Minutes past midnight.
	 * @param int  $quarter_mins Minutes per quarter-slot.
	 * @param bool $is_end       Whether rounding end time (ceils to prevent zero-length events).
	 *
	 * @return int Rounded minutes.
	 */
	public static function round_to_quarter_slot( int $minutes, int $quarter_mins, bool $is_end = false ): int {
		if ( $quarter_mins <= 0 ) {
			return $minutes;
		}

		return $is_end
			? (int) ( ceil( $minutes / $quarter_mins ) * $quarter_mins )
			: (int) ( floor( $minutes / $quarter_mins ) * $quarter_mins );
	}
}
