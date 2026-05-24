<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Events.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Events;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Events.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Events
 */
class Test_Events extends Base {

	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = Events::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'events', $slug, 'Failed to assert slug is events.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = Events::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'Events', $name, 'Failed to assert name is Events.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = Events::get_instance();
		$priority = Utility::invoke_hidden_method( $instance, 'get_priority' );

		$this->assertEquals( PHP_INT_MIN + 1, $priority, 'Failed to assert correct priority.' );
	}

	/**
	 * Coverage for get_sections method.
	 *
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_get_sections(): void {
		$instance = Events::get_instance();

		$section = Utility::invoke_hidden_method( $instance, 'get_sections' );
		$this->assertSame(
			'Date & Time',
			$section['date_time']['name'],
			'Failed to assert name is Date & Time (moved here from the removed Formatting tab).'
		);
		$this->assertArrayHasKey(
			'date_format',
			$section['date_time']['options'],
			'Failed to assert date_format option is present.'
		);
		$this->assertArrayHasKey(
			'time_format',
			$section['date_time']['options'],
			'Failed to assert time_format option is present.'
		);
		$this->assertArrayHasKey(
			'show_timezone',
			$section['date_time']['options'],
			'Failed to assert show_timezone option is present.'
		);
		$this->assertSame(
			'Event Display',
			$section['event_display']['name'],
			'Failed to assert name is Event Display.'
		);
		$this->assertIsArray(
			$section['archive_pages'],
			'Failed to assert archive_pages is an array.'
		);
		$this->assertIsArray(
			$section['urls'],
			'Failed to assert urls is an array.'
		);
	}
}
