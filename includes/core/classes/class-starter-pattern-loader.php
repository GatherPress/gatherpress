<?php
/**
 * Loads and registers starter pattern definitions.
 *
 * Centralizes the file-per-pattern convention used by the new-event and
 * new-venue starter pattern modals so adding a new pattern is just
 * dropping a `*.php` file into `includes/core/templates/<subsystem>/`.
 * Each file returns an associative array with `name`, `title`,
 * `description`, and `content` keys. Registration against core's block
 * patterns registry is also shared here so both subsystems honor the
 * same definition shape — including the optional per-pattern `postTypes`
 * key.
 *
 * @package GatherPress\Core
 * @since 0.34.0
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
 * @since 0.34.0
 */
class Starter_Pattern_Loader {

	/**
	 * Load every starter pattern definition found in a directory.
	 *
	 * Skips files that do not return an array or omit a `name` key — the
	 * `name` is what `register_block_pattern()` keys on, so without it
	 * the entry can never be registered.
	 *
	 * @since 0.34.0
	 *
	 * @param string $dir Absolute path to a directory of pattern files.
	 *                    Each file must `return` a definition array.
	 * @return array<int, array<string, mixed>> List of pattern definitions.
	 */
	public static function load( string $dir ): array {
		$patterns = array();
		$files    = glob( rtrim( $dir, '/' ) . '/*.php' );

		foreach ( (array) $files as $file ) {
			// `require` (not `require_once`) is intentional: the loader uses
			// each file's return value every call. `require_once` returns
			// `true` after the first include, which collapses repeat loads
			// (e.g. across PHPUnit tests in the same process) into empty
			// pattern arrays.
			$pattern = require $file; // NOSONAR — see comment above.

			if ( is_array( $pattern ) && ! empty( $pattern['name'] ) ) {
				$patterns[] = $pattern;
			}
		}

		return $patterns;
	}

	/**
	 * Register pattern definitions with core's block patterns registry.
	 *
	 * Every definition registers scoped to `core/post-content` (so the
	 * block editor's starter pattern modal surfaces it on new posts) plus
	 * the given default post types. A definition may carry its own
	 * `postTypes` key — mirroring `register_block_pattern()`'s property —
	 * to narrow that one pattern to specific slugs. That is the opt-out
	 * from support-level scoping for a pattern that should target one
	 * post type among several sharing a support.
	 *
	 * Entries that are not arrays or lack a `name` are skipped — `name`
	 * is what `register_block_pattern()` keys on.
	 *
	 * @since 0.35.0
	 *
	 * @param array $patterns   Pattern definitions (`name`, `title`,
	 *                          `description`, `content`, optional `postTypes`).
	 * @param array $post_types Default post type slugs for definitions
	 *                          without their own `postTypes` key.
	 * @return void
	 */
	public static function register( array $patterns, array $post_types ): void {
		foreach ( $patterns as $pattern ) {
			if ( ! is_array( $pattern ) || empty( $pattern['name'] ) ) {
				continue;
			}

			$pattern_post_types = $post_types;

			// Non-string entries are dropped rather than cast so a malformed
			// definition can't register a pattern against a bogus slug; an
			// empty or fully-malformed list falls back to the defaults.
			if ( ! empty( $pattern['postTypes'] ) && is_array( $pattern['postTypes'] ) ) {
				$scoped = array_values( array_filter( $pattern['postTypes'], 'is_string' ) );

				if ( ! empty( $scoped ) ) {
					$pattern_post_types = $scoped;
				}
			}

			register_block_pattern(
				$pattern['name'],
				array(
					'title'       => $pattern['title'] ?? '',
					'description' => $pattern['description'] ?? '',
					'content'     => $pattern['content'] ?? '',
					'blockTypes'  => array( 'core/post-content' ),
					'postTypes'   => $pattern_post_types,
					'source'      => 'plugin',
				)
			);
		}
	}
}
