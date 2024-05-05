<?php
/**
 * The Migrate trait defines a methods relevant to Exporting & Importing.
 *
 * This trait is responsible for ...
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Traits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Migrate Trait.
 *
 * @since 1.0.0
 */
trait Migrate {

	protected $pseudopostmetas = array(
		'gatherpress_datetimes' => [
			'export_callback' => array( '\GatherPress\Core\Export', 'datetimes_callback' ),
			'import_callback' => array( '\GatherPress\Core\Import', 'datetimes_callback' ),
		],
	);

	/**
	 * 
	 *
	 * @since 1.0.0
	 *
	 * @return self The instance of the class.
	 */
	final public static function pseudopostmetas(): array {
		/**
		 * Filters the ...
		 *
		 * @since 1.0.0
		 * @hook gatherpress_pseudopostmetas
		 * @param {array} $pseudopostmetas ...
		 * @returns {array} ...
		 */
		return apply_filters( 'gatherpress_pseudopostmetas', self::$pseudopostmetas );
	}
}
