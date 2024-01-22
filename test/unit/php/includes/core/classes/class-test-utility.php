<?php
/**
 * Class handles unit tests for GatherPress\Core\Utility.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use ErrorException;
use GatherPress\Core\Utility;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility as PMC_Utility;

/**
 * Class Test_Utility.
 *
 * @coversDefaultClass \GatherPress\Core\Utility
 */
class Test_Utility extends Base {
	/**
	 * Coverage for render_template method.
	 *
	 * @covers ::render_template
	 *
	 * @throws ErrorException Throws exception if callback to buffer_and_return is not callable.
	 * @return void
	 */
	public function test_render_template(): void {
		$this->assertEmpty( Utility::render_template( '' ) );

		$description   = 'This is a template for testing.';
		$template_path = GATHERPRESS_CORE_PATH . '/test/unit/php/assets/templates/test-template.php';
		$template      = Utility::render_template( $template_path, array( 'description' => $description ) );
		$this->assertStringContainsString( $description, $template );

		$template = PMC_Utility::buffer_and_return(
			array( Utility::class, 'render_template' ),
			array(
				$template_path,
				array( 'description' => $description ),
				false,
			),
		);
		$this->assertEmpty( $template );

		$template = PMC_Utility::buffer_and_return(
			array( Utility::class, 'render_template' ),
			array(
				$template_path,
				array( 'description' => $description ),
				true,
			),
		);
		$this->assertStringContainsString( $description, $template );
	}

	/**
	 * Coverage for prefix_key method.
	 *
	 * @covers ::prefix_key
	 *
	 * @return void
	 */
	public function test_prefix_key(): void {
		$this->assertSame(
			'gp_unittest',
			Utility::prefix_key( 'unittest' ),
			'Assert failed that gp_ prefix is applied.'
		);
		$this->assertSame(
			'gp_unittest',
			Utility::prefix_key( 'gp_unittest' ),
			'Assert failed that gp_ prefix is not reapplied if it exists already.'
		);
	}

	/**
	 * Coverage for unprefix_key method.
	 *
	 * @covers ::unprefix_key
	 *
	 * @return void
	 */
	public function test_unprefix_key() {
		$this->assertSame( 'unittest', Utility::unprefix_key( 'gp_unittest' ) );
	}

	/**
	 * Coverage for timezone_choices method.
	 *
	 * @covers ::timezone_choices
	 *
	 * @return void
	 */
	public function test_timezone_choices(): void {
		$timezones = Utility::timezone_choices();
		$keys      = array( 'Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'UTC', 'Manual Offsets' );

		$this->assertIsArray( $timezones );

		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $timezones );
			$this->assertIsArray( $timezones[ $key ] );
		}
	}
}
