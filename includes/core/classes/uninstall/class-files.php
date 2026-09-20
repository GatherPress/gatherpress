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

use WP_Filesystem_Base;

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
	 * Goes through `WP_Filesystem` rather than `unlink()` and `rmdir()`, so
	 * the deletion answers to whatever transport the site is configured
	 * for and nothing has to silence a warning. A site whose transport
	 * needs credentials has none to offer during an uninstall, and keeping
	 * the files is the right outcome there: they are inert, and the
	 * alternative is guessing at someone else's filesystem.
	 *
	 * @since 0.36.0
	 *
	 * @global WP_Filesystem_Base $wp_filesystem WordPress filesystem abstraction.
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		global $wp_filesystem;

		$uploads = wp_get_upload_dir();

		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return;
		}

		// Loading WordPress core file for the WP_Filesystem function, not importing a class.
		require_once ABSPATH . 'wp-admin/includes/file.php'; // NOSONAR.

		if ( ! WP_Filesystem() || ! $wp_filesystem instanceof WP_Filesystem_Base ) {
			return;
		}

		$wp_filesystem->rmdir( trailingslashit( $uploads['basedir'] ) . self::DIRECTORY, true );
	}
}
