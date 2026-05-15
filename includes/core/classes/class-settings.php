<?php
/**
 * Provides settings management for the GatherPress plugin.
 *
 * This class is responsible for handling various plugin settings and options,
 * including the administration menu, settings registration, and rendering.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Event;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Settings.
 *
 * This class handles the management of plugin settings, including options
 * related to event display, roles, and credits.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	const PARENT_SLUG = 'edit.php?post_type=gatherpress_event';
	const OPTION_NAME = 'gatherpress_settings';

	/**
	 * Default Leaflet tile layer URL.
	 *
	 * CartoDB "Positron" raster tiles are OSM-derived but served from a CDN with
	 * a per-domain free tier (~75k views/month) whose terms permit distribution in
	 * plugins. The public `tile.openstreetmap.org` endpoint, by contrast,
	 * explicitly prohibits third-party app/plugin use — sites that ship the
	 * plugin widely were intermittently blocked with a "Referer required" error.
	 *
	 * Override with the `gatherpress_interactive_map_tile_url` filter (e.g. for a
	 * self-hosted tile server or a provider that requires an API key).
	 *
	 * @since 1.0.0
	 */
	const MAP_TILE_URL = 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png';

	/**
	 * URL used in the default map attribution credit to OpenStreetMap.
	 *
	 * @since 1.0.0
	 */
	const MAP_TILE_ATTRIBUTION_OSM_URL = 'https://www.openstreetmap.org/copyright';

	/**
	 * URL used in the default map attribution credit to CARTO.
	 *
	 * @since 1.0.0
	 */
	const MAP_TILE_ATTRIBUTION_CARTO_URL = 'https://carto.com/attributions';

	/**
	 * The current page being accessed within the settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $current_page = '';

	/**
	 * The main sub-page identifier used for the settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $main_sub_page = '';

	/**
	 * Cached flat map of option keys to their default values.
	 *
	 * @since 1.0.0
	 * @var array|null
	 */
	protected ?array $defaults_cache = null;

	/**
	 * Constructor for the Settings class.
	 *
	 * Initializes the settings object, sets the current page, and sets up hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->set_current_page();
		$this->instantiate_classes();
		$this->setup_hooks();
	}

	/**
	 * Instantiate each settings-page subclass.
	 *
	 * Method name mirrors `Setup::instantiate_classes()` so the pattern
	 * is consistent top-to-bottom: every class that owns a set of
	 * singletons calls them `instantiate_classes()`. Keeps
	 * `Setup::instantiate_classes()` slim — a new settings page lands as
	 * a single added line here rather than edits to Setup. Each subclass
	 * is a singleton, so repeat calls are safe.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Settings\Credits::get_instance();
		Settings\Events::get_instance();
		Settings\Network::get_instance();
		Settings\Roles::get_instance();
		Settings\Rsvp_Settings::get_instance();
		Settings\Tools::get_instance();
		Settings\Venues::get_instance();
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
		add_action( 'init', array( $this, 'set_main_sub_page' ) );
		add_action( 'admin_menu', array( $this, 'options_page' ) );
		add_action( 'admin_head', array( $this, 'remove_sub_options' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'gatherpress_settings_section', array( $this, 'render_settings_form' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'maybe_flush_rewrite_rules' ), 10, 2 );

		add_filter( 'submenu_file', array( $this, 'select_menu' ) );
		add_filter( 'block_editor_settings_all', array( $this, 'add_editor_settings' ) );
	}

	/**
	 * Expose plugin settings and config to the block editor.
	 *
	 * Adds two namespaced keys under settings['gatherpress']:
	 * - 'settings': User-configurable values from the GatherPress Settings API.
	 * - 'config':   Infrastructure values (URLs, timezone data) that are not user-configurable.
	 *
	 * Editor JS accesses these via:
	 * - getFromSettings( key ) for Settings API values.
	 * - getFromConfig( key ) for infrastructure values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The block editor settings array.
	 * @return array The modified block editor settings array.
	 */
	public function add_editor_settings( array $settings ): array {
		if ( ! isset( $settings['gatherpress'] ) ) {
			$settings['gatherpress'] = array();
		}

		// User-configurable settings from the Settings API.
		$gatherpress_settings = array();

		foreach ( array_keys( $this->get_defaults_map() ) as $option ) {
			$camel_key                          = Utility::snake_to_camel( $option );
			$gatherpress_settings[ $camel_key ] = $this->get( $option );
		}

		$settings['gatherpress']['settings'] = $gatherpress_settings;

		// Infrastructure config values (not user-configurable).
		$settings['gatherpress']['config'] = array(
			'timezoneChoices'       => Utility::timezone_choices(),
			'siteTimezone'          => Utility::get_system_timezone(),
			'pluginUrl'             => GATHERPRESS_CORE_URL,
			'homeUrl'               => get_home_url(),
			'mapTileUrl'            => self::get_map_tile_url(),
			'mapTileAttribution'    => self::get_map_tile_attribution(),
			'venuesMapsSettingsUrl' => admin_url(
				sprintf(
					'edit.php?post_type=%s&page=%s',
					Event::POST_TYPE,
					sprintf( 'gatherpress_event_page_%s', Utility::prefix_key( 'venues' ) )
				)
			),
		);

		return $settings;
	}

	/**
	 * Returns the map tile layer URL, allowing sites to override the default.
	 *
	 * @since 1.0.0
	 *
	 * @return string Leaflet-compatible tile URL template.
	 */
	public static function get_map_tile_url(): string {
		/**
		 * Filters the Leaflet tile layer URL used by the venue map.
		 *
		 * @since 1.0.0
		 *
		 * @param string $url Default tile URL template (CartoDB Positron).
		 */
		$filtered = (string) apply_filters( 'gatherpress_interactive_map_tile_url', self::MAP_TILE_URL );

		return '' !== $filtered ? $filtered : self::MAP_TILE_URL;
	}

	/**
	 * Returns the map attribution string, allowing sites to override the default.
	 *
	 * @since 1.0.0
	 *
	 * @return string HTML attribution credit shown on the map.
	 */
	public static function get_map_tile_attribution(): string {
		$default = sprintf(
			/* translators: 1: OpenStreetMap credit link, 2: CARTO credit link. */
			__( '© %1$s contributors © %2$s', 'gatherpress' ),
			sprintf(
				'<a href="%s">OpenStreetMap</a>',
				esc_url( self::MAP_TILE_ATTRIBUTION_OSM_URL )
			),
			sprintf(
				'<a href="%s">CARTO</a>',
				esc_url( self::MAP_TILE_ATTRIBUTION_CARTO_URL )
			)
		);

		/**
		 * Filters the attribution HTML rendered with the venue map.
		 *
		 * Override alongside `gatherpress_interactive_map_tile_url` when switching
		 * providers so the correct credits are displayed.
		 *
		 * @since 1.0.0
		 *
		 * @param string $attribution Default attribution HTML.
		 */
		$filtered = (string) apply_filters( 'gatherpress_interactive_map_tile_attribution', $default );

		return '' !== $filtered ? $filtered : $default;
	}

	/**
	 * Set the main sub-page identifier.
	 *
	 * This method sets the main sub-page identifier based on the first sub-page key.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_main_sub_page(): void {
		$sub_pages           = $this->get_sub_pages();
		$this->main_sub_page = array_key_first( $sub_pages ) ?? '';
	}

	/**
	 * Get the main sub-page slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string The main sub-page slug.
	 */
	public function get_main_sub_page(): string {
		return $this->main_sub_page;
	}

	/**
	 * Helper method to set the current page based on the 'page' query parameter.
	 *
	 * This method retrieves and sanitizes the 'page' query parameter from the request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_current_page(): void {
		$page = Utility::get_http_input( INPUT_GET, 'page' );

		if ( ! empty( $page ) ) {
			$this->current_page = $page;
		}
	}

	/**
	 * Render a settings form section using a template file.
	 *
	 * This method is responsible for rendering a settings form section on a settings page
	 * using a template file. It accepts the current settings page identifier as a parameter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $page The slug of the current settings page.
	 */
	public function render_settings_form( string $page ): void {
		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/settings-form.php', GATHERPRESS_CORE_PATH ),
			array( 'page' => $page ),
			true
		);
	}

	/**
	 * Setup and register submenu pages under the GatherPress settings menu.
	 *
	 * This method adds submenu pages for various sections of GatherPress settings,
	 * allowing users to configure different aspects of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function options_page(): void {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Settings', 'gatherpress' ),
			__( 'Settings', 'gatherpress' ),
			'manage_options',
			Utility::prefix_key( $this->main_sub_page ),
			array( $this, 'settings_page' ),
			6
		);

		$sub_pages = $this->get_sub_pages();

		foreach ( $sub_pages as $sub_page => $setting ) {
			if ( $this->main_sub_page === $sub_page ) {
				continue;
			}

			$page = Utility::prefix_key( $sub_page );

			add_submenu_page(
				self::PARENT_SLUG,
				$setting['name'],
				$setting['name'],
				'manage_options',
				$page,
				array( $this, 'settings_page' )
			);
		}
	}

	/**
	 * Remove submenu pages from the GatherPress Settings menu.
	 *
	 * This method removes submenu pages that were added under the GatherPress Settings menu.
	 * It ensures that only the necessary submenu pages are displayed to users based on their
	 * role and permissions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function remove_sub_options(): void {
		$sub_pages = $this->get_sub_pages();

		foreach ( array_keys( $sub_pages ) as $sub_page ) {
			if ( $this->main_sub_page === $sub_page ) {
				continue;
			}

			remove_submenu_page( self::PARENT_SLUG, Utility::prefix_key( $sub_page ) );
		}
	}

	/**
	 * Register the settings pages and fields.
	 *
	 * This method is responsible for registering the main plugin settings as well as any additional
	 * settings sections and fields for sub-pages. It sets up the necessary WordPress settings and
	 * fields, including their callbacks, for each defined section and option.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_settings(): void {
		$sub_pages      = $this->get_sub_pages();
		$field_type_map = $this->build_field_type_map( $sub_pages );

		// phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic
		register_setting(
			self::OPTION_NAME,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => $this->sanitize_page_settings( $field_type_map ),
			)
		);

		foreach ( $sub_pages as $sub_page => $sub_page_settings ) {
			if ( isset( $sub_page_settings['sections'] ) ) {
				$this->register_sub_page_sections( (string) $sub_page, (array) $sub_page_settings['sections'] );
			}
		}
	}

	/**
	 * Register all sections and their option fields for a single sub-page.
	 *
	 * Extracted from `register_settings()` so that triple-nested loop body
	 * stays under SonarCloud's cognitive-complexity threshold; each section
	 * gets a `do_settings_section`-rendered description, and each option
	 * inside it becomes an `add_settings_field` callback that defers to
	 * `render_field()`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page Sub-page slug used to scope WP's settings API.
	 * @param array  $sections Sections array from the sub-page settings.
	 * @return void
	 */
	protected function register_sub_page_sections( string $sub_page, array $sections ): void {
		foreach ( $sections as $section => $section_settings ) {
			add_settings_section(
				(string) $section,
				$section_settings['name'],
				static function () use ( $section_settings ): void {
					if ( ! empty( $section_settings['description'] ) ) {
						echo '<p class="description">'
							. wp_kses_post( $section_settings['description'] ) . '</p>';
					}
				},
				Utility::prefix_key( $sub_page )
			);

			if ( ! isset( $section_settings['options'] ) ) {
				continue;
			}

			foreach ( (array) $section_settings['options'] as $option => $option_settings ) {
				$option_settings['callback'] = function () use ( $option, $option_settings ): void {
					$this->render_field( (string) $option, $option_settings );
				};

				add_settings_field(
					(string) $option,
					$option_settings['labels']['name'],
					$option_settings['callback'],
					Utility::prefix_key( $sub_page ),
					(string) $section
				);
			}
		}
	}

	/**
	 * Build a flat map of option keys to their field types.
	 *
	 * Iterates all sub-pages, sections, and options to produce
	 * a flat associative array of option_key => field_type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sub_pages The sub-pages array from get_sub_pages().
	 * @return array Flat map of option_key => field_type.
	 */
	protected function build_field_type_map( array $sub_pages ): array {
		$map        = array();
		$duplicates = array();

		// Use null-coalescing on each level rather than `if (! isset) continue;`
		// guards — same control flow, fewer branches for cognitive complexity.
		foreach ( $sub_pages as $sub_page_settings ) {
			foreach ( (array) ( $sub_page_settings['sections'] ?? array() ) as $section_settings ) {
				foreach ( (array) ( $section_settings['options'] ?? array() ) as $option => $option_settings ) {
					if ( isset( $map[ $option ] ) && ! in_array( $option, $duplicates, true ) ) {
						$duplicates[] = $option;
					}

					$map[ $option ] = $option_settings['field']['type'] ?? 'text';
				}
			}
		}

		if ( ! empty( $duplicates ) ) {
			add_action(
				'admin_notices',
				static function () use ( $duplicates ): void {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: Comma-separated list of duplicate option keys. */
								__(
									'GatherPress: Duplicate settings keys found: %s. Each key must be unique.',
									'gatherpress'
								),
								implode( ', ', $duplicates )
							)
						)
					);
				}
			);
		}

		return $map;
	}

	/**
	 * Creates a sanitization callback function for page settings.
	 *
	 * Generates a closure that sanitizes input values based on their defined field types
	 * using a flat field type map. Merges sanitized input with existing saved values to
	 * preserve settings from other tabs. Handles various input types including checkboxes,
	 * numbers, autocomplete fields, text fields, and select dropdowns.
	 *
	 * Values that equal their configured default are stripped from the merged
	 * result to keep the stored option lean. This means the option cannot
	 * represent "explicitly set to the default" vs "unset" — both collapse
	 * to the same state. Consumers rely on `get_flat_default()` as the
	 * authoritative source of defaults in both read paths.
	 *
	 * @param array  $field_type_map Flat map of option_key => field_type.
	 * @param string $scope          Storage scope: 'blog' (default) or 'network'.
	 *                               Determines which option store the closure
	 *                               reads from when merging with existing values.
	 * @return callable A callback function that sanitizes input based on field types.
	 */
	public function sanitize_page_settings( array $field_type_map, string $scope = 'blog' ): callable {
		return function ( $input ) use ( $field_type_map, $scope ): array {
			$sanitized = array();

			foreach ( $input as $key => $value ) {
				$type = $field_type_map[ $key ] ?? 'text';

				switch ( $type ) {
					case 'checkbox':
						$sanitized[ $key ] = (bool) $value;
						break;
					case 'number':
						// Preserve empty submissions as '' instead of
						// coercing to 0 via intval — so a field that
						// accepts empty (e.g. Width/Height "Auto") can
						// round-trip blank without silently saving 0.
						$sanitized[ $key ] = ( '' === $value || null === $value )
							? ''
							: intval( $value );
						break;
					case 'autocomplete':
						$sanitized[ $key ] = $this->sanitize_autocomplete( $value );
						break;
					case 'password':
					case 'text':
					case 'select':
					default:
						$sanitized[ $key ] = sanitize_text_field( (string) $value );
						break;
				}
			}

			// Merge with existing values to preserve settings from other tabs.
			$existing = $this->read_stored_options( $scope );
			$merged   = array_merge( $existing, $sanitized );

			// Remove values that match their defaults to keep the option lean.
			foreach ( $merged as $key => $value ) {
				$default = $this->get_flat_default( $key );

				if ( $value === $default ) {
					unset( $merged[ $key ] );
				}
			}

			return $merged;
		};
	}

	/**
	 * Sanitizes JSON data from autocomplete fields.
	 *
	 * Takes a JSON string representation of autocomplete data and ensures all values
	 * are properly sanitized. The function validates the JSON structure, sanitizes
	 * each field with appropriate WordPress sanitization functions, and returns the
	 * sanitized data as a JSON string.
	 *
	 * @param string $json_string The JSON string to sanitize.
	 * @return string Sanitized JSON string or empty array '[]' if invalid.
	 *
	 * @since 1.0.0
	 */
	public function sanitize_autocomplete( string $json_string ): string {
		// Decode.
		$data = json_decode( $json_string, true );

		// Check if valid JSON.
		if ( ! is_array( $data ) ) {
			return '[]';
		}

		// Sanitize each item.
		$sanitized = array();

		foreach ( $data as $item ) {
			$clean_item = array();

			// Sanitize each field appropriately.
			if ( isset( $item['id'] ) ) {
				$clean_item['id'] = absint( $item['id'] );
			}

			if ( isset( $item['slug'] ) ) {
				$clean_item['slug'] = sanitize_key( $item['slug'] );
			}

			if ( isset( $item['value'] ) ) {
				$clean_item['value'] = sanitize_text_field( $item['value'] );
			}

			$sanitized[] = $clean_item;
		}

		// Re-encode.
		return wp_json_encode( $sanitized );
	}

	/**
	 * Renders a settings field based on its type.
	 *
	 * This method renders the appropriate template for a settings field by mapping
	 * the field type to a template file and passing type-specific parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option          The unique option key for the field.
	 * @param array  $option_settings The option settings including field config.
	 * @return void
	 */
	public function render_field( string $option, array $option_settings ): void {
		$type      = $option_settings['field']['type'] ?? '';
		$name      = $this->get_name_field( $option );
		$value     = $this->get( $option );
		$inherited = $this->is_option_inherited( $option );

		$params = array(
			'name'        => $name,
			'option'      => Utility::prefix_key( $option ),
			'value'       => $value,
			'label'       => $option_settings['field']['label'] ?? '',
			'description' => $option_settings['description'] ?? '',
			'disabled'    => $inherited,
		);

		switch ( $type ) {
			case 'text':
				$params['size']    = $option_settings['field']['size'] ?? 'regular';
				$params['preview'] = $option_settings['field']['preview'] ?? array();
				break;
			case 'password':
				$params['size']    = $option_settings['field']['size'] ?? 'regular';
				$params['preview'] = $option_settings['field']['preview'] ?? array();
				break;
			case 'number':
				$params['size']        = $option_settings['field']['size'] ?? 'regular';
				$params['min']         = $option_settings['field']['options']['min'] ?? '';
				$params['max']         = $option_settings['field']['options']['max'] ?? '';
				$params['placeholder'] = $option_settings['field']['placeholder'] ?? '';
				$params['allow_empty'] = (bool) ( $option_settings['field']['allow_empty'] ?? false );
				break;
			case 'select':
				$params['options'] = $option_settings['field']['options'] ?? '';
				break;
			case 'autocomplete':
				$params['field_options'] = $option_settings['field']['options'] ?? array();
				break;
			default:
				// Field types without extra params (checkbox, etc.) render with the base $params.
				break;
		}

		if ( $inherited ) {
			echo '<div class="gatherpress-field-inherited" aria-disabled="true">';
		}

		Utility::render_template(
			sprintf(
				'%s/includes/templates/admin/settings/fields/%s.php',
				GATHERPRESS_CORE_PATH,
				$type
			),
			$params,
			true
		);

		if ( $inherited ) {
			if ( current_user_can( 'manage_network_options' ) ) {
				$inherited_message = wp_kses(
					sprintf(
						/* translators: %s: link to the network admin GatherPress settings page. */
						__( 'Inherited from the %s. Edit there to change this value.', 'gatherpress' ),
						sprintf(
							'<a href="%s">%s</a>',
							esc_url(
								network_admin_url(
									sprintf( 'settings.php?page=%s', Settings\Network::PAGE_SLUG )
								)
							),
							esc_html__( 'network', 'gatherpress' )
						)
					),
					array( 'a' => array( 'href' => true ) )
				);
			} else {
				$inherited_message = esc_html__( 'Inherited from the network.', 'gatherpress' );
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped via wp_kses / esc_html above.
			echo '<p class="description gatherpress-field-inherited__note">' . $inherited_message . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Set the value of a specific option in plugin settings.
	 *
	 * Updates the flat gatherpress_settings option with the given key-value pair.
	 * If the value matches the default, the key is removed to keep the option lean.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option The unique name of the option to set.
	 * @param mixed  $value  The value to set.
	 * @return void
	 */
	public function set( string $option, $value ): void {
		$options = get_option( self::OPTION_NAME, array() );

		if ( $value === $this->get_flat_default( $option ) ) {
			unset( $options[ $option ] );
		} else {
			$options[ $option ] = $value;
		}

		update_option( self::OPTION_NAME, $options );
	}

	/**
	 * Get the value of a specific option from plugin settings.
	 *
	 * This method retrieves the value of a specific option from the flat
	 * gatherpress_settings option. If the option is set, its value is returned;
	 * otherwise, the default value is returned.
	 *
	 * On a multisite subsite, options that are flagged as network-inherited
	 * are read from the main site instead of the local site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option The unique name of the option to retrieve.
	 * @return mixed The value of the option or its default value.
	 */
	public function get( string $option ) {
		// Read from the network options table when this is an inherited
		// option on a multisite subsite, otherwise the local options table.
		// `get_site_option` returns false on single-site, so the type check
		// below filters that out before the lookup.
		$options = $this->is_option_inherited( $option )
			? get_site_option( self::OPTION_NAME, array() )
			: get_option( self::OPTION_NAME, array() );

		if (
			is_array( $options )
			&& isset( $options[ $option ] )
			&& '' !== $options[ $option ]
		) {
			return $options[ $option ];
		}

		return $this->get_flat_default( $option );
	}

	/**
	 * Whether a given option is inherited from the network.
	 *
	 * Returns true when we're on a subsite of a multisite install, the
	 * network inheritance feature is enabled, and the option is listed as
	 * inherited in the network config. The result passes through the
	 * `gatherpress_network_is_option_inherited` filter so a companion plugin or
	 * site-specific code can override the decision for an individual site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option The option key to check.
	 * @return bool
	 */
	public function is_option_inherited( string $option ): bool {
		$inherited = false;

		// Apply inheritance on any site in the network (including the main site)
		// so the UI reflects the network config everywhere. The only exemption
		// is the network admin settings page itself, where super admins edit
		// the network values — fields there must remain fully editable.
		if ( is_multisite() && ! is_network_admin() ) {
			$config = Settings\Network::get_config();

			if ( ! empty( $config['enabled'] ) ) {
				$inherited = in_array( $option, (array) ( $config['inherited'] ?? array() ), true );
			}
		}

		/**
		 * Filters whether a specific GatherPress option is inherited from the network.
		 *
		 * Returning false exempts the current site from network-level inheritance
		 * for that option; returning true forces inheritance even if the network
		 * config would otherwise leave it site-editable.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $inherited Whether the option is inherited from the network.
		 * @param string $option    The option key being resolved.
		 * @param int    $blog_id   The current site ID.
		 */
		return (bool) apply_filters(
			'gatherpress_network_is_option_inherited',
			$inherited,
			$option,
			get_current_blog_id()
		);
	}

	/**
	 * Get the default value for a specific option from plugin settings.
	 *
	 * Uses a cached defaults map built from all sub-pages' sections
	 * to avoid repeated walks of the settings structure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option The unique name of the option to retrieve the default value for.
	 * @return mixed The default value of the option or an empty string if not defined.
	 */
	public function get_flat_default( string $option ) {
		$defaults = $this->get_defaults_map();

		return $defaults[ $option ] ?? '';
	}

	/**
	 * Build and cache a flat map of all option keys to their default values.
	 *
	 * Walks the sub-pages structure once and caches the result for the
	 * duration of the request.
	 *
	 * @since 1.0.0
	 *
	 * @return array Flat map of option_key => default_value.
	 */
	protected function get_defaults_map(): array {
		if ( null !== $this->defaults_cache ) {
			return $this->defaults_cache;
		}

		$this->defaults_cache = array();
		$sub_pages            = $this->get_sub_pages();

		foreach ( $sub_pages as $sub_page_settings ) {
			if ( ! isset( $sub_page_settings['sections'] ) ) {
				continue;
			}

			foreach ( (array) $sub_page_settings['sections'] as $section_settings ) {
				if ( ! isset( $section_settings['options'] ) ) {
					continue;
				}

				foreach ( (array) $section_settings['options'] as $option => $option_settings ) {
					$this->defaults_cache[ $option ] = $option_settings['field']['options']['default'] ?? '';
				}
			}
		}

		return $this->defaults_cache;
	}

	/**
	 * Generate the name attribute for a setting field.
	 *
	 * This method constructs the name attribute for a setting field based on the
	 * option name. The resulting name attribute is used to associate the field's
	 * value with its location within the flat settings structure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option Option of the setting field.
	 * @return string The generated name attribute for the setting field.
	 */
	public function get_name_field( string $option ): string {
		return sprintf(
			'%s[%s]',
			self::OPTION_NAME,
			sanitize_key( $option )
		);
	}

	/**
	 * Retrieve an array of sub-pages for the options page.
	 *
	 * This method fetches and organizes the sub-pages associated with the main options page.
	 * The sub-pages include information such as their settings and priority. Filters can be
	 * applied to modify the sub-pages before they are returned.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array of sub-pages, each with settings and priority information.
	 */
	public function get_sub_pages(): array {
		/**
		 * Filters the list of GatherPress sub pages.
		 *
		 * Allows a companion plugin or theme to extend GatherPress settings
		 * by adding additional sub pages to the settings page.
		 *
		 * @since 1.0.0
		 *
		 * @param array $sub_pages The array of sub pages.
		 *
		 * @return array Modified array of sub pages.
		 */
		$sub_pages = (array) apply_filters( 'gatherpress_sub_pages', array() );

		uasort( $sub_pages, array( $this, 'sort_sub_pages_by_priority' ) );

		return $sub_pages;
	}

	/**
	 * Sort an associative array of sub-pages by priority.
	 *
	 * This method compares the priority of two sub-pages and is used for sorting them.
	 * The default priority is 10 if not explicitly set.
	 *
	 * @since 1.0.0
	 *
	 * @param array $first  The first sub-page to compare by priority.
	 * @param array $second The second sub-page to compare by priority.
	 * @return int Returns a negative number if the first sub-page has a lower priority,
	 *             a positive number if the second sub-page has a lower priority,
	 *             or 0 if their priorities are equal.
	 */
	public function sort_sub_pages_by_priority( array $first, array $second ): int {
		$first['priority']  = isset( $first['priority'] ) ? intval( $first['priority'] ) : 10;
		$second['priority'] = isset( $second['priority'] ) ? intval( $second['priority'] ) : 10;

		return $first['priority'] <=> $second['priority'];
	}

	/**
	 * Render the options page for GatherPress settings.
	 *
	 * This method is responsible for rendering the primary options page of the GatherPress plugin.
	 * It displays settings and configurations for various sub-pages within the plugin's administration panel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function settings_page(): void {
		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/index.php', GATHERPRESS_CORE_PATH ),
			array(
				'sub_pages' => $this->get_sub_pages(),
				'page'      => $this->current_page,
			),
			true
		);
	}

	/**
	 * Select the GatherPress menu for all sub pages.
	 *
	 * This method ensures that the GatherPress menu is selected in the WordPress admin menu for all sub pages.
	 * It checks if the provided submenu name is empty and, if so, determines whether to select the 'General' subpage
	 * based on the current page. This helps maintain consistent menu selection across GatherPress settings pages.
	 *
	 * @since 1.0.0
	 *
	 * @param string $submenu The name of the sub menu page.
	 * @return string The selected submenu name, either the provided one or 'general'.
	 */
	public function select_menu( $submenu ): string {
		if ( empty( $submenu ) ) {
			$sub_pages = $this->get_sub_pages();

			if ( ! empty( $sub_pages ) ) {
				$page = Utility::unprefix_key( $this->current_page );

				if ( isset( $sub_pages[ $page ] ) ) {
					$submenu = Utility::prefix_key( $this->main_sub_page );
				}
			}
		}

		return (string) $submenu;
	}

	/**
	 * Schedule rewrite rules flush when rewrite-related settings change.
	 *
	 * Checks all fields marked with 'rewrite' => true in their config and
	 * flushes rewrite rules if any of their values changed.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $old_value The old option value.
	 * @param mixed $new_value The new option value.
	 * @return void
	 */
	public function maybe_flush_rewrite_rules( $old_value, $new_value ): void {
		foreach ( $this->get_rewrite_keys() as $key ) {
			$old = $old_value[ $key ] ?? '';
			$new = $new_value[ $key ] ?? '';

			if ( $old !== $new ) {
				delete_option( 'rewrite_rules' );
				return;
			}
		}
	}

	/**
	 * Get option keys that are flagged as affecting rewrite rules.
	 *
	 * Walks the settings config and returns keys for fields that have
	 * 'rewrite' => true in their field definition.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of option keys that affect rewrite rules.
	 */
	protected function get_rewrite_keys(): array {
		$keys      = array();
		$sub_pages = $this->get_sub_pages();

		foreach ( $sub_pages as $sub_page_settings ) {
			if ( ! isset( $sub_page_settings['sections'] ) ) {
				continue;
			}

			foreach ( (array) $sub_page_settings['sections'] as $section_settings ) {
				if ( ! isset( $section_settings['options'] ) ) {
					continue;
				}

				foreach ( (array) $section_settings['options'] as $option => $option_settings ) {
					if ( ! empty( $option_settings['field']['rewrite'] ) ) {
						$keys[] = $option;
					}
				}
			}
		}

		return $keys;
	}

	/**
	 * Export current settings as a structured array.
	 *
	 * Returns only non-default values (what's actually stored),
	 * along with version metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scope Storage scope: 'blog' (default) or 'network'.
	 *                      'network' reads the network-wide site option,
	 *                      used when exporting from Network Admin.
	 * @return array Export data with version, timestamp, scope, and settings.
	 */
	public function export_settings( string $scope = 'blog' ): array {
		return array(
			'version'     => GATHERPRESS_VERSION,
			'exported_at' => current_time( 'c' ),
			'scope'       => $scope,
			'settings'    => $this->read_stored_options( $scope ),
		);
	}

	/**
	 * Read the stored settings option in the given storage scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scope Storage scope: 'blog' or 'network'.
	 * @return array
	 */
	protected function read_stored_options( string $scope ): array {
		if ( 'network' === $scope ) {
			return (array) get_site_option( self::OPTION_NAME, array() );
		}

		return (array) get_option( self::OPTION_NAME, array() );
	}

	/**
	 * Write the stored settings option in the given storage scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scope   Storage scope: 'blog' or 'network'.
	 * @param array  $options Options array to persist.
	 * @return void
	 */
	protected function write_stored_options( string $scope, array $options ): void {
		if ( 'network' === $scope ) {
			update_site_option( self::OPTION_NAME, $options );
			return;
		}

		update_option( self::OPTION_NAME, $options );
	}

	/**
	 * Delete the stored settings option in the given storage scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scope Storage scope: 'blog' or 'network'.
	 * @return void
	 */
	protected function delete_stored_options( string $scope ): void {
		if ( 'network' === $scope ) {
			delete_site_option( self::OPTION_NAME );
			return;
		}

		delete_option( self::OPTION_NAME );
	}

	/**
	 * Validate import data without applying changes.
	 *
	 * Checks structure, version compatibility, and reports what
	 * would change if the import were applied.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data  The parsed import data.
	 * @param string $scope Storage scope: 'blog' (default) or 'network'.
	 * @return array Validation result with 'valid', 'changes', 'unknown', and 'warnings' keys.
	 */
	public function validate_import( array $data, string $scope = 'blog' ): array {
		$result = array(
			'valid'    => true,
			'changes'  => array(),
			'unknown'  => array(),
			'warnings' => array(),
		);

		if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			$result['valid']      = false;
			$result['warnings'][] = __( 'Invalid import file: missing settings data.', 'gatherpress' );

			return $result;
		}

		if ( isset( $data['version'] ) && GATHERPRESS_VERSION !== $data['version'] ) {
			$result['warnings'][] = sprintf(
				/* translators: 1: Export version, 2: Current version. */
				__(
					'Settings were exported from version %1$s (current: %2$s).',
					'gatherpress'
				),
				$data['version'],
				GATHERPRESS_VERSION
			);
		}

		$field_type_map = $this->build_field_type_map( $this->get_sub_pages() );
		$current        = $this->read_stored_options( $scope );

		foreach ( $data['settings'] as $key => $value ) {
			if ( ! isset( $field_type_map[ $key ] ) ) {
				$result['unknown'][] = $key;
				continue;
			}

			$current_value = $current[ $key ] ?? $this->get_flat_default( $key );

			if ( $value !== $current_value ) {
				$result['changes'][] = $key;
			}
		}

		return $result;
	}

	/**
	 * Import settings from a parsed export file.
	 *
	 * Validates, sanitizes, and applies imported settings. Supports
	 * merge (preserves existing) and replace (overwrites all) modes.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data  The parsed import data.
	 * @param string $mode  Import mode: 'merge' or 'replace'.
	 * @param string $scope Storage scope: 'blog' (default) or 'network'.
	 * @return array Result with 'success', 'imported', 'skipped', and 'warnings' keys.
	 */
	public function import_settings( array $data, string $mode = 'merge', string $scope = 'blog' ): array {
		$result = array(
			'success'  => false,
			'imported' => array(),
			'skipped'  => array(),
			'warnings' => array(),
		);

		$validation = $this->validate_import( $data, $scope );

		if ( ! $validation['valid'] ) {
			$result['warnings'] = $validation['warnings'];

			return $result;
		}

		$result['warnings'] = $validation['warnings'];
		$result['skipped']  = $validation['unknown'];

		$field_type_map = $this->build_field_type_map( $this->get_sub_pages() );
		$sanitize       = $this->sanitize_page_settings( $field_type_map, $scope );

		// Filter to only known keys.
		$to_import = array_intersect_key(
			$data['settings'],
			$field_type_map
		);

		// Sanitize imported values.
		if ( 'replace' === $mode ) {
			// Replace mode: clear existing, only use imported values.
			$this->delete_stored_options( $scope );
		}

		$sanitized = $sanitize( $to_import );

		$this->write_stored_options( $scope, $sanitized );

		if ( 'network' === $scope ) {
			Settings\Network::flush_config_cache();
		}

		$result['success']  = true;
		$result['imported'] = array_keys( $to_import );

		return $result;
	}
}
