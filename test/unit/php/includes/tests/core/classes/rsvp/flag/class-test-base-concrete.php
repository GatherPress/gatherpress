<?php
/**
 * Concrete test implementation of the RSVP flag Base for unit testing.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Rsvp\Flag;

use GatherPress\Core\Rsvp\Flag\Base;

/**
 * Concrete test implementation of the RSVP flag Base for unit testing.
 *
 * Base is abstract, so tests need a concrete flag. The slug is passed in so
 * one double can stand in for valid and invalid flags alike.
 */
class Test_Base_Concrete extends Base {

	/**
	 * The slug this test flag reports.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Set the slug this test flag reports.
	 *
	 * @param string $slug The flag slug.
	 */
	public function __construct( string $slug = 'test-flag' ) {
		$this->slug = $slug;
	}

	/**
	 * Get the slug for testing.
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return $this->slug;
	}
}
