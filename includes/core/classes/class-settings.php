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
		add_action( 'init', array( $this, 'set_main_sub_page' ) );
		add_action( 'admin_menu', array( $this, 'options_page' ) );
		add_action( 'admin_head', array( $this, 'remove_sub_options' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'gatherpress_settings_section', array( $this, 'render_settings_form' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'maybe_flush_rewrite_rules' ), 10, 2 );

		add_filter( 'submenu_file', array( $this, 'select_menu' ) );
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
			if ( ! isset( $sub_page_settings['sections'] ) ) {
				continue;
			}

			foreach ( (array) $sub_page_settings['sections'] as $section => $section_settings ) {
				add_settings_section(
					$section,
					$section_settings['name'],
					static function () use ( $section_settings ) {
						if ( ! empty( $section_settings['description'] ) ) {
							echo '<p class="description">'
								. wp_kses_post( $section_settings['description'] ) . '</p>';
						}
					},
					Utility::prefix_key( $sub_page )
				);

				if ( isset( $section_settings['options'] ) ) {
					foreach ( (array) $section_settings['options'] as $option => $option_settings ) {
						$option_settings['callback'] = function () use (
							$option,
							$option_settings
						) {
							$this->render_field( $option, $option_settings );
						};

						add_settings_field(
							$option,
							$option_settings['labels']['name'],
							$option_settings['callback'],
							Utility::prefix_key( $sub_page ),
							$section
						);
					}
				}
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

		foreach ( $sub_pages as $sub_page => $sub_page_settings ) {
			if ( ! isset( $sub_page_settings['sections'] ) ) {
				continue;
			}

			foreach ( (array) $sub_page_settings['sections'] as $section_settings ) {
				if ( ! isset( $section_settings['options'] ) ) {
					continue;
				}

				foreach ( (array) $section_settings['options'] as $option => $option_settings ) {
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
				static function () use ( $duplicates ) {
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
	 * @param array $field_type_map Flat map of option_key => field_type.
	 * @return callable A callback function that sanitizes input based on field types.
	 */
	public function sanitize_page_settings( array $field_type_map ): callable {
		return function ( $input ) use ( $field_type_map ): array {
			$sanitized = array();

			foreach ( $input as $key => $value ) {
				$type = $field_type_map[ $key ] ?? 'text';

				switch ( $type ) {
					case 'checkbox':
						$sanitized[ $key ] = (bool) $value;
						break;
					case 'number':
						$sanitized[ $key ] = intval( $value );
						break;
					case 'autocomplete':
						$sanitized[ $key ] = $this->sanitize_autocomplete( $value );
						break;
					case 'text':
					case 'select':
					default:
						$sanitized[ $key ] = sanitize_text_field( $value );
						break;
				}
			}

			// Merge with existing values to preserve settings from other tabs.
			$existing = get_option( self::OPTION_NAME, array() );
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
		$type  = $option_settings['field']['type'] ?? '';
		$name  = $this->get_name_field( $option );
		$value = $this->get_value( $option );

		$params = array(
			'name'        => $name,
			'option'      => Utility::prefix_key( $option ),
			'value'       => $value,
			'label'       => $option_settings['field']['label'] ?? '',
			'description' => $option_settings['description'] ?? '',
		);

		switch ( $type ) {
			case 'text':
				$params['size']    = $option_settings['field']['size'] ?? 'regular';
				$params['preview'] = $option_settings['field']['preview'] ?? array();
				break;
			case 'number':
				$params['size'] = $option_settings['field']['size'] ?? 'regular';
				$params['min']  = $option_settings['field']['options']['min'] ?? '';
				$params['max']  = $option_settings['field']['options']['max'] ?? '';
				break;
			case 'select':
				$params['options'] = $option_settings['field']['options'] ?? '';
				break;
			case 'autocomplete':
				$params['field_options'] = $option_settings['field']['options'] ?? array();
				break;
		}

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/fields/%s.php', GATHERPRESS_CORE_PATH, $type ),
			$params,
			true
		);
	}

	/**
	 * Get the value of a specific option from plugin settings.
	 *
	 * This method retrieves the value of a specific option from the flat
	 * gatherpress_settings option. If the option is set, its value is returned;
	 * otherwise, the default value is returned.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option The unique name of the option to retrieve.
	 * @return mixed The value of the option or its default value.
	 */
	public function get_value( string $option ) {
		$options = get_option( self::OPTION_NAME, array() );

		if ( isset( $options[ $option ] ) && '' !== $options[ $option ] ) {
			return $options[ $option ];
		}

		return $this->get_flat_default( $option );
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
}
