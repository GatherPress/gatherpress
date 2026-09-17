<?php
/**
 * Second test fixture class implementing the Singleton trait.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Traits;

use GatherPress\Core\Traits\Singleton;

/**
 * Class Test_Singleton_Other_Dummy.
 *
 * Second test fixture for verifying Singleton trait class isolation.
 *
 * @since 0.36.0
 */
class Test_Singleton_Other_Dummy {
	use Singleton;

	/**
	 * State variable for testing isolation.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	public string $state = 'other_initial';
}
