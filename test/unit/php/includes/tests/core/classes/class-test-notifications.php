<?php
/**
 * Class handles unit tests for GatherPress\Core\Notifications.
 *
 * @package GatherPress\Core
 * @since 0.34.1
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Notifications;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Notifications.
 *
 * @coversDefaultClass \GatherPress\Core\Notifications
 */
class Test_Notifications extends Base {

	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Notifications::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'admin_notices',
				'priority' => 10,
				'callback' => array( $instance, 'render' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for is_below_upcoming_php method.
	 *
	 * The comparison takes the version as an argument so both sides of the
	 * gate are testable without the suite having to run on an old PHP.
	 *
	 * @covers ::is_below_upcoming_php
	 *
	 * @return void
	 */
	public function test_is_below_upcoming_php(): void {
		$instance = Notifications::get_instance();

		$this->assertTrue(
			$instance->is_below_upcoming_php( '7.4.33' ),
			'Failed to assert that PHP 7.4 falls below the upcoming requirement.'
		);
		$this->assertTrue(
			$instance->is_below_upcoming_php( '8.0.30' ),
			'Failed to assert that PHP 8.0 falls below the upcoming requirement.'
		);
		$this->assertFalse(
			$instance->is_below_upcoming_php( '8.1.0' ),
			'Failed to assert that PHP 8.1 meets the upcoming requirement.'
		);
		$this->assertFalse(
			$instance->is_below_upcoming_php( '8.4.1' ),
			'Failed to assert that PHP 8.4 meets the upcoming requirement.'
		);
	}

	/**
	 * Coverage for is_below_upcoming_wp method.
	 *
	 * @covers ::is_below_upcoming_wp
	 *
	 * @return void
	 */
	public function test_is_below_upcoming_wp(): void {
		$instance = Notifications::get_instance();

		$this->assertTrue(
			$instance->is_below_upcoming_wp( '6.7' ),
			'Failed to assert that WordPress 6.7 falls below the upcoming requirement.'
		);
		$this->assertTrue(
			$instance->is_below_upcoming_wp( '6.9.2' ),
			'Failed to assert that WordPress 6.9.2 falls below the upcoming requirement.'
		);
		$this->assertFalse(
			$instance->is_below_upcoming_wp( '7.0' ),
			'Failed to assert that WordPress 7.0 meets the upcoming requirement.'
		);
		$this->assertFalse(
			$instance->is_below_upcoming_wp( '7.1' ),
			'Failed to assert that WordPress 7.1 meets the upcoming requirement.'
		);
	}

	/**
	 * Coverage for register and get_notices methods.
	 *
	 * @covers ::register
	 * @covers ::get_notices
	 *
	 * @return void
	 */
	public function test_register(): void {
		$instance = Notifications::get_instance();
		$original = Utility::get_hidden_property( $instance, 'notices' );

		$instance->register(
			'gatherpress_test_notice',
			static function (): bool {
				return true;
			},
			static function (): string {
				return 'Test notice.';
			}
		);

		$notices = $instance->get_notices();

		$this->assertArrayHasKey(
			'gatherpress_test_notice',
			$notices,
			'Failed to assert that the notice was registered.'
		);
		$this->assertSame(
			Notifications::CAPABILITY,
			$notices['gatherpress_test_notice']['capability'],
			'Failed to assert that the notice defaulted to the shared capability.'
		);

		Utility::set_and_get_hidden_property( $instance, 'notices', $original );
	}

	/**
	 * Coverage for render method: a notice renders while its condition holds.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_outputs_notice_when_condition_holds(): void {
		$instance = Notifications::get_instance();
		$original = Utility::get_hidden_property( $instance, 'notices' );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		Utility::set_and_get_hidden_property(
			$instance,
			'notices',
			array(
				'gatherpress_test_notice' => array(
					'condition'  => static function (): bool {
						return true;
					},
					'message'    => static function (): string {
						return 'Condition holds.';
					},
					'capability' => 'read',
					'type'       => 'warning',
				),
			)
		);

		$output = Utility::buffer_and_return( array( $instance, 'render' ) );

		$this->assertStringContainsString(
			'Condition holds.',
			$output,
			'Failed to assert that the notice rendered while its condition held.'
		);

		Utility::set_and_get_hidden_property( $instance, 'notices', $original );
	}

	/**
	 * Coverage for render method: a notice stays silent when its condition fails.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_is_silent_when_condition_fails(): void {
		$instance = Notifications::get_instance();
		$original = Utility::get_hidden_property( $instance, 'notices' );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		Utility::set_and_get_hidden_property(
			$instance,
			'notices',
			array(
				'gatherpress_test_notice' => array(
					'condition'  => static function (): bool {
						return false;
					},
					'message'    => static function (): string {
						return 'Condition fails.';
					},
					'capability' => 'read',
					'type'       => 'warning',
				),
			)
		);

		$output = Utility::buffer_and_return( array( $instance, 'render' ) );

		$this->assertStringNotContainsString(
			'Condition fails.',
			$output,
			'Failed to assert that the notice stayed silent while its condition failed.'
		);

		Utility::set_and_get_hidden_property( $instance, 'notices', $original );
	}

	/**
	 * Coverage for render method: the capability gate hides the notice.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_respects_capability_gate(): void {
		$instance = Notifications::get_instance();
		$original = Utility::get_hidden_property( $instance, 'notices' );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		Utility::set_and_get_hidden_property(
			$instance,
			'notices',
			array(
				'gatherpress_test_notice' => array(
					'condition'  => static function (): bool {
						return true;
					},
					'message'    => static function (): string {
						return 'Capability gated.';
					},
					'capability' => Notifications::CAPABILITY,
					'type'       => 'warning',
				),
			)
		);

		$output = Utility::buffer_and_return( array( $instance, 'render' ) );

		$this->assertStringNotContainsString(
			'Capability gated.',
			$output,
			'Failed to assert that a user without the capability was not shown the notice.'
		);

		Utility::set_and_get_hidden_property( $instance, 'notices', $original );
	}

	/**
	 * Coverage for the callables the requirement notices are registered with.
	 *
	 * The render tests swap in fixture notices, so without this the real
	 * condition and message closures would be registered but never executed.
	 *
	 * @covers ::register_requirement_notices
	 *
	 * @return void
	 */
	public function test_requirement_notice_callbacks(): void {
		$notices = Notifications::get_instance()->get_notices();
		$php     = $notices['gatherpress_upcoming_php_requirement'];
		$wp      = $notices['gatherpress_upcoming_wp_requirement'];

		// The conditions read the live environment, so the value depends on
		// where the suite runs. The comparison itself is asserted both ways in
		// its own test; here we only need the closure to execute.
		$this->assertIsBool(
			call_user_func( $php['condition'] ),
			'Failed to assert that the PHP condition returned a boolean.'
		);
		$this->assertIsBool(
			call_user_func( $wp['condition'] ),
			'Failed to assert that the WordPress condition returned a boolean.'
		);

		$php_message = call_user_func( $php['message'] );

		$this->assertStringContainsString(
			Notifications::UPCOMING_VERSION,
			$php_message,
			'Failed to assert that the PHP message named the upcoming version.'
		);
		$this->assertStringContainsString(
			Notifications::UPCOMING_REQUIRES_PHP,
			$php_message,
			'Failed to assert that the PHP message named the required PHP version.'
		);

		$wp_message = call_user_func( $wp['message'] );

		$this->assertStringContainsString(
			Notifications::UPCOMING_VERSION,
			$wp_message,
			'Failed to assert that the WordPress message named the upcoming version.'
		);
		$this->assertStringContainsString(
			Notifications::UPCOMING_REQUIRES_WP,
			$wp_message,
			'Failed to assert that the WordPress message named the required WordPress version.'
		);
	}

	/**
	 * Coverage for register_requirement_notices method.
	 *
	 * @covers ::register_requirement_notices
	 *
	 * @return void
	 */
	public function test_register_requirement_notices(): void {
		$instance = Notifications::get_instance();
		$original = Utility::get_hidden_property( $instance, 'notices' );

		// The constructor already registered these during plugin bootstrap,
		// which happens before coverage collection starts. Register again from
		// an empty slate so the registration itself is exercised under test.
		Utility::set_and_get_hidden_property( $instance, 'notices', array() );
		Utility::invoke_hidden_method( $instance, 'register_requirement_notices' );

		$notices = $instance->get_notices();

		$this->assertArrayHasKey(
			'gatherpress_upcoming_php_requirement',
			$notices,
			'Failed to assert that the PHP requirement notice was registered.'
		);
		$this->assertArrayHasKey(
			'gatherpress_upcoming_wp_requirement',
			$notices,
			'Failed to assert that the WordPress requirement notice was registered.'
		);

		Utility::set_and_get_hidden_property( $instance, 'notices', $original );
	}
}
