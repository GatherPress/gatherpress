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
 * Base is abstract, so tests need a concrete flag.
 */
class Test_Base_Concrete extends Base {

	/**
	 * The test flag slug.
	 *
	 * @var string
	 */
	public const SLUG = 'test-flag';
}
