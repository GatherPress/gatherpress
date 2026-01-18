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
	 * The post_meta name for GatherPress temporary entry,
	 * to hook into WordPress export.
	 *
	 * @since 1.0.0
	 * @var string $POST_META
	 */
	const POST_META = 'gatherpress_extend_export';

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
		/**
		 * Fires at the beginning of an export, before any headers are sent.
		 */
		add_action( 'export_wp', array( $this, 'export' ) );
	}

	/**
	 * Sets up the necessary hooks for the export process.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function export(): void {
		add_action( 'the_post', array( $this, 'prepare' ) );
		add_filter( 'wxr_export_skip_postmeta', array( $this, 'extend' ), 10, 3 );
	}

	/**
	 * Saves a temporary marker as postmeta,
	 * which allows to hook into the export process per post later on.
	 *
	 * Called via setup_postdata() at the beginning of each singular post export.
	 *
	 * Fires once the post data has been set up.
	 *
	 * @since 1.0.0
	 *
	 * @param  WP_Post $post  The Post object (passed by reference).
	 * @return void
	 */
	public function prepare( WP_Post $post ): void {
		if ( Validate::event_post_id( $post->ID ) ) {
			add_post_meta( $post->ID, self::POST_META, true );
		}
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
	 * But because there is no 'do_action('per-exported-post)',
	 * GatherPress created a post_meta entry as a temporary marker, to be used as an entry-point into
	 * WordPress' native export process, which is used now.
	 *
	 * @since 1.0.0
	 *
	 * @param  bool   $skip     Whether to skip the current post meta. Default false.
	 * @param  string $meta_key Current meta key.
	 * @param  object $meta     Current meta object.
	 * @return bool             Whether to skip the current post meta. Default false.
	 */
	public function extend( bool $skip, string $meta_key, object $meta ): bool {
		if ( self::POST_META === $meta_key ) {
			// Echos out xml with pseudo-postmeta.
			$this->run( get_post( $meta->post_id ) );

			// Deletes temporary marker.
			delete_post_meta( $meta->post_id, self::POST_META );

			// Prevent 'normal' export processing for that particular postmeta field,
			// because it doesn't exist in real and will trigger an error.
			return true;
		}

		return $skip;
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
	 * @return void
	 */
	public function run( WP_Post $post ): void {
		$pseudopostmetas = $this->get_pseudopostmetas();

		array_walk(
			$pseudopostmetas,
			array( $this, 'render' ),
			$post
		);
	}

	/**
	 * Render custom post_meta data into xml markup to be used while WordÜress' native export.
	 *
	 * @param  array   $callbacks Associative array with (import & export) callback functions for
	 *                            the non-existent post_meta entry, named by $key.
	 * @param string  $key       Name of the custom post_meta, that should be exported.
	 * @param  WP_Post $post      The currently exported 'gatherpress_event' post.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render( array $callbacks, string $key, WP_Post $post ) {
		if (
			! isset( $callbacks['export_callback'] ) ||
			! is_callable( $callbacks['export_callback'] ) ||
			! function_exists( 'wxr_cdata' )
		) {
			return;
		}

		$value = call_user_func( $callbacks['export_callback'], $post );

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- wxr_cdata handles escaping.
		?>
		<wp:postmeta>
			<wp:meta_key><?php echo wxr_cdata( $key ); ?></wp:meta_key>
			<wp:meta_value><?php echo wxr_cdata( $value ); ?></wp:meta_value>
		</wp:postmeta>
		<?php
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Returns dates, times and timezone from the 'wp_gatherpress_events' DB table
	 * as serialized string for the current post being exported.
	 *
	 * @since 1.0.0
	 *
	 * @param  WP_Post $post Current 'gatherpress_event' post being exported.
	 * @return string        Serialized JSON string with all date, time & timezone data of the current $post.
	 */
	public function datetimes_callback( WP_Post $post ): string {
		// Make sure to not get any user-related data.
		remove_all_filters( 'gatherpress_timezone' );

		$event = new Event( $post->ID );

		return maybe_serialize( $event->get_datetime() );
	}
}
