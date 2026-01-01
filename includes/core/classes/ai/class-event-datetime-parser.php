<?php
/**
 * Event Datetime Parser helper class.
 *
 * Handles parsing and merging datetime inputs for event updates.
 * Supports both full datetime strings and time-only inputs.
 *
 * @package GatherPress\Core\AI
 * @since 1.0.0
 */

namespace GatherPress\Core\AI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Class Event_Datetime_Parser.
 *
 * Parses datetime inputs for event updates, handling time-only and full datetime formats.
 *
 * @since 1.0.0
 */
class Event_Datetime_Parser {

	/**
	 * Default duration in hours when only start time is provided.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const DEFAULT_DURATION_HOURS = 2;

	/**
	 * Parse datetime input (time-only or full datetime).
	 *
	 * @since 1.0.0
	 *
	 * @param string      $input           The datetime input to parse (e.g., "3pm", "15:00", "2025-01-04 15:00:00").
	 * @param string|null $existing_date   Existing date to merge with if input is time-only (Y-m-d format).
	 * @param string      $timezone        Timezone for parsing (defaults to site timezone).
	 * @return DateTime Parsed DateTime object.
	 * @throws Exception If input cannot be parsed.
	 */
	public function parse_datetime_input(
		string $input,
		?string $existing_date = null,
		string $timezone = ''
	): DateTime {
		if ( empty( $timezone ) ) {
			$timezone = wp_timezone_string();
		}

		$tz    = new DateTimeZone( $timezone );
		$input = trim( $input );

		// Try parsing as full datetime first (Y-m-d H:i:s).
		$datetime = DateTime::createFromFormat( 'Y-m-d H:i:s', $input, $tz );
		if ( $datetime ) {
			// If we have an existing date and the provided date is "today",
			// preserve the existing date and only use the time portion.
			// This handles cases where OpenAI provides "today's date + time"
			// when the user only wanted to update the time.
			if ( $existing_date ) {
				$today         = new DateTime( 'now', $tz );
				$provided_date = $datetime->format( 'Y-m-d' );
				$today_date    = $today->format( 'Y-m-d' );

				// If the provided date is today but we have an existing date, use existing date + provided time.
				if ( $provided_date === $today_date && $existing_date !== $today_date ) {
					$existing_datetime = DateTime::createFromFormat( 'Y-m-d H:i:s', $existing_date . ' 00:00:00', $tz );
					if ( $existing_datetime ) {
						$existing_datetime->setTime(
							(int) $datetime->format( 'H' ),
							(int) $datetime->format( 'i' ),
							(int) $datetime->format( 's' )
						);
						return $existing_datetime;
					}
				}
			}
			return $datetime;
		}

		// Try parsing as date only (Y-m-d).
		$datetime = DateTime::createFromFormat( 'Y-m-d', $input, $tz );
		if ( $datetime ) {
			// Set to noon if only date provided.
			$datetime->setTime( 12, 0, 0 );
			return $datetime;
		}

		// Try parsing as time-only formats.
		$time_only = $this->parse_time_only( $input );
		if ( $time_only ) {
			// If we have an existing date, merge with it.
			if ( $existing_date ) {
				$existing_datetime = DateTime::createFromFormat( 'Y-m-d H:i:s', $existing_date . ' 00:00:00', $tz );
				if ( $existing_datetime ) {
					$existing_datetime->setTime( $time_only['hour'], $time_only['minute'], 0 );
					return $existing_datetime;
				}
			}

			// No existing date, use today.
			$datetime = new DateTime( 'now', $tz );
			$datetime->setTime( $time_only['hour'], $time_only['minute'], 0 );
			return $datetime;
		}

		// Try parsing with DateTime's flexible parser as last resort.
		try {
			$datetime = new DateTime( $input, $tz );
			return $datetime;
		} catch ( Exception $e ) {
			throw new Exception(
				sprintf(
					/* translators: %s: input value */
					esc_html__( 'Unable to parse datetime input: %s', 'gatherpress' ),
					esc_html( $input )
				)
			);
		}
	}

	/**
	 * Parse time-only input (e.g., "3pm", "15:00", "3:00 PM").
	 *
	 * @since 1.0.0
	 *
	 * @param string $input Time-only input string.
	 * @return array{hour: int, minute: int}|false Array with 'hour' and 'minute' keys, or false if not parseable.
	 */
	public function parse_time_only( string $input ) {
		$input = trim( strtolower( $input ) );

		// Remove "at" prefix if present (e.g., "at 3pm" -> "3pm").
		$input = preg_replace( '/^at\s+/', '', $input );

		// Patterns to match time formats.
		$patterns = array(
			// 12-hour format with am/pm.
			'/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)$/',
			// 24-hour format with colon.
			'/^(\d{1,2}):(\d{2})$/',
			// 24-hour format without colon.
			'/^(\d{1,2})(\d{2})$/',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $input, $matches ) ) {
				$hour   = (int) $matches[1];
				// Pattern 1 has optional minutes (?:), patterns 2-3 require it. Check key existence for pattern 1.
				// @phpstan-ignore-next-line -- Pattern 1 has optional $matches[2], but patterns 2-3 always have it.
				$minute = array_key_exists( 2, $matches ) ? (int) $matches[2] : 0;

				// Handle 12-hour format.
				if ( array_key_exists( 3, $matches ) ) {
					$ampm = strtolower( $matches[3] );
					if ( 'pm' === $ampm && 12 !== $hour ) {
						$hour += 12;
					} elseif ( 'am' === $ampm && 12 === $hour ) {
						$hour = 0;
					}
				}

				// Validate hour and minute.
				if ( $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 ) {
					return array(
						'hour'   => $hour,
						'minute' => $minute,
					);
				}
			}
		}

		return false;
	}

	/**
	 * Prepare datetime parameters for event update, merging with existing datetimes.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $new_datetimes     New datetime values to apply.
	 * @param array<string, mixed> $existing_datetime Existing event datetime data from Event::get_datetime().
	 * @return array<string, string> Prepared datetime parameters ready for Event::save_datetimes().
	 * @throws Exception If datetime parsing fails or validation fails.
	 */
	public function prepare_datetime_params( array $new_datetimes, array $existing_datetime ): array {
		$timezone = $new_datetimes['timezone'] ?? $existing_datetime['timezone'] ?? wp_timezone_string();
		$result   = array(
			'timezone' => $timezone,
		);

		// Get existing start and end datetimes (prefer local over GMT).
		$existing_start = ! empty( $existing_datetime['datetime_start'] )
			? $existing_datetime['datetime_start']
			: null;
		$existing_end   = ! empty( $existing_datetime['datetime_end'] )
			? $existing_datetime['datetime_end']
			: null;

		// Parse new start datetime if provided.
		if ( isset( $new_datetimes['datetime_start'] ) ) {
			// Extract date from existing datetime (local or GMT).
			$existing_start_date = null;
			if ( $existing_start ) {
				$existing_start_date = $this->extract_date_from_datetime( $existing_start, $timezone );
			} elseif ( ! empty( $existing_datetime['datetime_start_gmt'] ) ) {
				// Use GMT datetime and convert to local timezone.
				$existing_start_date = $this->extract_date_from_gmt(
					$existing_datetime['datetime_start_gmt'],
					$timezone
				);
			}

			$start_datetime           = $this->parse_datetime_input(
				$new_datetimes['datetime_start'],
				$existing_start_date,
				$timezone
			);
			$result['datetime_start'] = $start_datetime->format( 'Y-m-d H:i:s' );
		} elseif ( $existing_start ) {
			// Preserve existing start if not being updated.
			$result['datetime_start'] = $existing_start;
		}

		// Parse new end datetime if provided.
		if ( isset( $new_datetimes['datetime_end'] ) ) {
			// Extract date from existing datetime (local or GMT).
			$existing_end_date = null;
			if ( $existing_end ) {
				$existing_end_date = $this->extract_date_from_datetime( $existing_end, $timezone );
			} elseif ( ! empty( $existing_datetime['datetime_end_gmt'] ) ) {
				// Use GMT datetime and convert to local timezone.
				$existing_end_date = $this->extract_date_from_gmt(
					$existing_datetime['datetime_end_gmt'],
					$timezone
				);
			}

			$end_datetime           = $this->parse_datetime_input(
				$new_datetimes['datetime_end'],
				$existing_end_date,
				$timezone
			);
			$result['datetime_end'] = $end_datetime->format( 'Y-m-d H:i:s' );
		} elseif ( $existing_end && ! isset( $new_datetimes['datetime_start'] ) ) {
			// Preserve existing end only if we're not updating the start time.
			// If we're updating start, we'll recalculate end below.
			$result['datetime_end'] = $existing_end;
		}

		// If start is provided (updated) and end is missing, default end to start + default duration.
		// This handles the case where we update start time only - we want to recalculate end.
		if ( isset( $result['datetime_start'] ) && ! isset( $result['datetime_end'] ) ) {
			$start_dt = DateTime::createFromFormat(
				'Y-m-d H:i:s',
				$result['datetime_start'],
				new DateTimeZone( $timezone )
			);
			if ( $start_dt ) {
				$end_dt = clone $start_dt;
				$end_dt->modify( '+' . self::DEFAULT_DURATION_HOURS . ' hours' );
				$result['datetime_end'] = $end_dt->format( 'Y-m-d H:i:s' );
			}
		}

		// If only end is provided and start is missing, default start to end - default duration.
		if ( isset( $result['datetime_end'] ) && ! isset( $result['datetime_start'] ) ) {
			$end_dt = DateTime::createFromFormat(
				'Y-m-d H:i:s',
				$result['datetime_end'],
				new DateTimeZone( $timezone )
			);
			if ( $end_dt ) {
				$start_dt = clone $end_dt;
				$start_dt->modify( '-' . self::DEFAULT_DURATION_HOURS . ' hours' );
				$result['datetime_start'] = $start_dt->format( 'Y-m-d H:i:s' );
			}
		}

		// Validate that end is after start.
		if ( isset( $result['datetime_start'] ) && isset( $result['datetime_end'] ) ) {
			$start_dt = DateTime::createFromFormat(
				'Y-m-d H:i:s',
				$result['datetime_start'],
				new DateTimeZone( $timezone )
			);
			$end_dt   = DateTime::createFromFormat(
				'Y-m-d H:i:s',
				$result['datetime_end'],
				new DateTimeZone( $timezone )
			);

			if ( $start_dt && $end_dt && $end_dt <= $start_dt ) {
				throw new Exception(
					esc_html__( 'End datetime must be after start datetime.', 'gatherpress' )
				);
			}
		}

		return $result;
	}

	/**
	 * Extract date portion (Y-m-d) from datetime string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $datetime Datetime string in Y-m-d H:i:s format.
	 * @param string $timezone  Optional timezone for the datetime. If not provided, assumes local timezone.
	 * @return string|null Date portion in Y-m-d format, or null if parsing fails.
	 */
	private function extract_date_from_datetime( string $datetime, string $timezone = '' ): ?string {
		if ( empty( $timezone ) ) {
			$timezone = wp_timezone_string();
		}

		$tz = new DateTimeZone( $timezone );
		$dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime, $tz );
		if ( $dt ) {
			return $dt->format( 'Y-m-d' );
		}

		// Try date-only format.
		$dt = DateTime::createFromFormat( 'Y-m-d', $datetime, $tz );
		if ( $dt ) {
			return $dt->format( 'Y-m-d' );
		}

		return null;
	}

	/**
	 * Extract date from GMT datetime and convert to local timezone.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gmt_datetime GMT datetime string.
	 * @param string $timezone     Target timezone to convert to.
	 * @return string|null Date portion in Y-m-d format in the target timezone, or null if parsing fails.
	 */
	private function extract_date_from_gmt( string $gmt_datetime, string $timezone ): ?string {
		$gmt_tz = new DateTimeZone( 'UTC' );
		$dt     = DateTime::createFromFormat( 'Y-m-d H:i:s', $gmt_datetime, $gmt_tz );
		if ( ! $dt ) {
			return null;
		}

		// Convert to target timezone.
		$target_tz = new DateTimeZone( $timezone );
		$dt->setTimezone( $target_tz );
		return $dt->format( 'Y-m-d' );
	}
}
