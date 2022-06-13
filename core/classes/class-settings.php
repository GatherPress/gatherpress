<?php
/**
 * Class is responsible for managing plugin settings.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use \GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings.
 */
class Settings {

	use Singleton;

	const PARENT_SLUG = 'edit.php?post_type=gp_event';

	/**
	 * Current page.
	 *
	 * @var string
	 */
	protected $page = '';

	/**
	 * Role constructor.
	 */
	protected function __construct() {
		$this->set_page();
		$this->setup_hooks();
	}

	protected function set_page() {
		if ( isset( $_GET['page'] ) ) {
			$this->page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		}
	}

	/**
	 * Setup Hooks.
	 */
	protected function setup_hooks() {
		add_action( 'admin_menu', array( $this, 'options_page' ) );
		add_action( 'admin_head', array( $this, 'remove_sub_options' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_filter( 'submenu_file', array( $this, 'select_menu' ) );
	}

	/**
	 * Setup options page.
	 */
	public function options_page() {
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Settings', 'gatherpress' ),
			__( 'Settings', 'gatherpress' ),
			'manage_options',
			$this->prefix_key( 'general' ),
			array( $this, 'settings_page' ),
			6
		);

		$sub_pages = $this->get_sub_pages();

		foreach ( $sub_pages as $sub_page => $setting ) {
			if ( 'general' === $sub_page ) {
				continue;
			}

			$page = $this->prefix_key( $sub_page );

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
	 * Remove submenu pages from Settings menu.
	 */
	public function remove_sub_options() {
		$sub_pages = $this->get_sub_pages();

		foreach ( $sub_pages as $sub_page => $setting ) {
			if ( 'general' === $sub_page ) {
				continue;
			}

			remove_submenu_page( self::PARENT_SLUG, $this->prefix_key( $sub_page ) );
		}
	}

	/**
	 * Register settings page.
	 *
	 * @return void
	 */
	public function register_settings() {
		$sub_pages = $this->get_sub_pages();

		register_setting(
			'gatherpress',
			'gatherpress_settings'
		);

		foreach ( $sub_pages as $sub_page => $sub_page_settings ) {
			register_setting(
				$this->prefix_key( $sub_page ),
				$this->prefix_key( $sub_page )
			);

			if ( isset( $sub_page_settings['sections'] ) ) {
				foreach ( (array) $sub_page_settings['sections'] as $section => $section_settings ) {
					add_settings_section(
						$section,
						$section_settings['name'],
						function() use ( $section_settings ) {
							if ( ! empty( $section_settings['description'] ) ) {
								echo '<p class="description">' . esc_html( $section_settings['description'] ) . '</p>';
							}
						},
						$this->prefix_key( $sub_page )
					);

					if ( isset( $section_settings['options'] ) ) {
						foreach ( (array) $section_settings['options'] as $option => $option_settings ) {
							if ( $option_settings['field'] && method_exists( $this, $option_settings['field'] ) ) {
								$option_settings['callback'] = function() use ( $sub_page, $section, $option, $option_settings ) {
									$sub_page = $this->prefix_key( $sub_page );

									$this->{$option_settings['field']}( $sub_page, $section, $option, $option_settings );
								};
							}
							add_settings_field(
								$option,
								$option_settings['label'],
								$option_settings['callback'],
								$this->prefix_key( $sub_page ),
								$section,
								array( 'label_for' => $this->prefix_key( $option ) )
							);
						}
					}
				}
			}
		}
	}

	/**
	 * Outputs a text input field.
	 *
	 * @param string $sub_page        The sub page for the text field.
	 * @param string $section         The section for the text field.
	 * @param string $option          The option for the text field.
	 * @param array  $option_settings The option settings.
	 *
	 * @return void
	 */
	public function text_field( string $sub_page, string $section, string $option, array $option_settings ) {
		$name    = $this->get_name_field( $sub_page, $section, $option );
		$default = $option_settings['default'] ?? '';
		$value   = $this->get_value( $sub_page, $section, $option, $default );
		?>

		<input id="<?php echo esc_attr( $this->prefix_key( $option ) ); ?>" type='text' name="<?php echo esc_attr( $name ); ?>" class="regular-text" value="<?php echo esc_html( $value ); ?>" />
		<?php
		if ( ! empty( $option_settings['description'] ) ) {
			?>
			<p class="description"><?php echo esc_html( $option_settings['description'] ); ?></p>
			<?php
		}
	}

	/**
	 * Gets the value.
	 *
	 * @param string       $sub_page The sub page of the value.
	 * @param string       $section  The section of the value.
	 * @param string       $option   The option of the value.
	 * @param mixed|string $default  The default value.
	 *
	 * @return mixed
	 */
	public function get_value( string $sub_page, string $section = '', string $option = '', $default = '' ) {
		$options = $this->get_options( $sub_page );

		if ( ! empty( $section ) && ! empty( $option ) ) {
			return ( ! empty( $options[ $section ][ $option ] ) ) ? $options[ $section ][ $option ] : $default;
		} elseif ( ! empty( $section ) ) {
			return ( ! empty( $options[ $section ] ) ) ? $options[ $section ] : $default;
		}

		return $options;
	}

	/**
	 * Get currently set options from a GatherPress sub page.
	 *
	 * @param string $sub_page The sub page to get options.
	 *
	 * @return array
	 */
	public function get_options( string $sub_page ): array {
		$option = get_option( $sub_page );

		if ( ! empty( $option ) && is_array( $option ) ) {
			return $option;
		}

		return $this->get_option_defaults( $sub_page );
	}

	/**
	 * Default options for GatherPress sub pages.
	 *
	 * @param string $option The option to get the default.
	 *
	 * @return array
	 */
	public function get_option_defaults( string $option ): array {
		$sub_pages = $this->get_sub_pages();
		$option    = $this->unprefix_key( $option );
		$defaults  = array();

		if ( ! empty( $sub_pages[ $option ]['sections'] ) && is_array( $sub_pages[ $option ]['sections'] ) ) {
			foreach ( $sub_pages[ $option ]['sections'] as $section => $settings ) {
				if ( ! is_array( $settings['options'] ) ) {
					continue;
				}

				foreach ( $settings['options'] as $option => $values ) {
					$defaults[ $section ][ $option ] = $values['default'];
				}
			}
		}

		return $defaults;
	}

	/**
	 * Create name field for setting.
	 *
	 * @param string $sub_page Sub page of name field.
	 * @param string $section  Section of name field.
	 * @param string $option   Option of name field.
	 *
	 * @return string
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
	 * Get sub pages for options page.
	 *
	 * @return array
	 */
	public function get_sub_pages(): array {
		$sub_pages = array(
			'general' => array(
				'name'        => __( 'General', 'gatherpress' ),
				'description' => __( 'Settings for GatherPress.', 'gatherpress' ),
				'priority'    => 1,
			),
			'credits' => array(
				'name'     => __( 'Credits', 'gatherpress' ),
				'priority' => 99,
			),
		);

		$sub_pages = (array) apply_filters( 'gatherpress_settings_sub_pages', $sub_pages ); // @todo don't filter all pages, just allow to add additional subpages.

		uasort( $sub_pages, array( $this, 'sort_sub_pages_by_priority' ) );

		return $sub_pages;
	}

	/**
	 * Get Role options and settings for Language -> Roles.
	 *
	 * @return array
	 */
	public function get_role_options(): array {
		$role                = Role::get_instance();
		$role_names          = $role->get_roles();
		$role_defaults_names = $role->get_default_role_names();
		$options             = array();

		foreach ( $role_names as $role_name => $value ) {
			$options[ $role_name ] = array(
				'label'   => $value,
				'field'   => 'text_field',
				'default' => $role_defaults_names[ $role_name ] ?? $value,
			);
		}

		return $options;
	}

	/**
	 * Add gp- prefix.
	 *
	 * @param string $key The key for adding prefix.
	 *
	 * @return string
	 */
	public function prefix_key( string $key ): string {
		return sprintf( 'gp_%s', $key );
	}

	/**
	 * Remove gp- prefix.
	 *
	 * @param string $key The key for removing prefix.
	 *
	 * @return string
	 */
	public function unprefix_key( string $key ): string {
		return preg_replace( '/^gp_/', '', $key );
	}

	/**
	 * Sort associative array by priority. 10 is default.
	 *
	 * @param array $first  First to compare priority.
	 * @param array $second Second to compare priority.
	 *
	 * @return int
	 */
	public function sort_sub_pages_by_priority( array $first, array $second ): int {
		$first['priority']  = isset( $first['priority'] ) ? intval( $first['priority'] ) : 10;
		$second['priority'] = isset( $second['priority'] ) ? intval( $second['priority'] ) : 10;

		return ( $first['priority'] > $second['priority'] );
	}

	/**
	 * Render the options page.
	 */
	public function settings_page() {
		Utility::render_template(
			sprintf( '%s/templates/admin/settings.php', GATHERPRESS_CORE_PATH ),
			array(
				'sub_pages' => $this->get_sub_pages(),
				'page'      => $this->page,
			),
			true
		);
	}

	/**
	 * Select GatherPress in menu for all sub pages.
	 *
	 * @param string $submenu Name of sub menu page.
	 *
	 * @return string
	 */
	public function select_menu( $submenu ) {
		if ( empty( $submenu ) ) {
			$sub_pages = $this->get_sub_pages();

			if ( isset( $sub_pages ) ) {
				$page = $this->unprefix_key( $this->page );

				if ( isset( $sub_pages[ $page ] ) ) {
					$submenu = $this->prefix_key( 'general' );
				}
			}
		}

		return $submenu;
	}

}
