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
	 * Action hook, introduced to allow acting with GatherPress data to be exported.
	 *
	 * @since 1.0.0
	 * @var string $ACTION
	 */
	const ACTION = 'gatherpress_export';

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
		add_filter( 'wxr_export_skip_postmeta', array( $this, 'wxr_export_skip_postmeta' ), 10, 3 );
		add_action( ACTION, array( $this, 'export' ) );
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
	 * @param bool   $skip     Whether to skip the current post meta. Default false.
	 * @param string $meta_key Current meta key.
	 * @param object $meta     Current meta object.
	 *
	 * @return bool
	 */
	public static function wxr_export_skip_postmeta( bool $skip, string $meta_key, mixed $meta_data ): bool {
		if ( self::validate_object( $meta_key ) ) {
			/**
			 *  Action hook, introduced to allow acting with GatherPress data to be exported.
			 * 
			 * @hook  gatherpress_export
			 *
			 * @param WP_Post $post      The post to be exported.
			 * @param string  $meta_key  The post_meta key curently exported.
			 * @param mixed   $meta_data The data belonging to that $meta_key and $post.
			 */
			do_action( ACTION, get_post(), $meta_key, $meta_data );
		}
		return $skip;
	}

	/**
	 * Checks if the current post is of type 'gatherpress_event' 
	 * and if the given, processed post_meta key is '_edit_last'.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $meta_key Current meta key.
	 *
	 * @return bool
	 */
	protected static function validate_object( string $meta_key = '' ): bool {
		
		if ( Event::POST_TYPE !== get_post_type() ) {
			return false;
		}
		if ( '_edit_last' !== $meta_key ) {
			return false;
		}
		return true;
	}

	/**
	 * 
	 *
	 * @since 1.0.0
	 *
	 * @param  WP_Post $post
	 *
	 * @return void
	 */
	public static function export( WP_Post $post ): void {
		$pseudopostmetas = self::pseudopostmetas();
		array_walk(
			$pseudopostmetas,
			function ( array $callbacks, string $key ) use ( $post ) {
				if ( ! isset( $callbacks['export_callback'] ) || ! is_callable( $callbacks['export_callback'] ) ) {
					return;
				}
				$value = call_user_func( $callbacks['export_callback'], $post );
				?>
				<wp:postmeta>
					<wp:meta_key><?php echo wxr_cdata( $key ); ?></wp:meta_key>
					<wp:meta_value><?php echo wxr_cdata( $value ); ?></wp:meta_value>
				</wp:postmeta>
				<?php
			}
		);
	}

	/**
	 * Returns exportable data from the 'wp_gatherpress_events' DB table as serialized string.
	 *
	 * @since 1.0.0
	 *
	 * @param  WP_Post $post The post to be exported.
	 *
	 * @return string        Serialized JSON string with all date & time data of the given $post.
	 */
	public static function datetimes_callback( WP_Post $post ): string {
		// Make sure to not get any user-related data.
		remove_all_filters( 'gatherpress_timezone' );
		$event = new \GatherPress\Core\Event( $post->ID );
		return maybe_serialize( $event->get_datetime() );
	}
}
