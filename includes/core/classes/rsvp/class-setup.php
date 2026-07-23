<?php
/**
 * File comment block for Setup class.
 *
 * This file contains the definition of the Setup class, which handles
 * setup tasks related to RSVP functionality within the GatherPress plugin.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.30.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Provider_Registry;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Block_Type_Registry;
use WP_Comment;

/**
 * Handles setup tasks related to RSVP functionality.
 *
 * The Setup class initializes necessary hooks and configurations for managing RSVPs.
 * It registers a custom taxonomy for RSVPs and adjusts comment counts specifically for events.
 * Also owns instantiation of the Rsvp\* sibling singletons (Cleanup, Form, Query, Token) so
 * the outer `Setup::instantiate_classes()` can hand off the whole rsvp subsystem with a
 * single `Rsvp\Setup::get_instance()` line — same shape as `Settings::instantiate_classes()`.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.34.0
 */
final class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The RSVP list table instance.
	 *
	 * @since 0.34.0
	 * @var List_Table|null
	 */
	protected $list_table = null;

	/**
	 * Class constructor.
	 *
	 * Instantiates the sibling Rsvp\* singletons before wiring hooks so
	 * `Setup::instantiate_classes()` can hand off the whole rsvp
	 * subsystem with a single `Rsvp\Setup::get_instance()` line — same
	 * shape as `Settings::instantiate_classes()`.
	 *
	 * @since 0.34.0
	 */
	protected function __construct() {
		$this->instantiate_classes();
		$this->setup_hooks();
	}

	/**
	 * Instantiate each Rsvp\* sibling singleton.
	 *
	 * Keeps the outer `Setup::instantiate_classes()` slim — adding a new
	 * Rsvp\* class lands as a single line here rather than edits to
	 * Setup. Each subclass is a singleton, so repeat calls are safe.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function instantiate_classes(): void {
		Cleanup::get_instance();
		Form::get_instance();
		Query::get_instance();
		Provider_Registry::get_instance();
	}

	/**
	 * Gets the per page option name for RSVP list table.
	 *
	 * @since 0.34.0
	 *
	 * @return string The per page option name.
	 */
	private function get_per_page_option(): string {
		return sprintf( '%s_per_page', Rsvp::COMMENT_TYPE );
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'handle_rsvp_token' ) );
		// Priority 11 ensures post types are already registered (priority 10) before removing RSVP support.
		add_action( 'init', array( $this, 'maybe_disable_rsvp' ), 11 );
		add_action( 'wp_after_insert_post', array( $this, 'maybe_process_waiting_list' ) );
		add_action( 'wp_after_insert_post', array( $this, 'maybe_set_rsvp_meta_default' ) );
		add_action( 'admin_menu', array( $this, 'add_rsvp_submenu_page' ) );
		add_filter( 'allowed_block_types_all', array( $this, 'filter_rsvp_block_types' ) );
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
	 * Registers a custom taxonomy 'gatherpress_rsvp' for managing RSVP related functionalities
	 * specifically for comments.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			Status::TAXONOMY,
			'comment',
			array(
				'labels'             => array(),
				'hierarchical'       => false,
				'public'             => true,
				'show_ui'            => false,
				'show_admin_column'  => false,
				'query_var'          => true,
				'publicly_queryable' => false,
				'rewrite'            => false,
				'show_in_rest'       => true,
			)
		);

		register_taxonomy(
			Provider::TAXONOMY,
			'comment',
			array(
				'labels'             => array(),
				'hierarchical'       => false,
				'public'             => true,
				'show_ui'            => false,
				'show_admin_column'  => false,
				'query_var'          => true,
				'publicly_queryable' => false,
				'rewrite'            => false,
				'show_in_rest'       => true,
			)
		);
	}

	/**
	 * Disables RSVP sitewide when the master RSVP switch is turned off.
	 *
	 * Removes the `gatherpress-rsvp` post type support from all post types
	 * that currently support it. This causes all existing `post_type_supports()`
	 * guards throughout the plugin to return false, effectively disabling RSVP
	 * functionality without requiring individual checks everywhere.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function maybe_disable_rsvp(): void {
		if ( 'disabled' !== Settings::get_instance()->get( 'rsvp_mode' ) ) {
			return;
		}

		foreach ( get_post_types_by_support( 'gatherpress-rsvp' ) as $post_type ) {
			remove_post_type_support( $post_type, 'gatherpress-rsvp' );
		}
	}

	/**
	 * Filters RSVP blocks out of the allowed block types when RSVP is globally disabled.
	 *
	 * Removes all `gatherpress/rsvp*` blocks from the block inserter while leaving
	 * existing RSVP blocks already placed in posts fully intact for editing.
	 *
	 * @since 0.34.0
	 *
	 * @param bool|string[] $allowed_block_types Array of block type slugs or true for all blocks.
	 *
	 * @return bool|string[] Filtered allowed block types.
	 */
	public function filter_rsvp_block_types( $allowed_block_types ): bool|array {
		$settings         = Settings::get_instance();
		$remove_all_rsvp  = 'disabled' === $settings->get( 'rsvp_mode' );
		$remove_open_form = ! $settings->get( 'enable_open_rsvp' );

		if ( ! $remove_all_rsvp && ! $remove_open_form ) {
			return $allowed_block_types;
		}

		// Build list from all registered blocks when all are currently allowed.
		if ( true === $allowed_block_types ) {
			$allowed_block_types = array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() );
		}

		if ( is_array( $allowed_block_types ) ) {
			return array_values(
				array_filter(
					$allowed_block_types,
					static function ( $name ) use ( $remove_all_rsvp, $remove_open_form ): bool {
						if ( $remove_all_rsvp && str_contains( $name, 'gatherpress/rsvp' ) ) {
							return false;
						}
						if ( $remove_open_form && 'gatherpress/rsvp-form' === $name ) {
							return false;
						}
						return true;
					}
				)
			);
		}

		return $allowed_block_types;
	}

	/**
	 * Get user identifier for RSVP operations.
	 *
	 * Returns the current user ID if logged in, or email address from
	 * a valid RSVP token if accessing via magic link.
	 *
	 * @since 0.34.0
	 *
	 * @return string|int User ID if logged in, email address if via token, or 0 if neither.
	 */
	public function get_user_identifier(): string|int {
		$user_identifier = get_current_user_id();
		$rsvp_token      = Token::from_url_parameter();

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
	 * @since 0.34.0
	 *
	 * @param int $comments_number The original number of comments.
	 * @param int $post_id         The ID of the post.
	 *
	 * @return int Adjusted number of comments.
	 */
	public function adjust_comments_number( int $comments_number, int $post_id ): int {
		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-rsvp' ) ) {
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
	 * @since 0.34.0
	 *
	 * @param int $post_id The ID of the post being saved.
	 *
	 * @return void
	 */
	public function maybe_process_waiting_list( int $post_id ): void {
		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-rsvp' ) ) {
			return;
		}

		$rsvp = new Rsvp( $post_id );

		$rsvp->check_waiting_list();
	}

	/**
	 * Delegates to Rsvp::initialize_enabled() for the wp_after_insert_post hook.
	 *
	 * @since 0.34.0
	 *
	 * @param int $post_id The ID of the post being saved.
	 *
	 * @return void
	 */
	public function maybe_set_rsvp_meta_default( int $post_id ): void {
		// Skip non-event post types early to avoid an unnecessary Rsvp instantiation.
		if ( ! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-rsvp' ) ) {
			return;
		}

		( new Rsvp( $post_id ) )->initialize_enabled();
	}

	/**
	 * Adds an RSVPs submenu page to every RSVP-supporting post type's menu.
	 *
	 * Each post type declaring `gatherpress-rsvp` support gets its own
	 * RSVPs page under its admin menu, scoped to that post type's RSVPs —
	 * the same way WordPress scopes post lists per type (#1849). The page
	 * provides a dedicated interface for viewing and managing RSVPs, with
	 * capabilities similar to the WordPress comments page but specifically
	 * tailored for RSVPs.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function add_rsvp_submenu_page(): void {
		// Do not show any RSVPs submenu when RSVP is globally disabled.
		if ( 'disabled' === Settings::get_instance()->get( 'rsvp_mode' ) ) {
			return;
		}

		// When no post type declares `gatherpress-rsvp` support — e.g. a
		// companion plugin removed it from the event post type — the loop
		// simply adds nothing (#1849).
		foreach ( get_post_types_by_support( 'gatherpress-rsvp' ) as $post_type ) {
			$hook = add_submenu_page(
				sprintf( 'edit.php?post_type=%s', $post_type ),
				__( 'RSVPs', 'gatherpress' ),
				__( 'RSVPs', 'gatherpress' ),
				Rsvp::CAPABILITY,
				Rsvp::COMMENT_TYPE,
				array( $this, 'render_rsvp_admin_page' ),
				2
			);

			if ( false === $hook ) {
				continue;
			}

			add_action(
				sprintf( 'load-%s', $hook ),
				array( $this, 'prepare_rsvp_admin_page' )
			);
		}
	}

	/**
	 * Prepares the RSVP admin page for the post type being viewed.
	 *
	 * Runs on the `load-{$hook}` action of each per-post-type RSVPs page.
	 * Instantiates the list table scoped to the current screen's post type
	 * and registers its screen options (#1849).
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function prepare_rsvp_admin_page(): void {
		$screen_post_type = get_current_screen()->post_type ?? '';

		// Fall back to the event post type when the screen doesn't carry a
		// supporting post type (defensive; the submenu is only registered
		// for supporting post types).
		if ( ! post_type_supports( $screen_post_type, 'gatherpress-rsvp' ) ) {
			$screen_post_type = Event::POST_TYPE;
		}

		$this->list_table = new List_Table( array( 'post_type' => $screen_post_type ) );

		$this->setup_rsvp_list_table_screen_options();
	}

	/**
	 * Sets up screen options for the RSVP list table.
	 *
	 * This method registers the per-page screen option and column options
	 * for the RSVP list table in the WordPress admin.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function setup_rsvp_list_table_screen_options(): void {
		add_screen_option(
			'per_page',
			array(
				'label'   => __( 'RSVPs per page', 'gatherpress' ),
				'default' => List_Table::DEFAULT_PER_PAGE,
				'option'  => $this->get_per_page_option(),
			)
		);

		$this->list_table->register_column_options();
	}

	/**
	 * Renders the RSVP admin page in the WordPress dashboard.
	 *
	 * This method displays the custom admin interface for managing GatherPress RSVPs.
	 * It initializes and displays the List_Table which contains all RSVPs
	 * with options for filtering, bulk actions, and individual RSVP management.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function render_rsvp_admin_page(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( Rsvp::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage RSVPs.', 'gatherpress' ), 403 );
		}

		// The load-{$hook} action has already scoped the list table to the
		// current screen's post type; fall back to a default instance when
		// called outside that flow (#1849).
		$rsvp_table  = $this->list_table ?? new List_Table();
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
				'default' => List_Table::DEFAULT_PER_PAGE,
				'option'  => $this->get_per_page_option(),
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
	 * @since 0.34.0
	 *
	 * @param mixed  $status Screen option value. Default false to skip.
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 *
	 * @return mixed The screen option value or false to use default.
	 */
	public function set_rsvp_screen_options( $status, $option, $value ): mixed {
		if ( $this->get_per_page_option() === $option ) {
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
	 * @since 0.34.0
	 *
	 * @param string $parent_file The current parent file.
	 *
	 * @return string Modified parent file path to highlight the correct menu item.
	 */
	public function highlight_admin_menu( string $parent_file ): string {
		global $plugin_page, $typenow;

		if ( isset( $plugin_page ) && Rsvp::COMMENT_TYPE === $plugin_page ) {
			add_filter( 'submenu_file', array( $this, 'set_submenu_file' ) );

			// Each RSVP-supporting post type has its own RSVPs page, so
			// highlight whichever post type menu the page lives under (#1849).
			$post_type = ( ! empty( $typenow ) && post_type_supports( $typenow, 'gatherpress-rsvp' ) )
				? $typenow
				: Event::POST_TYPE;

			return sprintf( 'edit.php?post_type=%s', $post_type );
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
	 * @since 0.34.0
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
	 * @since 0.34.0
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
	 * Whenever the `?gatherpress_rsvp_token=…` query var is present we
	 * also queue `nocache_headers()` onto WP's `send_headers` action so
	 * any host-level page cache (WP Rocket, W3TC, Nginx FastCGI,
	 * Cloudflare when configured to honor origin Cache-Control) treats
	 * the URL as per-user and never stores it. The token acts as a
	 * magic link — the same URL keeps authenticating the same person on
	 * every reload — so caching it shared would either leak one user's
	 * authenticated view to another, or serve a stale render that
	 * doesn't reflect their RSVP (see #1626). We deferred this to
	 * `send_headers` rather than calling `nocache_headers()` inline so
	 * the headers fire at the right moment in the response lifecycle
	 * (when `headers_sent()` is still false), regardless of any output
	 * the handler might have triggered. The deferral runs regardless of
	 * whether the token actually validates, so an expired or wrong-hash
	 * token also stays out of the page cache.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function handle_rsvp_token(): void {
		$rsvp_token = Token::from_url_parameter();

		if ( ! $rsvp_token ) {
			return;
		}

		add_action( 'send_headers', 'nocache_headers' );
		$rsvp_token->approve_comment();
	}
}
