<?php
/**
 * The Migrate class defines methods relevant to Exporting & Importing.
 *
 * This class is responsible for ...
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Migrate.
 *
 * Provides common migration methods.
 *
 * @since 1.0.0
 */
class Migrate {

	/**
	 *
	 */
	const META_FILTER = 'gatherpress_pseudopostmetas';

	/**
	 * List of non-existent post_meta keys with array values containing getter and setter callback definitions.
	 *
	 * @since 1.0.0
	 * @var array $pseudopostmetas
	 */
	protected static $pseudopostmetas = array(
		'gatherpress_datetimes' => array(
			'export_callback' => array( '\GatherPress\Core\Export', 'datetimes_callback' ),
			'import_callback' => array( '\GatherPress\Core\Import', 'datetimes_callback' ),
		),
	);

	/**
	 *
	 *
	 * @since 1.0.0
	 *
	 * @return array 
	 */
	public static function get_pseudopostmetas(): array {
		/**
		 * Filters the ...
		 *
		 * @since 1.0.0
		 * @hook gatherpress_pseudopostmetas
		 * @param {array} $pseudopostmetas ...
		 * @returns {array} ...
		 */
		return (array) apply_filters( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			self::META_FILTER,
			self::$pseudopostmetas
		);
	}
}
