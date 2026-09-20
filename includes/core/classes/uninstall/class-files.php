<?php
/**
 * Uninstall task that removes the files the plugin generated.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Files.
 *
 * Removes the plugin's own directory under `wp-content/uploads`, where it
 * writes the files it generates rather than adding them to the media
 * library. Today that is the static venue maps, written by
 * `Venue\Map\Map`; anything the plugin generates later belongs in the same
 * place and goes the same way.
 *
 * On its own switch rather than with the posts these files describe,
 * because nobody authored them: they cost disk space, and a site keeping
 * its venues may still want the directory gone.
 *
 * The uploads directory is per-site, since `wp_get_upload_dir()` answers
 * differently inside `switch_to_blog()`, so this belongs in the per-site
 * pass and never in the network one.
 *
 * @since 0.36.0
 */
final class Files extends Base {

	/**
	 * The plugin's directory under `wp-content/uploads`.
	 *
	 * Everything the plugin writes goes somewhere under this, which is what
	 * lets one switch cover all of it. `Venue\Map\Map::UPLOADS_SUBDIR` names
	 * its own subdirectory of it, and `test_owns_every_generated_file()`
	 * fails if a writer ever puts something outside.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const DIRECTORY = 'gatherpress';

	/**
	 * Whether the administrator opted in to removing generated files.
	 *
	 * @since 0.36.0
	 *
	 * @return bool True when the task should run.
	 */
	public function applies(): bool {
		return Preferences::is_enabled( Preferences::TASK_FILES );
	}

	/**
	 * Remove the current site's generated files.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		$uploads = wp_get_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return;
		}

		$this->delete_directory( trailingslashit( $uploads['basedir'] ) . self::DIRECTORY );
	}

	/**
	 * Delete a directory and everything in it.
	 *
	 * @since 0.36.0
	 *
	 * @param string $directory Absolute path to the directory.
	 *
	 * @return void
	 */
	protected function delete_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		foreach ( $this->entries( $directory ) as $entry ) {
			$path = trailingslashit( $directory ) . $entry;

			if ( is_dir( $path ) ) {
				$this->delete_directory( $path );

				continue;
			}

			wp_delete_file( $path );
		}

		/*
		 * WP_Filesystem is not set up during an uninstall, and the writers
		 * that made these files reach for the same direct calls. A directory
		 * that will not go, because a handle is open on it or the permissions
		 * say no, is not worth failing the uninstall over.
		 */
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.PHP.NoSilencedErrors.Discouraged
		@rmdir( $directory );
	}

	/**
	 * The names in a directory, without the dot entries.
	 *
	 * @since 0.36.0
	 *
	 * @param string $directory Absolute path to the directory.
	 *
	 * @return string[] Entry names.
	 */
	protected function entries( string $directory ): array {
		// No WP_Filesystem during an uninstall; see delete_directory().
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_scandir
		$entries = scandir( $directory );

		if ( false === $entries ) {
			return array();
		}

		return array_values( array_diff( $entries, array( '.', '..' ) ) );
	}
}
