<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\Credits.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings;
use GatherPress\Core\Settings\Credits;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Credits.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Credits
 */
class Test_Credits extends Base {
	/**
	 * Coverage for setup_hooks method.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Credits::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_settings_section',
				'priority' => 9,
				'callback' => array( $instance, 'settings_section' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = Credits::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'credits', $slug, 'Failed to assert slug is credits.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = Credits::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'Credits', $name, 'Failed to assert name is Credits.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = Credits::get_instance();
		$priority = Utility::invoke_hidden_method( $instance, 'get_priority' );

		$this->assertEquals( PHP_INT_MAX, $priority, 'Failed to assert correct priority.' );
	}

	/**
	 * Coverage for settings_section method.
	 *
	 * @covers ::settings_section
	 * @covers ::credits_page
	 *
	 * @return void
	 */
	public function test_settings_section(): void {
		$instance = Credits::get_instance();
		$response = Utility::buffer_and_return(
			array( $instance, 'settings_section' ),
			array( 'gatherpress_general' )
		);

		$this->assertEmpty( $response, 'Failed to assert no markup was returned.' );
		$this->assertEquals(
			10,
			has_action( 'gatherpress_settings_section', array( Settings::get_instance(), 'render_settings_form' ) ),
			'Failed to assert gatherpress_settings_section has render_settings_form callback at priority 10.'
		);

		$response = Utility::buffer_and_return(
			array( $instance, 'settings_section' ),
			array( 'gatherpress_credits' )
		);

		$this->assertFalse(
			has_action( 'gatherpress_settings_section', array( Settings::get_instance(), 'render_settings_form' ) ),
			'Failed to assert gatherpress_settings_section does not have render_settings_form callback.'
		);

		$this->assertNotEmpty( $response, 'Failed to assert markup was returned.' );
	}
}
