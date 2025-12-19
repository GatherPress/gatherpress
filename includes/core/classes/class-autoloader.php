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
						false === strpos( $class_string, '\\' ) ||
						0 !== strpos( $class_string, $namespace_root )
					) {
						continue;
					}

					$structure = explode(
						'\\',
						str_replace( '_', '-', strtolower( $class_string ) )
					);

					$file       = $structure[ count( $structure ) - 1 ];
					$class_type = $structure[ count( $structure ) - 2 ];

					array_shift( $structure );
					array_pop( $structure );
					array_unshift( $structure, 'includes' );

					// Check if a specialized directory exists for this class type.
					$test_structure = $structure;

					array_pop( $test_structure );
					array_push( $test_structure, 'classes', $class_type );

					$specialized_dir = $path . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $test_structure );

					if ( is_dir( $specialized_dir ) ) {
						array_pop( $structure );
						array_push( $structure, 'classes', $class_type );
					} else {
						$structure[] = 'classes';
					}

					$structure[]         = sprintf( 'class-%s.php', $file );
					$resource_path       = $path . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $structure );
					$resource_path_valid = validate_file( $resource_path );

					if (
						file_exists( $resource_path ) &&
						( 0 === $resource_path_valid || 2 === $resource_path_valid )
					) {
						require_once $resource_path;
					}
				}
			}
		);
	}
}
