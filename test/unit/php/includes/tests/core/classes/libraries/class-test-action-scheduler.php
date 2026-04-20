<?php
/**
 * Tests for the Action Scheduler bootstrap class.
 *
 * The library is a Composer dependency routed to
 * `includes/libraries/action-scheduler/` by `composer/installers`. These
 * tests catch the two ways that wiring can silently break — the
 * installed library disappearing from the deploy, or the loader class
 * being dropped from `Setup::instantiate_classes()` during a refactor
 * — plus the non-fatal admin notice that tells site owners how to
 * recover when the library is missing.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Libraries;

use GatherPress\Core\Libraries\Action_Scheduler;
use GatherPress\Tests\Base;

/**
 * Class Test_Action_Scheduler.
 *
 * @coversDefaultClass \GatherPress\Core\Libraries\Action_Scheduler
 */
class Test_Action_Scheduler extends Base {
	/**
	 * The library must expose its canonical enqueue function after plugin
	 * init. A false here usually means either the vendored directory
	 * disappeared from the deploy or `Setup::instantiate_classes()` lost
	 * its `Libraries\Action_Scheduler::get_instance()` call — both silent breakages
	 * the CI-run suite needs to catch before release.
	 *
	 * @covers ::is_available
	 * @covers ::load_library
	 *
	 * @return void
	 */
	public function test_is_available_returns_true_when_library_loaded(): void {
		$this->assertTrue(
			Action_Scheduler::is_available(),
			'Vendored Action Scheduler must expose as_enqueue_async_action() after plugin init.'
		);
	}

	/**
	 * The vendored library version is pinned by `composer.json` +
	 * `composer.lock`. This assertion pins the expected version in PHP so
	 * a bump via `composer require woocommerce/action-scheduler:X.Y.Z`
	 * lands as a deliberate test diff rather than a silent library swap.
	 *
	 * @return void
	 */
	public function test_vendored_version_matches_pinned_release(): void {
		$library = trailingslashit( GATHERPRESS_CORE_PATH ) . 'includes/libraries/' . Action_Scheduler::LIBRARY_ENTRY;

		$this->assertFileExists( $library, 'Pinned Action Scheduler entry file must ship in the plugin.' );

		$metadata = get_file_data( $library, array( 'Version' => 'Version' ) );

		$this->assertSame(
			'3.9.3',
			$metadata['Version'],
			'Pinned Action Scheduler version must match the release tracked in composer.json + docs.'
		);
	}

	/**
	 * `Action_Scheduler` is wired into `Setup::instantiate_classes()` so
	 * its constructor runs during plugin bootstrap. The constructor hooks
	 * the admin notice onto `admin_notices`, which this test asserts so
	 * the non-fatal "library missing" fallback UX stays reachable.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_admin_notice_hook_is_registered(): void {
		$instance = Action_Scheduler::get_instance();

		$this->assertSame(
			10,
			has_action( 'admin_notices', array( $instance, 'maybe_render_missing_notice' ) ),
			'Action_Scheduler must hook its missing-library notice onto admin_notices at priority 10.'
		);
	}

	/**
	 * When the library is loaded, the admin notice must stay silent.
	 * Guards against a regression that accidentally surfaces the recovery
	 * notice on healthy installs.
	 *
	 * @covers ::maybe_render_missing_notice
	 *
	 * @return void
	 */
	public function test_notice_does_not_render_when_library_is_available(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		Action_Scheduler::get_instance()->maybe_render_missing_notice();
		$output = ob_get_clean();

		$this->assertSame(
			'',
			$output,
			'Admin notice must not render when Action Scheduler has loaded successfully.'
		);
	}

	/**
	 * Non-admin users never see the notice even when the library is
	 * missing — WordPress hides plugin-setup notices from editors and
	 * below to keep admin UI from leaking implementation details.
	 *
	 * @covers ::maybe_render_missing_notice
	 *
	 * @return void
	 */
	public function test_notice_respects_manage_options_capability(): void {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		ob_start();
		Action_Scheduler::get_instance()->maybe_render_missing_notice();
		$output = ob_get_clean();

		$this->assertSame(
			'',
			$output,
			'Users without manage_options must not see the library-missing notice.'
		);
	}
}
