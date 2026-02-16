<?php
/**
 * Class is responsible for loading and managing static assets like stylesheets and JavaScript files,
 * as well as localizing data as JavaScript objects on the page.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use Error;

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
		add_action( 'enqueue_block_assets', array( $this, 'block_enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_variation_assets' ) );
		add_action( 'init', array( $this, 'register_variation_assets' ) );
		add_action( 'wp_head', array( $this, 'add_global_object' ), PHP_INT_MIN );
		// Set priority to 11 to not conflict with media modal.
		add_action( 'admin_footer', array( $this, 'event_communication_modal' ), 11 );

		add_filter( 'render_block', array( $this, 'maybe_enqueue_styles' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'maybe_enqueue_tooltip_assets' ) );
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
		<script>window.GatherPress = <?php echo wp_json_encode( $this->localize( intval( get_the_ID() ) ) ); ?></script>
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
	public function block_enqueue_scripts(): void {
		// @todo remove once new blocks are completed.
		wp_enqueue_style( 'dashicons' );

		$asset = $this->get_asset_data( 'utility_style' );

		wp_register_style(
			'gatherpress-utility-style',
			$this->build . 'utility_style.css',
			$asset['dependencies'],
			$asset['version']
		);
	}

	/**
	 * Conditionally enqueue utility styles if GatherPress blocks are rendered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block settings.
	 * @return string The block content.
	 */
	public function maybe_enqueue_styles( string $block_content, array $block ): string {
		if ( isset( $block['blockName'] ) && str_contains( $block['blockName'], 'gatherpress/' ) ) {
			wp_enqueue_style( 'gatherpress-utility-style' );
		}

		return $block_content;
	}

	/**
	 * Conditionally enqueue tooltip assets if tooltip markup is found in block content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The block content.
	 * @return string The block content.
	 */
	public function maybe_enqueue_tooltip_assets( string $block_content ): string {
		if ( str_contains( $block_content, 'gatherpress-tooltip' ) ) {
			$this->enqueue_tooltip_assets();
		}

		return $block_content;
	}

	/**
	 * Register and enqueue tooltip frontend assets.
	 *
	 * Enqueues the tooltip view script which initializes CSS custom properties
	 * from data attributes for custom tooltip colors.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function enqueue_tooltip_assets(): void {
		static $enqueued = false;

		if ( $enqueued ) {
			return;
		}

		$enqueued = true;

		// Enqueue utility styles which include tooltip styles.
		wp_enqueue_style( 'gatherpress-utility-style' );

		// Enqueue tooltip view script for initializing CSS custom properties.
		$script_asset = $this->get_asset_data( 'tooltip_view' );

		wp_enqueue_script_module(
			'gatherpress-tooltip-view',
			$this->build . 'tooltip_view.js',
			array(
				array(
					'id'     => '@wordpress/interactivity',
					'import' => 'static',
				),
			),
			$script_asset['version']
		);
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

			wp_set_script_translations( 'gatherpress-panels', 'gatherpress' );

			$asset = $this->get_asset_data( 'modals' );
			wp_enqueue_script(
				'gatherpress-modals',
				$this->build . 'modals.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_set_script_translations( 'gatherpress-modals', 'gatherpress' );
		}

		$settings      = Settings::get_instance();
		$setting_hooks = array_map(
			function ( $key ) {
				return sprintf( 'gatherpress_event_page_%s', Utility::prefix_key( sanitize_key( $key ) ) );
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

			wp_set_script_translations( 'gatherpress-settings', 'gatherpress' );
		}

		if ( 'profile.php' === $hook ) {
			$asset = $this->get_asset_data( 'profile' );

			wp_enqueue_script(
				'gatherpress-profile',
				$this->build . 'profile.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_set_script_translations( 'gatherpress-profile', 'gatherpress' );
		}
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

		wp_enqueue_style( 'gatherpress-utility-style' );

		wp_add_inline_script(
			'gatherpress-editor',
			'GatherPress.misc.timezoneChoices = ' . wp_json_encode( Utility::timezone_choices() ),
			'before'
		);

		wp_set_script_translations( 'gatherpress-editor', 'gatherpress' );
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
			echo '<div id="gatherpress-event-communication-modal"></div>';
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
		$event               = new Event( $post_id );
		$settings            = Settings::get_instance();
		$event_details       = array();
		$event_rest_api_slug = sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE );
		$user_identifier     = Rsvp_Setup::get_instance()->get_user_identifier();

		if ( ! empty( $event->event ) ) {
			$event_details = array(
				'currentUser'         => $event->rsvp->get( $user_identifier ),
				'dateTime'            => $event->get_datetime(),
				'enableAnonymousRsvp' => (bool) get_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', true ),
				'maxAttendanceLimit'  => (int) get_post_meta( $post_id, 'gatherpress_max_attendance_limit', true ),
				'maxGuestLimit'       => (int) get_post_meta( $post_id, 'gatherpress_max_guest_limit', true ),
				'hasEventPast'        => $event->has_event_past(),
				'postId'              => $post_id,
				'responses'           => $event->rsvp->responses(),
			);
		}

		return array(
			'eventDetails' => $event_details,
			'misc'         => array(
				'isAdmin'          => is_admin(),
				'isUserLoggedIn'   => is_user_logged_in(),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'unregisterBlocks' => $this->unregister_blocks(),
			),
			'settings'     => array(
				'dateFormat'          => $settings->get_value( 'general', 'formatting', 'date_format' ),
				'enableAnonymousRsvp' => ( 1 === (int) $settings->get_value(
					'general',
					'general',
					'enable_anonymous_rsvp'
				) ),
				'mapPlatform'         => $settings->get_value( 'general', 'general', 'map_platform' ),
				'maxAttendanceLimit'  => $settings->get_value( 'general', 'general', 'max_attendance_limit' ),
				'maxGuestLimit'       => $settings->get_value( 'general', 'general', 'max_guest_limit' ),
				'showTimezone'        => ( 1 === (int) $settings->get_value(
					'general',
					'formatting',
					'show_timezone'
				) ),
				'timeFormat'          => $settings->get_value( 'general', 'formatting', 'time_format' ),
			),
			'urls'         => array(
				'pluginUrl'       => GATHERPRESS_CORE_URL,
				'eventApiPath'    => '/' . $event_rest_api_slug,
				'eventApiUrl'     => home_url( 'wp-json/' . $event_rest_api_slug ),
				'loginUrl'        => Utility::get_login_url( $post_id ),
				'registrationUrl' => Utility::get_registration_url( $post_id ),
				'homeUrl'         => get_home_url(),
			),
		);
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

		if ( ! is_admin() || ! get_post_type() ) {
			return $blocks;
		}

		switch ( get_post_type() ) {
			case Event::POST_TYPE:
				break;
			case Venue::POST_TYPE:
				$blocks = array(
					'gatherpress/online-event',
				);
				break;
			default:
				$blocks = array(
					'gatherpress/online-event',
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
	 * @param string  $asset The file name of the asset.
	 * @param ?string $path  (Optional) The absolute path to the asset file
	 *                       or null to use the path based on the default naming scheme.
	 * @return array An array containing asset-related data.
	 */
	protected function get_asset_data( string $asset, ?string $path = null ): array {
		$path = $path ?? $this->path . sprintf( '%s.asset.php', $asset );
		if ( empty( $this->asset_data[ $asset ] ) ) {
			// Loading WordPress asset metadata file that returns an array, not importing a class.
			$this->asset_data[ $asset ] = require_once $path; // NOSONAR.
		}

		return (array) $this->asset_data[ $asset ];
	}

	/**
	 * Register all assets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_variation_assets(): void {
		$variations = Block::get_instance()->get_block_variations();

		foreach ( $variations as $variation ) {
			$this->register_asset( $variation, 'variations/core/' );
		}
	}

	/**
	 * Enqueue all assets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_variation_assets(): void {
		array_map(
			array( $this, 'enqueue_asset' ),
			Block::get_instance()->get_block_variations()
		);
	}

	/**
	 * Register a new script and sets translated strings for the script.

	 * @since 1.0.0
	 *
	 * @param string $folder_name Slug of the block to register scripts and translations for.
	 * @param string $build_dir Name of the folder to register assets from, relative to the plugins root directory.
	 *
	 * @return void
	 */
	protected function register_asset( string $folder_name, string $build_dir = '' ): void {
		$slug     = sprintf( 'gatherpress-%s', $folder_name );
		$folders  = sprintf( '%1$s%2$s', $build_dir, $folder_name );
		$dir      = sprintf( '%1$s%2$s', $this->path, $folders );
		$path_php = sprintf( '%1$s/index.asset.php', $dir );
		$path_css = sprintf( '%1$s/index.css', $dir );
		$url_js   = sprintf( '%s/index.js', $this->build . $folders );
		$url_css  = sprintf( '%s/index.css', $this->build . $folders );

		if ( ! $this->asset_exists( $path_php, $folder_name ) ) {
			return;
		}

		$asset = $this->get_asset_data( $folder_name, $path_php );

		wp_register_script(
			$slug,
			$url_js,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( $slug, 'gatherpress' );

		if ( $this->asset_exists( $path_css, $folder_name, false ) ) {
			wp_register_style(
				$slug,
				$url_css,
				array( 'global-styles' ),
				$asset['version'],
				'screen'
			);
		}
	}

	/**
	 * Enqueue a script and a style with the same name, if registered.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $folder_name Slug of the block to load the frontend scripts for.
	 *
	 * @return void
	 */
	protected function enqueue_asset( string $folder_name ): void {
		$slug = sprintf( 'gatherpress-%s', $folder_name );
		wp_enqueue_script( $slug );

		if ( wp_style_is( $slug, 'registered' ) ) {
			wp_enqueue_style( $slug );
		}
	}

	/**
	 * A better file_exists with built-in error handling.
	 *
	 * @since 1.0.0
	 *
	 * @throws Error Throws error for non-existent file with given path,
	 *               if this is a development environment,
	 *               returns false for all other environments.
	 *
	 * @param  string $path Absolute path to the file to check.
	 * @param  string $name Name of the asset, without file type.
	 * @param  bool   $critical Whether file is mandatory for the plugin to work, defaults to true.
	 *
	 * @return bool
	 */
	protected function asset_exists( string $path, string $name, bool $critical = true ): bool {
		/**
		 * Filters whether an asset file is considered critical.
		 *
		 * This filter allows modification of the critical flag for asset files,
		 * which determines whether missing assets throw an Error in development
		 * environments or silently return false.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $critical Whether file is mandatory for the plugin to work.
		 * @param string $path     Full file path to the asset file.
		 * @param string $name     Name of the asset being loaded.
		 *
		 * @return bool True if asset is critical, false otherwise.
		 */
		$critical = apply_filters( 'gatherpress_asset_critical', $critical, $path, $name );

		if ( ! file_exists( $path ) ) {
			$error_message = sprintf(
				/* Translators: %s Name of a block-asset */
				__(
					// phpcs:ignore Generic.Files.LineLength.TooLong
					'You need to run `npm start` or `npm run build` for the "%1$s" block-asset first. %2$s does not exist.',
					'gatherpress'
				),
				$name,
				$path
			);

			if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) && $critical ) {
				throw new Error( esc_html( $error_message ) );
			} else {
				// Should write to the \error_log( $error_message ); if possible.
				return false;
			}
		}

		return true;
	}
}
