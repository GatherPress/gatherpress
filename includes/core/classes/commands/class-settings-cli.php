<?php
/**
 * Class responsible for WP-CLI commands related to settings within GatherPress.
 *
 * This class handles WP-CLI commands for exporting and importing
 * GatherPress plugin settings.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Core\Commands;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use WP_CLI;

/**
 * WP-CLI commands for managing GatherPress settings.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */
class Settings_Cli extends WP_CLI {

	/**
	 * Export GatherPress settings to JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : File path to write the export. If omitted, outputs to stdout.
	 *
	 * ## EXAMPLES
	 *
	 *    # Export to stdout.
	 *    $ wp gatherpress settings export
	 *
	 *    # Export to a file.
	 *    $ wp gatherpress settings export --file=gatherpress-settings.json
	 *
	 * @since 0.34.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function export( array $args = array(), array $assoc_args = array() ): void {
		$settings = Settings::get_instance();
		$data     = $settings->export_settings();
		$json     = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( ! empty( $assoc_args['file'] ) ) {
			$file = $assoc_args['file'];

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( false === file_put_contents( $file, $json ) ) {
				static::error(
					sprintf(
						/* translators: %s: File path. */
						__( 'Failed to write to file: %s', 'gatherpress' ),
						$file
					)
				);

				return; // @phpstan-ignore deadCode.unreachable
			}

			static::success(
				sprintf(
					/* translators: %s: File path. */
					__( 'Settings exported to %s', 'gatherpress' ),
					$file
				)
			);

			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output, not HTML.
		echo $json . "\n";
	}

	/**
	 * Import GatherPress settings from a JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the JSON file to import.
	 *
	 * [--mode=<mode>]
	 * : Import mode.
	 * ---
	 * default: merge
	 * options:
	 *  - merge
	 *  - replace
	 * ---
	 *
	 * [--apply]
	 * : Apply changes. Without this flag, import runs as a dry-run preview.
	 *
	 * ## EXAMPLES
	 *
	 *    # Preview what would change (default behavior).
	 *    $ wp gatherpress settings import gatherpress-settings.json
	 *
	 *    # Apply import with merge.
	 *    $ wp gatherpress settings import gatherpress-settings.json --apply
	 *
	 *    # Apply import with replace.
	 *    $ wp gatherpress settings import gatherpress-settings.json --apply --mode=replace
	 *
	 * @since 0.34.0
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function import( array $args = array(), array $assoc_args = array() ): void {
		$file = $args[0];

		if ( ! file_exists( $file ) ) {
			static::error(
				sprintf(
					/* translators: %s: File path. */
					__( 'File not found: %s', 'gatherpress' ),
					$file
				)
			);

			return; // @phpstan-ignore deadCode.unreachable
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			static::error( __( 'Invalid JSON file.', 'gatherpress' ) );

			return; // @phpstan-ignore deadCode.unreachable
		}

		$settings = Settings::get_instance();
		$apply    = isset( $assoc_args['apply'] );

		if ( ! $apply ) {
			static::log( __( 'Dry run — no changes will be applied. Use --apply to import.', 'gatherpress' ) );

			$validation = $settings->validate_import( $data );

			if ( ! empty( $validation['warnings'] ) ) {
				foreach ( $validation['warnings'] as $warning ) {
					static::warning( $warning );
				}
			}

			if ( ! empty( $validation['changes'] ) ) {
				static::log(
					sprintf(
						/* translators: %s: Comma-separated list of setting keys. */
						__( 'Would change: %s', 'gatherpress' ),
						implode( ', ', $validation['changes'] )
					)
				);
			} else {
				static::log( __( 'No changes would be made.', 'gatherpress' ) );
			}

			if ( ! empty( $validation['unknown'] ) ) {
				static::warning(
					sprintf(
						/* translators: %s: Comma-separated list of unknown keys. */
						__( 'Unknown keys (would be skipped): %s', 'gatherpress' ),
						implode( ', ', $validation['unknown'] )
					)
				);
			}

			return;
		}

		$mode   = $assoc_args['mode'] ?? 'merge';
		$result = $settings->import_settings( $data, $mode );

		if ( ! $result['success'] ) {
			static::error(
				! empty( $result['warnings'] )
					? implode( ' ', $result['warnings'] )
					: __( 'Import failed.', 'gatherpress' )
			);

			return; // @phpstan-ignore deadCode.unreachable
		}

		if ( ! empty( $result['warnings'] ) ) {
			foreach ( $result['warnings'] as $warning ) {
				static::warning( $warning );
			}
		}

		if ( ! empty( $result['skipped'] ) ) {
			static::warning(
				sprintf(
					/* translators: %s: Comma-separated list of skipped keys. */
					__( 'Skipped unknown keys: %s', 'gatherpress' ),
					implode( ', ', $result['skipped'] )
				)
			);
		}

		static::success(
			sprintf(
				/* translators: %d: Number of imported settings. */
				__( '%d setting(s) imported successfully.', 'gatherpress' ),
				count( $result['imported'] )
			)
		);
	}
}
