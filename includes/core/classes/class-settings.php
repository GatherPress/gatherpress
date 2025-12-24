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

use GatherPress\Core\Settings\Credits;
use GatherPress\Core\Settings\General;
use GatherPress\Core\Settings\Leadership;
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
	 * Constructor for the Settings class.
	 *
	 * Initializes the settings object, sets the current page, and sets up hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->instantiate_classes();
		$this->set_current_page();
		$this->setup_hooks();
	}

	/**
	 * Instantiate and initialize various settings classes.
	 *
	 * This method creates instances of the settings-related classes and initializes them.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Credits::get_instance();
		General::get_instance();
		Leadership::get_instance();
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
		add_action( 'gatherpress_text_after', array( $this, 'datetime_preview' ), 10, 2 );
		add_action( 'gatherpress_text_after', array( $this, 'url_rewrite_preview' ), 10, 2 );
		add_action( 'update_option_gatherpress_general', array( $this, 'maybe_flush_rewrite_rules' ), 10, 2 );

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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) ) {
			$this->current_page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
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

		foreach ( $sub_pages as $sub_page => $setting ) {
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
		$sub_pages = $this->get_sub_pages();

		foreach ( $sub_pages as $sub_page => $sub_page_settings ) {
			// phpcs:ignore PluginCheck.CodeAnalysis.SettingSanitization.register_settingDynamic
			register_setting(
				Utility::prefix_key( $sub_page ),
				Utility::prefix_key( $sub_page ),
				array(
					'sanitize_callback' => $this->sanitize_page_settings( $sub_page_settings ),
				)
			);

			if ( isset( $sub_page_settings['sections'] ) ) {
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
							if (
								$option_settings['field']['type']
								&& method_exists( $this, $option_settings['field']['type'] )
							) {
								$option_settings['callback'] = function () use (
									$sub_page,
									$section,
									$option,
									$option_settings
								) {
									$sub_page = Utility::prefix_key( $sub_page );
									$this->{$option_settings['field']['type']}(
										$sub_page,
										$section,
										$option,
										$option_settings
									);
								};
							}
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
	}

	/**
	 * Creates a sanitization callback function for page settings.
	 *
	 * Generates a closure that sanitizes input values based on their defined field types
	 * in the sub-page settings. Handles various input types including checkboxes, numbers,
	 * autocomplete fields, text fields, and select dropdowns.
	 *
	 * @param array $sub_page_settings The settings configuration for the sub-page,
	 *                                 containing sections and field type definitions.
	 * @return callable A callback function that sanitizes input based on field types.
	 */
	public function sanitize_page_settings( array $sub_page_settings ): callable {
		return function ( $input ) use ( $sub_page_settings ): array {
			foreach ( $input as $key => $value ) {
				foreach ( $value as $k => $v ) {
					$type = $sub_page_settings['sections'][ $key ]['options'][ $k ]['field']['type'];

					switch ( $type ) {
						case 'checkbox':
							$input[ $key ][ $k ] = (bool) $v;
							break;
						case 'number':
							$input[ $key ][ $k ] = intval( $v );
							break;
						case 'autocomplete':
							$input[ $key ][ $k ] = $this->sanitize_autocomplete( $v );
							break;
						case 'text':
						case 'select':
						default:
							$input[ $key ][ $k ] = sanitize_text_field( $v );
							break;
					}
				}
			}

			return $input;
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
	 * Outputs a text input field for a settings option.
	 *
	 * This method is responsible for rendering a text input field as a part of the plugin's settings page.
	 * It takes the sub-page, section, option, and option settings as parameters and displays the input field
	 * with the specified name, value, and description.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page        The sub page for the text field.
	 * @param string $section         The section for the text field.
	 * @param string $option          The option for the text field.
	 * @param array  $option_settings The option settings.
	 * @return void
	 */
	public function text( string $sub_page, string $section, string $option, array $option_settings ): void {
		$name  = $this->get_name_field( $sub_page, $section, $option );
		$value = $this->get_value( $sub_page, $section, $option );

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/fields/text.php', GATHERPRESS_CORE_PATH ),
			array(
				'name'        => $name,
				'option'      => Utility::prefix_key( $option ),
				'value'       => $value,
				'label'       => $option_settings['field']['label'] ?? '',
				'size'        => $option_settings['field']['size'] ?? 'regular',
				'description' => $option_settings['description'] ?? '',
			),
			true
		);
	}

	/**
	 * Render a number input field in GatherPress settings.
	 *
	 * This method is responsible for rendering a number input field in GatherPress settings.
	 * It generates the HTML markup for the input field, including labels, attributes, and descriptions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page        The slug of the sub-page within settings.
	 * @param string $section         The slug of the settings section.
	 * @param string $option          The name of the option.
	 * @param array  $option_settings An array containing option settings.
	 * @return void
	 */
	public function number( string $sub_page, string $section, string $option, array $option_settings ): void {
		$name  = $this->get_name_field( $sub_page, $section, $option );
		$value = $this->get_value( $sub_page, $section, $option );

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/fields/number.php', GATHERPRESS_CORE_PATH ),
			array(
				'name'        => $name,
				'option'      => Utility::prefix_key( $option ),
				'value'       => $value,
				'label'       => $option_settings['field']['label'] ?? '',
				'size'        => $option_settings['field']['size'] ?? 'regular',
				'min'         => $option_settings['field']['options']['min'] ?? '',
				'max'         => $option_settings['field']['options']['max'] ?? '',
				'description' => $option_settings['description'] ?? '',
			),
			true
		);
	}

	/**
	 * Outputs a checkbox input field for a settings option.
	 *
	 * This method is responsible for rendering a checkbox input field as a part of the plugin's settings page.
	 * It takes the sub-page, section, option, and option settings as parameters and displays the checkbox input field
	 * with the specified name, value, label, and description.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page        The sub page for the checkbox field.
	 * @param string $section         The section for the checkbox field.
	 * @param string $option          The option for the checkbox field.
	 * @param array  $option_settings The option settings.
	 * @return void
	 */
	public function checkbox( string $sub_page, string $section, string $option, array $option_settings ): void {
		$name  = $this->get_name_field( $sub_page, $section, $option );
		$value = $this->get_value( $sub_page, $section, $option );

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/fields/checkbox.php', GATHERPRESS_CORE_PATH ),
			array(
				'name'        => $name,
				'option'      => Utility::prefix_key( $option ),
				'value'       => $value,
				'label'       => $option_settings['field']['label'] ?? '',
				'description' => $option_settings['description'] ?? '',
			),
			true
		);
	}

	/**
	 * Outputs a select input field for a settings option.
	 *
	 * This method is responsible for rendering a select input field as a part of the plugin's settings page.
	 * It takes the sub-page, section, option, and option settings as parameters and displays the select input field
	 * with the specified name, value, label, and description.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page        The sub page for the select field.
	 * @param string $section         The section for the select field.
	 * @param string $option          The option for the select field.
	 * @param array  $option_settings The option settings.
	 * @return void
	 */
	public function select( string $sub_page, string $section, string $option, array $option_settings ): void {
		$name  = $this->get_name_field( $sub_page, $section, $option );
		$value = $this->get_value( $sub_page, $section, $option );

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/fields/select.php', GATHERPRESS_CORE_PATH ),
			array(
				'name'        => $name,
				'option'      => Utility::prefix_key( $option ),
				'options'     => $option_settings['field']['options'] ?? '',
				'value'       => $value,
				'label'       => $option_settings['field']['label'] ?? '',
				'description' => $option_settings['description'] ?? '',
			),
			true
		);
	}

	/**
	 * Outputs a dynamic select field for a type of content in the settings page.
	 *
	 * This method is responsible for rendering a dynamic select input field on the plugin's settings page.
	 * It takes the sub-page, section, option, and option settings as parameters and displays the select input field
	 * with the specified name, value, description, and field options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page        The sub page for the select field.
	 * @param string $section         The section for the select field.
	 * @param string $option          The option for the select field.
	 * @param array  $option_settings The option settings.
	 * @return void
	 */
	public function autocomplete( string $sub_page, string $section, string $option, array $option_settings ): void {
		$name  = $this->get_name_field( $sub_page, $section, $option );
		$value = $this->get_value( $sub_page, $section, $option );

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/fields/autocomplete.php', GATHERPRESS_CORE_PATH ),
			array(
				'name'          => $name,
				'option'        => Utility::prefix_key( $option ),
				'value'         => $value,
				'description'   => $option_settings['description'] ?? '',
				'field_options' => $option_settings['field']['options'] ?? array(),
			),
			true
		);
	}

	/**
	 * Get the value of a specific option from plugin settings.
	 *
	 * This method retrieves the value of a specific option from the plugin settings
	 * based on the provided sub-page, section, and option names. If the option is set,
	 * its value is returned; otherwise, the default value is returned.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page The sub-page associated with the value.
	 * @param string $section  The section within the sub-page where the option is located.
	 * @param string $option   The name of the option to retrieve.
	 * @return mixed The value of the option or its default value.
	 */
	public function get_value( string $sub_page, string $section = '', string $option = '' ) {
		$sub_page = Utility::prefix_key( $sub_page );
		$options  = $this->get_options( $sub_page );
		$default  = $this->get_default_value( $sub_page, $section, $option );

		return (
			isset( $options[ $section ][ $option ] ) &&
			'' !== $options[ $section ][ $option ]
		) ? $options[ $section ][ $option ] : $default;
	}

	/**
	 * Get the default value for a specific option from plugin settings.
	 *
	 * This method retrieves the default value of a specific option from the plugin settings
	 * based on the provided sub-page, section, and option names. If a default value is defined
	 * for the option, it will be returned; otherwise, an empty string is returned.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page The sub-page associated with the value.
	 * @param string $section  The section within the sub-page where the option is located.
	 * @param string $option   The name of the option to retrieve the default value for.
	 * @return mixed The default value of the option or an empty string if not defined.
	 */
	public function get_default_value( string $sub_page, string $section = '', string $option = '' ) {
		$sub_pages = $this->get_sub_pages();

		return $sub_pages[ Utility::unprefix_key( $sub_page ) ]['sections'][ $section ]['options']
			[ $option ]['field']['options']['default'] ?? '';
	}

	/**
	 * Get the currently set options for a specific GatherPress sub-page.
	 *
	 * This method retrieves the options currently set for a GatherPress sub-page
	 * from the WordPress database. If the options exist and are in an array format,
	 * they will be returned. If the options are not set or not found in the database,
	 * the default options for the sub-page will be returned.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page The sub-page for which to retrieve the options.
	 * @return array An array of currently set options for the sub-page or its default options.
	 */
	public function get_options( string $sub_page ): array {
		$option = get_option( $sub_page );

		if ( ! empty( $option ) && is_array( $option ) ) {
			return $option;
		}

		return $this->get_option_defaults( $sub_page );
	}

	/**
	 * Retrieve the default options for a specific GatherPress sub-page.
	 *
	 * This method fetches the default options defined for a GatherPress sub-page
	 * based on the provided option name. It compiles the default options from the
	 * sub-page's sections and their associated options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option The option for which to retrieve default values.
	 * @return array An array of default values for the specified sub-page option.
	 */
	public function get_option_defaults( string $option ): array {
		$sub_pages = $this->get_sub_pages();
		$option    = Utility::unprefix_key( $option );
		$defaults  = array();

		if ( ! empty( $sub_pages[ $option ]['sections'] ) && is_array( $sub_pages[ $option ]['sections'] ) ) {
			foreach ( $sub_pages[ $option ]['sections'] as $section => $settings ) {
				if ( ! is_array( $settings['options'] ) ) {
					continue;
				}

				foreach ( $settings['options'] as $option => $values ) {
					$defaults[ $section ][ $option ] = $values['default'] ?? '';
				}
			}
		}

		return $defaults;
	}

	/**
	 * Generate the name attribute for a setting field.
	 *
	 * This method constructs the name attribute for a setting field based on the provided
	 * sub-page, section, and option names. The resulting name attribute is used to associate
	 * the field's value with its location within the settings structure.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page Sub-page of the setting field.
	 * @param string $section  Section of the setting field.
	 * @param string $option   Option of the setting field.
	 * @return string The generated name attribute for the setting field.
	 */
	public function get_name_field( string $sub_page, string $section, string $option ): string {
		return sprintf(
			'%s[%s][%s]',
			sanitize_key( $sub_page ),
			sanitize_key( $section ),
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
	 * Display a preview of the formatted datetime based on the specified name and value.
	 *
	 * This method is used to display a preview of the formatted datetime based on the specified
	 * name and value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  The name of the datetime format option.
	 * @param string $value The value of the datetime format option.
	 * @return void
	 */
	public function datetime_preview( string $name, string $value ): void {
		if (
			'gatherpress_general[formatting][date_format]' === $name ||
			'gatherpress_general[formatting][time_format]' === $name
		) {
			Utility::render_template(
				sprintf( '%s/includes/templates/admin/settings/partials/datetime-preview.php', GATHERPRESS_CORE_PATH ),
				array(
					'name'  => $name,
					'value' => $value,
				),
				true
			);
		}
	}

	/**
	 * Display a preview of the rewritten URL based on the specified string.
	 *
	 * This method is used to display a preview of the rewritten URL based on the specified
	 * string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  The name of the url rewrite format option.
	 * @param string $value The value of the url rewrite format option.
	 * @return void
	 */
	public function url_rewrite_preview( string $name, string $value ): void {
		if (
			'gatherpress_general[urls][events]' === $name ||
			'gatherpress_general[urls][venues]' === $name ||
			'gatherpress_general[urls][topics]' === $name
		) {
			switch ( $name ) {
				case 'gatherpress_general[urls][events]':
					$suffix = _x( 'sample-event', 'URL permalink structure example for events', 'gatherpress' );
					break;
				case 'gatherpress_general[urls][venues]':
					$suffix = _x( 'sample-venue', 'URL permalink structure example for venues', 'gatherpress' );
					break;
				case 'gatherpress_general[urls][topics]':
					$suffix = _x(
						'sample-topic-term',
						'URL permalink structure example for topics',
						'gatherpress'
					);
					break;
				default:
					// Nothing to see here. Other URL types don't need special handling.
			}

			Utility::render_template(
				sprintf(
					'%s/includes/templates/admin/settings/partials/url-rewrite-preview.php',
					GATHERPRESS_CORE_PATH
				),
				array(
					'name'   => $name,
					'value'  => $value,
					'suffix' => $suffix,
				),
				true
			);
		}
	}

	/**
	 * Schedule rewrite rules flush when post type rewrite slugs change.
	 *
	 * Fires after the value of the 'gatherpress_general["urls"]' option-part has been successfully updated
	 * and only if it has changed since before.
	 *
	 * Deletes the core rewrite_rules option to trigger WordPress's automatic
	 * rewrite rule regeneration on the next request. This is more efficient
	 * than using a custom flag option.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $old_value The old option value.
	 * @param mixed $new_value The new option value.
	 * @return void
	 */
	public function maybe_flush_rewrite_rules( $old_value, $new_value ): void {
		if (
			( ! isset( $old_value['urls'] ) && isset( $new_value['urls'] ) ) ||
			( isset( $old_value['urls'] ) && ! isset( $new_value['urls'] ) ) ||
			( $old_value['urls'] !== $new_value['urls'] )
		) {
			delete_option( 'rewrite_rules' );
		}
	}
}
