<?php
/**
 * Class is responsible for loading all static assets.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) { // @codeCoverageIgnore
	exit; // @codeCoverageIgnore
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
	protected array $asset_data = array();

	/**
	 * URL to `build` directory.
	 *
	 * @var string
	 */
	protected string $build = GATHERPRESS_CORE_URL . 'build/';

	/**
	 * Path to `build` directory.
	 *
	 * @var string
	 */
	protected string $path = GATHERPRESS_CORE_PATH . '/build/';

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
		add_action( 'admin_print_scripts', array( $this, 'add_global_object' ), PHP_INT_MIN );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_enqueue_scripts' ) );
		add_action( 'wp_head', array( $this, 'add_global_object' ), PHP_INT_MIN );
		// Set priority to 11 to not conflict with media modal.
		add_action( 'admin_footer', array( $this, 'event_communication_modal' ), 11 );
	}

	/**
	 * Localize the global GatherPress js object for use in the build scripts.
	 *
	 * @return void
	 */
	public function add_global_object() {
		?>
		<script>
			const GatherPress = <?php echo wp_json_encode( $this->localize( get_the_ID() ?? 0 ) ); ?>
		</script>
		<?php
	}

	/**
	 * Enqueue frontend styles and scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'dashicons' );
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
				$this->build . 'style-settings_style.css',
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

		$asset = $this->get_asset_data( 'admin' );

		wp_enqueue_script(
			'gatherpress-admin',
			$this->build . 'admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Enqueue backend styles and scripts.
	 *
	 * @return void
	 */
	public function editor_enqueue_scripts(): void {
		$asset = $this->get_asset_data( 'editor' );

		wp_enqueue_script(
			'gatherpress-editor',
			$this->build . 'editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
	}

	/**
	 * Adds markup to event edit page to store communication modal.
	 *
	 * @return void
	 */
	public function event_communication_modal(): void {
		if ( get_post_type() === Event::POST_TYPE ) {
			echo '<div id="gp-event-communication-modal" />';
		}
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
			'attendees'         => ( $event->attendee ) ? $event->attendee->attendees() : array(), // @todo cleanup
			'current_user'      => ( $event->attendee && $event->attendee->get( get_current_user_id() ) ) ? $event->attendee->get( get_current_user_id() ) : '', // @todo cleanup
			'default_timezone'  => sanitize_text_field( wp_timezone_string() ),
			'event_announced'   => ( get_post_meta( $post_id, 'gp-event-announce', true ) ) ? 1 : 0,
			'event_datetime'    => $event->get_datetime(),
			'event_rest_api'    => home_url( 'wp-json/gatherpress/v1/event' ),
			'has_event_past'    => $event->has_event_past(),
			'is_admin'          => is_admin(),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'post_id'           => $post_id,
			'post_type'         => Event::POST_TYPE,
			'settings'          => array(
				// @todo settings to come...
			),
			'timezone_choices'  => Utility::timezone_choices(),
			'unregister_blocks' => $this->unregister_blocks(),
		);
	}

	/**
	 * List of blocks to unregistered based on post type.
	 *
	 * @return array
	 */
	protected function unregister_blocks(): array {
		$blocks = array();

		if ( ! is_admin() ) {
			return $blocks;
		}

		switch ( get_post_type() ) {
			case Event::POST_TYPE:
				$blocks = array(
					'gatherpress/venue-information',
				);
				break;
			case Venue::POST_TYPE:
				$blocks = array(
					'gatherpress/add-to-calendar',
					'gatherpress/attendance-list',
					'gatherpress/attendance-selector',
					'gatherpress/event-date',
					'gatherpress/event-venue',
					'gatherpress/online-event',
				);
				break;
			default:
				$blocks = array(
					'gatherpress/add-to-calendar',
					'gatherpress/attendance-list',
					'gatherpress/attendance-selector',
					'gatherpress/event-date',
					'gatherpress/event-venue',
					'gatherpress/online-event',
					'gatherpress/venue-information',
				);
		}

		return $blocks;
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
