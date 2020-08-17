<?php
/**
 * Function responsible for autoloading from namespace.
 *
 * @package GatherPress
 * @subpackage Helpers
 * @since 1.0.0
 */

namespace GatherPress\Inc\Helpers;

/**
 * Autoloader function.
 *
 * @param string $resource Namespace to autoload.
 */
function autoloader( $resource = '' ) {
	$namespace_root = 'GatherPress\\';

	$resource = trim( $resource, '\\' );

	if ( empty( $resource ) || strpos( $resource, '\\' ) === false || strpos( $resource, $namespace_root ) !== 0 ) {
		// not our namespace, bail out.
		return;
	}

	$theme_root = dirname( dirname( __DIR__ ) );

	$path = explode(
		'\\',
		str_replace( '_', '-', strtolower( $resource ) )
	);

	/*
	 * Time to determine which type of resource path it is,
	 * so that we can deduce the correct file path for it.
	 */
	if (
		( ! empty( $path[1] ) && 'inc' === $path[1] )
		&& ( ! empty( $path[2] ) && 'helpers' !== $path[2] )
	) {
		/*
		 * Theme resource for 'inc/classes' dir
		 * The path need 'classes' dir injected into it as all classes,
		 * services, traits, interfaces etc will be in 'classes' dir
		 */

		$class_path = untrailingslashit(
			implode(
				'/',
				array_slice( $path, 2 )
			)
		);

		$resource_path = sprintf( '%s/inc/classes/%s.php', untrailingslashit( $theme_root ), $class_path );
	} elseif (
		( ! empty( $path[1] ) && 'plugins' === $path[1] )
		&& ( ! empty( $path[2] ) && 'config' !== $path[2] )
	) {
		/*
		 * Plugin resource paths need 'classes' dir injected into the path as all
		 * plugin classes, interfaces & traits must be in 'classes' dir in plugin root.
		 */

		$plugin_name = untrailingslashit(
			implode(
				'/',
				array_slice( $path, 1, 2 )
			)
		);

		$class_path = strtolower(
			implode(
				'/',
				array_slice( $path, 3 )
			)
		);

		$resource_path = sprintf( '%s/%s/classes/%s.php', untrailingslashit( $theme_root ), $plugin_name, $class_path );
	} else {
		/*
		 * All other resource paths are translated as-is in lowercase
		 */

		if ( ! empty( $path[2] ) && 'config' === $path[2] ) {
			$path[2] = '_config';
		}

		array_shift( $path ); // knock off the first item, we don't need the root stub here.

		$resource_path = sprintf( '%s/%s.php', untrailingslashit( $theme_root ), implode( '/', $path ) );
	}

	$file_prefix = '';

	if ( strpos( $resource_path, 'traits' ) > 0 ) {
		$file_prefix = 'trait';
	} elseif ( strpos( $resource_path, 'interfaces' ) > 0 ) {
		$file_prefix = 'interface';
	} elseif ( strpos( $resource_path, '_config' ) > 0 ) {
		$file_prefix = 'class';
	} elseif ( strpos( $resource_path, 'classes' ) > 0 ) { // this has to be the last.
		$file_prefix = 'class';
	}

	if ( ! empty( $file_prefix ) ) {
		$resource_parts = explode( '/', $resource_path );

		$resource_parts[ count( $resource_parts ) - 1 ] = sprintf(
			'%s-%s',
			strtolower( $file_prefix ),
			$resource_parts[ count( $resource_parts ) - 1 ]
		);

		$resource_path = implode( '/', $resource_parts );
	}

	if ( file_exists( $resource_path ) && validate_file( $resource_path ) === 0 ) {
		require_once $resource_path;
	}
}

/**
 * Register autoloader
 */
spl_autoload_register( __NAMESPACE__ . '\autoloader' );
