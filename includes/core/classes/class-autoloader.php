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
			static function( $class ): bool {
				$structure = strtolower( $class );
				$structure = str_replace( '_', '-', $structure );
				$structure = explode( '\\', $structure );

				if ( 'gatherpress' !== array_shift( $structure ) ) {
					return false;
				}

				$file       = $structure[ count( $structure ) - 1 ];
				$class_type = $structure[ count( $structure ) - 2 ];

				array_pop( $structure );
				array_unshift( $structure, 'includes' );

				switch ( $class_type ) {
					case 'commands':
					case 'settings':
					case 'traits':
						array_pop( $structure );
						array_push( $structure, 'classes', $class_type );
						break;
					default:
						$structure[] = 'classes';
				}

				$structure[]         = sprintf( 'class-%s.php', $file );
				$resource_path       = GATHERPRESS_CORE_PATH . DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, $structure );
				$resource_path_valid = validate_file( $resource_path );

				if ( file_exists( $resource_path ) && ( 0 === $resource_path_valid || 2 === $resource_path_valid ) ) {
					require_once $resource_path;

					return true;
				}

				return false;
			}
		);
	}
}
