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
		add_filter( 'gatherpress_date_format', array( $this, 'user_set_date_format' ) );
		add_filter( 'gatherpress_time_format', array( $this, 'user_set_time_format' ) );
		add_filter( 'gatherpress_timezone', array( $this, 'user_set_timezone' ) );
	}

	/**
	 * Get date format for a user if logged in.
	 *
	 * This is a filter to get a user defined date format. 'gatherpress_date_format'
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_format The default date format.
	 *
	 * @return string The user's date format preference or the default if not set
	 */
	public function user_set_date_format( $date_format ): string {
		$user_id = get_current_user_id();

		if ( $user_id ) {
			$user_date_format = get_user_meta( $user_id, 'gatherpress_date_format', true );
			$date_format      = ! empty( $user_date_format ) ? $user_date_format : $date_format;
		}

		return $date_format;
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
			$time_format      = ! empty( $user_time_format ) ? $user_time_format : $time_format;
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
		$event_updates_opt_in = get_user_meta( $user->ID, 'gatherpress_event_updates_opt_in', true );

		$settings         = Settings::get_instance();
		$time_default     = $settings->get_value( 'general', 'formatting', 'time_format' );
		$date_default     = $settings->get_value( 'general', 'formatting', 'date_format' );
		$timezone_default = Utility::get_system_timezone();

		// Checkbox is selected by default. '1' is on, '0' is off.
		if ( '0' !== $event_updates_opt_in ) {
			$event_updates_opt_in = '1';
		}

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/user/notifications.php', GATHERPRESS_CORE_PATH ),
			array(
				'event_updates_opt_in' => $event_updates_opt_in,
			),
			true
		);

		// Render the user selected date/time format and timezone fields.
		$gatherpress_date_format = get_user_meta( $user->ID, 'gatherpress_date_format', true );
		$gatherpress_time_format = get_user_meta( $user->ID, 'gatherpress_time_format', true );
		$gatherpress_timezone    = get_user_meta( $user->ID, 'gatherpress_timezone', true );
		$tz_choices              = Utility::timezone_choices();
		$date_attrs              = array(
			'name'  => 'gatherpress_date_format',
			'value' => ! empty( $gatherpress_date_format ) ? $gatherpress_date_format : $date_default,
		);
		$time_attrs              = array(
			'name'  => 'gatherpress_time_format',
			'value' => ! empty( $gatherpress_time_format ) ? $gatherpress_time_format : $time_default,
		);

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/user/date-time.php', GATHERPRESS_CORE_PATH ),
			array(
				'date_format' => $gatherpress_date_format ? $gatherpress_date_format : $date_default,
				'time_format' => $gatherpress_time_format ? $gatherpress_time_format : $time_default,
				'timezone'    => $gatherpress_timezone ? $gatherpress_timezone : $timezone_default,
				'date_attrs'  => $date_attrs,
				'time_attrs'  => $time_attrs,
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
		if (
			empty( filter_input( INPUT_POST, '_wpnonce' ) ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( filter_input( INPUT_POST, '_wpnonce' ) ) ), 'update-user_' . $user_id )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', intval( filter_input( INPUT_POST, 'gatherpress_event_updates_opt_in' ) ) );
		update_user_meta( $user_id, 'gatherpress_date_format', sanitize_text_field( filter_input( INPUT_POST, 'gatherpress_date_format', FILTER_SANITIZE_ADD_SLASHES ) ) );
		update_user_meta( $user_id, 'gatherpress_time_format', sanitize_text_field( filter_input( INPUT_POST, 'gatherpress_time_format', FILTER_SANITIZE_ADD_SLASHES ) ) );
		update_user_meta( $user_id, 'gatherpress_timezone', sanitize_text_field( filter_input( INPUT_POST, 'gatherpress_timezone' ) ) );
	}
}
