<?php
/**
 * Handles misc. data validation
 *
 * This file contains the Validation class, which is responsible for validating
 * object data.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use DateTime;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Validate.
 *
 * The Validate class is responsible for ensuring event/venue/object data is
 * secure and of the correct format.
 *
 * @since 1.0.0
 */
class Validate {

	/**
	 * Validate RSVP status.
	 *
	 * Validates whether a given parameter is a valid RSVP status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $param An RSVP status to validate.
	 * @return bool True if the parameter is a valid RSVP status, false otherwise.
	 */
	public static function rsvp_status( $param ): bool {
		return in_array(
			$param,
			array(
				'attending',
				'waiting_list',
				'not_attending',
				'no_status',
			),
			true
		);
	}

	/**
	 * Validate Event Post ID.
	 *
	 * Validates whether a given parameter is a valid Event Post ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $param A Post ID to validate.
	 * @return bool True if the parameter is a valid Event Post ID, false otherwise.
	 */
	public static function event_post_id( $param ): bool {
		return (
			static::number( $param ) &&
			Event::POST_TYPE === get_post_type( $param )
		);
	}

	/**
	 * Validate a numeric value.
	 *
	 * Validates whether the given parameter is a valid numeric value greater than zero.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $param The value to validate.
	 * @return bool True if the parameter is a valid numeric value greater than zero, false otherwise.
	 */
	public static function number( $param ): bool {
		return (
			0 < intval( $param ) &&
			is_numeric( $param )
		);
	}

	/**
	 * Validate recipients for sending emails.
	 *
	 * Validates an array of email recipient options to ensure they are correctly structured.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $param An array of email recipients.
	 * @return bool True if the parameter is a valid array of email recipients, false otherwise.
	 */
	public static function send( $param ): bool {
		$expected_params = array( 'all', 'attending', 'waiting_list', 'not_attending' );

		if ( is_array( $param ) ) {
			foreach ( $expected_params as $expected_param ) {
				if (
					! array_key_exists( $expected_param, $param ) ||
					! is_bool( $param[ $expected_param ] )
				) {
					return false;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Validate an event list type.
	 *
	 * Validates whether the given event list type parameter is valid (either 'upcoming' or 'past').
	 *
	 * @since 1.0.0
	 *
	 * @param string $param The event list type to validate.
	 * @return bool True if the parameter is a valid event list type, false otherwise.
	 */
	public static function event_list_type( string $param ): bool {
		return in_array( $param, array( 'upcoming', 'past' ), true );
	}

	/**
	 * Validate a datetime string.
	 *
	 * Validates whether the given datetime string parameter is in the valid 'Y-m-d H:i:s' format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $param The datetime string to validate.
	 * @return bool True if the parameter is a valid datetime string, false otherwise.
	 */
	public static function datetime( string $param ): bool {
		return (bool) DateTime::createFromFormat( 'Y-m-d H:i:s', $param );
	}

	/**
	 * Validate a timezone identifier.
	 *
	 * Validates whether the given timezone identifier parameter is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param string $param The timezone identifier to validate.
	 * @return bool True if the parameter is a valid timezone identifier, false otherwise.
	 */
	public static function timezone( string $param ): bool {
		return in_array(
			Utility::maybe_convert_utc_offset( $param ),
			Utility::list_timezone_and_utc_offsets(),
			true
		);
	}
}
