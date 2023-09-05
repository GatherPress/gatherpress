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

	use Singleton;

	const PARENT_SLUG = 'edit.php?post_type=gp_event';

	/**
	 * The current page being accessed within the settings.
	 *
	 * @var string
	 */
	protected string $current_page = '';

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
		add_action( 'admin_menu', array( $this, 'options_page' ) );
		add_action( 'admin_head', array( $this, 'remove_sub_options' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_filter( 'submenu_file', array( $this, 'select_menu' ) );
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
			Utility::prefix_key( 'general' ),
			array( $this, 'settings_page' ),
			6
		);

		$sub_pages = $this->get_sub_pages();

		foreach ( $sub_pages as $sub_page => $setting ) {
			if ( 'general' === $sub_page ) {
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
			if ( 'general' === $sub_page ) {
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

		register_setting(
			'gatherpress',
			'gatherpress_settings'
		);

		foreach ( $sub_pages as $sub_page => $sub_page_settings ) {
			register_setting(
				Utility::prefix_key( $sub_page ),
				Utility::prefix_key( $sub_page )
			);

			if ( isset( $sub_page_settings['sections'] ) ) {
				foreach ( (array) $sub_page_settings['sections'] as $section => $section_settings ) {
					add_settings_section(
						$section,
						$section_settings['name'],
						function() use ( $section_settings ) {
							if ( ! empty( $section_settings['description'] ) ) {
								echo '<p class="description">' . wp_kses_post( $section_settings['description'] ) . '</p>';
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
								$option_settings['callback'] = function() use ( $sub_page, $section, $option, $option_settings ) {
									$sub_page = Utility::prefix_key( $sub_page );
									$this->{$option_settings['field']['type']}( $sub_page, $section, $option, $option_settings );
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
	 *
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
	 *
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
	 *
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
	 * Outputs credits to people set in latest.json on the settings page.
	 *
	 * This method is responsible for displaying credits to individuals or contributors
	 * that are set in the latest.json file on the plugin's settings page. It takes the
	 * sub-page, section, option, and option settings as parameters and renders the credits.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page        The sub page for displaying credits.
	 * @param string $section         The section for displaying credits.
	 * @param string $option          The option for displaying credits.
	 * @param array  $option_settings The option settings.
	 *
	 * @return void
	 */
	public function credits( string $sub_page, string $section, string $option, array $option_settings ): void {
		$credits = include sprintf( '%s/includes/data/credits/latest.php', GATHERPRESS_CORE_PATH );

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/fields/credits.php', GATHERPRESS_CORE_PATH ),
			array(
				'option'  => $option,
				'credits' => $credits[ $option ],
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
	 *
	 * @return mixed The value of the option or its default value.
	 */
	public function get_value( string $sub_page, string $section = '', string $option = '' ) {
		$options = $this->get_options( $sub_page );
		$default = $this->get_default_value( $sub_page, $section, $option );

		return (
			isset( $options[ $section ][ $option ] )
			&& '' !== $options[ $section ][ $option ]
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
	 *
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
	 *
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
	 *
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
	 *
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
		$sub_pages               = array();
		$sub_pages['general']    = $this->get_general_page();
		$sub_pages['leadership'] = $this->get_leadership_page();
		$sub_pages['credits']    = $this->get_credits_page();

		$sub_pages = (array) apply_filters( 'gatherpress_sub_pages', $sub_pages );

		uasort( $sub_pages, array( $this, 'sort_sub_pages_by_priority' ) );

		return $sub_pages;
	}

	/**
	 * Retrieve settings for the General page.
	 *
	 * This method returns an array of settings and options for the General page of the
	 * GatherPress settings. The General page includes settings related to event dates and
	 * event archive pages. Each section within the General page is defined with its own
	 * settings and options.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing the General page settings and options.
	 */
	public function get_general_page(): array {
		return array(
			'name'        => __( 'General', 'gatherpress' ),
			'description' => __( 'Settings for GatherPress.', 'gatherpress' ),
			'priority'    => PHP_INT_MIN,
			'sections'    => array(
				'general' => array(
					'name'        => __( 'General Settings', 'gatherpress' ),
					'description' => __(
						'GatherPress allows you to set event dates to reflect either the post date or event date. Default: show as event date.',
						'gatherpress'
					),
					'options'     => array(
						'post_or_event_date' => array(
							'labels' => array(
								'name' => __( 'Publish Date', 'gatherpress' ),
							),
							'field'  => array(
								'label'   => __( 'Show publish date as event date for events', 'gatherpress' ),
								'type'    => 'checkbox',
								'options' => array(
									'default' => '1',
								),
							),
						),
					),
				),
				'pages'   => array(
					'name'        => __( 'Event Archive Pages', 'gatherpress' ),
					'description' => __( 'GatherPress allows you to set event archives to pages you have created.', 'gatherpress' ),
					'options'     => array(
						'upcoming_events' => array(
							'labels' => array(
								'name' => __( 'Upcoming Events', 'gatherpress' ),
							),
							'field'  => array(
								'type'    => 'autocomplete',
								'options' => array(
									'type'  => 'page',
									'label' => __( 'Select Upcoming Events Archive Page', 'gatherpress' ),
									'limit' => 1,
								),
							),
						),
						'past_events'     => array(
							'labels' => array(
								'name' => __( 'Past Events', 'gatherpress' ),
							),
							'field'  => array(
								'type'    => 'autocomplete',
								'options' => array(
									'type'  => 'page',
									'label' => __( 'Select Past Events Archive Page', 'gatherpress' ),
									'limit' => 1,
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Retrieve settings for the Leadership page.
	 *
	 * This method returns an array of settings and options for the Leadership page of the
	 * GatherPress settings. The Leadership page includes settings related to roles and organizers.
	 * You can customize role labels and select organizers for each role.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing the Leadership page settings and options.
	 */
	public function get_leadership_page(): array {
		$roles = array(
			'organizers' => array(
				'labels' => array(
					'name'          => __( 'Organizers', 'gatherpress' ),
					'singular_name' => __( 'Organizer', 'gatherpress' ),
					'plural_name'   => __( 'Organizers', 'gatherpress' ),
				),
				'field'  => array(
					'type'    => 'autocomplete',
					'options' => array(
						'type'  => 'user',
						'label' => __( 'Select Organizers', 'gatherpress' ),
					),
				),
			),
		);

		return array(
			'name'        => __( 'Leadership', 'gatherpress' ),
			'description' => __( 'Leadership for GatherPress.', 'gatherpress' ),
			'sections'    => array(
				'roles' => array(
					'name'        => __( 'Roles', 'gatherpress' ),
					'description' => __( 'GatherPress allows you to customize role labels to be more appropriate for events.', 'gatherpress' ),
					'options'     => apply_filters( 'gatherpress_roles', $roles ),
				),
			),
		);
	}

	/**
	 * Retrieve settings for the Credits page.
	 *
	 * This method returns an array of settings and options for the Credits page of the GatherPress settings.
	 * The Credits page displays credits to individuals and contributors behind the GatherPress project.
	 * Users can get involved and see their names on this page.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing the Credits page settings and options.
	 */
	public function get_credits_page(): array {
		return array(
			'name'     => __( 'Credits', 'gatherpress' ),
			'priority' => PHP_INT_MAX,
			'sections' => array(
				'credits' => array(
					'name'        => __( 'Credits', 'gatherpress' ),
					'description' => sprintf(
					/* translators: %1$s: opening anchor tag, %2$s closing anchor tag. */
						__( 'Meet the folks behind GatherPress. Want to see your name here? %1$sGet Involved!%2$s', 'gatherpress' ),
						'<a href="https://github.com/GatherPress/gatherpress" target="_blank">',
						'</a>'
					),
					'options'     => array(
						'project-leads'    => array(
							'labels' => array(
								'name' => __( 'Project Leads', 'gatherpress' ),
							),
							'field'  => array(
								'type' => 'credits',
							),
						),
						'gatherpress-team' => array(
							'labels' => array(
								'name' => __( 'GatherPress Team', 'gatherpress' ),
							),
							'field'  => array(
								'type' => 'credits',
							),
						),
						'contributors'     => array(
							'labels' => array(
								'name' => __( 'Contributors', 'gatherpress' ),
							),
							'field'  => array(
								'type' => 'credits',
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Retrieve a list of user roles.
	 *
	 * This method returns an array of user roles defined for GatherPress. User roles
	 * are used to customize role labels to be more appropriate for events.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array containing user roles and their corresponding settings.
	 */
	public function get_user_roles(): array {
		$sub_pages = $this->get_sub_pages();
		$options   = (array) $sub_pages['leadership']['sections']['roles']['options'];

		return $options ?? array();
	}

	/**
	 * Retrieve the role of a user.
	 *
	 * This method returns the role of a user identified by their User ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 *
	 * @return string The role of the user, or 'Member' if no matching role is found.
	 */
	public function get_user_role( int $user_id ): string {
		$leadership = get_option( Utility::prefix_key( 'leadership' ) );
		$roles      = $leadership['roles'] ?? array();
		$default    = __( 'Member', 'gatherpress' );

		foreach ( $roles as $role => $users ) {
			foreach ( json_decode( $users ) as $user ) {
				if ( intval( $user->id ) === $user_id ) {
					$roles = $this->get_user_roles();

					return $roles[ $role ]['labels']['singular_name'] ?? $default;
				}
			}
		}

		return $default;
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
	 *
	 * @return int Returns a negative number if the first sub-page has a lower priority,
	 *             a positive number if the second sub-page has a lower priority,
	 *             or 0 if their priorities are equal.
	 */
	public function sort_sub_pages_by_priority( array $first, array $second ): int {
		$first['priority']  = isset( $first['priority'] ) ? intval( $first['priority'] ) : 10;
		$second['priority'] = isset( $second['priority'] ) ? intval( $second['priority'] ) : 10;

		return ( $first['priority'] > $second['priority'] );
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
	 *
	 * @return string The selected submenu name, either the provided one or 'general'.
	 */
	public function select_menu( $submenu ): string {
		if ( empty( $submenu ) ) {
			$sub_pages = $this->get_sub_pages();

			if ( ! empty( $sub_pages ) ) {
				$page = Utility::unprefix_key( $this->current_page );

				if ( isset( $sub_pages[ $page ] ) ) {
					$submenu = Utility::prefix_key( 'general' );
				}
			}
		}

		return (string) $submenu;
	}

}
