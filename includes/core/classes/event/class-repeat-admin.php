<?php
/**
 * Repeating events in the admin event list.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Post;
use WP_Query;

/**
 * Class Repeat_Admin.
 *
 * Marks repeating events in the admin event list: a "Repeating" state after
 * the title, how the event repeats under its date, and a Repeating view
 * beside Upcoming and Past.
 *
 * @since TBD
 */
final class Repeat_Admin {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Query var that narrows the admin event list to repeating events.
	 *
	 * @since TBD
	 * @var string
	 */
	const QUERY_VAR = 'gatherpress_repeating';

	/**
	 * Counts of repeating events, by post type.
	 *
	 * @since TBD
	 * @var array<string, int>
	 */
	protected array $counts = array();

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Register the list hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_list' ) );
		add_filter( 'display_post_states', array( $this, 'add_state' ), 10, 2 );
		add_action( 'registered_post_type', array( $this, 'register_post_type_hooks' ) );
	}

	/**
	 * Register the view and column hooks for an event post type.
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type just registered.
	 *
	 * @return void
	 */
	public function register_post_type_hooks( string $post_type ): void {
		if ( ! post_type_supports( $post_type, Event::SUPPORT ) ) {
			return;
		}

		// Priority 11: after Admin_List adds Upcoming and Past, and its date column, at 10.
		add_filter( sprintf( 'views_edit-%s', $post_type ), array( $this, 'add_view' ), 11 );
		add_action( sprintf( 'manage_%s_posts_custom_column', $post_type ), array( $this, 'add_summary' ), 11, 2 );
	}

	/**
	 * Allow the query var.
	 *
	 * @since TBD
	 *
	 * @param string[] $vars The public query vars.
	 *
	 * @return string[] The vars, with this one.
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	/**
	 * Narrow the admin event list to repeating events when the view asks for it.
	 *
	 * @since TBD
	 *
	 * @param WP_Query $query The query.
	 *
	 * @return void
	 */
	public function filter_list( WP_Query $query ): void {
		$post_type = $query->get( 'post_type' );

		if ( ! is_admin()
			|| ! $query->is_main_query()
			|| ! is_string( $post_type )
			|| ! post_type_supports( $post_type, Event::SUPPORT )
			|| ! $query->get( self::QUERY_VAR )
		) {
			return;
		}

		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = array(
			'key'     => Rules::META_KEY,
			'compare' => 'EXISTS',
		);

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Add the Repeating view after Past.
	 *
	 * @since TBD
	 *
	 * @param array<string, string> $views The list table's views.
	 *
	 * @return array<string, string> The views, with Repeating.
	 */
	public function add_view( array $views ): array {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return $views;
		}

		$current = '' !== Utility::get_http_input( INPUT_GET, self::QUERY_VAR );
		$link    = sprintf(
			'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
			esc_url(
				add_query_arg(
					array(
						self::QUERY_VAR => '1',
						'post_type'     => $screen->post_type,
					),
					admin_url( 'edit.php' )
				)
			),
			$current ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Repeating', 'gatherpress' ),
			number_format_i18n( $this->count( $screen->post_type ) )
		);

		if ( $current ) {
			// Only one view is current, and the list marks All when none of its own are.
			$marks = array( ' class="current"', ' aria-current="page"' );
			$views = array_map( static fn( string $view ): string => str_replace( $marks, '', $view ), $views );
		}

		$at = array_search( 'past', array_keys( $views ), true );
		$at = false === $at ? count( $views ) : $at + 1;

		return array_slice( $views, 0, $at, true )
			+ array( 'repeating' => $link )
			+ array_slice( $views, $at, null, true );
	}

	/**
	 * Mark a repeating event after its title.
	 *
	 * @since TBD
	 *
	 * @param array<string, string> $states The post's states.
	 * @param WP_Post               $post   The post.
	 *
	 * @return array<string, string> The states, with Repeating for a repeating event.
	 */
	public function add_state( array $states, WP_Post $post ): array {
		if ( post_type_supports( $post->post_type, Event::SUPPORT ) && self::repeats( $post->ID ) ) {
			$states['gatherpress_repeating'] = sprintf(
				'<span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span>%s',
				esc_html__( 'Repeating', 'gatherpress' )
			);
		}

		return $states;
	}

	/**
	 * Show how an event repeats under its date in the date column.
	 *
	 * @since TBD
	 *
	 * @param string $column  The column.
	 * @param int    $post_id The post ID.
	 *
	 * @return void
	 */
	public function add_summary( string $column, int $post_id ): void {
		$summary = 'datetime' === $column ? Repeat_Summary::for_event( new Event( $post_id ) ) : '';

		if ( '' !== $summary ) {
			printf( '<br><span class="gatherpress-repeat-summary">%s</span>', esc_html( $summary ) );
		}
	}

	/**
	 * Whether an event has repeat rules.
	 *
	 * A series that stopped repeating keeps its rules, since its past dates
	 * still repeat, and an event that never repeated has none.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event post ID.
	 *
	 * @return bool True if the event has rules.
	 */
	public static function repeats( int $post_id ): bool {
		return (bool) get_post_meta( $post_id, Rules::META_KEY, false );
	}

	/**
	 * How many events of a post type repeat, as the views count them.
	 *
	 * @since TBD
	 *
	 * @param string $post_type The post type.
	 *
	 * @return int The count, leaving out the trash and auto-drafts.
	 */
	protected function count( string $post_type ): int {
		global $wpdb;

		if ( ! isset( $this->counts[ $post_type ] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->counts[ $post_type ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(DISTINCT p.ID) FROM %i p INNER JOIN %i m ON m.post_id = p.ID AND m.meta_key = %s'
					. " WHERE p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft')",
					$wpdb->posts,
					$wpdb->postmeta,
					Rules::META_KEY,
					$post_type
				)
			);
		}

		return $this->counts[ $post_type ];
	}
}
