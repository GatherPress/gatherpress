<?php
/**
 * Adds GatherPress counts to the WordPress "At a Glance" dashboard widget.
 *
 * @package GatherPress\Core\Admin
 * @since TBD
 */

namespace GatherPress\Core\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Event;
use GatherPress\Core\Rsvp\Query as Rsvp_Query;
use GatherPress\Core\Rsvp\Response\Status as Rsvp_Status;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Venue;
use WP_Comment;
use WP_Post;
use WP_Query;

/**
 * Class Dashboard.
 *
 * Hooks into `dashboard_glance_items` and appends items per post type
 * for each GatherPress post-type-support group:
 *
 *  - gatherpress-event-date: upcoming and past counts
 *  - gatherpress-venue-information: total published venues
 *  - gatherpress-rsvp: attending and waiting_list counts
 *
 * Counts link to the relevant admin screen when the current user has the
 * required capability; otherwise the plain number is shown without a link.
 *
 * @since TBD
 */
class Dashboard {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Transient TTL in seconds.
	 *
	 * Counts are stored in wp_options-backed transients so they survive
	 * across requests on shared hosting where no persistent object cache
	 * (Redis, Memcached) is available. Five minutes is a reasonable balance
	 * between freshness and avoiding a DB query on every dashboard load.
	 *
	 * @since TBD
	 * @var int
	 */
	const TRANSIENT_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Object-cache group used for within-request deduplication.
	 *
	 * On sites with a persistent object cache backend this group is also
	 * persistent, so the transient layer is effectively bypassed after the
	 * first warm-up. On sites without one it acts only as a per-request
	 * dedup to avoid firing the same query twice in a single page load.
	 *
	 * @since TBD
	 * @var string
	 */
	const CACHE_GROUP = 'gatherpress_at_a_glance';

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since TBD
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'dashboard_glance_items', array( $this, 'add_glance_items' ) );
		add_action( 'admin_head-index.php', array( $this, 'print_glance_styles' ) );

		// Invalidate cached counts when event-date or venue posts change status
		// (publish, trash, untrash, future -> publish, etc.).
		add_action( 'transition_post_status', array( $this, 'invalidate_on_post_change' ), 10, 3 );

		// Invalidate RSVP counts whenever a status term is assigned
		// (covers every Rsvp::save() path that ends in wp_set_object_terms).
		add_action( 'set_object_terms', array( $this, 'invalidate_on_rsvp_terms' ), 10, 4 );

		// Invalidate RSVP counts when a comment is permanently deleted
		// (covers the no_status path in Rsvp::save() which calls wp_delete_comment).
		add_action( 'deleted_comment', array( $this, 'invalidate_on_rsvp_delete' ), 10, 2 );
	}

	/**
	 * Append GatherPress items to the "At a Glance" list.
	 *
	 * Each item is an HTML string - either an <a> tag (when the user can
	 * access the target screen) or a plain <span> (when they cannot).
	 *
	 * @since TBD
	 *
	 * @param string[] $items Existing glance items.
	 * @return string[] Extended list.
	 */
	public function add_glance_items( array $items ): array {
		$ours = array();

		// 1. Event-date post types: upcoming + past.
		foreach ( get_post_types_by_support( Event::SUPPORT ) as $post_type ) {
			$ours = array_merge( $ours, $this->event_date_items( $post_type ) );
		}

		// 2. RSVP-supporting post types: attending + waiting_list.
		foreach ( get_post_types_by_support( Rsvp::SUPPORT ) as $post_type ) {
			$ours = array_merge( $ours, $this->rsvp_items( $post_type ) );
		}

		// 3. Venue post types: total published.
		$venue_post_types = get_post_types_by_support( Venue::SUPPORT );
		foreach ( $venue_post_types as $post_type ) {
			$ours[] = $this->venue_item( $post_type );
			if ( 1 === count( $venue_post_types ) % 2 ) {
				// Add a spacer after the last venue item if the total count
				// of venue items is odd, so that the next item starts on the
				// left column.
				$ours[] = '<span class="gp-glance-spacer" aria-hidden="true"></span>';
			}
		}

		if ( empty( $ours ) ) {
			return $items;
		}

		// 4. Column-parity spacer.
		// The widget renders all <li> items in a two-column float layout
		// (each li is width:50%; float:left). An odd total item count means
		// the last item spans the full width alone. To keep our block
		// visually paired, prepend a single invisible spacer item so that
		// our first real item always starts on the left column.
		$total = $this->count_core_glance_items() + count( $items );
		if ( 1 === $total % 2 ) {
			array_unshift( $ours, '<span class="gp-glance-spacer" aria-hidden="true"></span>' );
		}

		return array_merge( $items, $ours );
	}

	/**
	 * Count the "At a Glance" items that WordPress core will print directly
	 * (before the dashboard_glance_items filter runs).
	 *
	 * WP core prints its own <li> elements for posts, pages, and comments
	 * outside the filter, so they are invisible to us inside the filter.
	 * We reproduce the same conditional logic here to get an accurate count.
	 *
	 * The rule: only count items that actually participate in the float layout.
	 * An item with class "hidden" (= display:none) takes no space, so it must
	 * not be counted even though its <li> is present in the DOM.
	 *
	 * Items counted:
	 *  - Published posts ('post'): 1 when publish > 0 (skipped entirely otherwise)
	 *  - Published pages ('page'): 1 when publish > 0 (skipped entirely otherwise)
	 *  - Approved comments: 1 when approved > 0 OR moderated > 0
	 *  - Comments in moderation: 1 when moderated > 0
	 *
	 * @since TBD
	 *
	 * @return int Number of core glance items visible in the float layout.
	 */
	protected function count_core_glance_items(): int {
		$count = 0;

		// Posts and pages: WP skips the <li> entirely when publish count is 0.
		foreach ( array( 'post', 'page' ) as $post_type ) {
			$counts = wp_count_posts( $post_type );
			if ( ! empty( $counts->publish ) ) {
				++$count;
			}
		}

		// Comments: WP only prints both items when approved > 0 OR moderated > 0.
		$num_comm = wp_count_comments();
		if ( ! empty( $num_comm->approved ) || ! empty( $num_comm->moderated ) ) {
			++$count;

			// Only count moderated comments when actually visible.
			if ( ! empty( $num_comm->moderated ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Print the inline <style> block that maps each glance item class to its
	 * dashicon unicode codepoint.
	 *
	 * @since TBD
	 * @return void
	 */
	public function print_glance_styles(): void {
		$rules = array();

		// Event-date and venue post types: derive icon from menu_icon.
		$icon_types = array_merge(
			array_values( get_post_types_by_support( Event::SUPPORT ) ),
			array_values( get_post_types_by_support( Venue::SUPPORT ) )
		);

		foreach ( array_unique( $icon_types ) as $post_type ) {
			$pt_obj = get_post_type_object( $post_type );
			if ( ! $pt_obj ) {
				continue;
			}

			$codepoint = $this->dashicon_codepoint( $pt_obj->menu_icon );
			if ( null === $codepoint ) {
				continue;
			}

			$class   = 'gp-glance-' . sanitize_html_class( $post_type );
			$rules[] = sprintf(
				'#dashboard_right_now li a.%1$s:before,#dashboard_right_now li span.%1$s:before{content:"%2$s";}',
				$class,
				$codepoint
			);
		}

		// RSVPs: always use the standard WP comment icon (\f101).
		foreach ( get_post_types_by_support( Rsvp::SUPPORT ) as $post_type ) {
			$class   = 'gp-glance-rsvp-' . sanitize_html_class( $post_type );
			$rules[] = sprintf(
				'#dashboard_right_now li a.%1$s:before,#dashboard_right_now li span.%1$s:before{content:"\\f101";}',
				$class
			);
		}

		// Spacer item: suppress the generic dashicon pseudo-element so it
		// occupies its grid cell silently.
		$rules[] = '#dashboard_right_now li:has(span.gp-glance-spacer){visibility:hidden;}';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<style>' . implode( '', $rules ) . "</style>\n";
	}

	/**
	 * Resolve a `menu_icon` value to its dashicon unicode codepoint string.
	 *
	 * @since TBD
	 *
	 * @param string|null $menu_icon The `menu_icon` value from the post type object.
	 * @return string|null Unicode codepoint string (e.g. '"\\f484"'), or null.
	 */
	protected function dashicon_codepoint( ?string $menu_icon ): ?string {
		$map = array(
			'dashicons-nametag'        => '\\f484',
			'dashicons-location'       => '\\f230',
			'dashicons-art'            => '\\f309',
			'dashicons-clock'          => '\\f469',
			'dashicons-id'             => '\\f336',
			'dashicons-calendar-alt'   => '\\f508',
			'dashicons-calendar'       => '\\f145',
			'dashicons-groups'         => '\\f307',
			'dashicons-admin-comments' => '\\f101',
			'dashicons-tag'            => '\\f323',
			'dashicons-category'       => '\\f318',
			'dashicons-admin-post'     => '\\f109',
			'dashicons-admin-page'     => '\\f105',
		);

		if ( empty( $menu_icon ) ) {
			return null;
		}

		if ( str_starts_with( $menu_icon, 'data:' ) || str_starts_with( $menu_icon, 'http' ) ) {
			return null;
		}

		return $map[ $menu_icon ] ?? null;
	}

	/**
	 * Return an integer count from the cache, or false on a miss.
	 *
	 * @since TBD
	 *
	 * @param string $transient_key Unique transient key for this count.
	 * @return int|false Cached count, or false on a full miss.
	 */
	protected function get_cached_count( string $transient_key ): int|false {
		$cached = wp_cache_get( $transient_key, self::CACHE_GROUP );
		if ( false !== $cached && is_int( $cached ) ) {
			return $cached;
		}

		$cached = get_transient( $transient_key );
		if ( false !== $cached && is_numeric( $cached ) ) {
			$cached = (int) $cached;
			wp_cache_set( $transient_key, $cached, self::CACHE_GROUP, self::TRANSIENT_TTL );
			return $cached;
		}

		return false;
	}

	/**
	 * Store a count in both the transient and the object cache.
	 *
	 * @since TBD
	 *
	 * @param string $transient_key Unique transient key for this count.
	 * @param int    $count         The value to store.
	 * @return void
	 */
	protected function set_cached_count( string $transient_key, int $count ): void {
		set_transient( $transient_key, $count, self::TRANSIENT_TTL );
		wp_cache_set( $transient_key, $count, self::CACHE_GROUP, self::TRANSIENT_TTL );
	}

	/**
	 * Return every transient key this class writes.
	 *
	 * @since TBD
	 *
	 * @return string[]
	 */
	protected function transient_keys(): array {
		$keys = array();

		foreach ( get_post_types_by_support( Event::SUPPORT ) as $post_type ) {
			$keys[] = sprintf( 'gp_glance_%s_upcoming', $post_type );
			$keys[] = sprintf( 'gp_glance_%s_past', $post_type );
		}

		foreach ( get_post_types_by_support( Rsvp::SUPPORT ) as $post_type ) {
			$keys[] = sprintf( 'gp_glance_rsvp_%s_attending', $post_type );
			$keys[] = sprintf( 'gp_glance_rsvp_%s_waiting_list', $post_type );
		}

		return $keys;
	}

	/**
	 * Delete every transient and object-cache entry this class writes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function delete_transients(): void {
		foreach ( $this->transient_keys() as $key ) {
			delete_transient( $key );
			wp_cache_delete( $key, self::CACHE_GROUP );
		}
	}

	/**
	 * Invalidate event and venue counts when a post changes status.
	 *
	 * @since TBD
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public function invalidate_on_post_change( string $new_status, string $old_status, WP_Post $post ): void {
		if ( $new_status === $old_status ) {
			return;
		}

		$relevant = post_type_supports( $post->post_type, Event::SUPPORT )
			|| post_type_supports( $post->post_type, Venue::SUPPORT );

		if ( ! $relevant ) {
			return;
		}

		$this->delete_transients();
	}

	/**
	 * Invalidate RSVP counts when a status term is assigned to a comment.
	 *
	 * @since TBD
	 *
	 * @param int               $object_id Object ID (comment ID for RSVPs).
	 * @param array<int|string> $terms     Array of term IDs/slugs being set.
	 * @param int[]             $tt_ids    Array of term taxonomy IDs.
	 * @param string            $taxonomy  Taxonomy slug.
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by WP's set_object_terms signature.
	 */
	public function invalidate_on_rsvp_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy ): void {
		if ( Rsvp_Status::TAXONOMY !== $taxonomy ) {
			return;
		}

		$this->delete_transients();
	}

	/**
	 * Invalidate RSVP counts when a comment is permanently deleted.
	 *
	 * @since TBD
	 *
	 * @param int        $comment_id The deleted comment ID.
	 * @param WP_Comment $comment    The deleted comment object.
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by WP's deleted_comment signature.
	 */
	public function invalidate_on_rsvp_delete( int $comment_id, WP_Comment $comment ): void {
		if ( Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return;
		}

		$this->delete_transients();
	}

	/**
	 * Build upcoming and past glance items for one event-date post type.
	 *
	 * @since TBD
	 *
	 * @param string $post_type Post type slug.
	 * @return string[] Two HTML strings: past, upcoming.
	 */
	protected function event_date_items( string $post_type ): array {
		$pt_obj = get_post_type_object( $post_type );
		if (
			! $pt_obj ||
			! is_string( $pt_obj->cap->edit_posts ) ||
			! is_string( $pt_obj->labels->name ) ||
			! is_string( $pt_obj->labels->singular_name )
		) {
			return array();
		}

		$can_edit  = current_user_can( $pt_obj->cap->edit_posts );
		$base_url  = admin_url( sprintf( 'edit.php?post_type=%s', $post_type ) );
		$css_class = 'gp-glance-' . sanitize_html_class( $post_type );

		$upcoming_count = $this->count_events( $post_type, 'upcoming' );
		$past_count     = $this->count_events( $post_type, 'past' );

		$plural   = $pt_obj->labels->name;
		$singular = $pt_obj->labels->singular_name;

		$past_text = sprintf(
			/* translators: 1: count, 2: post type label (singular or plural, matching the count) */
			_n(
				'%1$d Past %2$s',
				'%1$d Past %2$s',
				$past_count,
				'gatherpress'
			),
			number_format_i18n( $past_count ),
			1 === $past_count ? $singular : $plural
		);

		$upcoming_text = sprintf(
			/* translators: 1: count, 2: post type label (singular or plural, matching the count) */
			_n(
				'%1$d Upcoming %2$s',
				'%1$d Upcoming %2$s',
				$upcoming_count,
				'gatherpress'
			),
			number_format_i18n( $upcoming_count ),
			1 === $upcoming_count ? $singular : $plural
		);

		return array(
			$this->make_item(
				$past_text,
				$can_edit
					? add_query_arg( 'gatherpress_event_query', 'past', $base_url )
					: null,
				$css_class
			),
			$this->make_item(
				$upcoming_text,
				$can_edit
					? add_query_arg( 'gatherpress_event_query', 'upcoming', $base_url )
					: null,
				$css_class
			),
		);
	}

	/**
	 * Count published posts of a given event-date post type split by timing.
	 *
	 * @since TBD
	 *
	 * @param string $post_type        Post type slug.
	 * @param string $event_query_type 'upcoming' or 'past'.
	 * @return int
	 */
	protected function count_events( string $post_type, string $event_query_type ): int {
		$transient_key = sprintf( 'gp_glance_%s_%s', $post_type, $event_query_type );
		$cached        = $this->get_cached_count( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$query = new WP_Query(
			array(
				'post_type'               => $post_type,
				'post_status'             => 'publish',
				'posts_per_page'          => 1,
				'fields'                  => 'ids',
				'no_found_rows'           => false,
				'update_post_meta_cache'  => false,
				'update_post_term_cache'  => false,
				'gatherpress_event_query' => $event_query_type,
			)
		);

		$count = (int) $query->found_posts;
		$this->set_cached_count( $transient_key, $count );

		return $count;
	}

	/**
	 * Build a published-count glance item for one venue post type.
	 *
	 * @since TBD
	 *
	 * @param string $post_type Post type slug.
	 * @return string HTML string.
	 */
	protected function venue_item( string $post_type ): string {
		$pt_obj = get_post_type_object( $post_type );
		if (
			! $pt_obj ||
			! is_string( $pt_obj->cap->edit_posts ) ||
			! is_string( $pt_obj->labels->name ) ||
			! is_string( $pt_obj->labels->singular_name )
		) {
			return '';
		}

		$counts    = wp_count_posts( $post_type );
		$count     = is_numeric( $counts->publish ) ? (int) $counts->publish : 0;
		$can_edit  = current_user_can( $pt_obj->cap->edit_posts );
		$css_class = 'gp-glance-' . sanitize_html_class( $post_type );

		$text = sprintf(
			/* translators: 1: count, 2: post type label (singular or plural, matching the count) */
			_n(
				'%1$d %2$s',
				'%1$d %2$s',
				$count,
				'gatherpress'
			),
			number_format_i18n( $count ),
			1 === $count ? $pt_obj->labels->singular_name : $pt_obj->labels->name
		);

		return $this->make_item(
			$text,
			$can_edit
				? admin_url( sprintf( 'edit.php?post_type=%s', $post_type ) )
				: null,
			$css_class
		);
	}

	/**
	 * Build attending and waiting-list glance items for one RSVP post type.
	 *
	 * @since TBD
	 *
	 * @param string $post_type Post type slug.
	 * @return string[] Two HTML strings: attending, waiting_list.
	 */
	protected function rsvp_items( string $post_type ): array {
		$pt_obj = get_post_type_object( $post_type );
		if (
			! $pt_obj ||
			! is_string( $pt_obj->labels->singular_name )
		) {
			return array();
		}

		$can_moderate = current_user_can( Rsvp::CAPABILITY );
		$rsvp_url     = $can_moderate
			? admin_url( sprintf( 'edit.php?post_type=%s&page=%s', $post_type, Rsvp::COMMENT_TYPE ) )
			: null;
		$css_class    = 'gp-glance-rsvp-' . sanitize_html_class( $post_type );

		$attending    = $this->count_rsvps( $post_type, Rsvp_Status::ATTENDING->value );
		$waiting_list = $this->count_rsvps( $post_type, Rsvp_Status::WAITING_LIST->value );

		$pt_singular = $pt_obj->labels->singular_name;

		return array(
			$this->make_item(
				sprintf(
					/* translators: 1: count, 2: singular post type label */
					_n(
						'%1$d Attending RSVP (%2$s)',
						'%1$d Attending RSVPs (%2$s)',
						$attending,
						'gatherpress'
					),
					number_format_i18n( $attending ),
					$pt_singular
				),
				$rsvp_url,
				$css_class
			),
			$this->make_item(
				sprintf(
					/* translators: 1: count, 2: singular post type label */
					_n(
						'%1$d on Waiting List (%2$s)',
						'%1$d on Waiting List (%2$s)',
						$waiting_list,
						'gatherpress'
					),
					number_format_i18n( $waiting_list ),
					$pt_singular
				),
				$rsvp_url,
				$css_class
			),
		);
	}

	/**
	 * Count RSVPs for one post type filtered by status term.
	 *
	 * @since TBD
	 *
	 * @param string $post_type   Post type slug.
	 * @param string $status_slug Term slug: 'attending' or 'waiting_list'.
	 * @return int
	 */
	protected function count_rsvps( string $post_type, string $status_slug ): int {
		$transient_key = sprintf( 'gp_glance_rsvp_%s_%s', $post_type, $status_slug );
		$cached        = $this->get_cached_count( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$rsvp_query = Rsvp_Query::get_instance();
		$count      = $rsvp_query->get_rsvps(
			array(
				'count'     => true,
				'status'    => 'approve',
				'post_type' => $post_type,
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => Rsvp_Status::TAXONOMY,
						'field'    => 'slug',
						'terms'    => array( $status_slug ),
					),
				),
			)
		);

		$count = is_numeric( $count ) ? (int) $count : 0;
		$this->set_cached_count( $transient_key, $count );

		return $count;
	}

	/**
	 * Build a single glance item HTML string.
	 *
	 * @since TBD
	 *
	 * @param string      $text      Final, already-formatted link/span text.
	 * @param string|null $url       Admin URL, or null to render unlinked.
	 * @param string      $css_class CSS class placed on the <a>/<span> for icon targeting.
	 * @return string HTML string.
	 */
	protected function make_item( string $text, ?string $url, string $css_class = '' ): string {
		$class_attr = $css_class ? sprintf( ' class="%s"', esc_attr( $css_class ) ) : '';

		if ( null !== $url ) {
			return sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $class_attr, esc_html( $text ) );
		}

		return sprintf( '<span%s>%s</span>', $class_attr, esc_html( $text ) );
	}
}
