<?php
/**
 * Test fixture class implementing the Singleton trait.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Traits;

use GatherPress\Core\Traits\Singleton;

/**
 * Class Test_Singleton_Dummy.
 *
 * Test fixture for verifying Singleton trait instantiation and persistence.
 *
 * @since 0.36.0
 */
class Test_Singleton_Dummy {

	use Singleton;

	/**
	 * Total count of constructor invocations across all instances.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	public static int $constructor_calls = 0;

	/**
	 * Counter tracking constructor invocations.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	public int $constructor_count = 0;

	/**
	 * State variable for testing instance persistence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	public string $state = 'initial';

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 */
	public function __construct() {
		++self::$constructor_calls;
		++$this->constructor_count;
	}
}
