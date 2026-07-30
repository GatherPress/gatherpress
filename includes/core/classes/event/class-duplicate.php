<?php
/**
 * Duplicates an event into a fresh draft.
 *
 * Most groups run the same event repeatedly: same description, same venue,
 * same RSVP limits, new date. This file defines the `Duplicate` class, which
 * copies everything about an event except the parts that belong to the
 * original occurrence, so an organizer sets the date and publishes rather
 * than rebuilding the event from scratch.
 *
 * @package GatherPress\Core\Event
 * @since 0.35.0
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Error;
use WP_Post;

/**
 * Duplicates an event into a fresh draft.
 *
 * Copies content, taxonomy terms (including the venue), the featured image,
 * and every author-writable event meta key, then moves the datetimes forward
 * so the copy is not in the past. RSVPs, comments, the slug, and the
 * published status deliberately do not come along: those describe the
 * occurrence that already happened.
 *
 * @since 0.35.0
 */
final class Duplicate {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Query action that triggers a duplication request.
	 *
	 * @since 0.35.0
	 *
	 * @var string
	 */
	const ACTION = 'gatherpress_duplicate_event';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.35.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_action( sprintf( 'admin_action_%s', self::ACTION ), array( $this, 'handle_request' ) );
	}

	/**
	 * Add a Duplicate link to an event's row on the posts list table.
	 *
	 * Hooked on the generic `post_row_actions` rather than per post type:
	 * the support check below covers every post type acting as an event,
	 * including ones companion plugins register after this class loads.
	 *
	 * @since 0.35.0
	 *
	 * @param array   $actions Row action links keyed by action name.
	 * @param WP_Post $post    The post the row represents.
	 *
	 * @return array Row action links, with Duplicate added for events.
	 */
	public function row_action( array $actions, WP_Post $post ): array {
		if (
			! post_type_supports( $post->post_type, 'gatherpress-event-date' )
			|| ! current_user_can( 'edit_post', $post->ID )
		) {
			return $actions;
		}

		$actions['gatherpress_duplicate'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( self::get_url( $post->ID ) ),
			esc_attr(
				sprintf(
					/* translators: %s is the event title. */
					__( 'Duplicate &#8220;%s&#8221; as a new draft', 'gatherpress' ),
					$post->post_title
				)
			),
			esc_html__( 'Duplicate', 'gatherpress' )
		);

		return $actions;
	}

	/**
	 * Nonced admin URL that duplicates the given event when requested.
	 *
	 * @since 0.35.0
	 *
	 * @param int $post_id The event post ID to duplicate.
	 *
	 * @return string The duplication request URL.
	 */
	public static function get_url( int $post_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'post'   => $post_id,
				),
				admin_url( 'admin.php' )
			),
			sprintf( '%s_%d', self::ACTION, $post_id )
		);
	}

	/**
	 * Handle a duplication request and send the author to the new draft.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 *
	 * @throws Exception If reading the source event's datetimes fails.
	 */
	public function handle_request(): void {
		$post_id = absint( Utility::get_http_input( INPUT_GET, 'post' ) );

		check_admin_referer( sprintf( '%s_%d', self::ACTION, $post_id ) );

		$duplicate = $this->duplicate( $post_id );

		if ( is_wp_error( $duplicate ) ) {
			wp_die(
				esc_html( $duplicate->get_error_message() ),
				esc_html__( 'Duplicate event', 'gatherpress' ),
				array( 'response' => 403 )
			);
		}

		// A filtered-away redirect (which is how tests observe this) returns
		// false, so the exit stays behind the successful redirect.
		if ( wp_safe_redirect( admin_url( sprintf( 'post.php?post=%d&action=edit', $duplicate ) ) ) ) {
			exit;
		}
	}

	/**
	 * Duplicate an event into a new draft.
	 *
	 * @since 0.35.0
	 *
	 * @param int $post_id The event post ID to duplicate.
	 *
	 * @return int|WP_Error The new draft's post ID, or an error describing why it was refused.
	 *
	 * @throws Exception If reading the source event's datetimes fails.
	 */
	public function duplicate( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! post_type_supports( $post->post_type, 'gatherpress-event-date' ) ) {
			return new WP_Error(
				'gatherpress_duplicate_invalid_event',
				__( 'That event could not be found.', 'gatherpress' )
			);
		}

		$post_type_object = get_post_type_object( $post->post_type );

		if (
			! current_user_can( 'edit_post', $post->ID )
			|| ! current_user_can( $post_type_object->cap->create_posts )
		) {
			return new WP_Error(
				'gatherpress_duplicate_not_allowed',
				__( 'You are not allowed to duplicate this event.', 'gatherpress' )
			);
		}

		$postarr = array(
			'post_type'      => $post->post_type,
			'post_title'     => $post->post_title,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => 'draft',
			'post_author'    => get_current_user_id(),
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'menu_order'     => $post->menu_order,
		);

		/**
		 * Filters the post data used to create an event duplicate.
		 *
		 * The slug, the published status, and the RSVP comments are left out
		 * by design: they describe the occurrence being copied from. Anything
		 * added here is passed straight to `wp_insert_post()`.
		 *
		 * @since 0.35.0
		 *
		 * @param array   $postarr Post data for the duplicate.
		 * @param WP_Post $post    The event being duplicated.
		 *
		 * @return array Post data for the duplicate.
		 */
		$postarr = (array) apply_filters( 'gatherpress_duplicate_event_postarr', $postarr, $post );

		$duplicate_id = wp_insert_post( wp_slash( $postarr ), true );

		if ( is_wp_error( $duplicate_id ) ) {
			return $duplicate_id;
		}

		$this->copy_terms( $post, (int) $duplicate_id );
		$this->copy_meta( $post, (int) $duplicate_id );
		$this->copy_datetimes( $post, (int) $duplicate_id );

		/**
		 * Fires after an event has been duplicated.
		 *
		 * @since 0.35.0
		 *
		 * @param int     $duplicate_id The new draft's post ID.
		 * @param WP_Post $post         The event that was duplicated.
		 *
		 * @return void
		 */
		do_action( 'gatherpress_event_duplicated', (int) $duplicate_id, $post );

		return (int) $duplicate_id;
	}

	/**
	 * Copy every taxonomy term from the source event to the duplicate.
	 *
	 * Reads the terms actually attached to the source across all registered
	 * taxonomies rather than walking the post type's taxonomy list. The venue
	 * association is a hidden shadow taxonomy wired onto the post type by the
	 * venue subsystem, so on a site where that wiring has not run (or a
	 * companion plugin attaches its own shadow taxonomy later) the post type's
	 * list can be missing a taxonomy the post still has terms in. Grouping by
	 * what is attached copies topics, the venue, and anything a companion
	 * plugin added, in one query.
	 *
	 * @since 0.35.0
	 *
	 * @param WP_Post $post         The event being duplicated.
	 * @param int     $duplicate_id The new draft's post ID.
	 *
	 * @return void
	 */
	private function copy_terms( WP_Post $post, int $duplicate_id ): void {
		$terms = wp_get_object_terms( $post->ID, get_taxonomies() );

		if ( is_wp_error( $terms ) ) {
			return;
		}

		$term_ids_by_taxonomy = array();

		foreach ( $terms as $term ) {
			$term_ids_by_taxonomy[ $term->taxonomy ][] = $term->term_id;
		}

		foreach ( $term_ids_by_taxonomy as $taxonomy => $term_ids ) {
			wp_set_object_terms( $duplicate_id, $term_ids, $taxonomy );
		}
	}

	/**
	 * Copy the featured image and the author-writable event meta.
	 *
	 * Works from the meta the source event actually stores rather than a
	 * literal key list, so meta added later travels without this method being
	 * updated. Two groups are skipped: the derived datetime keys in
	 * `Meta::READONLY_DATETIME_KEYS`, which `Event::save_datetimes()` rewrites
	 * from scratch, and `gatherpress_datetime` itself, which
	 * `copy_datetimes()` sets to the shifted values.
	 *
	 * @since 0.35.0
	 *
	 * @param WP_Post $post         The event being duplicated.
	 * @param int     $duplicate_id The new draft's post ID.
	 *
	 * @return void
	 */
	private function copy_meta( WP_Post $post, int $duplicate_id ): void {
		$thumbnail_id = get_post_thumbnail_id( $post );

		if ( ! empty( $thumbnail_id ) ) {
			// update_post_meta rather than set_post_thumbnail: the source's
			// choice is already valid, and set_post_thumbnail re-validates by
			// rendering the attachment, which drops the image for any
			// attachment that produces no markup.
			update_post_meta( $duplicate_id, '_thumbnail_id', $thumbnail_id );
		}

		foreach ( (array) get_post_meta( $post->ID ) as $meta_key => $values ) {
			if (
				! str_starts_with( (string) $meta_key, 'gatherpress_' )
				|| 'gatherpress_datetime' === $meta_key
				|| in_array( $meta_key, Meta::READONLY_DATETIME_KEYS, true )
			) {
				continue;
			}

			// add_post_meta per stored row rather than a single update: a key
			// registered as non-single would otherwise collapse to one value.
			foreach ( (array) $values as $value ) {
				add_post_meta( $duplicate_id, (string) $meta_key, maybe_unserialize( $value ) );
			}
		}
	}

	/**
	 * Move the source event's datetimes forward onto the duplicate.
	 *
	 * The shift is a whole number of weeks, the smallest that puts the start
	 * in the future, which keeps the weekday, the time of day, and the
	 * timezone of the original: what a group repeating a meetup expects. The
	 * duration is preserved rather than recalculated. A duplicate of a future
	 * event still moves a week out, so two events never sit on the same slot
	 * by accident.
	 *
	 * @since 0.35.0
	 *
	 * @param WP_Post $post         The event being duplicated.
	 * @param int     $duplicate_id The new draft's post ID.
	 *
	 * @return void
	 *
	 * @throws Exception If the source datetimes cannot be read as dates.
	 */
	private function copy_datetimes( WP_Post $post, int $duplicate_id ): void {
		$datetime = ( new Event( $post->ID ) )->get_datetime();

		if ( empty( $datetime['datetime_start'] ) || empty( $datetime['datetime_end'] ) ) {
			return;
		}

		$timezone = new DateTimeZone( Utility::normalize_timezone_string( (string) $datetime['timezone'] ) );
		$start    = new DateTimeImmutable( (string) $datetime['datetime_start'], $timezone );
		$end      = new DateTimeImmutable( (string) $datetime['datetime_end'], $timezone );
		$now      = new DateTimeImmutable( 'now', $timezone );

		$weeks     = max( 1, (int) ceil( ( $now->getTimestamp() - $start->getTimestamp() ) / WEEK_IN_SECONDS ) );
		$new_start = $start->modify( sprintf( '+%d weeks', $weeks ) );
		$new_end   = $new_start->add(
			new DateInterval( sprintf( 'PT%dS', $end->getTimestamp() - $start->getTimestamp() ) )
		);

		$params = array(
			'post_id'        => $duplicate_id,
			'datetime_start' => $new_start->format( Event::DATETIME_FORMAT ),
			'datetime_end'   => $new_end->format( Event::DATETIME_FORMAT ),
			'timezone'       => $datetime['timezone'],
		);

		/**
		 * Filters the datetimes a duplicated event lands on.
		 *
		 * Defaults to the source event's slot moved forward by whole weeks
		 * until it is in the future, preserving weekday, time of day, and
		 * duration. Return values in the same shape to place the duplicate
		 * somewhere else.
		 *
		 * @since 0.35.0
		 *
		 * @param array   $params   Datetime values for the duplicate, as accepted by `Event::save_datetimes()`.
		 * @param array   $datetime The source event's datetime values.
		 * @param WP_Post $post     The event being duplicated.
		 *
		 * @return array Datetime values for the duplicate.
		 */
		$params = (array) apply_filters( 'gatherpress_duplicate_event_datetime', $params, $datetime, $post );

		// The JSON meta is what the editor reads; save_datetimes writes the
		// custom table and the derived meta the queries and list table use.
		update_post_meta(
			$duplicate_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $params['datetime_start'],
					'dateTimeEnd'   => $params['datetime_end'],
					'timezone'      => $params['timezone'],
				)
			)
		);

		( new Event( $duplicate_id ) )->save_datetimes( $params );
	}
}
