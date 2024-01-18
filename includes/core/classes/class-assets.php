<?php
/**
 * Class is responsible for loading and managing static assets like stylesheets and JavaScript files,
 * as well as localizing data as JavaScript objects on the page.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

/**
 * Class Assets.
 *
 * This class handles the loading and management of static assets, including stylesheets and JavaScript files.
 * Additionally, it provides a mechanism for localizing data as JavaScript objects,
 * enabling seamless integration of server-side data with client-side scripts.
 *
 * @since 1.0.0
 */
class Assets {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * An array used to cache data assets.
	 *
	 * This property stores data assets in an array for efficient access and management.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected array $asset_data = array();

	/**
	 * The URL to the 'build' directory.
	 *
	 * This property holds the URL to the 'build' directory, which is used to reference built assets
	 * such as stylesheets and JavaScript files.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $build = GATHERPRESS_CORE_URL . 'build/';

	/**
	 * The file system path to the 'build' directory.
	 *
	 * This property holds the file system path to the 'build' directory, which contains compiled assets
	 * such as minified stylesheets and JavaScript files. It is used for referencing these assets within
	 * the application.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $path = GATHERPRESS_CORE_PATH . '/build/';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
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
		add_action( 'admin_print_scripts', array( $this, 'add_global_object' ), PHP_INT_MIN );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_enqueue_scripts' ) );
		add_action( 'wp_head', array( $this, 'add_global_object' ), PHP_INT_MIN );
		// Set priority to 11 to not conflict with media modal.
		add_action( 'admin_footer', array( $this, 'event_communication_modal' ), 11 );
	}

	/**
	 * Localize the global GatherPress JavaScript object for use in build scripts.
	 *
	 * This method generates JavaScript code to create a global 'GatherPress' object containing localized data.
	 * This object is made available for use in JavaScript build scripts, enabling seamless integration of
	 * server-side data with client-side functionality.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_global_object(): void {
		?>
		<script>window.GatherPress = <?php echo wp_json_encode( $this->localize( get_the_ID() ?? 0 ) ); ?></script>
		<?php
	}

	/**
	 * Enqueue necessary frontend styles and scripts.
	 *
	 * This method is responsible for enqueuing essential frontend styles and scripts
	 * required for the proper functioning of the plugin on the frontend.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_style( 'dashicons' );
	}

	/**
	 * Enqueue backend styles and scripts for the WordPress admin.
	 *
	 * This method is responsible for enqueuing backend styles and scripts necessary for the
	 * proper functioning of the WordPress admin area. It conditionally loads assets based on
	 * the admin page's context, such as post editing, settings pages, or general admin pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook The name of the current admin page.
	 * @return void
	 */
	public function admin_enqueue_scripts( string $hook ): void {
		$asset = $this->get_asset_data( 'admin_style' );

		wp_enqueue_style(
			'gatherpress-admin-style',
			$this->build . 'admin_style.css',
			$asset['dependencies'],
			$asset['version']
		);

		if ( 'post-new.php' === $hook || 'post.php' === $hook ) {
			$asset = $this->get_asset_data( 'panels' );

			wp_enqueue_script(
				'gatherpress-panels',
				$this->build . 'panels.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_set_script_translations( 'gatherpress-panels', 'gatherpress', GATHERPRESS_CORE_PATH . '/languages' );

			$asset = $this->get_asset_data( 'modals' );
			wp_enqueue_script(
				'gatherpress-modals',
				$this->build . 'modals.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_set_script_translations( 'gatherpress-modals', 'gatherpress', GATHERPRESS_CORE_PATH . '/languages' );
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

			wp_set_script_translations( 'gatherpress-settings', 'gatherpress', GATHERPRESS_CORE_PATH . '/languages' );
		}

		$asset = $this->get_asset_data( 'admin' );

		wp_enqueue_script(
			'gatherpress-admin',
			$this->build . 'admin.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( 'gatherpress-admin', 'gatherpress', GATHERPRESS_CORE_PATH . '/languages' );
	}

	/**
	 * Enqueue backend styles and scripts for the WordPress block editor.
	 *
	 * This method is responsible for enqueuing backend styles and scripts required for the proper functioning
	 * of the WordPress block editor (Gutenberg). It ensures that the editor has access to necessary assets.
	 *
	 * @since 1.0.0
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

		wp_set_script_translations( 'gatherpress-editor', 'gatherpress', GATHERPRESS_CORE_PATH . '/languages' );
	}

	/**
	 * Adds markup to the event edit page for storing the communication modal.
	 *
	 * This method inserts HTML markup on the event edit page specifically for storing the communication modal.
	 * It is responsible for creating a designated container for the modal's content.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function event_communication_modal(): void {
		if ( get_post_type() === Event::POST_TYPE ) {
			echo '<div id="gp-event-communication-modal" />';
		}
	}

	/**
	 * Localize data for JavaScript usage.
	 *
	 * This method prepares and localizes data for use in JavaScript scripts. It collects various event-related
	 * information and settings, making them available in the client-side context. The localized data includes
	 * response details, current user information, time zone settings, event properties, and more.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The Post ID for an event.
	 * @return array An associative array containing localized data for JavaScript.
	 */
	protected function localize( int $post_id ): array {
		$event    = new Event( $post_id );
		$settings = Settings::get_instance();

		return array(
			'responses'         => ( $event->rsvp ) ? $event->rsvp->responses() : array(),
			'current_user'      => ( $event->rsvp && $event->rsvp->get( get_current_user_id() ) )
				? $event->rsvp->get( get_current_user_id() )
				: '', // Cleanup needed.
			'is_user_logged_in' => is_user_logged_in(),
			'default_timezone'  => sanitize_text_field( wp_timezone_string() ),
			'event_announced'   => ( get_post_meta( $post_id, 'gp-event-announce', true ) ) ? 1 : 0,
			'event_datetime'    => $event->get_datetime(),
			'event_rest_api'    => home_url( 'wp-json/gatherpress/v1/event' ),
			'has_event_past'    => $event->has_event_past(),
			'is_admin'          => is_admin(),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'post_id'           => $post_id,
			'settings'          => array(
				'date_format'   => $settings->get_value( 'general', 'formatting', 'date_format' ),
				'time_format'   => $settings->get_value( 'general', 'formatting', 'time_format' ),
				'show_timezone' => ( '1' === $settings->get_value( 'general', 'formatting', 'show_timezone' ) ),
			),
			'login_url'         => $this->get_login_url( $post_id ),
			'registration_url'  => $this->get_registration_url( $post_id ),
			'timezone_choices'  => Utility::timezone_choices(),
			'unregister_blocks' => $this->unregister_blocks(),
		);
	}

	/**
	 * Retrieve the login URL for the event.
	 *
	 * This method generates and returns the URL for logging in or accessing event-specific content.
	 * It takes the optional `$post_id` parameter to customize the URL based on the event's Post ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Optional. The Post ID of the event. Defaults to 0.
	 * @return string The login URL for the event.
	 */
	public function get_login_url( int $post_id = 0 ): string {
		$permalink = get_the_permalink( $post_id );

		return wp_login_url( $permalink );
	}

	/**
	 * Retrieve the registration URL for the event.
	 *
	 * This method generates and returns the URL for user registration or accessing event-specific registration.
	 * It takes the optional `$post_id` parameter to customize the URL based on the event's Post ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Optional. The Post ID of the event. Defaults to 0.
	 * @return string The registration URL for the event, or an empty string if user registration is disabled.
	 */
	public function get_registration_url( int $post_id = 0 ): string {
		$permalink = get_the_permalink( $post_id );
		$url       = '';

		if ( get_option( 'users_can_register' ) ) {
			$url = add_query_arg( 'redirect', $permalink, wp_registration_url() );
		}

		return $url;
	}

	/**
	 * Retrieve a list of blocks to unregister based on the current post type.
	 *
	 * This method determines which blocks should be unregistered on the current page
	 * in the WordPress admin based on the post type. It returns an array of block names
	 * that should be removed from the block editor for the given post type.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of block names to unregister.
	 */
	protected function unregister_blocks(): array {
		$blocks = array();

		if ( ! is_admin() ) {
			return $blocks;
		}

		switch ( get_post_type() ) {
			case Event::POST_TYPE:
				$blocks;
				break;
			case Venue::POST_TYPE:
				$blocks = array(
					'gatherpress/add-to-calendar',
					'gatherpress/event-date',
					'gatherpress/online-event',
					'gatherpress/rsvp',
					'gatherpress/rsvp-response',
				);
				break;
			default:
				$blocks = array(
					'gatherpress/add-to-calendar',
					'gatherpress/event-date',
					'gatherpress/online-event',
					'gatherpress/rsvp',
					'gatherpress/rsvp-response',
					'gatherpress/venue',
				);
		}

		return $blocks;
	}

	/**
	 * Retrieve asset data generated by the build script.
	 *
	 * This method fetches data related to a specific asset that has been generated by the build script.
	 * The data is cached to ensure efficient retrieval, as `require_once` only loads the file contents
	 * on the first request and returns `true` thereafter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $asset The file name of the asset.
	 * @return array An array containing asset-related data.
	 */
	protected function get_asset_data( string $asset ): array {
		if ( empty( $this->asset_data[ $asset ] ) ) {
			$this->asset_data[ $asset ] = require_once $this->path . sprintf( '%s.asset.php', $asset );
		}

		return $this->asset_data[ $asset ];
	}
}
