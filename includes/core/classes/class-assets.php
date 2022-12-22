<?php
/**
 * Class is responsible for loading all static assets.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use \GatherPress\Core\Traits\Singleton;

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
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts_2' ), 10, 1 );
		// add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_enqueue_scripts_2' ) );
		add_action( 'wp_head', array( $this, 'add_global_object' ) );
		add_action( 'admin_print_scripts', array( $this, 'add_global_object' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_to_calendar_script' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'maybe_deny_list_blocks' ) );
	}

	/**
	 * Enqueue Scripts
	 *
	 * @return void
	 */
	public function add_to_calendar_script() {
		wp_register_script(
			'add-to-calendar',
			plugins_url( 'js/add-to-calendar.js', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/add-to-calendar.js' ),
			true
		);

		if ( 'gp_event' === get_post_type() ) {
			wp_enqueue_script( 'add-to-calendar' );
		}
	}

	/**
	 * Enqueue Scripts
	 *
	 * @return void
	 */
	public function maybe_deny_list_blocks() {
		wp_register_script(
			'post-deny-list-blocks',
			plugins_url( 'js/post-deny-list.js', __FILE__ ),
			array(
				'wp-blocks',
				'wp-dom-ready',
				'wp-edit-post',
			),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/post-deny-list.js' ),
			true
		);
		wp_register_script(
			'event-deny-list-blocks',
			plugins_url( 'js/event-deny-list.js', __FILE__ ),
			array(
				'wp-blocks',
				'wp-dom-ready',
				'wp-edit-post',
			),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/event-deny-list.js' ),
			true
		);
		wp_register_script(
			'venue-deny-list-blocks',
			plugins_url( 'js/venue-deny-list.js', __FILE__ ),
			array(
				'wp-blocks',
				'wp-dom-ready',
				'wp-edit-post',
			),
			filemtime( plugin_dir_path( __FILE__ ) . 'js/venue-deny-list.js' ),
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
	 * Localize the global GatherPress js object for use in the build scripts.
	 */
	public function add_global_object() {
		$post_id = get_the_ID() ?? 0;
		?>
		<script>
		const GatherPress = <?php echo wp_json_encode( $this->localize( $post_id ) ); ?>
		</script>
		<?php
	}

	/**
	 * Enqueue frontend styles and scripts.
	 */
	public function enqueue_scripts() {
		// @todo some stuff is repeated in enqueuing for frontend and blocks. need to break into other methods.

		$asset = $this->get_asset_data( 'blocks_style' );

		wp_enqueue_style( 'wp-block-button' );

		wp_enqueue_style(
			'gatherpress-blocks-style',
			$this->build . 'blocks_style.css',
			$asset['dependencies'],
			$asset['version']
		);

		$asset = $this->get_asset_data( 'blocks_frontend' );

		wp_enqueue_script(
			'gatherpress-blocks-frontend',
			$this->build . 'blocks_frontend.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Enqueue backend styles and scripts.
	 *
	 * @param string $hook Name of file.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts_2( $hook ) {
		wp_enqueue_style(
			'gatherpress-admin-settings',
			plugins_url( 'css/admin-settings.css', __FILE__ ),
			array(),
			filemtime( plugin_dir_path( __FILE__ ) . 'css/admin-settings.css' )
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
		if ( 'post-new.php' === $hook || 'post.php' === $hook ) {
			$asset = $this->get_asset_data( 'panels' );

			wp_enqueue_script(
				'gatherpress-panels',
				$this->build . 'panels.js',
				$asset['dependencies'],
				$asset['version'],
				true
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
			wp_enqueue_style( 'wp-edit-blocks' );

			$asset = $this->get_asset_data( 'settings_style' );

			wp_enqueue_style(
				'gatherpress-settings-style',
				$this->build . 'settings_style.css',
				$asset['dependencies'],
				$asset['version']
			);

			$asset = $this->get_asset_data( 'settings' );

			wp_enqueue_script(
				'gatherpress-settings',
				$this->build . 'settings.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);
		}

	}

	/**
	 * Enqueue block styles and scripts.
	 */
	public function block_enqueue_scripts_2() {
		$post_id = $GLOBALS['post']->ID ?? 0;
		$event   = new Event( $post_id );

	}

	/**
	 * Enqueue block styles and scripts.
	 */
	public function block_enqueue_scripts() {
		$post_id = $GLOBALS['post']->ID ?? 0;
		$event   = new Event( $post_id );

		$asset = $this->get_asset_data( 'blocks_style' );

		wp_enqueue_style( 'wp-block-button' );

		wp_enqueue_style(
			'gatherpress-blocks-style',
			$this->build . 'blocks_style.css',
			$asset['dependencies'],
			$asset['version']
		);

		$asset = require_once $this->path . 'blocks_backend.asset.php';
		wp_enqueue_script(
			'gatherpress-blocks-backend',
			$this->build . 'blocks_backend.js',
			$asset['dependencies'],
			$asset['version'],
			true
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
			'event_announced'  => ( get_post_meta( $post_id, 'gatherpress-event-announce', true ) ) ? 1 : 0,
			'default_timezone' => sanitize_text_field( wp_timezone_string() ),
			'settings'         => array(
				// @todo settings to come...
			),
		);
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
			// $this->asset_data[ $asset ] = require_once $this->path . sprintf( '%s.asset.php', $asset );
		}
		return array();
		// return $this->asset_data[ $asset ];
	}

}
