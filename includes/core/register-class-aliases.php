<?php
/**
 * Registers class aliases mapping prior fully-qualified class names to their current locations.
 *
 * When a class moves into a subnamespace as part of an internal reorganization, this shim
 * keeps the prior fully-qualified name resolvable so downstream consumers (other plugins,
 * theme code, and existing call sites within this plugin) do not need to update their
 * `use` statements or fully-qualified references in lockstep with the move.
 *
 * Aliases resolve lazily via an autoloader: the underlying class file is only loaded when
 * the prior name is actually referenced.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

spl_autoload_register(
	static function ( string $class_string ): void {
		// Map of prior fully-qualified class names to their current fully-qualified class names.
		$aliases = array(
			'GatherPress\\Core\\Calendar'   => 'GatherPress\\Core\\Calendar\\Calendar',
			'GatherPress\\Core\\Event'      => 'GatherPress\\Core\\Event\\Event',
			'GatherPress\\Core\\Rsvp'       => 'GatherPress\\Core\\Rsvp\\Rsvp',
			'GatherPress\\Core\\Venue'      => 'GatherPress\\Core\\Venue\\Venue',
			'GatherPress\\Core\\Venue\\Map' => 'GatherPress\\Core\\Venue\\Map\\Map',
		);

		if ( ! isset( $aliases[ $class_string ] ) ) {
			return;
		}

		class_alias( $aliases[ $class_string ], $class_string );
	}
);
