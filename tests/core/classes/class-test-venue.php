<?php
/**
 * Class handles unit tests for GatherPress\Includes\Venue.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Includes;

use GatherPress\Includes\Venue;
use PMC\Unit_Test\Base;

/**
 * Class Test_Venue.
 *
 * @coversDefaultClass \GatherPress\Includes\Venue
 */
class Test_Venue extends Base {

	/**
	 * Cover for get_venue_term_slug method.
	 *
	 * @covers ::get_venue_term_slug
	 *
	 * @return void
	 */
	public function test_get_venue_term_slug() {
		$this->assertSame( '_venue_123', Venue::get_instance()->get_venue_term_slug( 123 ) );
	}

}
