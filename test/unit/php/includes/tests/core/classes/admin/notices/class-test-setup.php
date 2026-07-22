<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Notices\Setup.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Tests\Core\Admin\Notices;

use GatherPress\Core\Admin\Notices\Base;
use GatherPress\Core\Admin\Notices\Setup;
use GatherPress\Core\Admin\Notices\Upcoming_Php_Requirement;
use GatherPress\Tests\Base as Test_Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Notices\Setup
 */
class Test_Setup extends Test_Base {

	/**
	 * Clear dismissal state and the dismissal query args between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Base::OPTION_NAME );

		unset( $_GET[ Setup::DISMISS_QUERY_ARG ], $_GET['_wpnonce'] );

		parent::tearDown();
	}

	/**
	 * Replace the registry's notices, returning the previous set.
	 *
	 * The instance is a singleton that survives between tests, so anything
	 * mutating the registry has to put it back.
	 *
	 * @param Setup $instance The registry.
	 * @param array $notices  Notices to install.
	 *
	 * @return array The previous notices.
	 */
	private function swap_notices( Setup $instance, array $notices ): array {
		$original = Utility::get_hidden_property( $instance, 'notices' );

		Utility::set_and_get_hidden_property( $instance, 'notices', $notices );

		return $original;
	}

	/**
	 * Build a persistent notice for dismissal tests.
	 *
	 * @return Base The test double.
	 */
	private function make_persistent_notice(): Base {
		return new class() extends Base {

			/**
			 * Unique slug identifying this notice.
			 *
			 * @return string The slug.
			 */
			public function get_slug(): string {
				return 'gatherpress_test';
			}

			/**
			 * The notice's message.
			 *
			 * @return string The message.
			 */
			public function get_message(): string {
				return 'Test message.';
			}

			/**
			 * Whether dismissal is remembered across page loads.
			 *
			 * @return bool Always true.
			 */
			public function is_persistent(): bool {
				return true;
			}
		};
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
		$instance = Setup::get_instance();
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
		$instance = Setup::get_instance();
		$original = $this->swap_notices( $instance, array() );

		$instance->add( new Upcoming_Php_Requirement() );

		$this->assertArrayHasKey(
			'gatherpress_upcoming_php_requirement',
			$instance->get_notices(),
			'Failed to assert that the notice was registered under its slug.'
		);

		$this->swap_notices( $instance, $original );
	}

	/**
	 * Coverage for register_default_notices method.
	 *
	 * The constructor already ran during plugin bootstrap, which happens before
	 * coverage collection starts, so the registration is invoked again here
	 * from an empty slate.
	 *
	 * @covers ::register_default_notices
	 *
	 * @return void
	 */
	public function test_register_default_notices(): void {
		$instance = Setup::get_instance();
		$original = $this->swap_notices( $instance, array() );

		Utility::invoke_hidden_method( $instance, 'register_default_notices' );

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
		$instance = Setup::get_instance();
		$original = $this->swap_notices(
			$instance,
			array( 'gatherpress_test' => $this->make_persistent_notice() )
		);

		$output = Utility::buffer_and_return( array( $instance, 'render' ) );

		$this->assertStringContainsString(
			'Test message.',
			$output,
			'Failed to assert that an applicable notice rendered.'
		);

		$instance->get_notices()['gatherpress_test']->dismiss();

		$this->assertStringNotContainsString(
			'Test message.',
			Utility::buffer_and_return( array( $instance, 'render' ) ),
			'Failed to assert that a dismissed notice was skipped.'
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
		Setup::get_instance()->handle_dismissal();

		$this->assertFalse(
			get_option( Base::OPTION_NAME, false ),
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
		$_GET[ Setup::DISMISS_QUERY_ARG ] = 'gatherpress_not_registered';

		Setup::get_instance()->handle_dismissal();

		$this->assertFalse(
			get_option( Base::OPTION_NAME, false ),
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
		$instance = Setup::get_instance();
		$original = $this->swap_notices(
			$instance,
			array( 'gatherpress_upcoming_php_requirement' => new Upcoming_Php_Requirement() )
		);

		$slug = 'gatherpress_upcoming_php_requirement';

		$_GET[ Setup::DISMISS_QUERY_ARG ] = $slug;
		$_GET['_wpnonce']                 = wp_create_nonce( 'gatherpress_dismiss_notice_' . $slug );

		$instance->handle_dismissal();

		$this->assertFalse(
			get_option( Base::OPTION_NAME, false ),
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
		$instance = Setup::get_instance();
		$original = $this->swap_notices(
			$instance,
			array( 'gatherpress_test' => $this->make_persistent_notice() )
		);

		$_GET[ Setup::DISMISS_QUERY_ARG ] = 'gatherpress_test';
		$_GET['_wpnonce']                 = 'not-a-valid-nonce';

		$instance->handle_dismissal();

		$this->assertFalse(
			get_option( Base::OPTION_NAME, false ),
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
		$instance = Setup::get_instance();
		$original = $this->swap_notices(
			$instance,
			array( 'gatherpress_test' => $this->make_persistent_notice() )
		);

		$_GET[ Setup::DISMISS_QUERY_ARG ] = 'gatherpress_test';
		$_GET['_wpnonce']                 = wp_create_nonce( 'gatherpress_dismiss_notice_gatherpress_test' );

		$instance->handle_dismissal();

		$this->assertArrayHasKey(
			'gatherpress_test',
			get_option( Base::OPTION_NAME, array() ),
			'Failed to assert that the dismissal was recorded.'
		);

		$this->swap_notices( $instance, $original );
	}
}
