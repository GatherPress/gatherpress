<?php
/**
 * GatherPress counts in the dashboard's At a Glance widget.
 *
 * @package GatherPress\Core\Admin
 * @since TBD
 */

namespace GatherPress\Core\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Event\Admin_List;
use GatherPress\Core\Event\Query as Event_Query;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue;
use WP_Comment;
use WP_Post;
use WP_Post_Type;

/**
 * Class Dashboard.
 *
 * Adds upcoming and past events, attending and waiting list RSVPs, and
 * published venues to At a Glance, one set per post type that declares the
 * matching support. Each count links to the screen it was counted from when
 * the user can open it, and is plain text otherwise.
 *
 * @since TBD
 */
final class Dashboard {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Transient holding the event and RSVP counts.
	 *
	 * Venue counts come from `wp_count_posts()`, which core already caches.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const CACHE_KEY = 'gatherpress_glance_counts';

	/**
	 * Lifetime of the cached counts, in seconds.
	 *
	 * Also bounds how long an event that just ended keeps counting as upcoming.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 5 * MINUTE_IN_SECONDS;

	/**
	 * Dashicon code points for the menu icons GatherPress registers.
	 *
	 * Any other menu icon keeps core's default bullet.
	 *
	 * @since TBD
	 *
	 * @var array<string, string>
	 */
	const ICONS = array(
		'dashicons-nametag'  => '\f484',
		'dashicons-location' => '\f230',
	);

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'dashboard_glance_items', array( $this, 'add_glance_items' ) );
		add_action( 'admin_head-index.php', array( $this, 'print_styles' ) );
		add_action( 'clean_post_cache', array( $this, 'maybe_flush_for_post' ), 10, 2 );
		add_action( 'set_object_terms', array( $this, 'maybe_flush_for_terms' ), 10, 4 );
		add_action( 'transition_comment_status', array( $this, 'maybe_flush_for_comment' ), 10, 3 );
	}

	/**
	 * Add the GatherPress counts to At a Glance.
	 *
	 * @since TBD
	 *
	 * @param string[] $items Items added by other plugins.
	 *
	 * @return string[] The items with ours appended.
	 */
	public function add_glance_items( array $items ): array {
		$counts = $this->get_counts();
		$ours   = array();

		foreach ( $this->get_post_types( Event::SUPPORT ) as $post_type ) {
			$event_counts = $counts['events'][ $post_type->name ] ?? array();
			array_push( $ours, ...$this->get_event_items( $post_type, $event_counts ) );
		}

		foreach ( $this->get_post_types( Rsvp::SUPPORT ) as $post_type ) {
			$rsvp_counts = $counts['rsvps'][ $post_type->name ] ?? array();
			array_push( $ours, ...$this->get_rsvp_items( $post_type, $rsvp_counts ) );
		}

		// Venues go last, one item each, so an odd count only leaves a gap at the end.
		foreach ( $this->get_post_types( Venue::SUPPORT ) as $post_type ) {
			$ours[] = $this->get_venue_item( $post_type );
		}

		// The widget lays items out in two columns. Pad with an empty cell when
		// the items before ours end in the left column, so ours start on a new row.
		if ( $ours && 1 === ( $this->count_core_items() + count( $items ) ) % 2 ) {
			array_unshift( $ours, '<span class="gatherpress-glance-spacer" aria-hidden="true"></span>' );
		}

		return array_merge( $items, $ours );
	}

	/**
	 * Print the icon for each item.
	 *
	 * Core wraps filtered items in a bare `<li>`, so the icon rule targets
	 * the class on the link or span inside it instead.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function print_styles(): void {
		$rules = array(
			'#dashboard_right_now .gatherpress-glance-spacer{visibility:hidden}',
			'#dashboard_right_now .gatherpress-glance-rsvp:before{content:"\f101";content:"\f101" / ""}',
		);

		foreach ( $this->get_post_types( Event::SUPPORT, Venue::SUPPORT ) as $post_type ) {
			$icon = self::ICONS[ (string) $post_type->menu_icon ] ?? '';

			if ( $icon ) {
				$rules[] = sprintf(
					'#dashboard_right_now .gatherpress-glance-%1$s:before{content:"%2$s";content:"%2$s" / ""}',
					$post_type->name,
					$icon
				);
			}
		}

		// Static rules and registered post type keys only, nothing to escape.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<style>' . implode( '', $rules ) . "</style>\n";
	}

	/**
	 * Flush the counts when an event is saved, trashed or deleted.
	 *
	 * Hooked to `clean_post_cache` rather than `transition_post_status`, so
	 * a changed event date flushes too.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param int     $post_id Post ID, required by the hook signature.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function maybe_flush_for_post( int $post_id, WP_Post $post ): void {
		if ( post_type_supports( $post->post_type, Event::SUPPORT ) ) {
			delete_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Flush the counts when an RSVP response changes.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param int                    $object_id Object ID, required by the hook signature.
	 * @param array<int, int|string> $terms     Terms, required by the hook signature.
	 * @param array<int, int>        $tt_ids    Term taxonomy IDs, required by the hook signature.
	 * @param string                 $taxonomy  Taxonomy slug.
	 *
	 * @return void
	 */
	public function maybe_flush_for_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void {
		if ( Status::TAXONOMY === $taxonomy ) {
			delete_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Flush the counts when an RSVP is approved, held, trashed or deleted.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param string     $new_status New status, required by the hook signature.
	 * @param string     $old_status Old status, required by the hook signature.
	 * @param WP_Comment $comment    Comment object.
	 *
	 * @return void
	 */
	public function maybe_flush_for_comment( string $new_status, string $old_status, WP_Comment $comment ): void {
		if ( Rsvp::COMMENT_TYPE === $comment->comment_type ) {
			delete_transient( self::CACHE_KEY );
		}
	}

	/**
	 * Get the event and RSVP counts, from the cache when it is warm.
	 *
	 * Event counts come from the events list, so each one matches the view
	 * its item links to.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<string, array<string, int>>> Counts keyed by group, then post type.
	 */
	protected function get_counts(): array {
		$counts = get_transient( self::CACHE_KEY );

		if ( is_array( $counts ) ) {
			return $counts;
		}

		$counts = array(
			'events' => array(),
			'rsvps'  => array(),
		);

		foreach ( get_post_types_by_support( Event::SUPPORT ) as $post_type ) {
			$counts['events'][ $post_type ] = Admin_List::get_instance()->get_event_counts( $post_type );
		}

		foreach ( get_post_types_by_support( Rsvp::SUPPORT ) as $post_type ) {
			$counts['rsvps'][ $post_type ] = array(
				Status::ATTENDING->value    => $this->count_rsvps( $post_type, Status::ATTENDING ),
				Status::WAITING_LIST->value => $this->count_rsvps( $post_type, Status::WAITING_LIST ),
			);
		}

		set_transient( self::CACHE_KEY, $counts, self::CACHE_EXPIRATION );

		return $counts;
	}

	/**
	 * Count the approved RSVPs on one post type with one response.
	 *
	 * @since TBD
	 *
	 * @param string $post_type Post type name.
	 * @param Status $status    The response to count.
	 *
	 * @return int The number of RSVPs.
	 */
	protected function count_rsvps( string $post_type, Status $status ): int {
		return (int) Rsvp_Query::get_instance()->get_rsvps(
			array(
				'count'     => true,
				'status'    => 'approve',
				'post_type' => $post_type,
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Status::TAXONOMY,
						'field'    => 'slug',
						'terms'    => array( $status->value ),
					),
				),
			)
		);
	}

	/**
	 * Build the upcoming and past items for one event post type.
	 *
	 * @since TBD
	 *
	 * @param WP_Post_Type       $post_type The event post type.
	 * @param array<string, int> $counts    Upcoming and past counts.
	 *
	 * @return string[] The upcoming item, then the past item.
	 */
	protected function get_event_items( WP_Post_Type $post_type, array $counts ): array {
		$upcoming  = $counts['upcoming'] ?? 0;
		$past      = $counts['past'] ?? 0;
		$url       = current_user_can( $post_type->cap->edit_posts )
			? add_query_arg( 'post_type', $post_type->name, admin_url( 'edit.php' ) )
			: '';
		$css_class = 'gatherpress-glance-' . $post_type->name;

		return array(
			$this->get_item(
				sprintf(
					/* translators: 1: Number of events, 2: Post type label, singular or plural to match the number. */
					_n( '%1$s Upcoming %2$s', '%1$s Upcoming %2$s', $upcoming, 'gatherpress' ),
					number_format_i18n( $upcoming ),
					$this->get_label( $post_type, $upcoming )
				),
				$url ? add_query_arg( Event_Query::EVENT_QUERY_PARAM, 'upcoming', $url ) : '',
				$css_class
			),
			$this->get_item(
				sprintf(
					/* translators: 1: Number of events, 2: Post type label, singular or plural to match the number. */
					_n( '%1$s Past %2$s', '%1$s Past %2$s', $past, 'gatherpress' ),
					number_format_i18n( $past ),
					$this->get_label( $post_type, $past )
				),
				$url ? add_query_arg( Event_Query::EVENT_QUERY_PARAM, 'past', $url ) : '',
				$css_class
			),
		);
	}

	/**
	 * Build the attending and waiting list items for one RSVP post type.
	 *
	 * @since TBD
	 *
	 * @param WP_Post_Type       $post_type The post type the RSVPs belong to.
	 * @param array<string, int> $counts    Counts keyed by response.
	 *
	 * @return string[] The attending item, then the waiting list item.
	 */
	protected function get_rsvp_items( WP_Post_Type $post_type, array $counts ): array {
		$attending    = $counts[ Status::ATTENDING->value ] ?? 0;
		$waiting_list = $counts[ Status::WAITING_LIST->value ] ?? 0;
		$url          = current_user_can( Rsvp::CAPABILITY )
			? add_query_arg(
				array(
					'post_type' => $post_type->name,
					'page'      => Rsvp::COMMENT_TYPE,
					'status'    => 'approved',
				),
				admin_url( 'edit.php' )
			)
			: '';

		return array(
			$this->get_item(
				sprintf(
					/* translators: 1: Number of RSVPs, 2: Singular post type label. */
					_n( '%1$s Attending RSVP (%2$s)', '%1$s Attending RSVPs (%2$s)', $attending, 'gatherpress' ),
					number_format_i18n( $attending ),
					$post_type->labels->singular_name
				),
				$url ? add_query_arg( 'response', Status::ATTENDING->value, $url ) : '',
				'gatherpress-glance-rsvp'
			),
			$this->get_item(
				sprintf(
					/* translators: 1: Number of RSVPs, 2: Singular post type label. */
					_n( '%1$s on Waiting List (%2$s)', '%1$s on Waiting List (%2$s)', $waiting_list, 'gatherpress' ),
					number_format_i18n( $waiting_list ),
					$post_type->labels->singular_name
				),
				$url ? add_query_arg( 'response', Status::WAITING_LIST->value, $url ) : '',
				'gatherpress-glance-rsvp'
			),
		);
	}

	/**
	 * Build the published count item for one venue post type.
	 *
	 * @since TBD
	 *
	 * @param WP_Post_Type $post_type The venue post type.
	 *
	 * @return string The item.
	 */
	protected function get_venue_item( WP_Post_Type $post_type ): string {
		$count = (int) wp_count_posts( $post_type->name )->publish;
		$url   = current_user_can( $post_type->cap->edit_posts )
			? add_query_arg(
				array(
					'post_status' => 'publish',
					'post_type'   => $post_type->name,
				),
				admin_url( 'edit.php' )
			)
			: '';

		return $this->get_item(
			sprintf(
				/* translators: 1: Number of venues, 2: Post type label, singular or plural to match the number. */
				_n( '%1$s Published %2$s', '%1$s Published %2$s', $count, 'gatherpress' ),
				number_format_i18n( $count ),
				$this->get_label( $post_type, $count )
			),
			$url,
			'gatherpress-glance-' . $post_type->name
		);
	}

	/**
	 * Count the items core prints before the filtered ones.
	 *
	 * Mirrors `wp_dashboard_right_now()`. The moderation item is printed but
	 * hidden when nothing is waiting, so it only takes a cell when it is not.
	 *
	 * @since TBD
	 *
	 * @return int The number of visible core items.
	 */
	protected function count_core_items(): int {
		$count = 0;

		foreach ( array( 'post', 'page' ) as $post_type ) {
			if ( wp_count_posts( $post_type )->publish ) {
				++$count;
			}
		}

		$comments = wp_count_comments();

		if ( $comments->approved || $comments->moderated ) {
			$count += $comments->moderated ? 2 : 1;
		}

		return $count;
	}

	/**
	 * Get the registered post types that declare any of the given supports.
	 *
	 * @since TBD
	 *
	 * @param string ...$supports Post type supports.
	 *
	 * @return WP_Post_Type[] The post type objects.
	 */
	protected function get_post_types( string ...$supports ): array {
		$names = array();

		foreach ( $supports as $support ) {
			$names = array_merge( $names, get_post_types_by_support( $support ) );
		}

		// A support can be added to a post type that is never registered.
		return array_filter( array_map( 'get_post_type_object', array_unique( $names ) ) );
	}

	/**
	 * Pick the singular or plural label to go with a count.
	 *
	 * @since TBD
	 *
	 * @param WP_Post_Type $post_type The post type.
	 * @param int          $count     The count shown beside the label.
	 *
	 * @return string The label.
	 */
	protected function get_label( WP_Post_Type $post_type, int $count ): string {
		return 1 === $count ? $post_type->labels->singular_name : $post_type->labels->name;
	}

	/**
	 * Build one item, linked when there is somewhere to go.
	 *
	 * @since TBD
	 *
	 * @param string $text      The item text.
	 * @param string $url       Where the item links to, empty for plain text.
	 * @param string $css_class Class that picks the item's icon.
	 *
	 * @return string The escaped item.
	 */
	protected function get_item( string $text, string $url, string $css_class ): string {
		if ( $url ) {
			return sprintf(
				'<a class="%1$s" href="%2$s">%3$s</a>',
				esc_attr( $css_class ),
				esc_url( $url ),
				esc_html( $text )
			);
		}

		return sprintf( '<span class="%1$s">%2$s</span>', esc_attr( $css_class ), esc_html( $text ) );
	}
}
