<?php
/**
 * Loads starter pattern definitions from a templates directory.
 *
 * Centralizes the file-per-pattern convention used by the new-event and
 * new-venue starter pattern modals so adding a new pattern is just
 * dropping a `*.php` file into `includes/core/templates/<subsystem>/`.
 * Each file returns an associative array with `name`, `title`,
 * `description`, and `content` keys.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Starter_Pattern_Loader.
 *
 * Stateless helper — no constructor, no singleton. Callers pass a directory
 * path and receive the list of pattern definitions found there. Filtering
 * (e.g., `gatherpress_event_starter_patterns`) is the caller's responsibility
 * so the filter name stays scoped to each subsystem.
 *
 * @since 1.0.0
 */
class Starter_Pattern_Loader {

	/**
	 * Load every starter pattern definition found in a directory.
	 *
	 * Skips files that do not return an array or omit a `name` key — the
	 * `name` is what `register_block_pattern()` keys on, so without it
	 * the entry can never be registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir Absolute path to a directory of pattern files.
	 *                    Each file must `return` a definition array.
	 * @return array<int, array<string, mixed>> List of pattern definitions.
	 */
	public static function load( string $dir ): array {
		$patterns = array();
		$files    = glob( rtrim( $dir, '/' ) . '/*.php' );

		foreach ( (array) $files as $file ) {
			$pattern = require_once $file;

			if ( is_array( $pattern ) && ! empty( $pattern['name'] ) ) {
				$patterns[] = $pattern;
			}
		}

		return $patterns;
	}
}
