<?php
/**
 * Unit tests for the Plugins screen row warning.
 *
 * @package GatherPress\Core\Admin
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Admin;

use GatherPress\Core\Admin\Plugin_Row;
use GatherPress\Core\Settings;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Plugin_Row.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Plugin_Row
 */
class Test_Plugin_Row extends Base {

	/**
	 * Clean up after each test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Settings::OPTION_NAME );
		delete_site_option( Settings::OPTION_NAME );
		Preferences::flush_cache();

		parent::tearDown();
	}

	/**
	 * Opt in to one or more tasks for the current site.
	 *
	 * @since 0.36.0
	 *
	 * @param string ...$tasks Task keys.
	 *
	 * @return void
	 */
	protected function arm( string ...$tasks ): void {
		$settings = array();

		foreach ( $tasks as $task ) {
			$settings[ Preferences::option_key( $task ) ] = true;
		}

		update_option( Settings::OPTION_NAME, $settings );
		Preferences::flush_cache();
	}

	/**
	 * The warning is hooked into the plugin row meta.
	 *
	 * @covers ::setup_hooks
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Plugin_Row::get_instance();

		$this->assert_hooks(
			array(
				array(
					'type'     => 'action',
					'name'     => 'after_plugin_row_meta',
					'priority' => 10,
					'callback' => array( $instance, 'render_uninstall_warning' ),
				),
			),
			$instance
		);
	}

	/**
	 * Render the warning for a plugin row.
	 *
	 * @since 0.36.0
	 *
	 * @param string $plugin_file The row being rendered.
	 *
	 * @return string The output.
	 */
	protected function render( string $plugin_file ): string {
		ob_start();
		Plugin_Row::get_instance()->render_uninstall_warning( $plugin_file );

		return (string) ob_get_clean();
	}

	/**
	 * A site that opted into nothing gets no warning.
	 *
	 * @covers ::render_uninstall_warning
	 *
	 * @return void
	 */
	public function test_renders_nothing_when_nothing_is_armed(): void {
		$this->assertSame(
			'',
			$this->render( plugin_basename( GATHERPRESS_CORE_FILE ) ),
			'A site that loses nothing has no reason to be warned.'
		);
	}

	/**
	 * Another plugin's row is left alone.
	 *
	 * @covers ::render_uninstall_warning
	 *
	 * @return void
	 */
	public function test_renders_nothing_on_another_plugin_row(): void {
		$this->arm( Preferences::TASK_EVENTS );

		$this->assertSame(
			'',
			$this->render( 'another-plugin/another-plugin.php' ),
			'Nothing of ours is deleted by removing somebody else\'s plugin.'
		);
	}

	/**
	 * The warning names what goes, in the shape core uses in that cell.
	 *
	 * @covers ::render_uninstall_warning
	 * @covers ::get_message
	 *
	 * @return void
	 */
	public function test_renders_a_warning_naming_the_data(): void {
		$this->arm( Preferences::TASK_EVENTS, Preferences::TASK_RSVPS );

		$output = $this->render( plugin_basename( GATHERPRESS_CORE_FILE ) );

		$this->assertStringStartsWith(
			'<div class="gatherpress-plugin-row-warning"><div class="notice notice-warning inline notice-alt">',
			$output,
			'Same shape core gives an unmet dependency in this cell, in warning colors.'
		);
		$this->assertStringContainsString(
			'<strong>Deleting GatherPress will also delete your data.</strong>',
			$output,
			'The consequence leads, in bold.'
		);
		$this->assertStringContainsString(
			'Events and RSVPs',
			$output,
			'What goes is named in the words the administrator ticked.'
		);
		$this->assertStringContainsString(
			'page=gatherpress_uninstall_settings',
			$output,
			'The screen where the choice was made is one click away.'
		);
	}

	/**
	 * The message links a super admin to a screen they can act on.
	 *
	 * @covers ::get_message
	 *
	 * @return void
	 */
	public function test_message_links_to_the_network_screen_for_the_network(): void {
		$site    = Utility::invoke_hidden_method(
			Plugin_Row::get_instance(),
			'get_message',
			array( array( 'Events' ), false )
		);
		$network = Utility::invoke_hidden_method(
			Plugin_Row::get_instance(),
			'get_message',
			array( array( 'Events' ), true )
		);

		$this->assertStringContainsString(
			'post_type=gatherpress_event',
			(string) $site,
			'A site administrator is sent to their own screen.'
		);
		$this->assertStringContainsString(
			'page=gatherpress-network-settings',
			(string) $network,
			'The network Plugins screen is where a plugin is deleted on multisite, so the link has to lead there.'
		);
	}
}
