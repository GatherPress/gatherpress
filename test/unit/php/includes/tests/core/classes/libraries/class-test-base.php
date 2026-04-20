<?php
/**
 * Unit tests for GatherPress\Core\Libraries\Base.
 *
 * Coverage target: the "library missing" code paths on the abstract
 * base that the live Action Scheduler subclass can't reach in a healthy
 * test environment — `load_library()` short-circuiting when the entry
 * file isn't on disk, and `maybe_render_missing_notice()` actually
 * rendering its warning.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Libraries;

use GatherPress\Tests\Base as Base_Unit_Test;

/**
 * Class Test_Base.
 *
 * @coversDefaultClass \GatherPress\Core\Libraries\Base
 */
class Test_Base extends Base_Unit_Test {
	/**
	 * The constructor chains `load_library()` and `setup_hooks()`. When
	 * the configured entry file doesn't exist on disk, the require call
	 * is skipped silently — this test exercises the `file_exists()` false
	 * branch that the live Action Scheduler class can't reach in a
	 * healthy test environment.
	 *
	 * @covers ::__construct
	 * @covers ::load_library
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_constructor_skips_require_when_entry_missing(): void {
		$instance = new Test_Base_Concrete();

		$this->assertSame(
			10,
			has_action( 'admin_notices', array( $instance, 'maybe_render_missing_notice' ) ),
			'Base must register the admin notice regardless of whether the library loaded.'
		);
		$this->assertFalse(
			Test_Base_Concrete::is_available(),
			'Concrete helper reports unavailable so downstream notice tests hit the render branch.'
		);
	}

	/**
	 * Admin users must see the "library missing" warning so they can run
	 * `composer install` to recover. Checks that the rendered output
	 * actually contains the recovery command — not just that something
	 * came out of the buffer.
	 *
	 * @covers ::maybe_render_missing_notice
	 *
	 * @return void
	 */
	public function test_notice_renders_for_admins_when_library_missing(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance = new Test_Base_Concrete();

		ob_start();
		$instance->maybe_render_missing_notice();
		$output = ob_get_clean();

		$this->assertNotEmpty( $output, 'Admin users must see the missing-library notice.' );
		$this->assertStringContainsString(
			'Test Library',
			$output,
			'Notice must name the missing library (via get_library_name).'
		);
		$this->assertStringContainsString(
			'composer install',
			$output,
			'Notice must point at the recovery command.'
		);
	}
}
