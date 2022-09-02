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

				switch ( $class_type ) {
					case 'traits':
						array_pop( $structure );
						array_push( $structure, 'classes', 'traits' );
						break;
					default:
						$structure[] = 'classes';
				}

				$structure[]   = sprintf( 'class-%s.php', $file );
				$resource_path = GATHERPRESS_CORE_PATH . '/' . implode( '/', $structure );

				if ( file_exists( $resource_path ) && 0 === validate_file( $resource_path ) ) {
					require_once $resource_path;

					return true;
				}

				return false;
			}
		);
	}

}
