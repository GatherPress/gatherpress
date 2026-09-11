<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Setup.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Recurrence\Rest_Api;
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Event\Recurrence\Series;
use GatherPress\Core\Event\Recurrence\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use ReflectionClass;

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Setup
 */
class Test_Setup extends Base {

	/**
	 * `Recurrence\Setup` exists so `Event\Setup` can hand off the whole
	 * subsystem with one line, which only holds if every sibling is reached
	 * from here. Each of the three siblings that owns hooks is checked through
	 * a hook its own `setup_hooks()` registers, so a sibling silently dropping
	 * out of the list fails this test rather than going unnoticed until a save
	 * path stops firing.
	 *
	 * @covers ::instantiate_classes
	 *
	 * @return void
	 */
	public function test_instantiate_classes_reaches_every_sibling(): void {
		// Force the method to run inside the test's coverage window: Setup is a
		// singleton constructed during plugin bootstrap, so get_instance() here
		// returns the cached instance and does not re-enter the constructor.
		Utility::invoke_hidden_method( Setup::get_instance(), 'instantiate_classes' );

		$hooked_siblings = array(
			Meta::class        => array(
				'wp_after_insert_post',
				array( Meta::get_instance(), 'set_recurrence' ),
			),
			Occurrences::class => array(
				'wp_after_insert_post',
				array( Occurrences::get_instance(), 'maybe_project' ),
			),
			Query::class       => array(
				'deleted_post',
				array( Query::get_instance(), 'maybe_refresh_has_recurring_events_for_deleted_post' ),
			),
		);

		foreach ( $hooked_siblings as $class_name => $hook ) {
			$this->assertNotFalse(
				has_action( $hook[0], $hook[1] ),
				sprintf( 'Failed to assert that %s was instantiated and registered its hooks.', $class_name )
			);
		}

		// The remaining siblings register no hooks yet, so construction is all
		// there is to observe.
		$hookless_siblings = array(
			Context::class         => Context::get_instance(),
			Rest_Api::class        => Rest_Api::get_instance(),
			Rsvp_Occurrence::class => Rsvp_Occurrence::get_instance(),
			Series::class          => Series::get_instance(),
		);

		foreach ( $hookless_siblings as $class_name => $instance ) {
			$this->assertInstanceOf(
				$class_name,
				$instance,
				sprintf( 'Failed to assert that %s is reachable as a singleton.', $class_name )
			);
		}
	}

	/**
	 * The subsystem owns no hooks of its own while the contract is frozen, so
	 * `setup_hooks()` must contain no `add_action()` and no `add_filter()` call
	 * at all. `Base::count_hook_registrations()` reads the method's own source,
	 * which is what makes an empty body provable rather than merely unobserved.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks_registers_nothing(): void {
		Utility::invoke_hidden_method( Setup::get_instance(), 'setup_hooks' );

		$this->assertSame(
			array(
				'actions' => 0,
				'filters' => 0,
			),
			$this->count_hook_registrations( Setup::class, 'setup_hooks' ),
			'Failed to assert that setup_hooks registers no actions and no filters.'
		);
	}

	/**
	 * The constructor is protected, so the singleton contract is structural:
	 * `new Setup()` fails instead of minting a second instance that would
	 * re-run the sibling bootstrap. Its body is invoked directly because the
	 * instance already exists by the time this suite runs, so nothing else
	 * enters the constructor inside a test.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_constructor_is_protected_and_bootstraps_the_subsystem(): void {
		$constructor = ( new ReflectionClass( Setup::class ) )->getConstructor();

		$this->assertNotNull(
			$constructor,
			'Failed to assert that Setup declares its own constructor.'
		);
		$this->assertTrue(
			$constructor->isProtected(),
			'Failed to assert that the Setup constructor is protected.'
		);

		$before = Context::get_instance();

		$constructor->setAccessible( true );
		$constructor->invoke( Setup::get_instance() );

		// Re-entering the constructor is safe because the bootstrap is
		// idempotent: each sibling is reached through get_instance(), so the
		// second pass hands back the instance the first pass made.
		$this->assertSame(
			$before,
			Context::get_instance(),
			'Failed to assert that re-entering the constructor reuses the existing sibling instances.'
		);
	}
}
