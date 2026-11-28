<?php
/**
 * How a repeating event repeats, as a sentence.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;

/**
 * Class Repeat_Summary.
 *
 * Describes a repeat rule as a sentence, such as "Every 2 weeks on Monday and
 * Friday, until March 1, 2027." The strings match the editor's, in
 * `src/helpers/repeat.js`, so they share translations.
 *
 * @since TBD
 */
final class Repeat_Summary {

	/**
	 * The sentence for an event: the rule of the date it shows, or the one running today.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 *
	 * @return string The sentence, or an empty string for an event that doesn't repeat.
	 */
	public static function for_event( Event $event ): string {
		$rule = self::rule_for( $event );

		return $rule ? self::of_rule( $rule ) : '';
	}

	/**
	 * The rule to describe for an event.
	 *
	 * On a date's page or row, the rule that produces that date. Otherwise the
	 * first rule with dates from today on, or the last to end.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 *
	 * @return Rule|null The rule, or null for an event without a repeating rule.
	 */
	public static function rule_for( Event $event ): ?Rule {
		$rules = array_values(
			array_filter( $event->rules()->all(), static fn( Rule $rule ): bool => null !== $rule->frequency )
		);

		usort( $rules, static fn( Rule $a, Rule $b ): int => $a->starts_on <=> $b->starts_on );

		$occurrence = Current_Occurrence::get_instance()->get( $event->post->ID ?? 0 );
		$utc        = new DateTimeZone( 'UTC' );
		$shown      = $occurrence ? new DateTimeImmutable( substr( $occurrence['recurrence_id'], 0, 8 ), $utc ) : null;

		foreach ( $rules as $rule ) {
			$day = $shown ?? self::today( $rule->timezone );
			$on  = $rule->next_date( $day );

			// Shown dates compare by value, since the rule's date is a separate object.
			if ( $on && ( ! $shown || $on->format( 'Ymd' ) === $shown->format( 'Ymd' ) ) ) {
				return $rule;
			}
		}

		return $rules ? end( $rules ) : null;
	}

	/**
	 * The sentence for a rule.
	 *
	 * @since TBD
	 *
	 * @param Rule $rule A repeating rule.
	 *
	 * @return string Such as "Every week on Monday." or "Every month on day 5, until March 1, 2027."
	 */
	public static function of_rule( Rule $rule ): string {
		if ( null === $rule->ends_on ) {
			/* translators: %s: How often an event repeats, such as "Every week on Monday". */
			return sprintf( __( '%s.', 'gatherpress' ), self::repeat_phrase( $rule ) );
		}

		$format = (string) get_option( 'date_format' );

		return sprintf(
			/* translators: 1: How often an event repeats, such as "Every week on Monday". 2: A date. */
			__( '%1$s, until %2$s.', 'gatherpress' ),
			self::repeat_phrase( $rule ),
			wp_date( $format, $rule->ends_on->getTimestamp(), new DateTimeZone( 'UTC' ) )
		);
	}

	/**
	 * How often a rule repeats, as a sentence's start.
	 *
	 * @since TBD
	 *
	 * @param Rule $rule The rule.
	 *
	 * @return string Such as "Every 2 weeks on Monday and Friday".
	 */
	public static function repeat_phrase( Rule $rule ): string {
		$count = $rule->interval;

		return match ( $rule->frequency ) {
			'WEEKLY'  => self::weekly_phrase( $rule, $count ),
			'MONTHLY' => self::monthly_phrase( $rule, $count ),
			'DAILY'   => 1 === $count
				? __( 'Every day', 'gatherpress' )
				/* translators: %d: A number of days. */
				: sprintf( _n( 'Every %d day', 'Every %d days', $count, 'gatherpress' ), $count ),
			default   => 1 === $count
				? __( 'Every year', 'gatherpress' )
				/* translators: %d: A number of years. */
				: sprintf( _n( 'Every %d year', 'Every %d years', $count, 'gatherpress' ), $count ),
		};
	}

	/**
	 * How often a weekly rule repeats.
	 *
	 * @since TBD
	 *
	 * @param Rule $rule  A weekly rule.
	 * @param int  $count Its interval.
	 *
	 * @return string Such as "Every 2 weeks on Monday and Friday".
	 */
	public static function weekly_phrase( Rule $rule, int $count ): string {
		// A rule always has its days, taken from its start date when its RRULE names none.
		$days = self::list_of( array_map( array( self::class, 'weekday_name' ), $rule->weekdays ) );

		if ( 1 === $count ) {
			/* translators: %s: A list of weekday names. */
			return sprintf( __( 'Every week on %s', 'gatherpress' ), $days );
		}

		return sprintf(
			/* translators: 1: A number of weeks. 2: A list of weekday names. */
			_n( 'Every %1$d week on %2$s', 'Every %1$d weeks on %2$s', $count, 'gatherpress' ),
			$count,
			$days
		);
	}

	/**
	 * How often a monthly rule repeats.
	 *
	 * @since TBD
	 *
	 * @param Rule $rule  A monthly rule.
	 * @param int  $count Its interval.
	 *
	 * @return string Such as "Every month on the second Friday".
	 */
	public static function monthly_phrase( Rule $rule, int $count ): string {
		$ordinal = $rule->ordinal_weekday;
		$place   = $ordinal ? self::ordinal_weekday( (int) $ordinal[0], (string) $ordinal[1] ) : '';

		if ( '' !== $place ) {
			return 1 === $count
				/* translators: %s: A weekday's place in the month, such as "second Friday". */
				? sprintf( __( 'Every month on the %s', 'gatherpress' ), $place )
				: sprintf(
					/* translators: 1: A number of months. 2: A weekday's place in the month, e.g. "second Friday". */
					_n( 'Every %1$d month on the %2$s', 'Every %1$d months on the %2$s', $count, 'gatherpress' ),
					$count,
					$place
				);
		}

		// Without a weekday's place, a monthly rule has its day of the month.
		return 1 === $count
			/* translators: %d: Day of the month. */
			? sprintf( __( 'Every month on day %d', 'gatherpress' ), (int) $rule->month_day )
			: sprintf(
				/* translators: 1: A number of months. 2: Day of the month. */
				_n( 'Every %1$d month on day %2$d', 'Every %1$d months on day %2$d', $count, 'gatherpress' ),
				$count,
				(int) $rule->month_day
			);
	}

	/**
	 * A weekday's full name.
	 *
	 * @since TBD
	 *
	 * @param string $code A weekday code, such as `MO`.
	 *
	 * @return string The translated name, or the code when unknown.
	 */
	public static function weekday_name( string $code ): string {
		$names = array(
			'MO' => __( 'Monday', 'gatherpress' ),
			'TU' => __( 'Tuesday', 'gatherpress' ),
			'WE' => __( 'Wednesday', 'gatherpress' ),
			'TH' => __( 'Thursday', 'gatherpress' ),
			'FR' => __( 'Friday', 'gatherpress' ),
			'SA' => __( 'Saturday', 'gatherpress' ),
			'SU' => __( 'Sunday', 'gatherpress' ),
		);

		return $names[ $code ] ?? $code;
	}

	/**
	 * A weekday's place in its month, such as "second Friday".
	 *
	 * @since TBD
	 *
	 * @param int    $place 1 to 4, or -1 for the last.
	 * @param string $code  A weekday code.
	 *
	 * @return string The phrase, or an empty string for an unknown place.
	 */
	public static function ordinal_weekday( int $place, string $code ): string {
		$name = self::weekday_name( $code );

		return match ( $place ) {
			/* translators: %s: A weekday name. */
			1       => sprintf( _x( 'first %s', 'weekday in a month', 'gatherpress' ), $name ),
			/* translators: %s: A weekday name. */
			2       => sprintf( _x( 'second %s', 'weekday in a month', 'gatherpress' ), $name ),
			/* translators: %s: A weekday name. */
			3       => sprintf( _x( 'third %s', 'weekday in a month', 'gatherpress' ), $name ),
			/* translators: %s: A weekday name. */
			4       => sprintf( _x( 'fourth %s', 'weekday in a month', 'gatherpress' ), $name ),
			/* translators: %s: A weekday name. */
			-1      => sprintf( _x( 'last %s', 'weekday in a month', 'gatherpress' ), $name ),
			// Places beyond these aren't rule values.
			default => '',
		};
	}

	/**
	 * A list of names joined for a sentence.
	 *
	 * @since TBD
	 *
	 * @param string[] $names The names.
	 *
	 * @return string Such as "Monday, Wednesday and Friday".
	 */
	public static function list_of( array $names ): string {
		if ( count( $names ) <= 1 ) {
			return $names[0] ?? '';
		}

		$last = array_pop( $names );

		return sprintf(
		/* translators: 1: A comma-separated list. 2: The last item. */
			__( '%1$s and %2$s', 'gatherpress' ),
			// A list separator; translators can change it, such as to "、".
			implode( _x( ',', 'list separator', 'gatherpress' ) . ' ', $names ),
			$last
		);
	}

	/**
	 * Today in a timezone, at midnight UTC.
	 *
	 * @since TBD
	 *
	 * @param DateTimeZone $timezone The timezone.
	 *
	 * @return DateTimeImmutable The date.
	 */
	protected static function today( DateTimeZone $timezone ): DateTimeImmutable {
		$local = ( new DateTimeImmutable( 'now', $timezone ) )->format( 'Y-m-d' );

		return new DateTimeImmutable( $local, new DateTimeZone( 'UTC' ) );
	}
}
