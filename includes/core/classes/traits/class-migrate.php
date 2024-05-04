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
 * 
 *
 * @since 1.0.0
 */
trait Migrate {

	/**
	 * 
	 *
	 * @since 1.0.0
	 *
	 * @return self The instance of the class.
	 */
	final public static function pseudopostmetas(): array {
		return apply_filters(
			'gatherpress_pseudopostmetas',
			[
				'gatherpress_datetimes' => [
					'export_callback' => array( '\GatherPress\Core\Export', 'datetimes_callback' ),
					'import_callback' => __NAMESPACE__ . '\\import_datetimes_callback',
				],
			]
		);
	}
}
