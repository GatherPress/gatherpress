<?php
/**
 * Date Calculator class.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date Calculator class for handling recurring date calculations.
 *
 * This class provides functionality to calculate recurring dates based on
 * various patterns like "3rd Tuesday", "every Monday", etc.
 *
 * @since 1.0.0
 */
class Date_Calculator {

	/**
	 * Calculate recurring dates based on a pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Parameters including pattern, occurrences, and optional start_date.
	 * @return array Result with calculated dates or error.
	 */
	public function calculate_dates( array $params = array() ): array {
		// Validate required parameters.
		if ( empty( $params['pattern'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Pattern is required.', 'gatherpress' ),
			);
		}

		if ( empty( $params['occurrences'] ) || $params['occurrences'] < 1 ) {
			return array(
				'success' => false,
				'message' => __( 'Occurrences must be at least 1.', 'gatherpress' ),
			);
		}

		$pattern     = sanitize_text_field( $params['pattern'] );
		$occurrences = intval( $params['occurrences'] );
		$start_date  = ! empty( $params['start_date'] ) ? sanitize_text_field( $params['start_date'] ) : gmdate( 'Y-m-d' );

		// Validate start_date format.
		$start_datetime = \DateTime::createFromFormat( 'Y-m-d', $start_date );
		if ( ! $start_datetime ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid start_date format. Use Y-m-d.', 'gatherpress' ),
			);
		}

		// Parse the pattern and calculate dates.
		$dates = $this->calculate_recurring_dates( $pattern, $occurrences, $start_datetime );

		if ( empty( $dates ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: pattern */
					__( 'Could not calculate dates for pattern: %s', 'gatherpress' ),
					$pattern
				),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'dates'   => $dates,
				'pattern' => $pattern,
				'count'   => count( $dates ),
			),
			'message' => sprintf(
				/* translators: %d: number of dates */
				_n(
					'Calculated %d date.',
					'Calculated %d dates.',
					count( $dates ),
					'gatherpress'
				),
				count( $dates )
			),
		);
	}

	/**
	 * Calculate recurring dates based on a pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $pattern       The recurrence pattern.
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_recurring_dates( string $pattern, int $occurrences, \DateTime $start_datetime ): array {
		$dates       = array();
		$pattern_low = strtolower( trim( $pattern ) );

		// Parse pattern for Nth weekday.
		if ( preg_match( '/^(first|second|third|fourth|last|1st|2nd|3rd|4th|5th)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern_low, $matches ) ) {
			$ordinal = strtolower( $matches[1] );
			$weekday = strtolower( $matches[2] );

			// Convert ordinal to number.
			$ordinal_map = array(
				'first'  => 1,
				'1st'    => 1,
				'second' => 2,
				'2nd'    => 2,
				'third'  => 3,
				'3rd'    => 3,
				'fourth' => 4,
				'4th'    => 4,
				'fifth'  => 5,
				'5th'    => 5,
				'last'   => -1,
			);

			$nth = $ordinal_map[ $ordinal ] ?? 1;

			// Get current month/year as starting point.
			$current = clone $start_datetime;

			for ( $i = 0; $i < $occurrences; $i++ ) {
				$date = $this->get_nth_weekday_of_month( $current->format( 'Y' ), $current->format( 'm' ), $weekday, $nth );

				// If the calculated date is before start_date, move to next month.
				if ( $date < $start_datetime->format( 'Y-m-d' ) ) {
					$current->modify( '+1 month' );
					$date = $this->get_nth_weekday_of_month( $current->format( 'Y' ), $current->format( 'm' ), $weekday, $nth );
				}

				$dates[] = $date;

				// Move to next month.
				$current->modify( '+1 month' );
			}
		} elseif ( preg_match( '/^every\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern_low, $matches ) ) {
			// Pattern: "every Monday".
			$weekday = strtolower( $matches[1] );
			$current = clone $start_datetime;

			// Find the next occurrence of this weekday.
			$day_num     = $this->get_weekday_number( $weekday );
			$current_day = (int) $current->format( 'N' );

			if ( $current_day <= $day_num ) {
				$days_ahead = $day_num - $current_day;
			} else {
				$days_ahead = 7 - $current_day + $day_num;
			}

			$current->modify( "+{$days_ahead} days" );

			for ( $i = 0; $i < $occurrences; $i++ ) {
				$dates[] = $current->format( 'Y-m-d' );
				$current->modify( '+7 days' );
			}
		} else {
			// Unrecognized pattern.
			return array();
		}

		return $dates;
	}

	/**
	 * Get the date of the Nth occurrence of a weekday in a month.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $year    Year.
	 * @param int    $month   Month.
	 * @param string $weekday Weekday name (e.g., "monday").
	 * @param int    $nth     Nth occurrence (1-5, or -1 for last).
	 * @return string Date in Y-m-d format.
	 */
	private function get_nth_weekday_of_month( int $year, int $month, string $weekday, int $nth ): string {
		if ( -1 === $nth ) {
			// Last occurrence.
			$date = new \DateTime( "last {$weekday} of {$year}-{$month}" );
		} else {
			// Nth occurrence.
			$ordinal_text = array(
				1 => 'first',
				2 => 'second',
				3 => 'third',
				4 => 'fourth',
				5 => 'fifth',
			);
			$ordinal      = $ordinal_text[ $nth ] ?? 'first';
			$date         = new \DateTime( "{$ordinal} {$weekday} of {$year}-{$month}" );
		}

		return $date->format( 'Y-m-d' );
	}

	/**
	 * Get the ISO-8601 numeric representation of the day of the week.
	 *
	 * @since 1.0.0
	 *
	 * @param string $weekday Weekday name (e.g., "monday").
	 * @return int Day number (1 for Monday, 7 for Sunday).
	 */
	private function get_weekday_number( string $weekday ): int {
		$days = array(
			'monday'    => 1,
			'tuesday'   => 2,
			'wednesday' => 3,
			'thursday'  => 4,
			'friday'    => 5,
			'saturday'  => 6,
			'sunday'    => 7,
		);

		return $days[ strtolower( $weekday ) ] ?? 1;
	}
}
