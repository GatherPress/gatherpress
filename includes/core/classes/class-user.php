<?php
/**
 * User profile and notification settings handler.
 *
 * This file contains the User class which is responsible for handling
 * user profile fields related to notification settings within the GatherPress
 * Core functionality. It includes methods for rendering the notification
 * settings fields on the user profile and saving these settings.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_User;

/**
 * Class User.
 *
 * This class is responsible for handling user-specific operations such as
 * managing user profiles, handling user permissions, and user notification settings.
 * It includes methods for rendering and saving user profile fields.
 *
 * @since 1.0.0
 */
class User {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * 12-hour time preference value
	 *
	 * @var string
	 */
	const HOUR_12 = '12-hour';

	/**
	 * 24-hour time preference value
	 *
	 * @var string
	 */
	const HOUR_24 = '24-hour';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'show_user_profile', array( $this, 'profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );

		add_filter( 'gatherpress_datetime_format', array( $this, 'user_set_time_format' ) );
		add_filter( 'gatherpress_timezone', array( $this, 'user_set_timezone' ) );
	}

	/**
	 * Get time format for a user if logged in.
	 *
	 * This is a filter to get a user defined time format. 'gatherpress_time_format'
	 *
	 * @since 1.0.0
	 *
	 * @param string $time_format The default time format.
	 *
	 * @return string The user's time format preference or the default if not set
	 */
	public function user_set_time_format( $time_format ): string {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			$user_time_format = get_user_meta( $user_id, 'gatherpress_time_format', true );

			if ( static::HOUR_12 === $user_time_format ) {
				$time_format = str_replace( 'G', 'g', $time_format );

				if ( false === strpos( $time_format, 'a' ) ) {
					$time_format = str_replace( 'i', 'ia', $time_format );
				}
			} elseif ( static::HOUR_24 === $user_time_format ) {
				$time_format = str_replace(
					array(
						'g',
						'a',
						'A',
					),
					array(
						'G',
						'',
						'',
					),
					$time_format
				);
			}
		}

		return $time_format;
	}

	/**
	 * Get timezone for a user if logged in.
	 *
	 * This is a filter to get a user defined timezone. 'gatherpress_timezone'
	 *
	 * @since 1.0.0
	 *
	 * @param string $timezone The default timezone.
	 *
	 * @return string The user's timezone preference or the default if not set.
	 */
	public function user_set_timezone( $timezone ): string {
		$user_id = get_current_user_id();

		if ( ! is_admin() && $user_id ) {
			$gatherpress_timezone = get_user_meta( $user_id, 'gatherpress_timezone', true );
			$timezone             = ! empty( $gatherpress_timezone ) ? $gatherpress_timezone : $timezone;
		}

		return $timezone;
	}

	/**
	 * Check if a user has opted in to event updates.
	 *
	 * This method centralizes the logic for checking opt-in status,
	 * including handling defaults when no preference has been set.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID to check.
	 * @return bool True if the user has opted in, false otherwise.
	 */
	public function has_event_updates_opt_in( int $user_id ): bool {
		$opt_in = get_user_meta( $user_id, 'gatherpress_event_updates_opt_in', true );

		// If not explicitly set (empty string), use the default.
		if ( '' === $opt_in ) {
			/**
			 * Filters the default state of the event updates opt-in.
			 *
			 * This filter allows modification of the default opt-in state for compliance
			 * with regional privacy laws (e.g., GDPR in Germany) that may require
			 * opt-in consent to be unchecked by default.
			 *
			 * @since 1.0.0
			 *
			 * @param string $default_opt_in Default opt-in state ('1' for opted in, '0' for opted out).
			 * @param int    $user_id        The user ID.
			 */
			$opt_in = apply_filters( 'gatherpress_event_updates_default_opt_in', '1', $user_id );
		}

		return '1' === $opt_in;
	}

	/**
	 * Renders the profile fields for user notifications settings.
	 *
	 * This method is responsible for displaying the user notification
	 * settings in the WordPress admin area. It utilizes a template
	 * for rendering the settings form.
	 *
	 * @param WP_User $user The user object for which to display the notification settings.
	 *
	 * @return void
	 */
	public function profile_fields( WP_User $user ): void {
		// Use the helper method to determine opt-in status, then convert to string for the form.
		$event_updates_opt_in = $this->has_event_updates_opt_in( $user->ID ) ? '1' : '0';

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/user/notifications.php', GATHERPRESS_CORE_PATH ),
			array(
				'event_updates_opt_in' => $event_updates_opt_in,
			),
			true
		);

		// Render the user selected date/time format and timezone fields.
		$gatherpress_time_format = get_user_meta( $user->ID, 'gatherpress_time_format', true );
		$gatherpress_timezone    = get_user_meta( $user->ID, 'gatherpress_timezone', true );
		$tz_choices              = Utility::timezone_choices();

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/user/date-time.php', GATHERPRESS_CORE_PATH ),
			array(
				'time_format' => $gatherpress_time_format,
				'timezone'    => $gatherpress_timezone ? $gatherpress_timezone : Utility::get_system_timezone(),
				'tz_choices'  => $tz_choices,
			),
			true
		);
	}

	/**
	 * Saves the profile fields for user notifications settings.
	 *
	 * Handles the saving of user notification settings from the WordPress admin area.
	 * Ensures nonce verification for security and checks current user capabilities
	 * before updating the user meta information.
	 *
	 * @param int $user_id The ID of the user whose notification settings are being updated.
	 *
	 * @return void
	 */
	public function save_profile_fields( int $user_id ): void {
		$nonce = Utility::get_http_input( INPUT_POST, '_wpnonce' );

		if (
			empty( $nonce ) ||
			! wp_verify_nonce( $nonce, 'update-user_' . $user_id )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', intval( Utility::get_http_input( INPUT_POST, 'gatherpress_event_updates_opt_in' ) ) );
		update_user_meta( $user_id, 'gatherpress_time_format', Utility::get_http_input( INPUT_POST, 'gatherpress_time_format' ) );
		update_user_meta( $user_id, 'gatherpress_timezone', Utility::get_http_input( INPUT_POST, 'gatherpress_timezone' ) );
	}
}
