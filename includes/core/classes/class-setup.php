<?php
/**
 * Manages plugin setup and initialization.
 *
 * This class handles various aspects of plugin setup, including registering custom post types and taxonomies,
 * creating custom database tables, and setting up plugin hooks.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use Exception;
use GatherPress\Core\Traits\Singleton;
use WP_Site;

/**
 * Class Setup.
 *
 * Manages plugin setup and initialization.
 *
 * @since 1.0.0
 */
class Setup {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constructor for the Setup class.
	 *
	 * Initializes and sets up various components of the plugin.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->instantiate_classes();
		$this->setup_hooks();
	}

	/**
	 * Instantiate singleton classes.
	 *
	 * This method initializes various singleton classes used by the plugin.
	 * It may throw an Exception if there are issues instantiating the classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 *
	 * @throws Exception If there are issues instantiating singleton classes.
	 */
	protected function instantiate_classes(): void {
		Assets::get_instance();
		Block::get_instance();
		Cli::get_instance();
		Event_Query::get_instance();
		Event_Setup::get_instance();
		Export::get_instance();
		Import::get_instance();
		Rest_Api::get_instance();
		Rsvp_Query::get_instance();
		Rsvp_Setup::get_instance();
		Settings::get_instance();
		User::get_instance();
		Topic::get_instance();
		Venue::get_instance();
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
		register_activation_hook( GATHERPRESS_CORE_FILE, array( $this, 'activate_gatherpress_plugin' ) );
		register_deactivation_hook( GATHERPRESS_CORE_FILE, array( $this, 'deactivate_gatherpress_plugin' ) );

		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ) );
		add_action( 'admin_notices', array( $this, 'check_users_can_register' ) );
		add_action( 'network_admin_notices', array( $this, 'check_users_can_register' ) );
		add_action( 'admin_notices', array( $this, 'check_gatherpress_alpha' ) );
		add_action( 'network_admin_notices', array( $this, 'check_gatherpress_alpha' ) );
		add_action( 'wp_initialize_site', array( $this, 'on_site_create' ) );

		add_filter( 'block_categories_all', array( $this, 'register_gatherpress_block_category' ) );
		add_filter( 'wpmu_drop_tables', array( $this, 'on_site_delete' ) );
		add_filter( 'body_class', array( $this, 'add_gatherpress_body_classes' ) );
		add_filter(
			sprintf(
				'plugin_action_links_%s/%s',
				basename( GATHERPRESS_CORE_PATH ),
				basename( GATHERPRESS_CORE_FILE )
			),
			array( $this, 'filter_plugin_action_links' )
		);
		add_filter(
			sprintf(
				'network_admin_plugin_action_links_%s/%s',
				basename( GATHERPRESS_CORE_PATH ),
				basename( GATHERPRESS_CORE_FILE )
			),
			array( $this, 'filter_plugin_action_links' )
		);
	}

	/**
	 * Add custom links to the plugin action links in the WordPress plugins list.
	 *
	 * This method adds a 'Settings' link to the plugin's action links in the WordPress plugins list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $actions An array of existing action links.
	 * @return array An updated array of action links, including the 'Settings' link.
	 */
	public function filter_plugin_action_links( array $actions ): array {
		return array_merge(
			array(
				'settings' => '<a href="' . esc_url( admin_url( 'edit.php?post_type=gatherpress_event&page=gatherpress_general' ) ) . '">'
					. esc_html__( 'Settings', 'gatherpress' ) . '</a>',
			),
			$actions
		);
	}

	/**
	 * Activates the GatherPress plugin.
	 *
	 * This method handles the activation of the GatherPress plugin. If the plugin
	 * is being activated network-wide in a multisite installation, it iterates
	 * through each blog in the network and performs necessary setup actions
	 * (creating tables). If not network-wide, it only performs the setup actions
	 * for the current site.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 * @return void
	 */
	public function activate_gatherpress_plugin( bool $network_wide ): void {
		if ( is_multisite() && $network_wide ) {
			// Get all sites in the network and activate plugin on each one.
			$site_ids = get_sites(
				array(
					'fields'     => 'ids',
					'network_id' => get_current_site()->id,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				$this->create_tables();
				restore_current_blog();
			}
		} else {
			$this->create_tables();
		}
	}

	/**
	 * Deactivate the GatherPress plugin.
	 *
	 * This method is called when deactivating the GatherPress plugin. It flushes the rewrite rules to ensure
	 * proper functionality.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function deactivate_gatherpress_plugin(): void {
		flush_rewrite_rules();
	}

	/**
	 * Flush GatherPress rewrite rules if the previously added flag exists and then remove the flag.
	 *
	 * This method checks if the 'gatherpress_flush_rewrite_rules_flag' option exists. If it does, it flushes
	 * the rewrite rules to ensure they are up to date and removes the flag afterward.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'gatherpress_flush_rewrite_rules_flag' ) ) {
			flush_rewrite_rules();
			delete_option( 'gatherpress_flush_rewrite_rules_flag' );
		}
	}

	/**
	 * Creates a flag option to indicate that rewrite rules need to be flushed.
	 *
	 * This method checks if the 'gatherpress_flush_rewrite_rules_flag' option
	 * exists. If it does not, it adds the option and sets it to true. This flag
	 * can be used to determine when rewrite rules should be flushed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function maybe_create_flush_rewrite_rules_flag(): void {
		if ( ! get_option( 'gatherpress_flush_rewrite_rules_flag' ) ) {
			add_option( 'gatherpress_flush_rewrite_rules_flag', true );
		}
	}

	/**
	 * Add GatherPress-specific body classes to the existing body classes.
	 *
	 * This method appends custom body classes, such as 'gatherpress-enabled' and 'gatherpress-theme-{theme-name}',
	 * to the array of existing body classes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $classes Existing body classes.
	 * @return array An updated array of body classes.
	 */
	public function add_gatherpress_body_classes( array $classes ): array {
		$classes[] = 'gatherpress-enabled';
		$classes[] = sprintf( 'gatherpress-theme-%s', esc_attr( get_stylesheet() ) );

		return $classes;
	}

	/**
	 * Register GatherPress block category.
	 *
	 * This method registers the GatherPress block category and adds it to the array
	 * of registered block categories.
	 *
	 * @since 1.0.0
	 *
	 * @param array $block_categories Array of registered block categories.
	 * @return array An updated array of block categories.
	 */
	public function register_gatherpress_block_category( array $block_categories ): array {
		$category = array(
			'slug'  => 'gatherpress',
			'title' => __( 'GatherPress', 'gatherpress' ),
			'icon'  => 'nametag',
		);

		array_unshift( $block_categories, $category );

		return $block_categories;
	}

	/**
	 * Add the 'Online event' term to the venue taxonomy.
	 *
	 * This method adds the 'Online event' term to the venue taxonomy if it does not exist,
	 * or updates it if it already exists. This term is used to categorize online events.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_online_event_term(): void {
		Venue::get_instance()->register_taxonomy();

		$term_name = __( 'Online event', 'gatherpress' );
		$term_slug = 'online-event';
		$term      = term_exists( $term_slug, Venue::TAXONOMY );

		if ( ! $term ) {
			wp_insert_term(
				$term_name,
				Venue::TAXONOMY,
				array(
					'slug' => $term_slug,
				)
			);
		} else {
			wp_update_term(
				$term['term_id'],
				Venue::TAXONOMY,
				array(
					'name' => $term_name,
					'slug' => $term_slug,
				),
			);
		}
	}

	/**
	 * Handles actions to be taken when a new site is created in a multisite network.
	 *
	 * This function checks if the 'gatherpress' plugin is active across the network.
	 * If it is, it switches to the new site, calls the `create_table()` function,
	 * and then restores the current blog.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Site $new_site the newly created site.
	 *
	 * @return void
	 */
	public function on_site_create( WP_Site $new_site ): void {
		if ( is_plugin_active_for_network( 'gatherpress/gatherpress.php' ) ) {
			switch_to_blog( $new_site->blog_id );
			$this->create_tables();
			restore_current_blog();
		}
	}

	/**
	 * Delete custom tables on site deletion.
	 *
	 * This method is called when a site is deleted, and it allows the plugin to specify
	 * which custom tables associated with the plugin should be deleted. It returns an
	 * updated array of table names to be dropped during site deletion.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tables An array of names of the site tables to be dropped.
	 * @return array An updated array of table names to be deleted during site deletion.
	 */
	public function on_site_delete( array $tables ): array {
		global $wpdb;

		$tables[] = sprintf( Event::TABLE_FORMAT, $wpdb->prefix, Event::POST_TYPE );

		return $tables;
	}

	/**
	 * Creates necessary database tables for the GatherPress plugin.
	 *
	 * This method creates the required database tables for storing event and RSVP data.
	 * It constructs SQL queries for creating the tables with appropriate charset and
	 * collation, and then executes these queries using the `dbDelta` function to ensure
	 * the tables are created or updated as necessary. Additionally, it calls methods to
	 * add the online event term and to set a flag for flushing rewrite rules.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 * @return void
	 */
	protected function create_tables(): void {
		global $wpdb;

		$sql             = array();
		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$table = sprintf( Event::TABLE_FORMAT, $prefix );
		$sql[] = "CREATE TABLE {$table} (
					post_id bigint(20) unsigned NOT NULL default '0',
					datetime_start datetime NOT NULL default '0000-00-00 00:00:00',
					datetime_start_gmt datetime NOT NULL default '0000-00-00 00:00:00',
					datetime_end datetime NOT NULL default '0000-00-00 00:00:00',
					datetime_end_gmt datetime NOT NULL default '0000-00-00 00:00:00',
					timezone varchar(255) default NULL,
					PRIMARY KEY  (post_id),
					KEY datetime_start_gmt (datetime_start_gmt),
					KEY datetime_end_gmt (datetime_end_gmt)
				) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );

		$this->add_online_event_term();
		$this->maybe_create_flush_rewrite_rules_flag();
	}

	/**
	 * Display a notification to recommend enabling user registration for GatherPress functionality.
	 *
	 * This method checks if user registration is enabled in WordPress settings and displays a
	 * notification encouraging users to enable registration for optimal GatherPress functionality.
	 * Users have the option to suppress this notification permanently.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function check_users_can_register(): void {
		if (
			filter_var( get_option( 'users_can_register' ), FILTER_VALIDATE_BOOLEAN ) ||
			filter_var( get_option( 'gatherpress_suppress_site_notification' ), FILTER_VALIDATE_BOOLEAN ) ||
			filter_var( ! current_user_can( 'manage_options' ), FILTER_VALIDATE_BOOLEAN ) || (
				false === strpos( get_current_screen()->id, 'gatherpress' ) &&
				false === strpos( get_current_screen()->id, 'options-general' ) &&
				false === strpos( get_current_screen()->id, 'settings-network' )
			)
		) {
			return;
		}

		wp_enqueue_style( 'gatherpress-admin-style' );

		if (
			'gatherpress_suppress_site_notification' === filter_input( INPUT_GET, 'action' ) &&
			! empty( filter_input( INPUT_GET, '_wpnonce' ) ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( filter_input( INPUT_GET, '_wpnonce' ) ) ), 'clear-notification' )
		) {
			update_option( 'gatherpress_suppress_site_notification', true );
		} else {
			Utility::render_template(
				sprintf( '%s/includes/templates/admin/setup/site-check.php', GATHERPRESS_CORE_PATH ),
				array(),
				true
			);
		}
	}

	/**
	 * Checks if the GatherPress Alpha plugin is active and renders an admin notice if not.
	 *
	 * This method verifies whether the GatherPress Alpha plugin is currently active.
	 * If the plugin is not active, it renders an admin notice template to inform the user
	 * that the GatherPress Alpha plugin is required for compatibility and development purposes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function check_gatherpress_alpha(): void {
		if (
			defined( 'GATHERPRESS_ALPHA_VERSION' ) ||
			filter_var( ! current_user_can( 'install_plugins' ), FILTER_VALIDATE_BOOLEAN ) || (
				false === strpos( get_current_screen()->id, 'plugins' ) &&
				false === strpos( get_current_screen()->id, 'plugin-install' ) &&
				false === strpos( get_current_screen()->id, 'gatherpress' )
			)
		) {
			return;
		}

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/setup/gatherpress-alpha-check.php', GATHERPRESS_CORE_PATH ),
			array(),
			true
		);
	}
}
