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
		$event_updates_opt_in = get_user_meta( $user->ID, 'gp-event-updates-opt-in', true );

		$default_tz   = static::get_system_timezone();
		$date_default = get_option( 'date_format', 'l, F j, Y' );
		$time_default = get_option( 'time_format', 'g:i A' );

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
		$gp_date_format = get_user_meta( $user->ID, 'gp_date_format', true );
		$gp_time_format = get_user_meta( $user->ID, 'gp_time_format', true );
		$gp_timezone    = get_user_meta( $user->ID, 'gp_timezone', true );
		$tz_choices     = Utility::timezone_choices();
		$date_attrs     = array(
			'name'  => 'gp_date_format',
			'value' => ! empty( $gp_date_format ) ? $gp_date_format : $date_default,
		);
		$time_attrs     = array(
			'name'  => 'gp_time_format',
			'value' => ! empty( $gp_time_format ) ? $gp_time_format : $time_default,
		);

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/user/date-time.php', GATHERPRESS_CORE_PATH ),
			array(
				'date_format' => $gp_date_format ? $gp_date_format : $date_default,
				'time_format' => $gp_time_format ? $gp_time_format : $time_default,
				'timezone'    => $gp_timezone ? $gp_timezone : $default_tz,
				'date_attrs'  => $date_attrs,
				'time_attrs'  => $time_attrs,
				'tz_choices'  => $tz_choices,
			),
			true
		);
	}

	/**
	 * Get the system default timezone settings
	 *
	 * @since 0.29.9
	 *
	 * @return string
	 */
	private static function get_system_timezone(): string {
		$current_offset = get_option( 'gmt_offset' );
		$tzstring       = get_option( 'timezone_string' );

		// Remove old Etc mappings. Fallback to gmt_offset.
		if ( str_contains( $tzstring, 'Etc/GMT' ) ) {
			$tzstring = '';
		}

		if ( empty( $tzstring ) ) { // Create a UTC+- zone if no timezone string exists.
			if ( 0 == $current_offset ) {
				$tzstring = 'UTC+0';
			} elseif ( $current_offset < 0 ) {
				$tzstring = 'UTC' . $current_offset;
			} else {
				$tzstring = 'UTC+' . $current_offset;
			}
		}

		return $tzstring;
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
			! wp_verify_nonce( filter_input( INPUT_POST, '_wpnonce' ), 'update-user_' . $user_id )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		update_user_meta( $user_id, 'gp-event-updates-opt-in', intval( filter_input( INPUT_POST, 'gp-event-updates-opt-in' ) ) );
		update_user_meta( $user_id, 'gp_date_format', sanitize_text_field( filter_input( INPUT_POST, 'gp_date_format' ) ) );
		update_user_meta( $user_id, 'gp_time_format', sanitize_text_field( filter_input( INPUT_POST, 'gp_time_format' ) ) );
		update_user_meta( $user_id, 'gp_timezone', sanitize_text_field( filter_input( INPUT_POST, 'gp_timezone' ) ) );
	}
}
