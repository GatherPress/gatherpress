<?php
/**
 * Class handles unit tests for GatherPress\Core\Commands\Settings_Cli.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Commands;

use GatherPress\Core\Commands\Settings_Cli;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Settings_Cli.
 *
 * @coversDefaultClass \GatherPress\Core\Commands\Settings_Cli
 */
class Test_Settings_Cli extends Base {

	/**
	 * Coverage for export to stdout.
	 *
	 * @covers ::export
	 *
	 * @return void
	 */
	public function test_export_to_stdout(): void {
		$cli = new Settings_Cli();

		update_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'export' ),
			array( array(), array() )
		);

		$data = json_decode( $output, true );

		$this->assertIsArray( $data, 'Failed to assert output is valid JSON.' );
		$this->assertArrayHasKey( 'version', $data, 'Failed to assert version key exists.' );
		$this->assertArrayHasKey( 'settings', $data, 'Failed to assert settings key exists.' );
		$this->assertSame( 'google', $data['settings']['map_platform'], 'Failed to assert exported value.' );

		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for export to file.
	 *
	 * @covers ::export
	 *
	 * @return void
	 */
	public function test_export_to_file(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		update_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'export' ),
			array( array(), array( 'file' => $file ) )
		);

		$this->assertStringContainsString( 'Settings exported to', $output, 'Failed to assert success message.' );

		// Verify file contents.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $file );
		$data     = json_decode( $contents, true );

		$this->assertSame( 'google', $data['settings']['map_platform'], 'Failed to assert file contents.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for import with merge mode.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_merge(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		// Set existing setting.
		update_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		// Write import file with a different setting.
		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array( 'max_attendance_limit' => 100 ),
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, wp_json_encode( $data ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( $file ), array( 'apply' => true ) )
		);

		$this->assertStringContainsString( 'imported successfully', $output, 'Failed to assert success message.' );

		// Verify merge preserved existing value.
		$settings = get_option( 'gatherpress_settings' );

		$this->assertSame( 'google', $settings['map_platform'], 'Failed to assert existing value preserved.' );
		$this->assertSame( 100, $settings['max_attendance_limit'], 'Failed to assert imported value.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for import with replace mode.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_replace(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		update_option( 'gatherpress_settings', array( 'map_platform' => 'google' ) );

		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array( 'max_attendance_limit' => 100 ),
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, wp_json_encode( $data ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array(
				array( $file ),
				array(
					'apply' => true,
					'mode'  => 'replace',
				),
			)
		);

		$this->assertStringContainsString( 'imported successfully', $output, 'Failed to assert success message.' );

		// Verify replace removed old value.
		$settings = get_option( 'gatherpress_settings' );

		$this->assertArrayNotHasKey( 'map_platform', $settings, 'Failed to assert old value was removed.' );
		$this->assertSame( 100, $settings['max_attendance_limit'], 'Failed to assert imported value.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for import with dry-run mode.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_dry_run(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array( 'map_platform' => 'google' ),
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, wp_json_encode( $data ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( $file ), array() )
		);

		$this->assertStringContainsString( 'Would change', $output, 'Failed to assert dry-run output.' );

		// Verify nothing was actually imported.
		$settings = get_option( 'gatherpress_settings', array() );

		$this->assertArrayNotHasKey( 'map_platform', $settings, 'Failed to assert dry-run did not import.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
	}

	/**
	 * Coverage for import with non-existent file.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_file_not_found(): void {
		$cli    = new Settings_Cli();
		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( '/nonexistent/file.json' ), array() )
		);

		$this->assertStringContainsString( 'File not found', $output, 'Failed to assert file not found error.' );
	}

	/**
	 * Coverage for import with invalid JSON file.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_invalid_json(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, 'not valid json{' );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( $file ), array() )
		);

		$this->assertStringContainsString( 'Invalid JSON', $output, 'Failed to assert invalid JSON error.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
	}

	/**
	 * Coverage for import with unknown keys.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_with_unknown_keys(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array(
				'unknown_key'  => 'value',
				'map_platform' => 'google',
			),
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, wp_json_encode( $data ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( $file ), array( 'apply' => true ) )
		);

		$this->assertStringContainsString( 'unknown_key', $output, 'Failed to assert unknown key warning.' );
		$this->assertStringContainsString( 'imported successfully', $output, 'Failed to assert import succeeded.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for import with version mismatch warning.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_version_mismatch(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		$data = array(
			'version'  => '0.0.1',
			'settings' => array( 'map_platform' => 'google' ),
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, wp_json_encode( $data ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( $file ), array( 'apply' => true ) )
		);

		$this->assertStringContainsString( '0.0.1', $output, 'Failed to assert version warning.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
		delete_option( 'gatherpress_settings' );
	}

	/**
	 * Coverage for dry-run import with no changes.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_dry_run_no_changes(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		// Import settings that match defaults — no changes.
		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array( 'map_platform' => 'osm' ),
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, wp_json_encode( $data ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( $file ), array() )
		);

		$this->assertStringContainsString( 'No changes', $output, 'Failed to assert no changes message.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
	}

	/**
	 * Coverage for export to an unwritable file path.
	 *
	 * @covers ::export
	 *
	 * @return void
	 */
	public function test_export_to_unwritable_file(): void {
		$cli    = new Settings_Cli();
		$output = Utility::buffer_and_return(
			array( $cli, 'export' ),
			array( array(), array( 'file' => '/nonexistent/directory/file.json' ) )
		);

		$this->assertStringContainsString( 'Failed to write', $output, 'Failed to assert unwritable file error.' );
	}

	/**
	 * Coverage for dry-run import with version mismatch warnings.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_dry_run_version_mismatch(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		$data = array(
			'version'  => '0.0.1',
			'settings' => array( 'map_platform' => 'google' ),
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, wp_json_encode( $data ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( $file ), array() )
		);

		$this->assertStringContainsString( '0.0.1', $output, 'Failed to assert version mismatch warning in dry-run.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
	}

	/**
	 * Coverage for import with missing settings key.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_missing_settings_key(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		// Data has version but no settings key.
		$data = array( 'version' => '1.0.0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, wp_json_encode( $data ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( $file ), array( 'apply' => true ) )
		);

		$this->assertStringContainsString(
			'Invalid import file',
			$output,
			'Failed to assert missing settings key error.'
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
	}

	/**
	 * Coverage for dry-run import with unknown keys.
	 *
	 * @covers ::import
	 *
	 * @return void
	 */
	public function test_import_dry_run_unknown_keys(): void {
		$cli  = new Settings_Cli();
		$file = tempnam( sys_get_temp_dir(), 'gatherpress_test_' );

		$data = array(
			'version'  => GATHERPRESS_VERSION,
			'settings' => array( 'unknown_key' => 'value' ),
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $file, wp_json_encode( $data ) );

		$output = Utility::buffer_and_return(
			array( $cli, 'import' ),
			array( array( $file ), array() )
		);

		$this->assertStringContainsString( 'unknown_key', $output, 'Failed to assert unknown key warning.' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $file );
	}
}
