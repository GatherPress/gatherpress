<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Setup.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Venue\Map\Manager;
use GatherPress\Core\Venue\Map\Map;
use GatherPress\Core\Venue\Map\Prewarm;
use GatherPress\Core\Venue\Map\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Setup
 */
class Test_Setup extends Base {
	/**
	 * Map\Setup is the hub for the venue map subsystem — its
	 * `instantiate_classes()` is what wires Manager / Map / Prewarm so
	 * `Venue\Setup` can hand off in one line. Per-sibling
	 * proof-of-construction via each one's distinctive
	 * `setup_hooks()`-registered callback. Catches the case where a
	 * sibling silently drops out of `instantiate_classes()`.
	 *
	 * @covers ::__construct
	 * @covers ::instantiate_classes
	 *
	 * @return void
	 */
	public function test_instantiate_classes_registers_siblings(): void {
		Utility::invoke_hidden_method( Setup::get_instance(), 'instantiate_classes' );

		$expected_hooks = array(
			Manager::class => array(
				'init',
				array( Manager::get_instance(), 'do_register_action' ),
				0,
			),
			Map::class     => array(
				'rest_api_init',
				array( Map::get_instance(), 'register_rest_routes' ),
				10,
			),
			Prewarm::class => array(
				'switch_theme',
				array( Prewarm::get_instance(), 'on_theme_switched' ),
				10,
			),
		);

		foreach ( $expected_hooks as $class_name => $expected ) {
			list( $hook, $callback, $priority ) = $expected;
			$this->assertSame(
				$priority,
				has_action( $hook, $callback ),
				sprintf( '%s must be instantiated so its %s hook registers.', $class_name, $hook )
			);
		}
	}
}
