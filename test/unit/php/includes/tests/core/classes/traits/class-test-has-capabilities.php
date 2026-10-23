<?php
/**
 * Unit tests for GatherPress\Core\Traits\Has_Capabilities.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Traits;

use GatherPress\Core\Capability;
use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Has_Capabilities.
 *
 * @coversDefaultClass \GatherPress\Core\Traits\Has_Capabilities
 */
class Test_Has_Capabilities extends Base {

	/**
	 * Verify Event and Venue default to the read capability on Capability.
	 *
	 * @since TBD
	 *
	 * @covers ::read_capability
	 *
	 * @return void
	 */
	public function test_read_capability(): void {
		$this->assertSame( Capability::READ_POST, Event::read_capability() );
		$this->assertSame( Capability::READ_POST, Venue::read_capability() );
	}

	/**
	 * Verify Event and Venue default to the edit capability on Capability.
	 *
	 * @since TBD
	 *
	 * @covers ::edit_capability
	 *
	 * @return void
	 */
	public function test_edit_capability(): void {
		$this->assertSame( Capability::EDIT_POST, Event::edit_capability() );
		$this->assertSame( Capability::EDIT_POST, Venue::edit_capability() );
	}
}
