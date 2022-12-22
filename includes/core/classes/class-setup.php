<?php
/**
 * Class is responsible for executing plugin setups.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use \GatherPress\Core\Traits\Singleton;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Setup.
 */
class Setup {

	use Singleton;

	/**
	 * Setup constructor.
	 */
	protected function __construct() {
		$this->instantiate_classes();
		$this->setup_hooks();
	}

	/**
	 * Instantiate singletons.
	 */
	protected function instantiate_classes() {
		Assets::get_instance();
		Block::get_instance();
		Query::get_instance();
		Rest_Api::get_instance();
		Settings::get_instance();
		Venue::get_instance();
		// @todo move these classes to a `buddypress` directory in plugin.
		// BuddyPress::get_instance();
		// Email::get_instance();
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'gatherpress', CLI::class );
		}
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		register_activation_hook( GATHERPRESS_CORE_FILE, array( $this, 'activate_gatherpress_plugin' ) );
		register_deactivation_hook( GATHERPRESS_CORE_FILE, array( $this, 'deactivate_gatherpress_plugin' ) );
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'init', array( $this, 'maybe_create_custom_table' ) );
		add_action( 'delete_post', array( $this, 'delete_event' ) );
		add_action( sprintf( 'manage_%s_posts_custom_column', Event::POST_TYPE ), array( $this, 'custom_columns' ), 10, 2 );
		add_action( 'init', array( $this, 'maybe_flush_gatherpress_rewrite_rules' ) );

		add_filter( 'block_categories_all', array( $this, 'block_category' ) );
		add_filter( 'wpmu_drop_tables', array( $this, 'on_site_delete' ) );
		add_filter( sprintf( 'manage_%s_posts_columns', Event::POST_TYPE ), array( $this, 'set_custom_columns' ) );
		add_filter( sprintf( 'manage_edit-%s_sortable_columns', Event::POST_TYPE ), array( $this, 'sortable_columns' ) );
		add_filter( 'get_the_date', array( $this, 'get_the_event_date' ) );
		add_filter( 'the_time', array( $this, 'get_the_event_date' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'display_post_states', array( $this, 'set_event_archive_labels' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'timezone_check_admin_notice' ) );
	}

	/**
	 * Activate GatherPress plugin.
	 *
	 * @return void
	 */
	public function activate_gatherpress_plugin() {
		if ( ! get_option( 'gatherpress_flush_rewrite_rules_flag' ) ) {
			add_option( 'gatherpress_flush_rewrite_rules_flag', true );
		}
	}

	/**
	 * Activate GatherPress plugin.
	 *
	 * @return void
	 */
	public function deactivate_gatherpress_plugin() {
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrite rules if the previously added flag exists,
	 * and then remove the flag.
	 */
	public function maybe_flush_gatherpress_rewrite_rules() {
		if ( get_option( 'gatherpress_flush_rewrite_rules_flag' ) ) {
			flush_rewrite_rules();
			delete_option( 'gatherpress_flush_rewrite_rules_flag' );
		}
	}

	/**
	 * AddAttendanceSelector.js body class for active theme.
	 *
	 * @param array $classes Body classes.
	 *
	 * @return mixed
	 */
	public function body_class( $classes ) {
		$classes[] = 'gatherpress-enabled';
		$classes[] = sprintf( 'gatherpress-theme-%s', esc_attr( get_stylesheet() ) );

		return $classes;
	}

	/**
	 * Add GatherPress block category.
	 *
	 * @param array $block_categories All the registered block categories.
	 *
	 * @return array
	 */
	public function block_category( $block_categories ) {
		$gatherpress_category = array(
			'slug'  => 'gatherpress',
			'title' => __( 'GatherPress', 'gatherpress' ),
			'icon'  => 'nametag',
		);

		array_unshift( $block_categories, $gatherpress_category );

		return $block_categories;
	}

	/**
	 * Display custom admin notice function
	 *
	 * @return void
	 */
	public function timezone_check_admin_notice() {
		$timezone = get_option( 'timezone_string' );
		if ( $timezone ) {
			return;
		}
		$class   = 'notice notice-error is-dismissible';
		$string1 = __( 'Please set', 'gatherpress' );
		$string2 = __( 'your timezone', 'gatherpress' );
		$string3 = __( 'in order to ensure proper GatherPress settings!', 'gatherpress' );

		printf(
			'<div class="%1$s"><p>%2$s <a href="%3$s">%4$s</a> %5$s</p></div>',
			esc_attr( $class ),
			esc_html( $string1 ),
			esc_url( get_admin_url() . 'options-general.php#timezone_string' ),
			esc_html( $string2 ),
			esc_html( $string3 )
		);
	}

	/**
	 * Register the GatherPress post types and taxonomies.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		$this->register_post_types();
		$this->register_taxonomies();
	}

	/**
	 * Register the GatherPress post types.
	 *
	 * @todo cleanup registering so it's not so repeated.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_post_types() {
		register_post_type(
			Event::POST_TYPE,
			array(
				'labels'        => array(
					'name'               => _x( 'Events', 'Post Type General Name', 'gatherpress' ),
					'singular_name'      => _x( 'Event', 'Post Type Singular Name', 'gatherpress' ),
					'menu_name'          => __( 'Events', 'gatherpress' ),
					'all_items'          => __( 'All Events', 'gatherpress' ),
					'view_item'          => __( 'View Event', 'gatherpress' ),
					'add_new_item'       => __( 'Add New Event', 'gatherpress' ),
					'add_new'            => __( 'Add New', 'gatherpress' ),
					'edit_item'          => __( 'Edit Event', 'gatherpress' ),
					'update_item'        => __( 'Update Event', 'gatherpress' ),
					'search_items'       => __( 'Search Events', 'gatherpress' ),
					'not_found'          => __( 'Not Found', 'gatherpress' ),
					'not_found_in_trash' => __( 'Not found in Trash', 'gatherpress' ),
				),
				'show_in_rest'  => true,
				'public'        => true,
				'hierarchical'  => false,
				'template'      => array(
					array( 'gatherpress/event-date' ),
					array( 'gatherpress/add-to-calendar' ),
					array( 'gatherpress/venue' ),
					array( 'gatherpress/attendance-selector' ),
					array( 'gatherpress/attendance-list' ),
				),
				'menu_position' => 4,
				'supports'      => array(
					'title',
					'editor',
					'excerpt',
					'thumbnail',
					'comments',
					'revisions',
				),
				'menu_icon'     => 'dashicons-nametag',
				'rewrite'       => array(
					'slug' => 'events',
				),
			)
		);

		register_post_type(
			Venue::POST_TYPE,
			array(
				'labels'       => array(
					'name'               => _x( 'Venues', 'Post Type General Name', 'gatherpress' ),
					'singular_name'      => _x( 'Venue', 'Post Type Singular Name', 'gatherpress' ),
					'menu_name'          => __( 'Venues', 'gatherpress' ),
					'all_items'          => __( 'Venues', 'gatherpress' ),
					'view_item'          => __( 'View Venue', 'gatherpress' ),
					'add_new_item'       => __( 'Add New Venue', 'gatherpress' ),
					'add_new'            => __( 'Add New', 'gatherpress' ),
					'edit_item'          => __( 'Edit Venue', 'gatherpress' ),
					'update_item'        => __( 'Update Venue', 'gatherpress' ),
					'search_items'       => __( 'Search Venues', 'gatherpress' ),
					'not_found'          => __( 'Not Found', 'gatherpress' ),
					'not_found_in_trash' => __( 'Not found in Trash', 'gatherpress' ),
				),
				'show_in_rest' => true,
				'public'       => true,
				'hierarchical' => false,
				'show_in_menu' => 'edit.php?post_type=gp_event',
				'supports'     => array(
					'title',
					'editor',
					'thumbnail',
					'revisions',
					'custom-fields',
				),
				'menu_icon'    => 'dashicons-location',
				'template'     => array(
					array( 'gatherpress/venue-information' ),
				),
				'rewrite'      => array(
					'slug' => 'venues',
				),
			)
		);

		register_post_meta(
			Venue::POST_TYPE,
			'_venue_information',
			array(
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
			)
		);
	}

	/**
	 * Register the GatherPress taxonomies.
	 *
	 * @todo cleanup registering so it's not so repeated.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		register_taxonomy(
			Event::TAXONOMY,
			Event::POST_TYPE,
			array(
				'labels'            => array(
					'name'              => _x( 'Topics', 'taxonomy general name', 'gatherpress' ),
					'singular_name'     => _x( 'Topic', 'taxonomy singular name', 'gatherpress' ),
					'search_items'      => __( 'Search Topics', 'gatherpress' ),
					'all_items'         => __( 'All Topics', 'gatherpress' ),
					'view_item'         => __( 'View Topic', 'gatherpress' ),
					'parent_item'       => __( 'Parent Topic', 'gatherpress' ),
					'parent_item_colon' => __( 'Parent Topic:', 'gatherpress' ),
					'edit_item'         => __( 'Edit Topic', 'gatherpress' ),
					'update_item'       => __( 'Update Topic', 'gatherpress' ),
					'add_new_item'      => __( 'Add New Topic', 'gatherpress' ),
					'new_item_name'     => __( 'New Topic Name', 'gatherpress' ),
					'not_found'         => __( 'No Topics Found', 'gatherpress' ),
					'back_to_items'     => __( 'Back to Topics', 'gatherpress' ),
					'menu_name'         => __( 'Topics', 'gatherpress' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => array( 'slug' => 'topic' ),
				'show_in_rest'      => true,
			)
		);

		register_taxonomy(
			Venue::TAXONOMY,
			Event::POST_TYPE,
			array(
				'labels'            => array(),
				'hierarchical'      => false,
				'public'            => true,
				'show_ui'           => false,
				'show_admin_column' => false,
				'query_var'         => true,
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Delete custom table on site deletion.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tables Array of names of the site tables to be dropped.
	 *
	 * @return array
	 */
	public function on_site_delete( array $tables ): array {
		global $wpdb;

		$tables[] = sprintf( Event::TABLE_FORMAT, $wpdb->prefix, Event::POST_TYPE );
		$tables[] = sprintf( Attendee::TABLE_FORMAT, $wpdb->prefix );

		return $tables;
	}

	/**
	 * Delete event record from custom table when event is deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id An event post ID.
	 */
	public function delete_event( int $post_id ) {
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
	 * Maybe create custom table if doesn't exist for main site or current site in network.
	 *
	 * @since 1.0.0
	 */
	public function maybe_create_custom_table() {
		$this->create_tables();

		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();

			switch_to_blog( $blog_id );
			$this->create_tables();
			restore_current_blog();
		}
	}

	/**
	 * Create custom event table.
	 *
	 * @since 1.0.0
	 */
	protected function create_tables() {
		global $wpdb;

		$sql             = array();
		$charset_collate = $GLOBALS['wpdb']->get_charset_collate();

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

		$table = sprintf( Attendee::TABLE_FORMAT, $wpdb->prefix );
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
	 * Populate custom columns for Event post type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column  The name of the column to display.
	 * @param int    $post_id The current post ID.
	 */
	public function custom_columns( string $column, int $post_id ) {
		$event = new Event( $post_id );

		switch ( $column ) {
			case 'datetime':
				echo esc_html( $event->get_display_datetime() );
				break;
		}
	}

	/**
	 * Set custom columns for Event post type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An associative array of column headings.
	 *
	 * @return array
	 */
	public function set_custom_columns( array $columns ): array {
		$placement = 2;
		$insert    = array(
			'datetime' => __( 'Event date &amp; time', 'gatherpress' ),
		);

		return array_slice( $columns, 0, $placement, true ) + $insert + array_slice( $columns, $placement, null, true );
	}

	/**
	 * Make custom columns sortable for Event post type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $columns An array of sortable columns.
	 *
	 * @return array
	 */
	public function sortable_columns( array $columns ): array {
		$columns['datetime'] = 'datetime';

		return $columns;
	}

	/**
	 * Returns the event date instead of publish date for events.
	 *
	 * @since 1.0.0
	 *
	 * @param string $the_date The formatted date.
	 *
	 * @return string
	 */
	public function get_the_event_date( $the_date ): string {
		global $post;

		if ( ! is_a( $post, '\WP_Post' ) || Event::POST_TYPE !== $post->post_type ) {
			return $the_date;
		}

		$event = new Event( $post->ID );

		return $event->get_display_datetime();
	}

	/**
	 * Add Upcoming and Past Events display states to assigned pages.
	 *
	 * @param array    $post_states An array of post display states.
	 * @param \WP_Post $post        The current post object.
	 *
	 * @return array
	 */
	public function set_event_archive_labels( array $post_states, \WP_Post $post ) {
		$general = get_option( Utility::prefix_key( 'general' ) );
		$pages   = $general['pages'];

		if ( empty( $pages ) || ! is_array( $pages ) ) {
			return $post_states;
		}

		$archive_pages = array(
			'past_events'     => json_decode( $pages['past_events'] ),
			'upcoming_events' => json_decode( $pages['upcoming_events'] ),
		);

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

}
