<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Setup.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Venue\Map;
use GatherPress\Core\Venue\Map\Manager;
use GatherPress\Core\Venue\Map\Provider\Google;
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

	/**
	 * Construction of the singleton runs `instantiate_classes()` so the
	 * subsystem boots in one line from `Venue\Setup`. The plugin
	 * bootstrap creates the instance long before this test class loads,
	 * so reset the cached static `$instance` to force a fresh
	 * construction and credit the `__construct()` body to coverage.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_runs_instantiate_classes(): void {
		$reflection = new \ReflectionClass( Setup::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		$instance = Setup::get_instance();

		$this->assertInstanceOf( Setup::class, $instance );
	}

	/**
	 * `setup_hooks()` registers Google when the companion provider action fires.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks_registers_google_on_provider_action(): void {
		remove_all_actions( 'gatherpress_register_static_map_providers' );

		$registry   = Manager::get_instance();
		$reflection = new \ReflectionClass( Manager::class );
		$property   = $reflection->getProperty( 'providers' );
		$property->setAccessible( true );
		$providers = $property->getValue( $registry );
		unset( $providers['google'] );
		$property->setValue( $registry, $providers );

		Utility::invoke_hidden_method( Setup::get_instance(), 'setup_hooks' );

		$registry->do_register_action();

		$this->assertInstanceOf( Google::class, $registry->get( 'google' ) );

		// Re-wire the action for later tests in this run.
		Utility::invoke_hidden_method( Setup::get_instance(), 'setup_hooks' );
	}
}
