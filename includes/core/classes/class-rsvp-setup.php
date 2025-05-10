<?php
/**
 * File comment block for Rsvp_Setup class.
 *
 * This file contains the definition of the Rsvp_Setup class, which handles
 * setup tasks related to RSVP functionality within the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_List_Table;

require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

/**
 * Handles setup tasks related to RSVP functionality.
 *
 * The Rsvp_Setup class initializes necessary hooks and configurations for managing RSVPs.
 * It registers a custom taxonomy for RSVPs and adjusts comment counts specifically for events.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */
class Rsvp_Setup {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'wp_after_insert_post', array( $this, 'maybe_process_waiting_list' ) );
		add_action('admin_menu', array($this, 'add_rsvp_submenu_page'));
		add_action(
			sprintf( 'load-gp_event_page_%s', Rsvp::COMMENT_TYPE ),
			array( $this, 'add_rsvp_screen_options' )
		);

		add_filter( 'set-screen-option', array( $this, 'set_rsvp_screen_options' ), 10, 3 );
		add_filter( 'parent_file', array( $this, 'highlight_admin_menu' ) );
		add_filter( 'get_comments_number', array( $this, 'adjust_comments_number' ), 10, 2 );
		add_filter( 'admin_comment_types_dropdown', array( $this, 'register_rsvp_comment_type' ) );
	}

	/**
	 * Register custom comment taxonomy for RSVPs.
	 *
	 * Registers a custom taxonomy 'gatherpress_rsvp' for managing RSVP related functionalities specifically for comments.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			Rsvp::TAXONOMY,
			'comment',
			array(
				'labels'             => array(),
				'hierarchical'       => false,
				'public'             => true,
				'show_ui'            => false,
				'show_admin_column'  => false,
				'query_var'          => true,
				'publicly_queryable' => false,
				'show_in_rest'       => true,
			)
		);
	}

	/**
	 * Adjusts the number of comments displayed for event posts.
	 *
	 * Retrieves and returns the count of approved RSVP comments for event posts.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comments_number The original number of comments.
	 * @param int $post_id         The ID of the post.
	 *
	 * @return int Adjusted number of comments.
	 */
	public function adjust_comments_number( int $comments_number, int $post_id ): int {
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return $comments_number;
		}

		$comment_count = get_comment_count( $post_id );

		return $comment_count['approved'];
	}

	/**
	 * Process the waiting list for an event after it has been saved.
	 *
	 * Checks if the saved post is an event and not an autosave,
	 * then processes any waiting list entries if applicable.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
	public function maybe_process_waiting_list( int $post_id ): void {
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$rsvp = new Rsvp( $post_id );

		$rsvp->check_waiting_list();
	}

	/**
	 * Adds GatherPress RSVP to the comment types dropdown in the admin.
	 *
	 * This filter callback adds the 'gatherpress_rsvp' comment type to the dropdown
	 * filter in the WordPress admin comments screen, allowing admins to filter and
	 * view only RSVP responses.
	 *
	 * @since 1.0.0
	 *
	 * @param array $comment_types Array of comment types.
	 * @return array Modified array with RSVP comment type added.
	 */
	public function register_rsvp_comment_type( array $comment_types ): array {
		$comment_types['gatherpress_rsvp'] = __( 'RSVPs', 'gatherpress' );

		return $comment_types;
	}

	/**
	 * Adds a submenu page for managing GatherPress RSVPs.
	 *
	 * This method adds a submenu page under the Events menu in the WordPress admin.
	 * The page provides a dedicated interface for viewing and managing RSVPs
	 * to GatherPress events, with capabilities similar to the WordPress comments
	 * page but specifically tailored for RSVPs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_rsvp_submenu_page(): void {
		$hook = add_submenu_page(
			sprintf( 'edit.php?post_type=%s', Event::POST_TYPE ),
			__( 'RSVPs', 'gatherpress' ),
			__( 'RSVPs', 'gatherpress' ),
			Rsvp::CAPABILITY,
			Rsvp::COMMENT_TYPE,
			array( $this, 'render_rsvp_admin_page' ),
			2
		);

		// Add these hooks to run only on our page
		add_action( "load-$hook", array( $this, 'add_rsvp_screen_options' ) );
	}

	/**
	 * Renders the RSVP admin page in the WordPress dashboard.
	 *
	 * This method displays the custom admin interface for managing GatherPress RSVPs.
	 * It initializes and displays the RSVP_List_Table which contains all RSVPs
	 * with options for filtering, bulk actions, and individual RSVP management.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function render_rsvp_admin_page(): void {
		$rsvp_table = new RSVP_List_Table();

		$rsvp_table->prepare_items();

		echo '<div class="wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'RSVPs', 'gatherpress' ) . '</h1>';
		echo '<hr class="wp-header-end">';

		$rsvp_table->process_bulk_action();

		echo '<form method="post">';

		$rsvp_table->display();

		echo '</form>';
		echo '</div>';
	}

	/**
	 * Adds screen options for the RSVP admin page.
	 *
	 * @param WP_Screen $screen Current WP_Screen object.
	 * @return void
	 */
	public function add_rsvp_screen_options( $screen ): void {
		// The screen ID will be something like gp_event_page_gatherpress-rsvps
		// But to be safer, let's check if the page parameter matches our page
		if ( ! isset( $_GET['page'] ) || Rsvp::COMMENT_TYPE !== $_GET['page'] ) {
			return;
		}

		// Add per page screen option
		add_screen_option( 'per_page', array(
			'label'   => __( 'RSVPs per page', 'gatherpress' ),
			'default' => 20,
			'option'  => sprintf( '%s_per_page', Rsvp::COMMENT_TYPE )
		) );
	}

	/**
	 * Saves screen options for the RSVP admin page.
	 *
	 * @param mixed  $status Screen option value. Default false to skip.
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 * @return mixed
	 */
	public function set_rsvp_screen_options( $status, $option, $value ) {
		if ( sprintf( '%s_per_page', Rsvp::COMMENT_TYPE ) === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Highlights the correct submenu item when on the RSVP admin page.
	 *
	 * @param string $parent_file The parent file.
	 * @return string The modified parent file.
	 */
	public function highlight_admin_menu( string $parent_file ): string {
		global $plugin_page, $submenu_file;

		if ( isset( $plugin_page ) && Rsvp::COMMENT_TYPE === $plugin_page ) {
			$submenu_file = Rsvp::COMMENT_TYPE;
			$parent_file  = sprintf( 'edit.php?post_type=%s', Event::POST_TYPE );
		}

		return $parent_file;
	}
}
