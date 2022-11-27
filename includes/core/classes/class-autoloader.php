<?php
/**
 * Class is autoloading GatherPress class files.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader.
 */
class Autoloader {

	/**
	 * Register method for autoloader.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register(
			function( $class ) {
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
					case 'traits':
						array_pop( $structure );
						array_push( $structure, 'classes', 'traits' );
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
