<?php
/**
 * Class is responsible for loading and managing static assets like stylesheets and JavaScript files.
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
 * It also provides frontend interactivity state via the WordPress Interactivity API.
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
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'enqueue_block_assets', array( $this, 'block_enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_variation_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_aql_integration' ) );
		add_action( 'init', array( $this, 'register_variation_assets' ) );
		add_action( 'wp_head', array( $this, 'add_interactivity_state' ) );
		// Set priority to 11 to not conflict with media modal.
		add_action( 'admin_footer', array( $this, 'event_communication_modal' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_timezone_shim' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_timezone_shim' ) );

		add_filter( 'render_block', array( $this, 'maybe_enqueue_styles' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'maybe_enqueue_tooltip_assets' ) );
	}

	/**
	 * Patch `wp.date`'s timezone when WordPress hands Moment Timezone a bogus
	 * `UTC+0` (or `UTC-0`) string.
	 *
	 * On a fresh WordPress install the Settings → General timezone is
	 * unset (no `timezone_string`, `gmt_offset` of 0), so WP core emits
	 * `wp.date.setSettings({ timezone: { string: 'UTC+0' } })`. That is
	 * not a valid IANA zone, which triggers a stream of
	 * "Moment Timezone has no data for UTC+0" console warnings from the
	 * block editor's panels and any plugin using `@wordpress/date`.
	 *
	 * We can't reach into WP core, but we can append an inline script
	 * after its `setSettings` call to re-call it with a valid zone. We
	 * only normalize the zero-offset case; non-zero UTC offsets are
	 * rarer and surface a different warning that users fix by choosing
	 * an IANA zone in Settings → General.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_timezone_shim(): void {
		if ( ! wp_script_is( 'wp-date', 'registered' ) ) {
			return;
		}

		$script_path = GATHERPRESS_CORE_PATH . '/includes/templates/admin/timezone-shim.js';

		if ( ! file_exists( $script_path ) ) {
			return; // @codeCoverageIgnore -- template ships with the plugin.
		}

		wp_enqueue_script( 'wp-date' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a plugin-local static file.
		wp_add_inline_script( 'wp-date', file_get_contents( $script_path ), 'after' );
	}

	/**
	 * Set initial interactivity state for frontend blocks.
	 *
	 * Provides the REST API URL to the gatherpress interactivity store
	 * so that frontend view scripts can make API requests without
	 * relying on window globals.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_interactivity_state(): void {
		$event_post_types = get_post_types_by_support( 'gatherpress-event-date' );

		if ( ! is_singular( $event_post_types ) ) {
			return;
		}

		$event_rest_api_slug = sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE );

		wp_interactivity_state(
			'gatherpress',
			array(
				'eventApiUrl' => home_url( 'wp-json/' . $event_rest_api_slug ),
			)
		);
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

		wp_enqueue_script(
			'gatherpress-tooltip-view',
			$this->build . 'tooltip_view.js',
			array(),
			$script_asset['version'],
			true
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
		if ( post_type_supports( (string) get_post_type(), 'gatherpress-event-date' ) ) {
			echo '<div id="gatherpress-event-communication-modal"></div>';
		}
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
	 * Conditionally enqueue the Advanced Query Loop integration script.
	 *
	 * Only enqueues when the AQL plugin is active and its script is registered.
	 * Adds AQL's script handle as a dependency so GatherPress loads after AQL.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_aql_integration(): void {
		// Only load when Advanced Query Loop is active.
		if ( ! wp_script_is( 'advanced-query-loop', 'registered' ) ) {
			return;
		}

		$asset_path = $this->path . 'integrations/aql/index.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = include_once $asset_path;

		// Add AQL as a dependency so our script loads after theirs.
		$dependencies   = $asset['dependencies'] ?? array();
		$dependencies[] = 'advanced-query-loop';

		wp_enqueue_script(
			'gatherpress-aql-integration',
			$this->build . 'integrations/aql/index.js',
			$dependencies,
			$asset['version'] ?? false,
			true
		);

		wp_set_script_translations( 'gatherpress-aql-integration', 'gatherpress' );
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
