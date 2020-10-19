<?php
/**
 * Class is responsible for executing plugin setups.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

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
		BuddyPress::get_instance();
		Email::get_instance();
		Query::get_instance();
		Rest_Api::get_instance();
		Role::get_instance();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'change_event_rewrite_rule' ) );
		add_action( 'init', array( $this, 'maybe_create_custom_table' ) );
		add_action( 'delete_post', array( $this, 'delete_event' ) );
		add_action( sprintf( 'manage_%s_posts_custom_column', Event::POST_TYPE ), array( $this, 'custom_columns' ), 10, 2 );

		add_filter( 'block_categories', array( $this, 'block_category' ) );
		add_filter( 'wpmu_drop_tables', array( $this, 'on_site_delete' ) );
		add_filter( 'wp_unique_post_slug', array( $this, 'append_id_to_event_slug' ), 10, 4 );
		add_filter( sprintf( 'manage_%s_posts_columns', Event::POST_TYPE ), array( $this, 'set_custom_columns' ) );
		add_filter( sprintf( 'manage_edit-%s_sortable_columns', Event::POST_TYPE ), array( $this, 'sortable_columns' ) );
		add_filter( 'get_the_date', array( $this, 'get_the_event_date' ), 10, 2 );
		add_filter( 'the_time', array( $this, 'get_the_event_date' ), 10, 2 );
	}

	/**
	 * Add GatherPress block category.
	 *
	 * @param array $categories All the registered block categories.
	 *
	 * @return array
	 */
	public function block_category( $categories ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'gatherpress',
					'title' => __( 'GatherPress', 'gatherpress' ),
				),
			)
		);
	}

	/**
	 * Register the GatherPress post types.
	 *
	 * @since 1.0.0
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
				'menu_position' => 3,
				'supports'      => array(
					'title',
					'editor',
					'thumbnail',
					'comments',
					'revisions',
				),
				'menu_icon'     => 'dashicons-calendar',
				'rewrite'       => array(
					'slug' => 'events',
				),
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
	public function on_site_delete( array $tables ) : array {
		global $wpdb;

		$tables[] = sprintf( Event::TABLE_FORMAT, $wpdb->prefix, Event::POST_TYPE );
		$tables[] = sprintf( Attendee::TABLE_FORMAT, $wpdb->prefix );

		return $tables;
	}

	/**
	 * Add new rewrite rule for event to append Post ID.
	 *
	 * @since 1.0.0
	 */
	public function change_event_rewrite_rule() {
		add_rewrite_rule(
			'^events/([^/]*)-([0-9]+)/?$',
			sprintf(
				'index.php?post_type=%s&postname=$matches[1]&p=$matches[2]',
				Event::POST_TYPE
			),
			'top'
		);
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
		$this->create_table();

		if ( is_multisite() ) {
			$blog_id = get_current_blog_id();

			switch_to_blog( $blog_id );
			$this->create_table();
			restore_current_blog();
		}
	}

	/**
	 * Create custom event table.
	 *
	 * @since 1.0.0
	 */
	protected function create_table() {
		global $wpdb;

		$sql             = array();
		$charset_collate = $GLOBALS['wpdb']->get_charset_collate();

		$table = sprintf( Event::TABLE_FORMAT, $wpdb->prefix, Event::TABLE_FORMAT );
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
					PRIMARY KEY  (id),
					KEY post_id (post_id),
					KEY user_id (user_id),
					KEY status (status)
				) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}

	/**
	 * Ensure that event slugs always have ID appended to URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug        The desired slug (post_name).
	 * @param int    $post_id     Post ID.
	 * @param string $post_status No uniqueness checks are made if the post is still draft or pending.
	 * @param string $post_type   Post type.
	 *
	 * @return string
	 */
	public function append_id_to_event_slug( string $slug, int $post_id, string $post_status, string $post_type ) : string {
		if ( Event::POST_TYPE !== $post_type ) {
			return $slug;
		}

		if ( 1 > intval( $post_id ) ) {
			return $slug;
		}

		if ( ! preg_match( '/-(\d+)$/', $slug, $matches ) ) {
			return "{$slug}-{$post_id}";
		}

		$slug_id = intval( $matches[1] );

		if ( $slug_id === $post_id ) {
			return $slug;
		}

		return preg_replace( '/-\d+$/', '-' . $post_id, $slug );
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
	public function set_custom_columns( array $columns ) : array {
		$placement = 2;
		$insert    = array(
			'datetime' => __( 'Date & time', 'gatherpress' ),
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
	public function sortable_columns( array $columns ) : array {
		$columns['datetime'] = 'datetime';

		return $columns;
	}

	/**
	 * Returns the event date instead of publish date for events.
	 *
	 * @since 1.0.0
	 *
	 * @param string $the_date The formatted date.
	 * @param string $format   PHP date format.
	 *
	 * @return string
	 */
	public function get_the_event_date( $the_date, $format ) : string {
		global $post;

		if ( ! is_a( $post, '\WP_Post' ) && Event::POST_TYPE !== $post->post_type ) {
			return $the_date;
		}

		if ( empty( $format ) ) {
			$format = get_option( 'date_format' );
		}

		$event = new Event( $post->ID );

		return $event->get_datetime_start( $format );
	}

}
