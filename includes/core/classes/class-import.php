<?php
/**
 * Class responsible for importing content using WordPress' native import tool(s).
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Migrate;
use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Import.
 *
 * Manages Import ...
 *
 * @since 1.0.0
 */
class Import extends Migrate {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * 
	 */
	const ACTION = 'gatherpress_import';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		
		if ( class_exists( 'WXR_Importer' ) ) {
			// WordPress Importer (v2)
			// https://github.com/humanmade/Wordpress-Importer
			add_filter( 'wxr_importer.pre_process.post', array( '\GatherPress\Core\Import', 'import_events' ) );
			
		} else {
			// Default WordPres Importer
			// https://github.com/WordPress/wordpress-importer/issues/42
			add_filter( 'wp_import_post_data_raw', array( '\GatherPress\Core\Import', 'import_events' ) );
		}
		add_action( ACTION, array( $this, 'import' ) );
	}

	/**
	 * 
	 *
	 * @param  array $post_data_raw The result of 'wp_import_post_data_raw'. @see https://github.com/WordPress/wordpress-importer/blob/71bdd41a2aa2c6a0967995ee48021037b39a1097/src/class-wp-import.php#L631
	 *
	 * @return array
	 */
	public static function import_events( array $post_data_raw ): array {
		if ( self::validate_object( $post_data_raw ) ) {
			do_action( ACTION, $post_data_raw );
		}
		return $post_data_raw;
	}

	/**
	 * 
	 *
	 * @param  array $postdata
	 *
	 * @return bool
	 */
	protected static function validate_object( array $postdata ): bool {
		if ( ! isset( $postdata['post_type'] ) || 'gatherpress_event' !== $postdata['post_type'] ) {
			return false;
		}
		return true;
	}


	public static function import(): void {
		add_filter( 'add_post_metadata', array( '\GatherPress\Core\Import', 'add_post_metadata' ), 10, 5 );
	}

	/**
	 * 
	 * 
	 * @see https://developer.wordpress.org/reference/hooks/add_meta_type_metadata/
	 * @see https://www.ibenic.com/hook-wordpress-metadata/
	 *
	 * @param  [type] $save
	 * @param  int    $object_id
	 * @param  string $meta_key
	 * @param  mixed  $meta_value
	 * @param  bool   $unique
	 *
	 * @return void
	 */
	public static function add_post_metadata( null|bool $save, int $object_id, string $meta_key, mixed $meta_value, bool $unique ): ?bool {
		$pseudopostmetas = self::pseudopostmetas();
		if ( ! isset( $pseudopostmetas[ $meta_key ] ) ) {
			return $save;
		}
		if ( ! isset( $pseudopostmetas[ $meta_key ], $pseudopostmetas[ $meta_key ]['import_callback'] ) || ! is_callable( $pseudopostmetas[ $meta_key ]['import_callback'] ) ) {
			return $save;
		}
		/**
		 * Save data, e.g. into a custom DB table.
		 */
		call_user_func( 
			$pseudopostmetas[ $meta_key ]['import_callback'],
			$object_id,
			$meta_value
		);
		/**
		 * Returning a non-null value will effectively short-circuit the saving of 'normal' meta data.
		 */
		return false;
	}


	/**
	 * Save $data into some place, which is not post_meta.
	 *
	 * @param  int   $post_id
	 * @param  array $data
	 *
	 * @return void
	 */
	public static function datetimes_callback( int $post_id, array $data ): void {
		$event = new \GatherPress\Core\Event( $post_id );
		$event->save_datetimes( maybe_unserialize( $data ) );
	}



}
