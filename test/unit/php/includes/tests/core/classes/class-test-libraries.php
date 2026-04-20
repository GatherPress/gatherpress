<?php
/**
 * Unit tests for GatherPress\Core\Libraries.
 *
 * The Libraries class is the single hand-off point from
 * `Setup::instantiate_classes()` into every vendored-library wrapper.
 * These tests lock down that contract so a refactor can't silently drop
 * a library from the bootstrap chain without a failing test to stop it.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Libraries;
use GatherPress\Core\Libraries\Action_Scheduler;
use GatherPress\Tests\Base;

/**
 * Class Test_Libraries.
 *
 * @coversDefaultClass \GatherPress\Core\Libraries
 */
class Test_Libraries extends Base {
	/**
	 * `get_instance()` returns the singleton that `Setup::instantiate_classes()`
	 * hands off to; locking the class type in catches an accidental class
	 * rename or namespace move.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_get_instance_returns_libraries_loader(): void {
		$this->assertInstanceOf( Libraries::class, Libraries::get_instance() );
	}

	/**
	 * Loading the Libraries class must propagate to every wrapped
	 * library. Action Scheduler's availability is the proxy — if
	 * `load_libraries()` lost its `Action_Scheduler::get_instance()` call,
	 * the AS `admin_notices` hook would never register and this assertion
	 * would fail (the wrapper's hook registration is what we're actually
	 * asserting here, not `as_enqueue_async_action`'s definition, which
	 * is wired up separately in the test bootstrap).
	 *
	 * @covers ::load_libraries
	 *
	 * @return void
	 */
	public function test_load_libraries_instantiates_action_scheduler(): void {
		Libraries::get_instance();

		$this->assertSame(
			10,
			has_action(
				'admin_notices',
				array( Action_Scheduler::get_instance(), 'maybe_render_missing_notice' )
			),
			'Libraries must instantiate Action_Scheduler so its missing-library admin notice is registered.'
		);
	}
}
