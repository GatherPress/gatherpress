<?php
/**
 * Unit tests for GatherPress\Core\Traits\Singleton.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Traits;

use GatherPress\Core\Feed;
use GatherPress\Core\Site_Health;
use GatherPress\Core\Topic;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Tests\Base;
use ReflectionClass;
use ReflectionProperty;

/**
 * Class Test_Singleton.
 *
 * Tests the Singleton design pattern trait used across the GatherPress codebase.
 *
 * @coversDefaultClass \GatherPress\Core\Traits\Singleton
 */
class Test_Singleton extends Base {

	/**
	 * Tear down after each test.
	 *
	 * Resets dummy singleton instances to maintain test isolation.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->reset_dummy_instance( Test_Singleton_Dummy::class );
		$this->reset_dummy_instance( Test_Singleton_Other_Dummy::class );

		parent::tear_down();
	}

	/**
	 * Helper to reset a singleton class instance via reflection.
	 *
	 * @param string $class_name Class name to reset.
	 *
	 * @return void
	 */
	private function reset_dummy_instance( string $class_name ): void {
		$property = new ReflectionProperty( $class_name, 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Verifies that get_instance returns an instance of the calling class.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_get_instance_instantiates_class(): void {
		$instance = Test_Singleton_Dummy::get_instance();

		$this->assertInstanceOf(
			Test_Singleton_Dummy::class,
			$instance,
			'Failed to assert that get_instance returns an instance of Test_Singleton_Dummy.'
		);
	}

	/**
	 * Verifies that get_instance returns the exact same instance across calls.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_get_instance_returns_same_instance(): void {
		$instance1 = Test_Singleton_Dummy::get_instance();
		$instance2 = Test_Singleton_Dummy::get_instance();

		$this->assertSame(
			$instance1,
			$instance2,
			'Failed to assert that multiple get_instance calls return the exact same object reference.'
		);
	}

	/**
	 * Verifies that the constructor is called only once during initial instantiation.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_constructor_called_only_once(): void {
		$instance = Test_Singleton_Dummy::get_instance();
		Test_Singleton_Dummy::get_instance();
		Test_Singleton_Dummy::get_instance();

		$this->assertSame(
			1,
			$instance->constructor_count,
			'Failed to assert that the constructor was invoked only once.'
		);
	}

	/**
	 * Verifies that state mutations persist across subsequent get_instance calls.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_state_persistence_across_invocations(): void {
		$instance = Test_Singleton_Dummy::get_instance();
		$this->assertSame( 'initial', $instance->state );

		$instance->state = 'updated_state';

		$reobtained = Test_Singleton_Dummy::get_instance();
		$this->assertSame(
			'updated_state',
			$reobtained->state,
			'Failed to assert that state modification persists across get_instance calls.'
		);
	}

	/**
	 * Verifies that distinct classes consuming the Singleton trait maintain independent instances.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_distinct_classes_have_isolated_singletons(): void {
		$dummy_a = Test_Singleton_Dummy::get_instance();
		$dummy_b = Test_Singleton_Other_Dummy::get_instance();

		$this->assertInstanceOf( Test_Singleton_Dummy::class, $dummy_a );
		$this->assertInstanceOf( Test_Singleton_Other_Dummy::class, $dummy_b );

		$this->assertNotSame(
			(object) $dummy_a,
			(object) $dummy_b,
			'Failed to assert that distinct classes maintain distinct singleton instances.'
		);

		$dummy_a->state = 'modified_a';
		$dummy_b->state = 'modified_b';

		$this->assertSame( 'modified_a', Test_Singleton_Dummy::get_instance()->state );
		$this->assertSame( 'modified_b', Test_Singleton_Other_Dummy::get_instance()->state );
	}

	/**
	 * Verifies that the instance property is private and static.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function test_reflection_property_is_private_and_static(): void {
		$property = new ReflectionProperty( Test_Singleton_Dummy::class, 'instance' );

		$this->assertTrue(
			$property->isPrivate(),
			'Failed to assert that the $instance property is private.'
		);
		$this->assertTrue(
			$property->isStatic(),
			'Failed to assert that the $instance property is static.'
		);
	}

	/**
	 * Verifies that resetting instance via reflection triggers re-instantiation.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_reset_instance_via_reflection_creates_new_instance(): void {
		$first_instance = Test_Singleton_Dummy::get_instance();

		$this->reset_dummy_instance( Test_Singleton_Dummy::class );

		$second_instance = Test_Singleton_Dummy::get_instance();

		$this->assertNotSame(
			$first_instance,
			$second_instance,
			'Failed to assert that resetting $instance allows a new instance to be created.'
		);
		$this->assertSame(
			1,
			$second_instance->constructor_count,
			'Failed to assert that the new instance executed its constructor.'
		);
	}

	/**
	 * Verifies that production core classes using Singleton return valid singleton instances.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_instance
	 *
	 * @return void
	 */
	public function test_core_classes_comply_with_singleton(): void {
		$topic1 = Topic::get_instance();
		$topic2 = Topic::get_instance();

		$this->assertInstanceOf( Topic::class, $topic1 );
		$this->assertSame( $topic1, $topic2 );

		$feed1 = Feed::get_instance();
		$feed2 = Feed::get_instance();

		$this->assertInstanceOf( Feed::class, $feed1 );
		$this->assertSame( $feed1, $feed2 );

		$health1 = Site_Health::get_instance();
		$health2 = Site_Health::get_instance();

		$this->assertInstanceOf( Site_Health::class, $health1 );
		$this->assertSame( $health1, $health2 );
	}
}
