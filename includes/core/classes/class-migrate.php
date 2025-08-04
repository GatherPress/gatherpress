<?php
/**
 * The Migrate class defines methods relevant to Exporting & Importing.
 *
 * This file contains the Migrate class, which is responsible for migration of data,
 * that is not saved in WordPress' default db tables, within the GatherPress plugin.
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
	 * List of non-existent post_meta keys with array values containing getter and setter callback definitions.
	 *
	 * @since 1.0.0
	 * @var array $pseudopostmetas
	 */
	protected array $pseudopostmetas = array(
		'gatherpress_datetimes' => array(
			'export_callback' => array( Export::class, 'datetimes_callback' ),
			'import_callback' => array( Import::class, 'datetimes_callback' ),
		),
	);

	/**
	 * Returns a filterable list of data-names and their respective callbacks
	 * to either get that data during export or set that data during import.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_pseudopostmetas(): array {
		/**
		 * Filters the list of data-names and their respective export- and import-callbacks.
		 *
		 * The filter allows to hook into WordPress' native import & export processes,
		 * when post types of the GatherPress plugin are being migrated.
		 * That can be helpful, if you want to import event- or venue-data from another plugin.
		 *
		 * @example
		 *   Example use of the filter to illustrate function signatures for the callbacks.
		 *
		 *   ```php
		 *   \add_filter(
		 *       'gatherpress_pseudopostmetas',
		 *       function ( array $pseudopostmetas ): array {
		 *           $pseudopostmetas['my_gatherpress_extension_data_name'] = [
		 *               'export_callback' => function ( WP_Post $post ): string {
		 *                   // Do something with $post.
		 *                   // Query & prepare custom data
		 *                   // to exported with the current post.
		 *                   return 'my_gatherpress_extension_data';
		 *               },
		 *               'import_callback' => function (int $post_id, $meta_value ): void {
		 *                   // Save data for given post_id to a custom location,
		 *                   // when data should not end up in the postmeta table.
		 *                   return;
		 *               },
		 *           ];
		 *           return $pseudopostmetas;
		 *       }
		 *   );
		 *   ```
		 *
		 * @since 1.0.0
		 *
		 * @param  array $pseudopostmetas List of data-names and their respective export- and import-callbacks.
		 * @return array
		 */
		return (array) apply_filters( 'gatherpress_pseudopostmetas', $this->pseudopostmetas );
	}
}
