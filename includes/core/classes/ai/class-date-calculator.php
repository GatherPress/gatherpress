<?php
/**
 * Date Calculator class.
 *
 * @package GatherPress\Core\AI
 * @since 1.0.0
 */

namespace GatherPress\Core\AI;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Date Calculator class for handling recurring date calculations.
 *
 * This class provides functionality to calculate recurring dates based on
 * various patterns like "3rd Tuesday", "every Monday", etc. Used primarily
 * for AI-powered date calculations via the Abilities API.
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

		// Handle relative patterns first (next, last, this, tomorrow, yesterday).
		if ( preg_match( '/^(next|last|this)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern_low, $matches ) ) {
			$relative = strtolower( $matches[1] );
			$weekday  = strtolower( $matches[2] );
			$dates    = $this->calculate_relative_weekday_dates( $relative, $weekday, $occurrences, $start_datetime );
		} elseif ( preg_match( '/^(tomorrow|yesterday)$/i', $pattern_low, $matches ) ) {
			$relative = strtolower( $matches[1] );
			$dates    = $this->calculate_relative_day_dates( $relative, $occurrences, $start_datetime );
		} elseif ( preg_match( '/^(next|last)\s+(week|month)$/i', $pattern_low, $matches ) ) {
			$relative = strtolower( $matches[1] );
			$period   = strtolower( $matches[2] );
			$dates    = $this->calculate_relative_period_dates( $relative, $period, $occurrences, $start_datetime );
		} elseif ( preg_match( '/^(in|after)\s+(\d+)\s+(day|days)$/i', $pattern_low, $matches ) ) {
			$days  = intval( $matches[2] );
			$dates = $this->calculate_future_day_dates( $days, $occurrences, $start_datetime );
		} elseif ( preg_match( '/^(\d+)\s+(day|days)\s+(ago|before)$/i', $pattern_low, $matches ) ) {
			$days  = intval( $matches[1] );
			$dates = $this->calculate_past_day_dates( $days, $occurrences, $start_datetime );
		} elseif ( preg_match( '/^every\s+other\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern_low, $matches ) ) {
			// Bi-weekly pattern.
			$weekday = strtolower( $matches[1] );
			$dates   = $this->calculate_biweekly_dates( $weekday, $occurrences, $start_datetime );
		} elseif ( preg_match( '/^every\s+(\d+)\s+(week|weeks|month|months)$/i', $pattern_low, $matches ) ) {
			// Interval-based pattern.
			$interval = intval( $matches[1] );
			$period   = strtolower( $matches[2] );
			$dates    = $this->calculate_interval_dates( $interval, $period, $occurrences, $start_datetime );
		} elseif ( preg_match( '/^(\d+)\s+weeks?\s+from\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern_low, $matches ) ) {
			// X weeks from weekday pattern (2 weeks from Thursday).
			$weeks   = intval( $matches[1] );
			$weekday = strtolower( $matches[2] );
			$dates   = $this->calculate_weeks_from_weekday( $weeks, $weekday, $occurrences, $start_datetime );
		} elseif ( preg_match( '/^(first|second|third|fourth|last|1st|2nd|3rd|4th|5th)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern_low, $matches ) ) {
			// Original Nth weekday pattern.
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
				$date = $this->get_nth_weekday_of_month( (int) $current->format( 'Y' ), (int) $current->format( 'm' ), $weekday, $nth );

				// If the calculated date is before start_date, move to next month.
				if ( $date < $start_datetime->format( 'Y-m-d' ) ) {
					$current->modify( '+1 month' );
					$date = $this->get_nth_weekday_of_month( (int) $current->format( 'Y' ), (int) $current->format( 'm' ), $weekday, $nth );
				}

				$dates[] = $date;

				// Move to the first day of the month after the date we just added.
				$date_obj = \DateTime::createFromFormat( 'Y-m-d', $date );
				$current = clone $date_obj;
				$current->modify( 'first day of next month' );
			}
		} elseif ( preg_match( '/^every\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)$/i', $pattern_low, $matches ) ) {
			// Original weekly pattern.
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

	/**
	 * Calculate dates for relative weekday patterns (next, last, this).
	 *
	 * @since 1.0.0
	 *
	 * @param string    $relative      The relative term (next, last, this).
	 * @param string    $weekday       The weekday name.
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_relative_weekday_dates( string $relative, string $weekday, int $occurrences, \DateTime $start_datetime ): array {
		$dates       = array();
		$current     = clone $start_datetime;
		$day_num     = $this->get_weekday_number( $weekday );
		$current_day = (int) $current->format( 'N' );

		for ( $i = 0; $i < $occurrences; $i++ ) {
			$target_date = clone $current;

			switch ( $relative ) {
				case 'next':
					// Always go to next week's occurrence, never this week's.
					$days_ahead = 7 - $current_day + $day_num;
					$target_date->modify( "+{$days_ahead} days" );
					break;

				case 'last':
					// Find previous occurrence of weekday.
					if ( $current_day >= $day_num ) {
						$days_back = $current_day - $day_num;
					} else {
						$days_back = $current_day + 7 - $day_num;
					}
					$target_date->modify( "-{$days_back} days" );
					break;

				case 'this':
					// Find this week's occurrence of weekday.
					if ( $current_day <= $day_num ) {
						$days_ahead = $day_num - $current_day;
					} else {
						$days_ahead = 7 - $current_day + $day_num;
					}
					$target_date->modify( "+{$days_ahead} days" );
					break;
			}

			$dates[] = $target_date->format( 'Y-m-d' );

			// Move to next week for subsequent occurrences.
			$current->modify( '+7 days' );
			$current_day = (int) $current->format( 'N' );
		}

		return $dates;
	}

	/**
	 * Calculate dates for relative day patterns (tomorrow, yesterday).
	 *
	 * @since 1.0.0
	 *
	 * @param string    $relative      The relative term (tomorrow, yesterday).
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_relative_day_dates( string $relative, int $occurrences, \DateTime $start_datetime ): array {
		$dates   = array();
		$current = clone $start_datetime;

		for ( $i = 0; $i < $occurrences; $i++ ) {
			switch ( $relative ) {
				case 'tomorrow':
					$current->modify( '+1 day' );
					break;
				case 'yesterday':
					$current->modify( '-1 day' );
					break;
			}

			$dates[] = $current->format( 'Y-m-d' );
		}

		return $dates;
	}

	/**
	 * Calculate dates for relative period patterns (next week, last month).
	 *
	 * @since 1.0.0
	 *
	 * @param string    $relative      The relative term (next, last).
	 * @param string    $period        The period (week, month).
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_relative_period_dates( string $relative, string $period, int $occurrences, \DateTime $start_datetime ): array {
		$dates   = array();
		$current = clone $start_datetime;

		for ( $i = 0; $i < $occurrences; $i++ ) {
			switch ( $relative ) {
				case 'next':
					if ( 'week' === $period ) {
						$current->modify( '+1 week' );
					} elseif ( 'month' === $period ) {
						$current->modify( '+1 month' );
					}
					break;
				case 'last':
					if ( 'week' === $period ) {
						$current->modify( '-1 week' );
					} elseif ( 'month' === $period ) {
						$current->modify( '-1 month' );
					}
					break;
			}

			$dates[] = $current->format( 'Y-m-d' );
		}

		return $dates;
	}

	/**
	 * Calculate dates for future day patterns (in 3 days, after 5 days).
	 *
	 * @since 1.0.0
	 *
	 * @param int       $days          Number of days ahead.
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_future_day_dates( int $days, int $occurrences, \DateTime $start_datetime ): array {
		$dates   = array();
		$current = clone $start_datetime;

		for ( $i = 0; $i < $occurrences; $i++ ) {
			$current->modify( "+{$days} days" );
			$dates[] = $current->format( 'Y-m-d' );
		}

		return $dates;
	}

	/**
	 * Calculate dates for past day patterns (3 days ago, 5 days before).
	 *
	 * @since 1.0.0
	 *
	 * @param int       $days          Number of days back.
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_past_day_dates( int $days, int $occurrences, \DateTime $start_datetime ): array {
		$dates   = array();
		$current = clone $start_datetime;

		for ( $i = 0; $i < $occurrences; $i++ ) {
			$current->modify( "-{$days} days" );
			$dates[] = $current->format( 'Y-m-d' );
		}

		return $dates;
	}

	/**
	 * Calculate dates for bi-weekly patterns (every other Monday).
	 *
	 * @since 1.0.0
	 *
	 * @param string    $weekday       The weekday name.
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_biweekly_dates( string $weekday, int $occurrences, \DateTime $start_datetime ): array {
		$dates   = array();
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
			$current->modify( '+14 days' ); // Every other week.
		}

		return $dates;
	}

	/**
	 * Calculate dates for interval-based patterns (every 2 weeks, every 3 months).
	 *
	 * @since 1.0.0
	 *
	 * @param int       $interval      The interval number.
	 * @param string    $period        The period (week, weeks, month, months).
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_interval_dates( int $interval, string $period, int $occurrences, \DateTime $start_datetime ): array {
		$dates   = array();
		$current = clone $start_datetime;

		for ( $i = 0; $i < $occurrences; $i++ ) {
			$dates[] = $current->format( 'Y-m-d' );

			// Apply the interval.
			if ( 'week' === $period || 'weeks' === $period ) {
				$current->modify( "+{$interval} weeks" );
			} elseif ( 'month' === $period || 'months' === $period ) {
				$current->modify( "+{$interval} months" );
			}
		}

		return $dates;
	}

	/**
	 * Calculate dates for "X weeks from weekday" patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param int       $weeks         Number of weeks to add.
	 * @param string    $weekday       The weekday name.
	 * @param int       $occurrences   Number of occurrences.
	 * @param \DateTime $start_datetime Starting date.
	 * @return array Array of date strings in Y-m-d format.
	 */
	private function calculate_weeks_from_weekday( int $weeks, string $weekday, int $occurrences, \DateTime $start_datetime ): array {
		$dates   = array();
		$current = clone $start_datetime;

		// Find the target weekday.
		$day_num     = $this->get_weekday_number( $weekday );
		$current_day = (int) $current->format( 'N' );

		// Calculate days to the target weekday.
		if ( $current_day <= $day_num ) {
			$days_ahead = $day_num - $current_day;
		} else {
			$days_ahead = 7 - $current_day + $day_num;
		}

		// Move to the target weekday.
		$current->modify( "+{$days_ahead} days" );

		// Add the specified number of weeks.
		$current->modify( "+{$weeks} weeks" );

		// Return the single target date.
		$dates[] = $current->format( 'Y-m-d' );

		return $dates;
	}
}
