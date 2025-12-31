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
use GatherPress\Core\AI\Abilities_Integration;
use GatherPress\Core\AI\Admin_Page;
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
		// Only instantiate Abilities Integration if Abilities API is available.
		if ( function_exists( 'wp_register_ability' ) ) {
			Abilities_Integration::get_instance();
		}
		Admin_Page::get_instance();
		Assets::get_instance();
		Block::get_instance();
		Cli::get_instance();
		Feed::get_instance();
		Event_Query::get_instance();
		Event_Rest_Api::get_instance();
		Event_Setup::get_instance();
		Export::get_instance();
		Import::get_instance();
		Rsvp_Form::get_instance();
		Rsvp_Query::get_instance();
		Rsvp_Setup::get_instance();
		Settings::get_instance();
		Topic::get_instance();
		User::get_instance();
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

		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_action( 'admin_notices', array( $this, 'check_gatherpress_alpha' ) );
		add_action( 'network_admin_notices', array( $this, 'check_gatherpress_alpha' ) );
		add_action( 'wp_initialize_site', array( $this, 'on_site_create' ) );
		add_action( 'send_headers', array( $this, 'smash_table' ) );

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
				'settings' => '<a href="' .
					esc_url( admin_url( 'edit.php?post_type=gatherpress_event&page=gatherpress_general' ) ) .
					'">' . esc_html__( 'Settings', 'gatherpress' ) . '</a>',
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
	 * Schedule rewrite rules flush by deleting the core rewrite_rules option.
	 *
	 * WordPress will automatically regenerate rewrite rules on the next request
	 * when the rewrite_rules option is missing. This is more efficient than
	 * calling flush_rewrite_rules() directly and removes the need for a custom
	 * flag option.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function schedule_rewrite_flush(): void {
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Adds a privacy policy statement.
	 *
	 * Every plugin that collects, uses, or stores user data,
	 * or passes it to an external source or third party,
	 * should add a section of suggested text to the privacy policy postbox.
	 *
	 * This is best done with wp_add_privacy_policy_content( $plugin_name, $policy_text ).
	 * This will allow site administrators to pull that information into their site’s privacy policy.
	 *
	 * The HTML contents of the $content supports use of a specialized .privacy-policy-tutorial CSS class
	 * which can be used to provide supplemental information.
	 * Any content contained within HTML elements that have the .privacy-policy-tutorial CSS class applied
	 * will be omitted from the clipboard when the section content is copied.
	 *
	 * @see https://developer.wordpress.org/plugins/privacy/suggesting-text-for-the-site-privacy-policy/
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_privacy_policy_content() {
		$content = '<h2>' .
			__( 'Inform your visitors about GatherPress\' use of OpenStreetMap services.', 'gatherpress' ) .
			'</h2>'
				. '<p><strong class="privacy-policy-tutorial">' . __( 'Suggested Text:', 'gatherpress' ) . '</strong> '
				. __(
					'When viewing maps on event or venue pages, your IP address and certain technical information (such as browser type and referrer URL) are transmitted to the OpenStreetMap Foundation, which operates the map service. ', // phpcs:ignore Generic.Files.LineLength.TooLong
					'gatherpress'
				)
				. sprintf(
					// translators: %1$s: privacy policy URL of the OpenStreetMap foundation.
					__(
						'This data is processed according to their <a href="%1$s" target="_blank">privacy policy</a>. ',
						'gatherpress'
					),
					'https://osmfoundation.org/wiki/Privacy_Policy'
				)
				. sprintf(
					// translators: %1$s: privacy policy URL of the OpenStreetMap foundation.
					__(
						'For more information about what data OpenStreetMap collects and how it is used, please refer to their <a href="%1$s" target="_blank">privacy documents</a>.', // phpcs:ignore Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					'https://osmfoundation.org/wiki/Privacy_Policy'
				)
				. '</p>';

		wp_add_privacy_policy_content( 'GatherPress', wp_kses_post( wpautop( $content, false ) ) );
	}

	/**
	 * Add GatherPress-specific body classes to the existing body classes.
	 *
	 * This method appends custom body classes, such as 'gatherpress-enabled' and
	 * 'gatherpress-theme-{theme-name}', to the array of existing body classes.
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
				intval( $term['term_id'] ),
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
			switch_to_blog( intval( $new_site->blog_id ) );
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

		$tables[] = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );

		return $tables;
	}

	/**
	 * Creates necessary database tables for the GatherPress plugin.
	 *
	 * This method creates the required database tables for storing event and RSVP data.
	 * It constructs SQL queries for creating the tables with appropriate charset and collation,
	 * and then executes these queries using the `dbDelta` function to ensure the tables are created
	 * or updated as necessary. Additionally, it calls methods to add the online event term
	 * and to set a flag for flushing rewrite rules.
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

		// Loading WordPress core file for dbDelta function, not importing a class.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php'; // NOSONAR.

		dbDelta( $sql );

		$this->add_online_event_term();
		$this->schedule_rewrite_flush();
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
		/**
		 * Filters whether GatherPress Alpha is considered active.
		 *
		 * Allows tests to override the constant check.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $is_alpha_active Whether GatherPress Alpha is active.
		 */
		$is_alpha_active = apply_filters( 'gatherpress_is_alpha_active', defined( 'GATHERPRESS_ALPHA_VERSION' ) );

		if (
			$is_alpha_active ||
			filter_var( ! current_user_can( 'install_plugins' ), FILTER_VALIDATE_BOOLEAN ) || (
				! str_contains( get_current_screen()->id, 'plugins' ) &&
				! str_contains( get_current_screen()->id, 'plugin-install' ) &&
				! str_contains( get_current_screen()->id, 'gatherpress' )
			)
		) {
			return;
		}

		wp_admin_notice(
			__(
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'The GatherPress Alpha plugin is not installed or activated. This plugin is currently in heavy development and requires GatherPress Alpha to handle breaking changes. Please <a href="https://github.com/GatherPress/gatherpress-alpha" target="_blank">download and install GatherPress Alpha</a> to ensure compatibility and avoid issues.',
				'gatherpress'
			),
			array(
				'type'        => 'warning',
				'dismissible' => true,
			)
		);
	}

	/**
	 * Smash tables and add a custom HTTP header to show undying love for the Buffalo Bills.
	 *
	 * ♫ Let’s Go Buffalo! ♫
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function smash_table(): void {
		header( 'X-Bills-Mafia: Go Bills!' );
	}
}
