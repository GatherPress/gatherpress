<?php
/**
 * The date range of a calendar.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use GatherPress\Core\Event;
use WP_Block;

/**
 * Class Date_Range.
 *
 * The view, the days, and the heading of what a calendar shows.
 *
 * @since TBD
 */
final class Date_Range {

	/**
	 * The date format used throughout the calendar.
	 *
	 * @since TBD
	 * @var string
	 */
	const DATE_FORMAT = 'Y-m-d';

	/**
	 * The view types, with the most units each view shows.
	 *
	 * @since TBD
	 * @var array<string, int>
	 */
	const MAX_UNITS = array(
		'month' => 12,
		'week'  => 5,
		'day'   => 7,
	);

	/**
	 * First day of the range (Y-m-d).
	 *
	 * @since TBD
	 * @var string
	 */
	public readonly string $start_date;

	/**
	 * Last day of the range (Y-m-d).
	 *
	 * @since TBD
	 * @var string
	 */
	public readonly string $end_date;

	/**
	 * Year of the target date.
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $year;

	/**
	 * Month of the target date (1 to 12).
	 *
	 * @since TBD
	 * @var int
	 */
	public readonly int $month;

	/**
	 * Localized heading, like "October 2026".
	 *
	 * @since TBD
	 * @var string
	 */
	public readonly string $heading;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param string                 $view_type  View type ('month', 'week', 'day').
	 * @param int                    $unit_count Number of months, weeks or days.
	 * @param DateTimeImmutable      $start      First day, at 00:00:00.
	 * @param DateTimeImmutable      $end        Last day, at 23:59:59.
	 * @param Week                   $week       The days of the week the calendar shows.
	 * @param DateTimeImmutable|null $target     Date the year and month come from. Defaults to the start.
	 * @param string|null            $heading    Heading. Defaults to one formatted from the dates.
	 */
	public function __construct(
		public readonly string $view_type,
		public readonly int $unit_count,
		public readonly DateTimeImmutable $start,
		public readonly DateTimeImmutable $end,
		public readonly Week $week,
		?DateTimeImmutable $target = null,
		?string $heading = null,
	) {
		$target = $target ?? $start;

		$this->start_date = $start->format( self::DATE_FORMAT );
		$this->end_date   = $end->format( self::DATE_FORMAT );
		$this->year       = (int) $target->format( 'Y' );
		$this->month      = (int) $target->format( 'n' );
		$this->heading    = $heading ?? $this->format_heading();
	}

	/**
	 * Creates the date range of a calendar from its block attributes.
	 *
	 * With an event as its date range source, the calendar shows the days of that event.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Calendar block attributes.
	 * @param int                  $page       Page of the Query block. Each page moves the range by its units.
	 * @param WP_Block|null        $block      Block whose context gives the post of the `context` source.
	 *
	 * @return self The date range.
	 */
	public static function from_attributes( array $attributes, int $page = 1, ?WP_Block $block = null ): self {
		$view_type  = self::resolve_view_type( $attributes['viewType'] ?? null );
		$week       = Week::from_attributes( $attributes );
		$post_dates = self::get_source_post_dates( $attributes, $block );

		if ( null !== $post_dates ) {
			$unit_count    = self::count_units( $view_type, $post_dates, $week );
			$selected_date = $post_dates->start_date;
		} else {
			$unit_count    = self::resolve_unit_count( $attributes, $view_type );
			$selected_date = is_string( $attributes['selectedDate'] ?? null ) ? $attributes['selectedDate'] : '';
		}

		$date_modifier = is_numeric( $attributes['dateModifier'] ?? null ) ? (int) $attributes['dateModifier'] : 0;
		$offset        = $date_modifier + ( max( 0, $page - 1 ) * $unit_count );
		$target        = self::resolve_base_date( $selected_date, $view_type, $offset );

		list( $start, $end ) = self::calculate_bounds( $view_type, $target, $unit_count, $week );

		return new self( $view_type, $unit_count, $start, $end, $week, $target );
	}

	/**
	 * Creates the date range of a calendar from the query of its Query block.
	 *
	 * The Query block of a calendar carries the range that `Calendar_Query` worked out for it.
	 * Without that range, the range comes from the attributes.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $query      The query of the Query block.
	 * @param array<string, mixed> $attributes Calendar block attributes.
	 * @param int                  $page       Page of the Query block.
	 * @param WP_Block|null        $block      Block whose context gives the post of the `context` source.
	 *
	 * @return self The date range.
	 */
	public static function from_query(
		array $query,
		array $attributes = array(),
		int $page = 1,
		?WP_Block $block = null
	): self {
		$start_date = $query[ Query_Builder::CALENDAR_QUERY_START_DATE ] ?? '';
		$end_date   = $query[ Query_Builder::CALENDAR_QUERY_END_DATE ] ?? '';

		if ( ! is_string( $start_date ) || '' === $start_date || ! is_string( $end_date ) || '' === $end_date ) {
			return self::from_attributes( $attributes, $page, $block );
		}

		$view_type  = self::resolve_view_type(
			$query[ Query_Builder::CALENDAR_QUERY_VIEW_TYPE ] ?? $attributes['viewType'] ?? null
		);
		$week       = Week::from_attributes( $attributes );
		$post_dates = self::get_source_post_dates( $attributes, $block );
		$unit_count = null !== $post_dates
			? self::count_units( $view_type, $post_dates, $week )
			: self::resolve_unit_count( $attributes, $view_type );
		$heading    = $query[ Query_Builder::CALENDAR_QUERY_HEADING ] ?? null;

		return new self(
			$view_type,
			$unit_count,
			self::parse_date_or_current( $start_date ),
			self::parse_date_or_current( $end_date )->setTime( 23, 59, 59 ),
			$week,
			null,
			is_string( $heading ) ? $heading : null
		);
	}

	/**
	 * Validates a view type.
	 *
	 * @since TBD
	 *
	 * @param mixed $view_type View type to check.
	 *
	 * @return string The view type, or 'month' when it is not one.
	 */
	private static function resolve_view_type( mixed $view_type ): string {
		return is_string( $view_type ) && isset( self::MAX_UNITS[ $view_type ] ) ? $view_type : 'month';
	}

	/**
	 * Resolves the number of units, from 1 to the most of the view.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Calendar block attributes.
	 * @param string               $view_type  View type.
	 *
	 * @return int Number of units.
	 */
	private static function resolve_unit_count( array $attributes, string $view_type ): int {
		$unit_count = is_numeric( $attributes['unitCount'] ?? null ) ? (int) $attributes['unitCount'] : 1;

		return min( self::MAX_UNITS[ $view_type ], max( 1, $unit_count ) );
	}

	/**
	 * Gets the dates of the event that is the date range source of a calendar.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes Calendar block attributes.
	 * @param WP_Block|null        $block      Block whose context gives the post of the `context` source.
	 *
	 * @return Post_Dates|null Dates of the event, or null without a source event.
	 */
	private static function get_source_post_dates( array $attributes, ?WP_Block $block ): ?Post_Dates {
		$post_id = match ( $attributes['dateRangeSource'] ?? 'default' ) {
			'selected' => is_numeric( $attributes['postId'] ?? null ) ? (int) $attributes['postId'] : 0,
			'context'  => self::resolve_context_post_id( $block ),
			default    => 0,
		};
		$post_dates = $post_id > 0 ? new Post_Dates( $post_id ) : null;

		return $post_dates && $post_dates->is_event ? $post_dates : null;
	}

	/**
	 * Resolves the post of the `context` date range source.
	 *
	 * @since TBD
	 *
	 * @param WP_Block|null $block Block whose context gives the post.
	 *
	 * @return int The post of the block context, or else the current post.
	 */
	private static function resolve_context_post_id( ?WP_Block $block ): int {
		$post_id = $block instanceof WP_Block ? ( $block->context['postId'] ?? null ) : null;

		return is_numeric( $post_id ) && (int) $post_id > 0 ? (int) $post_id : max( 0, (int) get_the_ID() );
	}

	/**
	 * Counts the units of a view that the days of an event span.
	 *
	 * @since TBD
	 *
	 * @param string     $view_type  View type.
	 * @param Post_Dates $post_dates Dates of the event.
	 * @param Week       $week       The days of the week of the calendar.
	 *
	 * @return int Number of units, from 1 to the most of the view.
	 */
	private static function count_units( string $view_type, Post_Dates $post_dates, Week $week ): int {
		$start = self::parse_date_or_current( $post_dates->start_date );
		$end   = self::parse_date_or_current( $post_dates->end_date );
		$units = match ( $view_type ) {
			'day'   => (int) $start->diff( $end )->days + 1,
			'week'  => (int) round(
				abs( $week->get_start( $end )->getTimestamp() - $week->get_start( $start )->getTimestamp() )
				/ WEEK_IN_SECONDS
			) + 1,
			default => ( ( (int) $end->format( 'Y' ) - (int) $start->format( 'Y' ) ) * 12 )
				+ (int) $end->format( 'n' ) - (int) $start->format( 'n' ) + 1,
		};

		return max( 1, min( self::MAX_UNITS[ $view_type ], $units ) );
	}

	/**
	 * Parses a Y-m-d date string, falling back to the current datetime.
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
	 * Resolves the date the range is built around.
	 *
	 * @since TBD
	 *
	 * @param string $selected_date Selected date (Y-m-d or Y-m), or an empty string for today.
	 * @param string $view_type     View type.
	 * @param int    $offset        Number of units to move the date by.
	 *
	 * @return DateTimeImmutable The date.
	 */
	private static function resolve_base_date(
		string $selected_date,
		string $view_type,
		int $offset
	): DateTimeImmutable {
		$base_date = null;

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
			$base_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $selected_date, wp_timezone() );
		} elseif ( preg_match( '/^\d{4}-\d{2}$/', $selected_date ) ) {
			$base_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $selected_date . '-01', wp_timezone() );
		}

		if ( ! $base_date instanceof DateTimeImmutable ) {
			$base_date = current_datetime();
		}

		// Normalize to the 1st of the month in month view to prevent overflow (e.g. Jan 31 + 1 month).
		if ( 'month' === $view_type ) {
			$base_date = $base_date->modify( 'first day of this month' );
		}

		// The view types are also the units to move by.
		return 0 !== $offset ? $base_date->modify( sprintf( '%+d %s', $offset, $view_type ) ) : $base_date;
	}

	/**
	 * Calculates the first and last day of a view.
	 *
	 * @since TBD
	 *
	 * @param string            $view_type  View type.
	 * @param DateTimeImmutable $base_date  Date the range is built around.
	 * @param int               $unit_count Number of units.
	 * @param Week              $week       The days of the week of the calendar.
	 *
	 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable} First day at 00:00:00 and last day at 23:59:59.
	 */
	private static function calculate_bounds(
		string $view_type,
		DateTimeImmutable $base_date,
		int $unit_count,
		Week $week
	): array {
		return match ( $view_type ) {
			'week'  => $week->get_bounds( $base_date, $unit_count ),
			'day'   => array(
				$base_date->setTime( 0, 0, 0 ),
				$base_date->modify( sprintf( '+%d days', $unit_count - 1 ) )->setTime( 23, 59, 59 ),
			),
			// Month view: spans from Month 1's first day to Month N's last day.
			default => array(
				$base_date->modify( 'first day of this month' )->setTime( 0, 0, 0 ),
				$base_date
					->modify( sprintf( '+%d months', $unit_count - 1 ) )
					->modify( 'last day of this month' )
					->setTime( 23, 59, 59 ),
			),
		};
	}

	/**
	 * Gets the first day of the first week row of a week view.
	 *
	 * @since TBD
	 *
	 * @return DateTimeImmutable First day of the week of the start, at 00:00:00.
	 */
	public function get_week_start(): DateTimeImmutable {
		return $this->week->get_start( $this->start );
	}

	/**
	 * Gets the date range of each month of a month view.
	 *
	 * @since TBD
	 *
	 * @return non-empty-list<self> One range per month, each with its own heading.
	 */
	public function get_months(): array {
		$months = array();
		$count  = max( 1, $this->unit_count );

		for ( $i = 0; $i < $count; $i++ ) {
			$month    = $this->start->modify( 'first day of this month' )->modify( "+{$i} months" );
			$months[] = new self(
				'month',
				1,
				$month->setTime( 0, 0, 0 ),
				$month->modify( 'last day of this month' )->setTime( 23, 59, 59 ),
				$this->week
			);
		}

		return $months;
	}

	/**
	 * Gets the translated names of the columns of the range.
	 *
	 * A day view names each of its days. The other views name the days of the week.
	 *
	 * @since TBD
	 *
	 * @return list<string> Translated day names, like "Mon".
	 */
	public function get_day_names(): array {
		if ( 'day' !== $this->view_type ) {
			return $this->week->get_day_names();
		}

		$day_names = array();

		for ( $i = 0; $i < $this->unit_count; $i++ ) {
			$day_names[] = (string) wp_date( 'D', $this->start->modify( "+{$i} days" )->getTimestamp() );
		}

		return $day_names;
	}

	/**
	 * Gets the number of grid columns.
	 *
	 * @since TBD
	 *
	 * @param bool $has_time_gutter Whether the time grid adds a column for its hours.
	 *
	 * @return int Number of columns.
	 */
	public function get_columns_count( bool $has_time_gutter = false ): int {
		$columns = 'day' === $this->view_type ? max( 1, $this->unit_count ) : count( $this->week->get_days() );

		if ( $has_time_gutter && 'month' !== $this->view_type ) {
			++$columns;
		}

		return $columns;
	}

	/**
	 * Formats the heading of the range.
	 *
	 * @since TBD
	 *
	 * @return string Formatted heading.
	 */
	private function format_heading(): string {
		if ( 'day' === $this->view_type && $this->start_date === $this->end_date ) {
			return (string) wp_date(
				/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'l, F j, Y', 'Calendar heading: single day', 'gatherpress' ),
				$this->start->getTimestamp()
			);
		}

		if ( 'month' === $this->view_type && $this->start->format( 'Y-m' ) === $this->end->format( 'Y-m' ) ) {
			return (string) wp_date(
				/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'F Y', 'Calendar heading: single month', 'gatherpress' ),
				$this->start->getTimestamp()
			);
		}

		list( $start_format, $end_format ) = 'month' === $this->view_type
			? $this->get_month_range_formats()
			: $this->get_day_range_formats();

		return sprintf(
			/* translators: %1$s: start date, %2$s: separator, %3$s: end date. */
			_x( '%1$s %2$s %3$s', 'Calendar heading: date range', 'gatherpress' ),
			wp_date( $start_format, $this->start->getTimestamp() ),
			$this->get_separator(),
			wp_date( $end_format, $this->end->getTimestamp() )
		);
	}

	/**
	 * Gets the date formats of the start and end of a heading for days.
	 *
	 * @since TBD
	 *
	 * @return array{0: string, 1: string} Start and end date formats.
	 */
	private function get_day_range_formats(): array {
		if ( $this->start->format( 'Y' ) !== $this->end->format( 'Y' ) ) {
			return array(
				/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'M j, Y', 'Calendar heading: start of date range across years', 'gatherpress' ),
				/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'M j, Y', 'Calendar heading: end of date range', 'gatherpress' ),
			);
		}

		if ( $this->start->format( 'n' ) !== $this->end->format( 'n' ) ) {
			return array(
				/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'M j', 'Calendar heading: start of date range across months', 'gatherpress' ),
				/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
				_x( 'M j, Y', 'Calendar heading: end of date range', 'gatherpress' ),
			);
		}

		return array(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			_x( 'M j', 'Calendar heading: start of date range within a month', 'gatherpress' ),
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			_x( 'j, Y', 'Calendar heading: end of date range within a month', 'gatherpress' ),
		);
	}

	/**
	 * Gets the date formats of the start and end of a heading for months.
	 *
	 * @since TBD
	 *
	 * @return array{0: string, 1: string} Start and end date formats.
	 */
	private function get_month_range_formats(): array {
		if ( $this->start->format( 'Y' ) !== $this->end->format( 'Y' ) ) {
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			$format = _x( 'M Y', 'Calendar heading: month range across years', 'gatherpress' );

			return array( $format, $format );
		}

		return array(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			_x( 'F', 'Calendar heading: start of month range within a year', 'gatherpress' ),
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			_x( 'F Y', 'Calendar heading: end of month range within a year', 'gatherpress' ),
		);
	}

	/**
	 * Gets the separator between the dates of a heading.
	 *
	 * Uses the same `gatherpress_datetime_separator` filter as the Event Date block, with an
	 * en dash as the default.
	 *
	 * @since TBD
	 *
	 * @return string Filtered datetime separator.
	 */
	private function get_separator(): string {
		$default_separator = _x( '–', 'Calendar heading date range separator', 'gatherpress' );
		$separator         = trim( Event::get_datetime_separator( null, $default_separator ) );

		return '' !== $separator ? $separator : $default_separator;
	}
}
