<?php
/**
 * Class handles unit tests for GatherPress\Core\User.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\User;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_User.
 *
 * @coversDefaultClass \GatherPress\Core\User
 */
class Test_User extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = User::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'show_user_profile',
				'priority' => 10,
				'callback' => array( $instance, 'profile_fields' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'edit_user_profile',
				'priority' => 10,
				'callback' => array( $instance, 'profile_fields' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'personal_options_update',
				'priority' => 10,
				'callback' => array( $instance, 'save_profile_fields' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'edit_user_profile_update',
				'priority' => 10,
				'callback' => array( $instance, 'save_profile_fields' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'gatherpress_datetime_format',
				'priority' => 10,
				'callback' => array( $instance, 'user_set_time_format' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'gatherpress_timezone',
				'priority' => 10,
				'callback' => array( $instance, 'user_set_timezone' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for profile_fields.
	 *
	 * @covers ::profile_fields
	 * @covers ::has_event_updates_opt_in
	 *
	 * @return void
	 */
	public function test_profile_fields(): void {
		$instance = User::get_instance();
		$user     = $this->mock->user( true )->get();

		// Test default checkbox state (should be checked).
		delete_user_meta( $user->ID, 'gatherpress_event_updates_opt_in' );
		$markup = Utility::buffer_and_return( array( $instance, 'profile_fields' ), array( $user ) );
		$this->assertStringContainsString(
			'checked=\'checked\'',
			$markup,
			'Failed to assert that checkbox is checked by default.'
		);

		// Test with explicit opt-out.
		update_user_meta( $user->ID, 'gatherpress_event_updates_opt_in', 0 );
		$markup = Utility::buffer_and_return( array( $instance, 'profile_fields' ), array( $user ) );
		$this->assertStringNotContainsString(
			'checked=\'checked\'',
			$markup,
			'Failed to assert that checkbox is not checked.'
		);

		// Test with explicit opt-in.
		update_user_meta( $user->ID, 'gatherpress_event_updates_opt_in', 1 );
		$markup = Utility::buffer_and_return( array( $instance, 'profile_fields' ), array( $user ) );
		$this->assertStringContainsString(
			'checked=\'checked\'',
			$markup,
			'Failed to assert that checkbox is checked.'
		);

		// Test with filter changing default to unchecked.
		delete_user_meta( $user->ID, 'gatherpress_event_updates_opt_in' );
		add_filter(
			'gatherpress_event_updates_default_opt_in',
			function () {
				return '0';
			}
		);

		$markup = Utility::buffer_and_return( array( $instance, 'profile_fields' ), array( $user ) );
		$this->assertStringNotContainsString(
			'checked=\'checked\'',
			$markup,
			'Failed to assert that checkbox respects filter for unchecked default.'
		);

		// Clean up filter.
		remove_all_filters( 'gatherpress_event_updates_default_opt_in' );

		// Check 12 vs 24 hour preference.
		update_user_meta( $user->ID, 'gatherpress_time_format', User::HOUR_12 );

		$markup = Utility::buffer_and_return(
			array( $instance, 'profile_fields' ),
			array( $user )
		);

		$this->assertStringContainsString(
			'<option value="12-hour"  selected=\'selected\'>',
			$markup,
			"12-hour option was expected to be selected but wasn't"
		);
	}

	/**
	 * Coverage for user time formatting option
	 *
	 * @covers ::user_set_time_format
	 *
	 * @return void
	 */
	public function test_user_set_time_format(): void {
		$instance = User::get_instance();
		$user     = $this->mock->user( true )->get();

		// Sanity check.
		$this->assertEquals( 'g:ia', $instance->user_set_time_format( 'g:ia' ) );

		// Override 24-hour site to 12 hour (adds am/pm).
		update_user_meta( $user->ID, 'gatherpress_time_format', User::HOUR_12 );
		$this->assertEquals(
			'g:ia',
			$instance->user_set_time_format( 'G:i' )
		);

		// Override 12-hour site to 24 hour (removes am/pm).
		update_user_meta( $user->ID, 'gatherpress_time_format', User::HOUR_24 );
		$this->assertEquals(
			'G:i',
			$instance->user_set_time_format( 'g:ia' )
		);
	}

	/**
	 * Coverage for timezone getter
	 *
	 * @covers ::user_set_timezone
	 *
	 * @return void
	 */
	public function test_user_set_timezone(): void {
		$instance = User::get_instance();
		$user     = $this->mock->user( true )->get();

		// Sanity check.
		$this->assertEquals( 'my-dawg', $instance->user_set_timezone( 'my-dawg' ) );

		// Check override.
		update_user_meta( $user->ID, 'gatherpress_timezone', 'Hammer Time' );
		$this->assertEquals(
			'Hammer Time',
			$instance->user_set_timezone( 'ET-or-whatever' )
		);
	}

	/**
	 * Coverage for save_profile_fields method with valid nonce and permissions.
	 *
	 * @covers ::save_profile_fields
	 *
	 * @return void
	 */
	public function test_save_profile_fields(): void {
		$instance = User::get_instance();
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Mock input data using filter.
		$mock_data = array(
			INPUT_POST => array(
				'_wpnonce'                         => wp_create_nonce( 'update-user_' . $user_id ),
				'gatherpress_event_updates_opt_in' => '1',
				'gatherpress_time_format'          => User::HOUR_24,
				'gatherpress_timezone'             => 'America/New_York',
			),
		);

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $mock_data ) {
				return $mock_data[ $type ][ $var_name ] ?? null;
			},
			10,
			3
		);

		// Save profile fields.
		$instance->save_profile_fields( $user_id );

		// Verify all three meta fields were saved.
		$this->assertEquals(
			'1',
			get_user_meta( $user_id, 'gatherpress_event_updates_opt_in', true ),
			'Event updates opt-in should be saved.'
		);
		$this->assertEquals(
			User::HOUR_24,
			get_user_meta( $user_id, 'gatherpress_time_format', true ),
			'Time format should be saved.'
		);
		$this->assertEquals(
			'America/New_York',
			get_user_meta( $user_id, 'gatherpress_timezone', true ),
			'Timezone should be saved.'
		);

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for save_profile_fields method with invalid nonce.
	 *
	 * @covers ::save_profile_fields
	 *
	 * @return void
	 */
	public function test_save_profile_fields_invalid_nonce(): void {
		$instance = User::get_instance();
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Mock input data with invalid nonce.
		$mock_data = array(
			INPUT_POST => array(
				'_wpnonce'                         => 'invalid_nonce',
				'gatherpress_event_updates_opt_in' => '1',
			),
		);

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $mock_data ) {
				return $mock_data[ $type ][ $var_name ] ?? null;
			},
			10,
			3
		);

		// Save profile fields (should return early).
		$instance->save_profile_fields( $user_id );

		// Verify meta was NOT saved.
		$this->assertEmpty(
			get_user_meta( $user_id, 'gatherpress_event_updates_opt_in', true ),
			'Meta should not be saved with invalid nonce.'
		);

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for save_profile_fields method with missing nonce.
	 *
	 * @covers ::save_profile_fields
	 *
	 * @return void
	 */
	public function test_save_profile_fields_missing_nonce(): void {
		$instance = User::get_instance();
		$user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Mock input data without nonce (returns null).
		$mock_data = array(
			INPUT_POST => array(
				'gatherpress_event_updates_opt_in' => '1',
			),
		);

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $mock_data ) {
				return $mock_data[ $type ][ $var_name ] ?? null;
			},
			10,
			3
		);

		// Save profile fields (should return early).
		$instance->save_profile_fields( $user_id );

		// Verify meta was NOT saved.
		$this->assertEmpty(
			get_user_meta( $user_id, 'gatherpress_event_updates_opt_in', true ),
			'Meta should not be saved without nonce.'
		);

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for save_profile_fields method without edit_user capability.
	 *
	 * @covers ::save_profile_fields
	 *
	 * @return void
	 */
	public function test_save_profile_fields_no_capability(): void {
		$instance = User::get_instance();
		$user_id  = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Mock input data to edit admin (subscriber can't edit admin).
		$mock_data = array(
			INPUT_POST => array(
				'_wpnonce'                         => wp_create_nonce( 'update-user_' . $admin_id ),
				'gatherpress_event_updates_opt_in' => '1',
			),
		);

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $mock_data ) {
				return $mock_data[ $type ][ $var_name ] ?? null;
			},
			10,
			3
		);

		// Try to save admin profile fields (should return early).
		$instance->save_profile_fields( $admin_id );

		// Verify meta was NOT saved.
		$this->assertEmpty(
			get_user_meta( $admin_id, 'gatherpress_event_updates_opt_in', true ),
			'Subscriber should not be able to edit administrator profile.'
		);

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Coverage for user_set_time_format when format already contains 'a'.
	 *
	 * @covers ::user_set_time_format
	 *
	 * @return void
	 */
	public function test_user_set_time_format_already_has_am_pm(): void {
		$instance = User::get_instance();
		$user     = $this->mock->user( true )->get();

		// Override to 12-hour with a format that already has 'a'.
		update_user_meta( $user->ID, 'gatherpress_time_format', User::HOUR_12 );
		$this->assertEquals(
			'g:ia',
			$instance->user_set_time_format( 'g:ia' ),
			'Should not add duplicate am/pm when already present.'
		);
	}

	/**
	 * Coverage for user_set_timezone in admin context.
	 *
	 * @covers ::user_set_timezone
	 *
	 * @return void
	 */
	public function test_user_set_timezone_in_admin(): void {
		$instance = User::get_instance();
		$user     = $this->mock->user( true )->get();

		// Set user timezone preference.
		update_user_meta( $user->ID, 'gatherpress_timezone', 'America/New_York' );

		// Mock admin context.
		set_current_screen( 'dashboard' );

		// In admin, should return the default timezone, not user preference.
		$this->assertEquals(
			'UTC',
			$instance->user_set_timezone( 'UTC' ),
			'User timezone should not be used in admin context.'
		);

		// Clean up.
		set_current_screen( 'front' );
	}

	/**
	 * Coverage for has_event_updates_opt_in method.
	 *
	 * @covers ::has_event_updates_opt_in
	 *
	 * @return void
	 */
	public function test_has_event_updates_opt_in(): void {
		$instance = User::get_instance();
		$user     = $this->factory->user->create();

		// Test default behavior (should be true when not set).
		$this->assertTrue(
			$instance->has_event_updates_opt_in( $user ),
			'Default opt-in should be true when user meta is not set.'
		);

		// Test explicitly opted out.
		update_user_meta( $user, 'gatherpress_event_updates_opt_in', '0' );
		$this->assertFalse(
			$instance->has_event_updates_opt_in( $user ),
			'Should return false when user has explicitly opted out.'
		);

		// Test explicitly opted in.
		update_user_meta( $user, 'gatherpress_event_updates_opt_in', '1' );
		$this->assertTrue(
			$instance->has_event_updates_opt_in( $user ),
			'Should return true when user has explicitly opted in.'
		);

		// Test with filter changing default to opted out.
		delete_user_meta( $user, 'gatherpress_event_updates_opt_in' );
		add_filter(
			'gatherpress_event_updates_default_opt_in',
			function () {
				return '0';
			}
		);

		$this->assertFalse(
			$instance->has_event_updates_opt_in( $user ),
			'Should return false when filter sets default to opted out.'
		);

		// Clean up filter.
		remove_all_filters( 'gatherpress_event_updates_default_opt_in' );

		// Test that filter doesn't override explicit user preference.
		update_user_meta( $user, 'gatherpress_event_updates_opt_in', '1' );
		add_filter(
			'gatherpress_event_updates_default_opt_in',
			function () {
				return '0';
			}
		);

		$this->assertTrue(
			$instance->has_event_updates_opt_in( $user ),
			'Filter should not override explicit user preference.'
		);

		// Clean up.
		remove_all_filters( 'gatherpress_event_updates_default_opt_in' );
	}
}
