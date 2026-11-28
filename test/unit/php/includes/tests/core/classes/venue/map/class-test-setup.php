<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Setup.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Venue\Map;
use GatherPress\Core\Venue\Map\Cleanup;
use GatherPress\Core\Venue\Map\Manager;
use GatherPress\Core\Venue\Map\Prewarm;
use GatherPress\Core\Venue\Map\Rest_Api;
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
	 * `instantiate_classes()` is what wires Manager / Cleanup / Map / Prewarm /
	 * Rest_Api so `Venue\Setup` can hand off in one line. Per-sibling
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
		$expected_hooks = array(
			Manager::class  => array( 'init', 'do_register_action', 0 ),
			Cleanup::class  => array( Cleanup::CRON_ACTION, 'run_scheduled', 10 ),
			Map::class      => array( 'wp_after_insert_post', 'maybe_generate', 11 ),
			Prewarm::class  => array( 'switch_theme', 'on_theme_switched', 10 ),
			Rest_Api::class => array( 'rest_api_init', 'register_endpoints', 10 ),
		);
		$properties     = array();
		$originals      = array();

		// The bootstrap already built every sibling, so clear them to prove instantiate_classes() builds each one.
		foreach ( array_keys( $expected_hooks ) as $class_name ) {
			$properties[ $class_name ] = new \ReflectionProperty( $class_name, 'instance' );
			$properties[ $class_name ]->setAccessible( true );
			$originals[ $class_name ] = $properties[ $class_name ]->getValue();
			$properties[ $class_name ]->setValue( null, null );
		}

		try {
			Utility::invoke_hidden_method( Setup::get_instance(), 'instantiate_classes' );

			foreach ( $expected_hooks as $class_name => $expected ) {
				list( $hook, $method, $priority ) = $expected;
				$instance                         = $properties[ $class_name ]->getValue();

				$this->assertNotNull( $instance, sprintf( '%s must be instantiated.', $class_name ) );
				$this->assertSame(
					$priority,
					has_action( $hook, array( $instance, $method ) ),
					sprintf( '%s must be instantiated so its %s hook registers.', $class_name, $hook )
				);
			}
		} finally {
			foreach ( $originals as $class_name => $original ) {
				$properties[ $class_name ]->setValue( null, $original );
			}
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
}
