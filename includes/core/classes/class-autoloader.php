<?php
/**
 * Class responsible for autoloading GatherPress class files.
 *
 * The Autoloader class is responsible for automatically loading class files as needed
 * to ensure a clean and organized codebase. It maps class names to their corresponding
 * file locations within the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Autoloader.
 *
 * This class is responsible for automatic loading of classes and namespaces.
 *
 * @since 1.0.0
 */
class Autoloader {

	/**
	 * Register method for autoloader.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register(
			static function ( string $class_string = '' ): void {
				/**
				 * Filters the registered autoloaders for GatherPress.
				 *
				 * This filter allows developers to add or modify autoloaders for GatherPress. By using this filter,
				 * namespaces and their corresponding paths can be registered.
				 *
				 * @param array $registered_autoloaders An associative array of namespaces and their paths.
				 * @return array Modified array of namespaces and their paths.
				 * @since 1.0.0
				 *
				 * @example
				 * ```php
				 * function gatherpress_awesome_autoloader( array $namespace ): array {
				 *     $namespace['GatherPress_Awesome'] = __DIR__;
				 *
				 *     return $namespace;
				 * }
				 * add_filter( 'gatherpress_autoloader', 'gatherpress_awesome_autoloader' );
				 * ```
				 *
				 * **Example:** The namespace `GatherPress_Awesome\Setup` would map to
				 * `gatherpress-awesome/includes/classes/class-setup.php`.
				 */
				$registered_autoloaders = apply_filters( 'gatherpress_autoloader', array() );

				$registered_autoloaders = array_merge(
					$registered_autoloaders,
					array(
						'GatherPress' => GATHERPRESS_CORE_PATH,
					)
				);

				foreach ( $registered_autoloaders as $namespace => $path ) {
					$namespace_root = sprintf( '%s\\', $namespace );
					$class_string   = trim( $class_string, '\\' );

					if (
						empty( $class_string ) ||
						! str_contains( $class_string, '\\' ) ||
						! str_starts_with( $class_string, $namespace_root )
					) {
						continue;
					}

					$structure = explode(
						'\\',
						str_replace( '_', '-', strtolower( $class_string ) )
					);

					// Pull off the class name (last segment) and the registered root
					// namespace (first segment). Whatever remains is the path under
					// the autoloader's root directory.
					$file = array_pop( $structure );
					array_shift( $structure );
					$class_filename = sprintf( 'class-%s.php', $file );

					/*
					 * Two layouts coexist in the codebase:
					 *
					 *   A. Production (`includes/core/classes/…`): `classes/` sits
					 *      directly under the first segment, and any deeper
					 *      namespace segments mirror the directory structure
					 *      under `classes/` —
					 *        Core\Setup            → includes/core/classes/class-setup.php
					 *        Core\Blocks\Modal     → includes/core/classes/blocks/class-modal.php
					 *        Core\Rsvp\Type\User   → includes/core/classes/rsvp/type/class-user.php
					 *
					 *   B. Test fixtures (`test/unit/php/includes/tests/…`):
					 *      `classes/` lands at the END of the namespace path and
					 *      the file sits directly inside it —
					 *        Tests\Core\Test_Geocoding    → includes/tests/core/classes/class-test-geocoding.php
					 *        Tests\Core\Blocks\Test_Modal → includes/tests/core/blocks/classes/class-test-modal.php
					 *
					 * Build both candidate paths and use whichever exists.
					 */
					$candidates = array();

					$prod_structure = $structure;
					array_unshift( $prod_structure, 'includes' );
					array_splice( $prod_structure, 2, 0, 'classes' );
					$prod_structure[] = $class_filename;
					$candidates[]     = $path . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $prod_structure );

					$test_structure = $structure;
					array_unshift( $test_structure, 'includes' );
					$test_structure[] = 'classes';
					$test_structure[] = $class_filename;
					$candidates[]     = $path . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $test_structure );

					foreach ( $candidates as $resource_path ) {
						$resource_path_valid = validate_file( $resource_path );

						if (
							file_exists( $resource_path ) &&
							( 0 === $resource_path_valid || 2 === $resource_path_valid )
						) {
							// Autoloader dynamically loads class files at runtime - cannot use 'use' keyword.
							require_once $resource_path; // NOSONAR.
							break;
						}
					}
				}
			}
		);
	}
}
