<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Notifications.
 *
 * @package GatherPress\Core\Admin
 * @since 0.34.1
 */

namespace GatherPress\Tests\Core\Admin;

use GatherPress\Core\Admin\Notice;
use GatherPress\Core\Admin\Notifications;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Notifications.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Notifications
 */
class Test_Notifications extends Base {

	/**
	 * Clear dismissal state and the dismissal query args between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Notice::OPTION_NAME );

		unset( $_GET[ Notifications::DISMISS_QUERY_ARG ], $_GET['_wpnonce'] );

		parent::tearDown();
	}

	/**
	 * Replace the registry's notices, returning the previous set.
	 *
	 * The instance is a singleton that survives between tests, so anything
	 * mutating the registry has to put it back.
	 *
	 * @param Notifications $instance The registry.
	 * @param array         $notices  Notices to install.
	 *
	 * @return array The previous notices.
	 */
	private function swap_notices( Notifications $instance, array $notices ): array {
		$original = Utility::get_hidden_property( $instance, 'notices' );

		Utility::set_and_get_hidden_property( $instance, 'notices', $notices );

		return $original;
	}

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
				'name'     => 'admin_init',
				'priority' => 10,
				'callback' => array( $instance, 'handle_dismissal' ),
			),
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
	 * Coverage for add and get_notices methods.
	 *
	 * @covers ::add
	 * @covers ::get_notices
	 *
	 * @return void
	 */
	public function test_add(): void {
		$instance = Notifications::get_instance();
		$original = $this->swap_notices( $instance, array() );

		$instance->add( new Notice( 'gatherpress_test' ) );

		$this->assertArrayHasKey(
			'gatherpress_test',
			$instance->get_notices(),
			'Failed to assert that the notice was registered under its slug.'
		);

		$this->swap_notices( $instance, $original );
	}

	/**
	 * Coverage for render method.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render(): void {
		$instance = Notifications::get_instance();
		$original = $this->swap_notices(
			$instance,
			array(
				'gatherpress_shown'   => new Notice(
					'gatherpress_shown',
					array(
						'message'   => 'Shown notice.',
						'condition' => static function (): bool {
							return true;
						},
					)
				),
				'gatherpress_skipped' => new Notice(
					'gatherpress_skipped',
					array(
						'message'   => 'Skipped notice.',
						'condition' => static function (): bool {
							return false;
						},
					)
				),
			)
		);

		$output = Utility::buffer_and_return( array( $instance, 'render' ) );

		$this->assertStringContainsString(
			'Shown notice.',
			$output,
			'Failed to assert that an applicable notice rendered.'
		);
		$this->assertStringNotContainsString(
			'Skipped notice.',
			$output,
			'Failed to assert that an inapplicable notice was skipped.'
		);

		$this->swap_notices( $instance, $original );
	}

	/**
	 * Coverage for handle_dismissal with no dismissal requested.
	 *
	 * @covers ::handle_dismissal
	 *
	 * @return void
	 */
	public function test_handle_dismissal_without_a_request(): void {
		$instance = Notifications::get_instance();

		$instance->handle_dismissal();

		$this->assertFalse(
			get_option( Notice::OPTION_NAME, false ),
			'Failed to assert that nothing was recorded without a dismissal request.'
		);
	}

	/**
	 * Coverage for handle_dismissal with a slug that is not registered.
	 *
	 * @covers ::handle_dismissal
	 *
	 * @return void
	 */
	public function test_handle_dismissal_with_an_unknown_slug(): void {
		$instance = Notifications::get_instance();

		$_GET[ Notifications::DISMISS_QUERY_ARG ] = 'gatherpress_not_registered';

		$instance->handle_dismissal();

		$this->assertFalse(
			get_option( Notice::OPTION_NAME, false ),
			'Failed to assert that an unknown slug was ignored.'
		);
	}

	/**
	 * Coverage for handle_dismissal against a non-persistent notice.
	 *
	 * @covers ::handle_dismissal
	 *
	 * @return void
	 */
	public function test_handle_dismissal_ignores_non_persistent_notices(): void {
		$instance = Notifications::get_instance();
		$original = $this->swap_notices(
			$instance,
			array( 'gatherpress_test' => new Notice( 'gatherpress_test' ) )
		);

		$_GET[ Notifications::DISMISS_QUERY_ARG ] = 'gatherpress_test';
		$_GET['_wpnonce']                         = wp_create_nonce( 'gatherpress_dismiss_notice_gatherpress_test' );

		$instance->handle_dismissal();

		$this->assertFalse(
			get_option( Notice::OPTION_NAME, false ),
			'Failed to assert that a non-persistent notice was not recorded as dismissed.'
		);

		$this->swap_notices( $instance, $original );
	}

	/**
	 * Coverage for handle_dismissal with a bad nonce.
	 *
	 * A stale or forged link must be ignored quietly rather than taking over
	 * the screen, which is why this uses wp_verify_nonce over
	 * check_admin_referer.
	 *
	 * @covers ::handle_dismissal
	 *
	 * @return void
	 */
	public function test_handle_dismissal_rejects_a_bad_nonce(): void {
		$instance = Notifications::get_instance();
		$original = $this->swap_notices(
			$instance,
			array(
				'gatherpress_test' => new Notice( 'gatherpress_test', array( 'persistent' => true ) ),
			)
		);

		$_GET[ Notifications::DISMISS_QUERY_ARG ] = 'gatherpress_test';
		$_GET['_wpnonce']                         = 'not-a-valid-nonce';

		$instance->handle_dismissal();

		$this->assertFalse(
			get_option( Notice::OPTION_NAME, false ),
			'Failed to assert that a bad nonce prevented the dismissal.'
		);

		$this->swap_notices( $instance, $original );
	}

	/**
	 * Coverage for handle_dismissal on the happy path.
	 *
	 * @covers ::handle_dismissal
	 *
	 * @return void
	 */
	public function test_handle_dismissal_records_the_dismissal(): void {
		$instance = Notifications::get_instance();
		$original = $this->swap_notices(
			$instance,
			array(
				'gatherpress_test' => new Notice( 'gatherpress_test', array( 'persistent' => true ) ),
			)
		);

		$_GET[ Notifications::DISMISS_QUERY_ARG ] = 'gatherpress_test';
		$_GET['_wpnonce']                         = wp_create_nonce( 'gatherpress_dismiss_notice_gatherpress_test' );

		$instance->handle_dismissal();

		$this->assertArrayHasKey(
			'gatherpress_test',
			get_option( Notice::OPTION_NAME, array() ),
			'Failed to assert that the dismissal was recorded.'
		);

		$this->swap_notices( $instance, $original );
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
	 * Coverage for register_requirement_notices and the callables it registers.
	 *
	 * The constructor already ran during plugin bootstrap, which happens before
	 * coverage collection starts, so the registration is invoked again here
	 * from an empty slate. The condition and message callables are then called
	 * directly, since nothing else in the suite executes them.
	 *
	 * @covers ::register_requirement_notices
	 *
	 * @return void
	 */
	public function test_register_requirement_notices(): void {
		$instance = Notifications::get_instance();
		$original = $this->swap_notices( $instance, array() );

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

		$php = $notices['gatherpress_upcoming_php_requirement'];
		$wp  = $notices['gatherpress_upcoming_wp_requirement'];

		$this->assertSame(
			Notice::TYPE_WARNING,
			$php->get_type(),
			'Failed to assert that the PHP notice is a warning.'
		);
		$this->assertFalse(
			$php->is_persistent(),
			'Failed to assert that a requirement warning cannot be silenced permanently.'
		);

		// The conditions read the live environment, so the value depends on
		// where the suite runs. The comparisons themselves are asserted both
		// ways above; here the closures only need to execute.
		$this->assertIsBool(
			call_user_func( Utility::get_hidden_property( $php, 'condition' ) ),
			'Failed to assert that the PHP condition returned a boolean.'
		);
		$this->assertIsBool(
			call_user_func( Utility::get_hidden_property( $wp, 'condition' ) ),
			'Failed to assert that the WordPress condition returned a boolean.'
		);

		$php_message = $php->get_message();

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

		$wp_message = $wp->get_message();

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

		$this->swap_notices( $instance, $original );
	}
}
