<?php
/**
 * Network settings page for GatherPress multisite.
 *
 * Registers "GatherPress" under Network Admin → Settings with a tabbed UI
 * that mirrors the per-site GatherPress Settings (Events, Venues, Roles,
 * RSVP) plus an additional "Network" tab for the inheritance allowlist.
 *
 * Values set here are saved to the network-wide site option
 * `gatherpress_settings` via `update_site_option()`; subsites with an option
 * in the inherited allowlist resolve `Settings::get()` to that network value.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Network.
 *
 * @since 1.0.0
 */
class Network {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Site option storing the network inheritance config (enabled + inherited list).
	 *
	 * @since 1.0.0
	 */
	const OPTION_NAME = 'gatherpress_network_settings';

	/**
	 * Admin page slug used in the network admin menu.
	 *
	 * @since 1.0.0
	 */
	const PAGE_SLUG = 'gatherpress-network-settings';

	/**
	 * Slug for the special "Network" tab (inheritance allowlist).
	 *
	 * @since 1.0.0
	 */
	const NETWORK_TAB = 'network';

	/**
	 * Nonce + edit action for saving the inheritance allowlist.
	 *
	 * @since 1.0.0
	 */
	const NONCE_ACTION = 'gatherpress_network_settings_save';
	const EDIT_ACTION  = 'gatherpress_network_settings';

	/**
	 * Nonce + edit action for saving the network-level settings values
	 * (the content of the Events/Venues/Roles/RSVP tabs).
	 *
	 * @since 1.0.0
	 */
	const VALUES_NONCE_ACTION = 'gatherpress_network_values_save';
	const VALUES_EDIT_ACTION  = 'gatherpress_network_values';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'network_admin_menu', array( $this, 'register_page' ) );
		add_action( 'network_admin_edit_' . self::EDIT_ACTION, array( $this, 'handle_save' ) );
		add_action( 'network_admin_edit_' . self::VALUES_EDIT_ACTION, array( $this, 'handle_values_save' ) );
		add_action( 'admin_notices', array( $this, 'subsite_inheritance_notice' ) );
		add_action( 'admin_head', array( $this, 'print_inherited_styles' ) );
	}

	/**
	 * Print inline styles that dim inherited fields on subsite settings pages.
	 *
	 * Runs on every admin_head so the styling is applied whether or not the
	 * built SCSS bundle is available.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function print_inherited_styles(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( '' === $page || 0 !== strpos( $page, 'gatherpress_' ) ) {
			return;
		}

		echo '<style>
			.gatherpress-field-inherited { opacity: 0.6; pointer-events: none; }
			.gatherpress-field-inherited__note { font-style: italic; margin-top: 4px; pointer-events: auto; }
		</style>';
	}

	/**
	 * Show a notice on a subsite's GatherPress Settings page when any option
	 * is currently inherited from the network.
	 *
	 * Links to the Network Admin → Settings → GatherPress page so super admins
	 * know where to change values that are locked here.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function subsite_inheritance_notice(): void {
		if ( ! is_multisite() || is_main_site() ) {
			return;
		}

		// Only surface the notice to users who can actually act on the link.
		// Regular site admins get per-field notes instead (no dead-end link).
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( '' === $page || 0 !== strpos( $page, 'gatherpress_' ) ) {
			return;
		}

		$config = self::get_config();

		if ( empty( $config['enabled'] ) || empty( $config['inherited'] ) ) {
			return;
		}

		$settings   = Settings::get_instance();
		$has_locked = false;

		foreach ( (array) $config['inherited'] as $option_key ) {
			if ( $settings->is_option_inherited( (string) $option_key ) ) {
				$has_locked = true;
				break;
			}
		}

		if ( ! $has_locked ) {
			return;
		}

		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_url(
				network_admin_url(
					sprintf( 'settings.php?page=%s', self::PAGE_SLUG )
				)
			),
			esc_html__( 'Network Admin → Settings → GatherPress', 'gatherpress' )
		);

		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			wp_kses(
				sprintf(
					/* translators: %s: link to the network admin GatherPress settings page. */
					__(
						// phpcs:disable Generic.Files.LineLength.TooLong
						'Some GatherPress settings on this site are inherited from the network. Locked fields can only be changed from %s.',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					$link
				),
				array( 'a' => array( 'href' => true ) )
			)
		);
	}

	/**
	 * Register the Network Admin → Settings → GatherPress page and scope the
	 * read filter to only this screen so network values appear in the UI
	 * while the per-site Settings remain untouched.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_page(): void {
		$hook = add_submenu_page(
			'settings.php',
			__( 'GatherPress Network Settings', 'gatherpress' ),
			__( 'GatherPress', 'gatherpress' ),
			'manage_network_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'scope_read_filter' ) );
			add_action( 'load-' . $hook, array( $this, 'maybe_redirect_to_default_tab' ) );
		}
	}

	/**
	 * Redirect bare `?page=gatherpress-network-settings` to the Network tab
	 * so the URL always reflects the active tab (and bookmarks land correctly).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_redirect_to_default_tab(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check on a navigation parameter.
		if ( isset( $_GET['tab'] ) ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => self::NETWORK_TAB,
				),
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}

	/**
	 * Attach the read-routing filter on the network admin page load.
	 *
	 * Ensures `get_option( 'gatherpress_settings' )` returns the network-wide
	 * value while this page renders, so field values show network values,
	 * not the blog option of whichever site is "current" for the admin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function scope_read_filter(): void {
		add_filter( 'pre_option_' . Settings::OPTION_NAME, array( $this, 'route_read_to_site_option' ) );
	}

	/**
	 * Short-circuit get_option to return the network-wide site option.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $pre The pre-filter value (false when not short-circuited).
	 * @return mixed
	 */
	public function route_read_to_site_option( $pre ) {
		unset( $pre );

		return get_site_option( Settings::OPTION_NAME, array() );
	}

	/**
	 * Render the network admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gatherpress' ) );
		}

		$sub_pages   = $this->get_network_sub_pages();
		$current_tab = $this->get_current_tab( $sub_pages );

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/network-page.php', GATHERPRESS_CORE_PATH ),
			array(
				'sub_pages'   => $sub_pages,
				'current_tab' => $current_tab,
				'config'      => self::get_config(),
			),
			true
		);
	}

	/**
	 * Sub-pages shown at network admin: existing sub-pages minus Tools
	 * (import/export is blog-scoped), plus the special "Network" tab.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function get_network_sub_pages(): array {
		$sub_pages = Settings::get_instance()->get_sub_pages();

		unset( $sub_pages['tools'] );

		$sub_pages[ self::NETWORK_TAB ] = array(
			'name'     => __( 'Network', 'gatherpress' ),
			'priority' => PHP_INT_MIN, // Before Events.
			'sections' => array(),
		);

		uasort( $sub_pages, array( Settings::get_instance(), 'sort_sub_pages_by_priority' ) );

		return $sub_pages;
	}

	/**
	 * Resolve the current tab from the `tab` query arg, falling back to the
	 * first sub-page when missing or unknown.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sub_pages Available sub-pages keyed by slug.
	 * @return string
	 */
	protected function get_current_tab( array $sub_pages ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection is a navigation parameter.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( '' !== $tab && isset( $sub_pages[ $tab ] ) ) {
			return $tab;
		}

		return (string) array_key_first( $sub_pages );
	}

	/**
	 * Handle save of the inheritance allowlist (Network tab).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'gatherpress' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validated above.
		$raw = isset( $_POST[ self::OPTION_NAME ] ) && is_array( $_POST[ self::OPTION_NAME ] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST[ self::OPTION_NAME ] )
			: array();

		update_site_option( self::OPTION_NAME, $this->sanitize( $raw ) );

		$this->redirect_to_tab( self::NETWORK_TAB );
	}

	/**
	 * Handle save of the network-level settings values (other tabs).
	 *
	 * Routes reads to the site option while the existing Settings sanitize
	 * closure runs (so it merges with the current network values rather than
	 * the main site's blog option), then persists the sanitized result to
	 * the site option.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_values_save(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'gatherpress' ) );
		}

		check_admin_referer( self::VALUES_NONCE_ACTION );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validated above.
		$raw = isset( $_POST[ Settings::OPTION_NAME ] ) && is_array( $_POST[ Settings::OPTION_NAME ] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing
			? wp_unslash( $_POST[ Settings::OPTION_NAME ] )
			: array();

		$submitted = sanitize_key(
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validated above.
			isset( $_POST['gatherpress_tab'] ) ? wp_unslash( $_POST['gatherpress_tab'] ) : ''
		);

		$settings       = Settings::get_instance();
		$sub_pages      = $settings->get_sub_pages();
		$field_type_map = $this->build_field_type_map( $sub_pages );

		add_filter( 'pre_option_' . Settings::OPTION_NAME, array( $this, 'route_read_to_site_option' ) );
		$sanitize  = $settings->sanitize_page_settings( $field_type_map );
		$sanitized = $sanitize( $raw );
		remove_filter( 'pre_option_' . Settings::OPTION_NAME, array( $this, 'route_read_to_site_option' ) );

		update_site_option( Settings::OPTION_NAME, $sanitized );

		$available = $this->get_network_sub_pages();
		$tab       = isset( $available[ $submitted ] ) ? $submitted : (string) array_key_first( $available );

		$this->redirect_to_tab( $tab );
	}

	/**
	 * Flat map of option_key => field_type, built from the Settings sub-pages.
	 *
	 * Mirrors `Settings::build_field_type_map()` (protected there) so we can
	 * sanitize POSTs in our own save handler without exposing that method.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sub_pages Sub-pages from Settings::get_sub_pages().
	 * @return array<string, string>
	 */
	protected function build_field_type_map( array $sub_pages ): array {
		$map = array();

		foreach ( $sub_pages as $sub_page_settings ) {
			if ( empty( $sub_page_settings['sections'] ) ) {
				continue;
			}

			foreach ( (array) $sub_page_settings['sections'] as $section_settings ) {
				if ( empty( $section_settings['options'] ) ) {
					continue;
				}

				foreach ( (array) $section_settings['options'] as $option => $option_settings ) {
					$map[ $option ] = $option_settings['field']['type'] ?? 'text';
				}
			}
		}

		return $map;
	}

	/**
	 * Redirect back to the network admin page on the given tab with a
	 * success flag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tab The tab slug to return to.
	 * @return void
	 */
	protected function redirect_to_tab( string $tab ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'tab'     => $tab,
					'updated' => 'true',
				),
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}

	/**
	 * Sanitize the submitted inheritance config.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Raw input array.
	 * @return array
	 */
	public function sanitize( $input ): array {
		$input     = is_array( $input ) ? $input : array();
		$inherited = isset( $input['inherited'] ) && is_array( $input['inherited'] )
			? array_values(
				array_unique(
					array_filter(
						array_map( 'sanitize_key', $input['inherited'] ),
						static function ( string $key ): bool {
							return '' !== $key;
						}
					)
				)
			)
			: array();

		return array(
			'enabled'   => ! empty( $input['enabled'] ),
			'inherited' => $inherited,
		);
	}

	/**
	 * Current inheritance config, merged with defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array Config with 'enabled' (bool) and 'inherited' (string[]).
	 */
	public static function get_config(): array {
		$defaults = self::get_default_config();

		if ( ! is_multisite() ) {
			return $defaults;
		}

		$stored = get_site_option( self::OPTION_NAME, array() );

		if ( ! is_array( $stored ) ) {
			return $defaults;
		}

		return array_merge( $defaults, $stored );
	}

	/**
	 * Default inheritance config.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function get_default_config(): array {
		return array(
			'enabled'   => false,
			'inherited' => array(),
		);
	}
}
