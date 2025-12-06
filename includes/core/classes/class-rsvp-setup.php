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

use GatherPress\Core\Blocks\Rsvp_Form;
use GatherPress\Core\Rsvp_Token;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Comment;
use WP_User;

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
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'handle_rsvp_token' ) );
		add_action( 'wp_after_insert_post', array( $this, 'maybe_process_waiting_list' ) );
		add_action( 'admin_menu', array( $this, 'add_rsvp_submenu_page' ) );
		add_filter( 'comment_notification_recipients', array( $this, 'remove_rsvp_notification_emails' ), 10, 2 );

		add_filter(
			sprintf( 'set_screen_option_%s_per_page', Rsvp::COMMENT_TYPE ),
			array( $this, 'set_rsvp_screen_options' ),
			10,
			3
		);
		add_filter( 'parent_file', array( $this, 'highlight_admin_menu' ) );
		add_filter( 'get_comments_number', array( $this, 'adjust_comments_number' ), 10, 2 );
		add_filter( 'comment_text', array( $this, 'maybe_hide_rsvp_comment_content' ), 10, 2 );
	}

	/**
	 * Register custom comment taxonomy for RSVPs.
	 *
	 * Registers a custom taxonomy 'gatherpress_rsvp' for managing RSVP related functionalities specifically for comments.
	 *
	 * @since 1.0.0
	 *
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
	 * Get user identifier for RSVP operations.
	 *
	 * Returns the current user ID if logged in, or email address from
	 * a valid RSVP token if accessing via magic link.
	 *
	 * @since 1.0.0
	 *
	 * @return string|int User ID if logged in, email address if via token, or 0 if neither.
	 */
	public function get_user_identifier() {
		$user_identifier = get_current_user_id();
		$rsvp_token      = Rsvp_Token::from_url_parameter();

		if ( $rsvp_token && ! empty( $rsvp_token->get_comment() ) ) {
			$user_identifier = $rsvp_token->get_email();
		}

		return $user_identifier;
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
	 *
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

		$list_table = new RSVP_List_Table();

		add_action(
			"load-$hook",
			static function () use ( $list_table ) {
				add_screen_option(
					'per_page',
					array(
						'label'   => __( 'RSVPs per page', 'gatherpress' ),
						'default' => RSVP_List_Table::DEFAULT_PER_PAGE,
						'option'  => sprintf( '%s_per_page', Rsvp::COMMENT_TYPE ),
					)
				);

				$list_table->register_column_options();
			}
		);
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
	public function render_rsvp_admin_page(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( Rsvp::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage RSVPs.', 'gatherpress' ), 403 );
		}

		$rsvp_table  = new RSVP_List_Table();
		$search_term = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$status      = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';
		$event       = isset( $_REQUEST['event'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['event'] ) ) : '';

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/rsvp/list-table.php', GATHERPRESS_CORE_PATH ),
			array(
				'rsvp_table'  => $rsvp_table,
				'search_term' => $search_term,
				'status'      => $status,
				'event'       => $event,
			),
			true
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Filters the comment content to hide private notes for non-moderators.
	 *
	 * Checks if the comment is a GatherPress RSVP comment and applies visibility
	 * rules based on user permissions. Returns empty string for non-moderators
	 * to protect private RSVP information.
	 *
	 * @since 1.0.0
	 *
	 * @param string          $comment_content Text of the comment.
	 * @param WP_Comment|null $comment        The comment object.
	 *
	 * @return string Filtered comment text.
	 */
	public function maybe_hide_rsvp_comment_content( string $comment_content, ?WP_Comment $comment ): string {
		if ( null === $comment || Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return $comment_content;
		}

		if ( ! current_user_can( Rsvp::CAPABILITY ) ) {
			return '';
		}

		return $comment_content;
	}

	/**
	 * Registers screen options for the RSVP administration page.
	 *
	 * Adds options to control the number of RSVPs displayed per page and
	 * which columns are visible in the table. These settings are accessible
	 * via the Screen Options tab on the RSVP admin page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_rsvp_screen_options(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || Rsvp::COMMENT_TYPE !== $_GET['page'] ) {
			return;
		}

		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'RSVPs per page', 'gatherpress' ),
				'default' => RSVP_List_Table::DEFAULT_PER_PAGE,
				'option'  => sprintf( '%s_per_page', Rsvp::COMMENT_TYPE ),
			)
		);

		$screen = get_current_screen();

		if ( $screen ) {
			$screen->add_option( 'columns', array() );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Saves user preferences for per-page display options.
	 *
	 * Processes and saves the screen options for controlling how many RSVPs
	 * display per page in the admin table. Only processes options relevant
	 * to the RSVP listing page.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $status Screen option value. Default false to skip.
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 *
	 * @return mixed The screen option value or false to use default.
	 */
	public function set_rsvp_screen_options( $status, $option, $value ) {
		if ( sprintf( '%s_per_page', Rsvp::COMMENT_TYPE ) === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Ensures the correct parent menu item is highlighted for the RSVP admin page.
	 *
	 * When viewing the RSVP admin page, this function sets the appropriate parent
	 * menu item to be highlighted in the admin menu. It also adds a filter to
	 * set the correct submenu item.
	 *
	 * @since 1.0.0
	 *
	 * @param string $parent_file The current parent file.
	 *
	 * @return string Modified parent file path to highlight the correct menu item.
	 */
	public function highlight_admin_menu( string $parent_file ): string {
		global $plugin_page;

		if ( isset( $plugin_page ) && Rsvp::COMMENT_TYPE === $plugin_page ) {
			add_filter( 'submenu_file', array( $this, 'set_submenu_file' ) );

			return sprintf( 'edit.php?post_type=%s', Event::POST_TYPE );
		}

		return $parent_file;
	}

	/**
	 * Sets the active submenu file for the RSVP admin page.
	 *
	 * Ensures the RSVP submenu item is correctly highlighted when viewing
	 * the RSVP admin page. Works with WordPress admin menu highlighting
	 * system to indicate the current active page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The submenu file slug to mark as active.
	 */
	public function set_submenu_file(): string {
		return Rsvp::COMMENT_TYPE;
	}

	/**
	 * Removes email notifications for RSVP comments.
	 *
	 * Prevents WordPress from sending standard comment notification emails for RSVP submissions.
	 * RSVPs should not trigger the same notifications as regular comments since they are
	 * specialized interactions with events rather than commentary. This avoids confusing
	 * event authors with irrelevant comment notifications.
	 *
	 * @todo Implement custom RSVP notification emails for event organizers with relevant
	 *       information about new RSVPs and changes to existing RSVPs.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $emails     Array of email addresses to notify.
	 * @param string $comment_id The comment ID.
	 *
	 * @return array Empty array for RSVP comments, original array otherwise.
	 */
	public function remove_rsvp_notification_emails( array $emails, string $comment_id ): array {
		if ( get_comment_type( (int) $comment_id ) !== Rsvp::COMMENT_TYPE ) {
			return $emails;
		}

		return array();
	}

	/**
	 * Handle RSVP token from URL and approve associated comment.
	 *
	 * Validates the RSVP token from the URL parameter and automatically
	 * approves the corresponding comment if the token is valid.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_rsvp_token(): void {
		$rsvp_token = Rsvp_Token::from_url_parameter();

		if ( $rsvp_token ) {
			$rsvp_token->approve_comment();
		}
	}
}
