<?php
/**
 * Class is responsible for loading and managing static assets like stylesheets and JavaScript files.
 *
 * @package GatherPress\Core
 * @since 0.27.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Error;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Assets.
 *
 * This class handles the loading and management of static assets, including stylesheets and JavaScript files.
 * It also provides frontend interactivity state via the WordPress Interactivity API.
 *
 * @since 0.27.0
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
	 * @since 0.27.0
	 * @var array
	 */
	protected array $asset_data = array();

	/**
	 * The URL to the 'build' directory.
	 *
	 * This property holds the URL to the 'build' directory, which is used to reference built assets
	 * such as stylesheets and JavaScript files.
	 *
	 * @since 0.27.0
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
	 * @since 0.27.0
	 * @var string
	 */
	protected string $path = GATHERPRESS_CORE_PATH . '/build/';

	/**
	 * Cached list of block variation folder names from `build/variations/core/`.
	 *
	 * @since 0.34.0
	 * @var string[]
	 */
	protected array $block_variation_names = array();

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.27.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.27.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'enqueue_block_assets', array( $this, 'register_block_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_enqueue_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_variation_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_aql_integration' ) );
		add_action( 'init', array( $this, 'register_variation_assets' ) );
		add_action( 'wp_head', array( $this, 'add_interactivity_state' ) );
		// Set priority to 11 to not conflict with media modal.
		add_action( 'admin_footer', array( $this, 'event_communication_modal' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_timezone_shim' ) );
		// Late priority (100) so that on the frontend this runs after any
		// block/script that might enqueue wp-date for this request — see
		// the is_admin()/enqueued-vs-registered branch in the callback.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_timezone_shim' ), 100 );

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
	 * after its `setSettings` call to re-call it with a valid zone.
	 *
	 * In the admin, `wp-date` is registered on essentially every screen
	 * (block editor panels use it), so we enqueue it ourselves and patch
	 * it unconditionally there. On the frontend, `wp-date` is *always*
	 * registered by WordPress core regardless of whether the current
	 * page uses it — so the same "is it registered" check that works in
	 * the admin would force `wp-date` (and its `moment` dependency) onto
	 * every single frontend pageview, including pages with no
	 * GatherPress block at all. On the frontend we instead check whether
	 * `wp-date` has actually been *enqueued* by something else for this
	 * request, and only patch it in that case. The callback is hooked at
	 * a late priority (100) on `wp_enqueue_scripts` so that check reflects
	 * enqueues made by GatherPress's own blocks and other plugins/themes
	 * earlier in the request.
	 *
	 * We only normalize the zero-offset case; non-zero UTC offsets are
	 * rarer and surface a different warning that users fix by choosing
	 * an IANA zone in Settings → General.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function enqueue_timezone_shim(): void {
		if ( is_admin() ) {
			if ( ! wp_script_is( 'wp-date', 'registered' ) ) {
				return;
			}

			wp_enqueue_script( 'wp-date' );
		} elseif ( ! wp_script_is( 'wp-date', 'enqueued' ) ) {
			// Frontend: never force-load wp-date (and moment.js) on pages
			// that don't otherwise need it. Only patch the timezone
			// setting if another block/script already enqueued wp-date.
			return;
		}

		$script_path = GATHERPRESS_CORE_PATH . '/includes/templates/admin/timezone-shim.js';

		// The shim file ships with the plugin; this guard is defensive in case
		// someone strips template assets from a distribution.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
		// @codeCoverageIgnoreStart
		if ( ! file_exists( $script_path ) ) {
			return;
		}
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
		// @codeCoverageIgnoreEnd

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a plugin-local static file.
		wp_add_inline_script( 'wp-date', file_get_contents( $script_path ), 'after' );
	}

	/**
	 * Set initial interactivity state for frontend blocks.
	 *
	 * Provides the REST API URL to the gatherpress interactivity store so
	 * frontend view scripts (RSVP nonce/status requests) can build API URLs
	 * without relying on window globals.
	 *
	 * The state is set on every front-end view rather than only on singular
	 * event pages: RSVP and other interactive blocks also render in event
	 * archives and Query Loops, where the previous `is_singular()` gate left
	 * `eventApiUrl` undefined — the view scripts then requested
	 * `/event/undefined/nonce` (404) and every RSVP from an archive failed
	 * (#1752). The value is a static site URL, so emitting it broadly is
	 * cheap; the interactivity runtime only serializes it when a gatherpress
	 * interactive block is actually present on the page.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function add_interactivity_state(): void {
		$event_rest_api_slug = sprintf( '%s/event', GATHERPRESS_REST_NAMESPACE );

		wp_interactivity_state(
			'gatherpress',
			array(
				'eventApiUrl' => home_url( 'wp-json/' . $event_rest_api_slug ),
			)
		);
	}

	/**
	 * Register the shared utility stylesheet and enqueue it in the block editor.
	 *
	 * Hooked on `enqueue_block_assets`, which fires in two contexts with
	 * different responsibilities:
	 *
	 * - Frontend: registers the `gatherpress-utility-style` handle so other
	 *   code paths can enqueue it by name. The actual frontend enqueue is
	 *   delegated to `maybe_enqueue_styles()` on the `render_block` filter,
	 *   which only fires the enqueue when a `gatherpress/*` block is being
	 *   rendered — so frontends that don't use a gatherpress block don't
	 *   load the CSS.
	 *
	 * - Block editor: also enqueues unconditionally so the stylesheet lands
	 *   inside the editor canvas iframe. `enqueue_block_assets` is the
	 *   documented hook for iframe-bound styles; `enqueue_block_editor_assets`
	 *   only reaches the wrapper UI, and a late, `render_block`-driven enqueue
	 *   during dynamic-block rendering trips the "stylesheet was added to the
	 *   iframe incorrectly" warning in newer WordPress (issue #1645).
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_block_assets(): void {
		$asset = $this->get_asset_data( 'utility_style' );

		wp_register_style(
			'gatherpress-utility-style',
			$this->build . 'utility_style.css',
			$asset['dependencies'],
			$asset['version']
		);

		if ( is_admin() ) {
			wp_enqueue_style( 'gatherpress-utility-style' );
		}
	}

	/**
	 * Conditionally enqueue utility styles if GatherPress blocks are rendered.
	 *
	 * @since 0.33.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block settings.
	 *
	 * @return string The block content.
	 */
	public function maybe_enqueue_styles( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) ) {
			return $block_content;
		}

		/**
		 * Filters additional block-name prefixes whose blocks should
		 * auto-enqueue the GatherPress utility stylesheet.
		 *
		 * Companion plugins and themes can use this filter to share the
		 * utility CSS with their own blocks (e.g. `gatherpress-awesome/`).
		 * The `gatherpress/` prefix is appended after this filter runs and
		 * cannot be removed through it.
		 *
		 * @since 0.27.0
		 *
		 * @param string[] $prefixes Additional block-name prefixes to match.
		 */
		$prefixes   = (array) apply_filters( 'gatherpress_asset_utility_style_block_prefixes', array() );
		$prefixes[] = 'gatherpress/';

		foreach ( $prefixes as $prefix ) {
			if ( str_starts_with( $block['blockName'], (string) $prefix ) ) {
				wp_enqueue_style( 'gatherpress-utility-style' );
				break;
			}
		}

		return $block_content;
	}

	/**
	 * Conditionally enqueue tooltip assets if tooltip markup is found in block content.
	 *
	 * @since 0.34.0
	 *
	 * @param string $block_content The block content.
	 *
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
	 * @since 0.34.0
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
	 * @since 0.27.0
	 *
	 * @param string $hook The name of the current admin page.
	 *
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

			// Shared utility classes (`gatherpress--is-hidden`, etc.) used by
			// settings UI like the `show_if` row visibility toggle. The handle
			// is registered on the frontend in `register_block_assets`; re-
			// register here so it's available on admin settings pages too.
			$utility_asset = $this->get_asset_data( 'utility_style' );

			wp_register_style(
				'gatherpress-utility-style',
				$this->build . 'utility_style.css',
				$utility_asset['dependencies'],
				$utility_asset['version']
			);
			wp_enqueue_style( 'gatherpress-utility-style' );

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
	 * @since 0.27.0
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

		// `gatherpress-utility-style` is enqueued from `register_block_assets`
		// (on `enqueue_block_assets`) so it reaches the editor canvas iframe.
		// Enqueuing it here on `enqueue_block_editor_assets` only loaded it in
		// the wrapper UI and triggered an "added to the iframe incorrectly"
		// warning in newer WordPress.

		wp_set_script_translations( 'gatherpress-editor', 'gatherpress' );
	}

	/**
	 * Adds markup to the event edit page for storing the communication modal.
	 *
	 * This method inserts HTML markup on the event edit page specifically for storing the communication modal.
	 * It is responsible for creating a designated container for the modal's content.
	 *
	 * @since 0.27.0
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
	 * Results are memoized in the `$this->asset_data` cache so each file is read at most once per request.
	 * Plain `require` is used rather than `require_once`: `require_once` returns `true` (not the array)
	 * if the same file was already loaded elsewhere in the request, and `(array) true` would corrupt the
	 * `dependencies` / `version` lookups. A missing file yields an empty array rather than a fatal.
	 *
	 * @since 0.27.0
	 *
	 * @param string  $asset The file name of the asset.
	 * @param ?string $path  (Optional) The absolute path to the asset file
	 *                       or null to use the path based on the default naming scheme.
	 * @return array An array containing asset-related data.
	 */
	protected function get_asset_data( string $asset, ?string $path = null ): array {
		$path = $path ?? $this->path . sprintf( '%s.asset.php', $asset );
		if ( empty( $this->asset_data[ $asset ] ) ) {
			// Loading a WordPress asset metadata file that returns an array, not importing a class.
			$this->asset_data[ $asset ] = file_exists( $path ) ? require $path : array();
		}

		return (array) $this->asset_data[ $asset ];
	}

	/**
	 * Get a list of subfolder names from the /build/variations/core/ directory.
	 *
	 * @since 0.34.0
	 *
	 * @return string[] List of block-variations foldernames.
	 */
	public function get_block_variations(): array {
		$variations_directory = sprintf( '%1$s/build/variations/core/', GATHERPRESS_CORE_PATH );

		if ( ! file_exists( $variations_directory ) ) {
			return array();
		}

		if ( empty( $this->block_variation_names ) ) {
			$this->block_variation_names = array_values(
				array_diff(
					scandir( $variations_directory ),
					array( '..', '.' )
				)
			);
		}

		return array_filter( $this->block_variation_names );
	}

	/**
	 * Register all assets.
	 *
	 * @since 0.33.0
	 *
	 * @return void
	 */
	public function register_variation_assets(): void {
		foreach ( $this->get_block_variations() as $variation ) {
			$this->register_asset( $variation, 'variations/core/' );
		}
	}

	/**
	 * Enqueue all assets.
	 *
	 * @since 0.33.0
	 *
	 * @return void
	 */
	public function enqueue_variation_assets(): void {
		array_map(
			array( $this, 'enqueue_asset' ),
			$this->get_block_variations()
		);
	}

	/**
	 * Conditionally enqueue the Advanced Query Loop integration script.
	 *
	 * Only enqueues when the AQL plugin is active and its script is registered.
	 * Adds AQL's script handle as a dependency so GatherPress loads after AQL.
	 *
	 * @since 0.34.0
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

		// Plain include, not include_once: a repeat include_once would return
		// `true` instead of the asset array if the file was already loaded
		// (existence is already guaranteed by the file_exists guard above).
		$asset = include $asset_path;

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
	 *
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
		 * @since 0.27.0
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
