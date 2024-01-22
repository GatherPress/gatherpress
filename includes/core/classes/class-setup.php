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

use Exception;
use GatherPress\Core\Traits\Singleton;
use WP_Post;

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
	 * Instantiate singleton classes and set up WP-CLI command.
	 *
	 * This method initializes various singleton classes used by the plugin
	 * and adds a WP-CLI command if WP_CLI is defined. It may throw an Exception
	 * if there are issues instantiating the classes.
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
		Rest_Api::get_instance();
		Settings::get_instance();
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

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'delete_post', array( $this, 'delete_event' ) );
		add_action(
			sprintf( 'manage_%s_posts_custom_column', Event::POST_TYPE ),
			array( $this, 'custom_columns' ),
			10,
			2
		);
		add_action( 'init', array( $this, 'maybe_flush_gatherpress_rewrite_rules' ) );
		add_action( 'admin_notices', array( $this, 'check_users_can_register' ) );

		add_filter( 'block_categories_all', array( $this, 'register_gatherpress_block_category' ) );
		add_filter( 'wpmu_drop_tables', array( $this, 'on_site_delete' ) );
		add_filter(
			sprintf( 'manage_%s_posts_columns', Event::POST_TYPE ),
			array( $this, 'set_custom_columns' )
		);
		add_filter(
			sprintf( 'manage_edit-%s_sortable_columns', Event::POST_TYPE ),
			array( $this, 'sortable_columns' )
		);
		add_filter( 'get_the_date', array( $this, 'get_the_event_date' ) );
		add_filter( 'the_time', array( $this, 'get_the_event_date' ) );
		add_filter( 'body_class', array( $this, 'add_gatherpress_body_classes' ) );
		add_filter( 'display_post_states', array( $this, 'set_event_archive_labels' ), 10, 2 );
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
		add_filter( 'load_textdomain_mofile', array( $this, 'load_mofile' ), 10, 2 );
	}

	/**
	 * Loads gatherpress for GatherPress.
	 *
	 * @todo needed until plugin is added to wordpress.org plugin directory.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'gatherpress', false, GATHERPRESS_DIR_NAME . '/languages' );
	}

	/**
	 * Find language files in gatherpress/languages when missing in wp-content/languages/plugins/
	 *
	 * The translation files will be in wp-content/languages/plugins/ once the plugin on the
	 * repository and translated in translate.wordpress.org.
	 *
	 * @todo needed until plugin is added to wordpress.org plugin directory.
	 *
	 * Until that, we need to load from /languages folder and load the textdomain.
	 * See https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#plugins-on-wordpress-org.
	 *
	 * @since 1.0.0
	 *
	 * @param string $mofile The path to the translation file.
	 * @param string $domain The text domain of the translation file.
	 * @return string The updated path to the translation file based on the locale
	 */
	public function load_mofile( string $mofile, string $domain ): string {
		if ( 'gatherpress' === $domain && false !== strpos( $mofile, WP_LANG_DIR . '/plugins/' ) ) {
			$locale = apply_filters( 'plugin_locale', determine_locale(), $domain );  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$mofile = WP_PLUGIN_DIR . '/' . GATHERPRESS_DIR_NAME . '/languages/' . $domain . '-' . $locale . '.mo';
		}

		return $mofile;
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
				'settings' => '<a href="' . esc_url( admin_url( 'edit.php?post_type=gp_event&page=gp_general' ) ) . '">'
					. esc_html__( 'Settings', 'gatherpress' ) . '</a>',
			),
			$actions
		);
	}

	/**
	 * Activate the GatherPress plugin.
	 *
	 * This method performs activation tasks for the GatherPress plugin, such as renaming blocks and tables,
	 * creating custom tables, and setting a flag to flush rewrite rules if necessary.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function activate_gatherpress_plugin(): void {
		$this->maybe_create_custom_table();
		$this->add_online_event_term();

		if ( ! get_option( 'gatherpress_flush_rewrite_rules_flag' ) ) {
			add_option( 'gatherpress_flush_rewrite_rules_flag', true );
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
	public function maybe_flush_gatherpress_rewrite_rules(): void {
		if ( get_option( 'gatherpress_flush_rewrite_rules_flag' ) ) {
			flush_rewrite_rules();
			delete_option( 'gatherpress_flush_rewrite_rules_flag' );
		}
	}

	/**
	 * Add GatherPress-specific body classes to the existing body classes.
	 *
	 * This method appends custom body classes, such as 'gp-enabled' and 'gp-theme-{theme-name}',
	 * to the array of existing body classes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $classes Existing body classes.
	 * @return array An updated array of body classes.
	 */
	public function add_gatherpress_body_classes( array $classes ): array {
		$classes[] = 'gp-enabled';
		$classes[] = sprintf( 'gp-theme-%s', esc_attr( get_stylesheet() ) );

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
	 * Register GatherPress post types and taxonomies.
	 *
	 * This method is responsible for registering the GatherPress post types and taxonomies,
	 * as well as adding the online event term. It initializes the necessary content structures
	 * for the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		$this->register_post_types();
		$this->register_taxonomies();
	}

	/**
	 * Register GatherPress post types.
	 *
	 * This method is responsible for registering the GatherPress post types, including
	 * 'Event' and 'Venue' post types. It sets up their labels, supports, and other parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		// Register Event post type and meta.
		register_post_type(
			Event::POST_TYPE,
			Event::get_post_type_registration_args()
		);

		foreach ( Event::get_post_meta_registration_args() as $meta_key => $args ) {
			register_post_meta(
				Event::POST_TYPE,
				$meta_key,
				$args
			);
		}

		// Register Venue post type and meta.
		register_post_type(
			Venue::POST_TYPE,
			Venue::get_post_type_registration_args()
		);

		foreach ( Venue::get_post_meta_registration_args() as $meta_key => $args ) {
			register_post_meta(
				Venue::POST_TYPE,
				$meta_key,
				$args
			);
		}
	}

	/**
	 * Register GatherPress taxonomies.
	 *
	 * This method is responsible for registering the GatherPress taxonomies, including
	 * the 'Topics' taxonomy. It sets up their labels, hierarchical structure, and other parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		// Register Event taxonomy.
		register_taxonomy(
			Event::TAXONOMY,
			Event::POST_TYPE,
			Event::get_taxonomy_registration_args()
		);

		// Register Venue taxonomy.
		register_taxonomy(
			Venue::TAXONOMY,
			Event::POST_TYPE,
			Venue::get_taxonomy_registration_args()
		);
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
		$this->register_taxonomies();

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
		$tables[] = sprintf( Rsvp::TABLE_FORMAT, $wpdb->prefix );

		return $tables;
	}

	/**
	 * Delete event record from custom table when an event is deleted.
	 *
	 * This method is called when an event post is deleted, and it ensures that the corresponding
	 * record in the custom table associated with the event is also deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id An event post ID.
	 * @return void
	 */
	public function delete_event( int $post_id ): void {
		global $wpdb;

		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix, Event::POST_TYPE );

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * Create a custom table if it doesn't exist for the main site or the current site in a network.
	 *
	 * This method checks whether the custom database tables required for the plugin exist
	 * and creates them if they don't. It handles both the main site and, in a multisite network,
	 * the current site.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_create_custom_table(): void {
		$this->create_tables();

		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();

			switch_to_blog( $blog_id );
			$this->create_tables();
			restore_current_blog();
		}
	}

	/**
	 * Create custom database tables for GatherPress events and RSVPs.
	 *
	 * This method creates custom database tables for storing GatherPress event data and RSVP information.
	 * It ensures that the required tables are set up with the appropriate schema.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function create_tables(): void {
		global $wpdb;

		$sql             = array();
		$charset_collate = $wpdb->get_charset_collate();

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix );
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

		$table = sprintf( Rsvp::TABLE_FORMAT, $wpdb->prefix );
		$sql[] = "CREATE TABLE {$table} (
					id bigint(20) unsigned NOT NULL auto_increment,
					post_id bigint(20) unsigned NOT NULL default '0',
					user_id bigint(20) unsigned NOT NULL default '0',
					timestamp datetime NOT NULL default '0000-00-00 00:00:00',
					status varchar(255) default NULL,
					guests tinyint(1) default 0,
					PRIMARY KEY  (id),
					KEY post_id (post_id),
					KEY user_id (user_id),
					KEY status (status)
				) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}

	/**
	 * Populate custom columns for Event post type in the admin dashboard.
	 *
	 * This method is used to display custom columns for Event post types in the WordPress admin dashboard.
	 * It provides additional information for each event, such as its datetime.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column  The name of the column to display.
	 * @param int    $post_id The current post ID.
	 * @return void
	 */
	public function custom_columns( string $column, int $post_id ): void {
		if ( 'datetime' === $column ) {
			$event = new Event( $post_id );

			echo esc_html( $event->get_display_datetime() );
		}
	}

	/**
	 * Set custom columns for Event post type in the admin dashboard.
	 *
	 * This method is used to define custom columns for Event post types in the WordPress admin dashboard.
	 * It adds an additional column for displaying event date and time.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An associative array of column headings.
	 * @return array An updated array of column headings, including the custom columns.
	 */
	public function set_custom_columns( array $columns ): array {
		$placement = 2;
		$insert    = array(
			'datetime' => __( 'Event date &amp; time', 'gatherpress' ),
		);

		return array_slice( $columns, 0, $placement, true ) + $insert + array_slice( $columns, $placement, null, true );
	}

	/**
	 * Make custom columns sortable for Event post type in the admin dashboard.
	 *
	 * This method allows the custom columns, including the 'Event date & time' column,
	 * to be sortable in the WordPress admin dashboard for Event post types.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of sortable columns.
	 * @return array An updated array of sortable columns.
	 */
	public function sortable_columns( array $columns ): array {
		// Add 'datetime' as a sortable column.
		$columns['datetime'] = 'datetime';

		return $columns;
	}

	/**
	 * Returns the event date instead of the publish date for events.
	 *
	 * This method retrieves the event date instead of the publish date for events
	 * based on the plugin settings. It checks if the event date should be used instead
	 * of the publish date and returns the formatted event date accordingly.
	 *
	 * @since 1.0.0
	 *
	 * @param string $the_date The formatted date.
	 * @return string The updated date string, representing the event date.
	 */
	public function get_the_event_date( $the_date ): string {
		$settings       = Settings::get_instance();
		$use_event_date = $settings->get_value( 'general', 'general', 'post_or_event_date' );

		// Check if the post is of the 'Event' post type and if event date should be used.
		if ( Event::POST_TYPE !== get_post_type() || 1 !== intval( $use_event_date ) ) {
			return $the_date;
		}

		// Get the event date and return it as the formatted date.
		$event = new Event( get_the_ID() );

		return $event->get_display_datetime();
	}

	/**
	 * Add Upcoming and Past Events display states to assigned pages.
	 *
	 * This method adds custom display states to assigned pages for "Upcoming Events" and "Past Events"
	 * based on the plugin settings. It checks if the current post object corresponds to any of the assigned
	 * pages and adds display states accordingly.
	 *
	 * @since 1.0.0
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 * @return array An updated array of post display states with custom labels if applicable.
	 */
	public function set_event_archive_labels( array $post_states, WP_Post $post ): array {
		// Retrieve plugin general settings.
		$general = get_option( Utility::prefix_key( 'general' ) );
		$pages   = $general['pages'] ?? '';

		if ( empty( $pages ) || ! is_array( $pages ) ) {
			return $post_states;
		}

		// Define archive pages for "Upcoming Events" and "Past Events".
		$archive_pages = array(
			'past_events'     => json_decode( $pages['past_events'] ),
			'upcoming_events' => json_decode( $pages['upcoming_events'] ),
		);

		// Check if the current post corresponds to any assigned archive page and add display states.
		foreach ( $archive_pages as $key => $value ) {
			if ( ! empty( $value ) && is_array( $value ) ) {
				$page = $value[0];

				if ( $page->id === $post->ID ) {
					$post_states[ sprintf( 'gp_%s', $key ) ] = sprintf( 'GP %s', $page->value );
				}
			}
		}

		return $post_states;
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
	public function check_users_can_register() : void {
		if (
			filter_var( get_option( 'users_can_register' ), FILTER_VALIDATE_BOOLEAN ) ||
			filter_var( get_option( 'gp_suppress_membership_notification' ), FILTER_VALIDATE_BOOLEAN )
		) {
			return;
		}

		if (
			null !== filter_input( INPUT_GET, 'action' ) &&
			'suppress_gp_membership_notification' === filter_input( INPUT_GET, 'action' ) &&
			! empty( filter_input( INPUT_GET, '_wpnonce' ) ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( filter_input( INPUT_GET, '_wpnonce' ) ) ), 'clear-notification' )
		) {
			update_option( 'gp_suppress_membership_notification', true );
		} else {
			Utility::render_template(
				sprintf( '%s/includes/templates/admin/setup/membership-check.php', GATHERPRESS_CORE_PATH ),
				array(),
				true
			);
		}
	}
}
