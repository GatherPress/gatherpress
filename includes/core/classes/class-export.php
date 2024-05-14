<?php
/**
 * Class responsible for exporting content using WordPress' native export tool.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Migrate;
use GatherPress\Core\Traits\Singleton;
use WP_Post;

/**
 * Class Export.
 *
 * The Export class handles the exporting of content using WordPress' native export tool.
 * This class will enhance overall export management, provide effective filtering
 * and support validation of the export-objects based on their post type and meta data.
 *
 * @since 1.0.0
 */
class Export extends Migrate {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

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
		add_filter( 'wxr_export_skip_postmeta', array( $this, 'set_entry_point' ), 10, 3 );
		add_action( 'gatherpress_export', array( $this, 'export' ) );
	}

	/**
	 * Extend WordPress' native Export
	 *
	 * WordPress' native Export can be extended in hacky way using `wxr_export_skip_postmeta`
	 * where GatherPress echos out some pseudo-post-meta fields,
	 * before returning `false` like the default.
	 *
	 * @source https://github.com/WordPress/wordpress-develop/blob/6.5/src/wp-admin/includes/export.php#L655-L677
	 *
	 * Normally this filters whether to selectively skip post meta used for WXR exports.
	 * Returning a truthy value from the filter will skip the current meta object from being exported.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/wxr_export_skip_postmeta/
	 *
	 * But there is no need to use this filter in real,
	 * GatherPress just uses it as entry-point into
	 * WordPress' native export process.
	 *
	 * A problem or caveat could be, that this filter only runs,
	 * if a post has real-existing data in the post_meta table.
	 * Right now, this whole operation relies on the existence of the '_edit_last' post meta key.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $skip      Whether to skip the current post meta. Default false.
	 * @param string $meta_key  Current meta key.
	 * @param object $meta      Current meta object.
	 *
	 * @return bool
	 */
	public static function set_entry_point( bool $skip, string $meta_key, object $meta ): bool {
		if ( self::validate( $meta_key ) ) {
			/**
			 * Fires for every GatherPress data to be exported.
			 *
			 * @since 1.0.0
			 *
			 * @param WP_Post $post      Current 'gatherpress_event' post being exported.
			 * @param string  $meta_key  Current meta key.
			 * @param object  $meta      Current meta object.
			 */
			do_action( 'gatherpress_export', get_post(), $meta_key, $meta );
		}
		return $skip;
	}

	/**
	 * Checks if the currently exported post is of type 'gatherpress_event'
	 * and if the given, processed meta_key is '_edit_last'.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $meta_key Current meta key.
	 *
	 * @return bool
	 */
	protected static function validate( string $meta_key = '' ): bool {

		if ( Event::POST_TYPE !== get_post_type() ) {
			return false;
		}
		if ( '_edit_last' !== $meta_key ) {
			return false;
		}
		return true;
	}

	/**
	 * Exports all custom data.
	 *
	 * Gets all 'pseudopostmetas' and generates WXR-compatible output for each,
	 * the generated xml markup is rendered into the WordPress export file directly.
	 *
	 * An export file like this can be imported into GatherPress using
	 * the native 'WordPress importer' and its potential replacement the 'WordPress importer (v2)'.
	 *
	 * @since 1.0.0
	 *
	 * @param  WP_Post $post Current 'gatherpress_event' post being exported.
	 *
	 * @return void
	 */
	public static function export( WP_Post $post ): void {
		$pseudopostmetas = self::get_pseudopostmetas();
		array_walk(
			$pseudopostmetas,
			function ( array $callbacks, string $key ) use ( $post ) {
				if ( ! isset( $callbacks['export_callback'] ) || ! is_callable( $callbacks['export_callback'] ) ) {
					return;
				}
				$value = call_user_func( $callbacks['export_callback'], $post );
				?>
				<wp:postmeta>
					<wp:meta_key><?php echo wxr_cdata( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></wp:meta_key>
					<wp:meta_value><?php echo wxr_cdata( $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></wp:meta_value>
				</wp:postmeta>
				<?php
			}
		);
	}

	/**
	 * Returns dates, times and timezone from the 'wp_gatherpress_events' DB table
	 * as serialized string for the current post being exported.
	 *
	 * @since 1.0.0
	 *
	 * @param  WP_Post $post Current 'gatherpress_event' post being exported.
	 *
	 * @return string        Serialized JSON string with all date, time & timezone data of the current $post.
	 */
	public static function datetimes_callback( WP_Post $post ): string {
		// Make sure to not get any user-related data.
		remove_all_filters( 'gatherpress_timezone' );
		$event = new \GatherPress\Core\Event( $post->ID );
		return maybe_serialize( $event->get_datetime() );
	}
}
