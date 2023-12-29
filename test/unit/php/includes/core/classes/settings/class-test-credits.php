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
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Credits.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Credits
 */
class Test_Credits extends Base {
	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$instance = Credits::get_instance();

		Utility::invoke_hidden_method( $instance, '__construct' );

		$this->assertSame(
			'Credits',
			Utility::get_hidden_property( $instance, 'name' ),
			'Failed to assert name matches Credits.'
		);

		$this->assertSame(
			PHP_INT_MAX,
			Utility::get_hidden_property( $instance, 'priority' ),
			'Failed to assert priority matches PHP_INT_MAX.'
		);

		$this->assertSame(
			'credits',
			Utility::get_hidden_property( $instance, 'slug' ),
			'Failed to assert slug matches credits.'
		);
	}

	/**
	 * Coverage for setup_hooks method.
	 *
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
	 * Coverage for settings_section method.
	 *
	 * @covers ::settings_section
	 * @covers ::credits_page
	 *
	 * @return void
	 */
	public function test_settings_section(): void {
		$instance = Credits::get_instance();
		$response = Utility::buffer_and_return( array( $instance, 'settings_section' ), array( 'gp_general' ) );

		$this->assertEmpty( $response, 'Failed to assert no markup was returned.' );
		$this->assertEquals(
			10,
			has_action( 'gatherpress_settings_section', array( Settings::get_instance(), 'render_settings_form' ) ),
			'Failed to assert gatherpress_settings_section has render_settings_form callback at priority 10.'
		);

		$response = Utility::buffer_and_return( array( $instance, 'settings_section' ), array( 'gp_credits' ) );

		$this->assertFalse(
			has_action( 'gatherpress_settings_section', array( Settings::get_instance(), 'render_settings_form' ) ),
			'Failed to assert gatherpress_settings_section does not have render_settings_form callback.'
		);

		$this->assertNotEmpty( $response, 'Failed to assert markup was returned.' );
	}
}
