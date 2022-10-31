<?php
/**
 * Class is responsible for loading all static assets.
 *
 * @package GatherPress
 * @subpackage Includes
 * @since 1.0.0
 */

namespace GatherPress\Includes;

use \GatherPress\Includes\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets.
 */
class Assets {

	use Singleton;

	/**
	 * Cache data assets.
	 *
	 * @var array
	 */
	protected $asset_data = array();

	/**
	 * URL to `build` directory.
	 *
	 * @var string
	 */
	protected $build = GATHERPRESS_CORE_URL . 'build/';

	/**
	 * Path to `build` directory.
	 *
	 * @var string
	 */
	protected $path = GATHERPRESS_CORE_PATH . '/build/';

	/**
	 * Assets constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'maybe_deny_blocks_list' ) );
	}

	/**
	 * Enqueue frontend styles and scripts.
	 */
	public function enqueue_scripts() {
		// @todo some stuff is repeated in enqueuing for frontend and blocks. need to break into other methods.
		$post_id = get_the_ID() ?? 0;

		$asset = include( plugin_dir_path( GATHERPRESS_CORE_FILE ) . 'build/blocks/attendance-selector/index.asset.php' );
		wp_enqueue_script(
			'gatherpress-attendance-selector',
			GATHERPRESS_CORE_URL . 'build/blocks/attendance-selector/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'gatherpress-attendance-selector',
			'GatherPress',
			$this->localize( $post_id )
		);
	}

	/**
	 * Enqueue backend styles and scripts.
	 *
	 * @param string $hook Name of file.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'gp_event_page_gp_credits' === $hook ) {
			wp_enqueue_style(
				'gatherpress-admin',
				plugins_url( 'assets/css/admin-dashboard.css', __DIR__ ),
				[],
				filemtime( plugin_dir_path(__DIR__) . 'assets/css/admin-dashboard.css' )
			);
		}
		$settings      = Settings::get_instance();
		$setting_hooks = array_map(
			function( $key ) {
				return sprintf( 'gp_event_page_gp_%s', sanitize_key( $key ) );
			},
			array_keys( $settings->get_sub_pages() )
		);

		if ( in_array( $hook, $setting_hooks, true ) ) {
			// Need to load block styling for some dynamic fields.
			// wp_enqueue_style( 'wp-edit-blocks' );

			// $asset = $this->get_asset_data( 'settings_style' );

			// wp_enqueue_style(
			// 	'gatherpress-settings-style',
			// 	$this->build . 'settings_style.css',
			// 	$asset['dependencies'],
			// 	$asset['version']
			// );

			// $asset = $this->get_asset_data( 'settings' );

			// wp_enqueue_script(
			// 	'gatherpress-settings',
			// 	$this->build . 'settings.js',
			// 	$asset['dependencies'],
			// 	$asset['version'],
			// 	true
			// );
		}
	}

	/**
	 * Enqueue block styles and scripts.
	 */
	public function block_enqueue_scripts() {
		$post_id = $GLOBALS['post']->ID ?? 0;
		$event   = new Event( $post_id );
		$post_id = get_the_ID() ?? 0;

		$asset = include( plugin_dir_path( GATHERPRESS_CORE_FILE ) . 'build/blocks/event-date/index.asset.php' );
		wp_enqueue_script(
			'gatherpress-blocks-backend',
			GATHERPRESS_CORE_URL . 'build/blocks/event-date/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'gatherpress-blocks-backend',
			'GatherPress',
			$this->localize( $post_id )
		);
	}

	/**
	 * Localize data to JavaScript.
	 *
	 * @param int $post_id Post ID for an event.
	 *
	 * @return array
	 */
	protected function localize( int $post_id ): array {
		$event    = new Event( $post_id );
		$settings = Settings::get_instance();
		return array(
			'attendees'        => ( $event->attendee ) ? $event->attendee->attendees() : array(), // @todo cleanup
			'current_user'     => ( $event->attendee && $event->attendee->get( get_current_user_id() ) ) ? $event->attendee->get( get_current_user_id() ) : '', // @todo cleanup
			'event_rest_api'   => home_url( 'wp-json/gatherpress/v1/event' ),
			'has_event_past'   => $event->has_event_past(),
			'is_admin'         => is_admin(),
			'nonce'            => wp_create_nonce( 'wp_rest' ),
			'post_id'          => $post_id,
			'event_datetime'   => $event->get_datetime(),
			'event_announced'  => ( get_post_meta( $post_id, 'gp-event-announce', true ) ) ? 1 : 0,
			'default_timezone' => sanitize_text_field( wp_timezone_string() ),
			'settings'         => array(
				// @todo settings to come...
			),
		);
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	function maybe_deny_blocks_list() {
		wp_register_script(
			'post-deny-list-blocks',
			plugins_url( 'js/venue-deny-list.js', __DIR__ ),
			array(
				'wp-blocks',
				'wp-dom-ready',
				'wp-edit-post'
			),
			filemtime( plugin_dir_path( __DIR__ ) . 'js/post-deny-list.js' ),
			true
		);
		wp_register_script(
			'event-deny-list-blocks',
			plugins_url( 'js/event-deny-list.js', __DIR__ ),
			array(
				'wp-blocks',
				'wp-dom-ready',
				'wp-edit-post'
			),
			filemtime( plugin_dir_path( __DIR__ ) . 'js/event-deny-list.js' ),
			true
		);
		wp_register_script(
			'venue-deny-list-blocks',
			plugins_url( 'js/venue-deny-list.js', __DIR__ ),
			array(
				'wp-blocks',
				'wp-dom-ready',
				'wp-edit-post'
			),
			filemtime( plugin_dir_path( __DIR__ ) . 'js/venue-deny-list.js' ),
			true
		);
		if ( 'post' === get_post_type() || 'page' === get_post_type() ) {
			wp_enqueue_script( 'post-deny-list-blocks' );
		}
		if ( 'gp_event' === get_post_type() ) {
			wp_enqueue_script( 'event-deny-list-blocks' );
		}
		if ( 'gp_venue' === get_post_type() ) {
			wp_enqueue_script( 'venue-deny-list-blocks' );
		}
	}

	/**
	 * Retrieve asset data generated by build script.
	 *
	 * Data is cached as `require_once` only returns the file contents on the
	 * first request, returning `true` thereafter.
	 *
	 * @param string $asset File name of the asset.
	 *
	 * @return array
	 */
	protected function get_asset_data( string $asset ): array {
		if ( empty( $this->asset_data[ $asset ] ) ) {
			$this->asset_data[ $asset ] = require_once $this->path . sprintf( '%s.asset.php', $asset );
		}

		return $this->asset_data[ $asset ];
	}

}
