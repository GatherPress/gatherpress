<?php
/**
 * Concrete test implementation of Base for unit testing.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Base;

/**
 * Concrete test implementation of Base for unit testing.
 *
 * Since Base is now abstract, we need a concrete implementation for testing.
 */
class Test_Base_Concrete extends Base {
	/**
	 * Get the slug for testing.
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'test-slug';
	}

	/**
	 * Get the name for testing.
	 *
	 * @return string
	 */
	protected function get_name(): string {
		return 'Test Name';
	}
}
