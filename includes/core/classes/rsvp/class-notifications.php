<?php
/**
 * RSVP Notifications handler.
 *
 * Manages sending notifications to event organizers and attendees when RSVP status changes.
 * Type-aware notifications allow different notification strategies per RSVP type.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Rsvp\Manager;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event\Event;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Rsvp_Notifications.
 *
 * Handles all RSVP-related notifications (to organizers, attendees, etc.).
 *
 * @since 1. 0.0
 */
class Notifications {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class constructor.
	 *
	 * Initializes the notification handler and sets up hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for RSVP notifications.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_rsvp_saved', array( $this, 'on_rsvp_saved' ), 10, 3 );
		add_action( 'gatherpress_rsvp_status_updated', array( $this, 'on_rsvp_status_updated' ), 10, 3 );
		add_action( 'gatherpress_rsvp_deleted', array( $this, 'on_rsvp_deleted' ), 10, 2 );
	}

	/**
	 * Handle RSVP saved notification.
	 *
	 * Called when an RSVP is created or updated.  Routes to type-specific handlers.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $comment_id The comment ID of the RSVP.
	 * @param string $rsvp_type  The RSVP type slug.
	 * @param array  $data       {.
	 *     @type string $status     The RSVP status ('attending', 'waiting_list', 'not_attending').
	 *     @type int    $guests     Number of guests.
	 *     @type int    $anonymous  Whether RSVP is anonymous.
	 *     @type mixed  $identifier The identifier (user ID, email, actor URI, etc.).
	 *     @type int    $event_id   The event post ID.
	 * }
	 *
	 * @return void
	 */
	public function on_rsvp_saved( int $comment_id, string $rsvp_type, array $data ): void {
		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof \WP_Comment ) {
			return;
		}

		if ( ! isset( $data['event_id'] ) || Event::POST_TYPE !== get_post_type( $data['event_id'] ) ) {
			return;
		}

		$event = new Event( $data['event_id'] );

		// Generic notification for organizer.
		$this->notify_organizer_rsvp_received( $comment, $event, $data );
	}

	/**
	 * Handle RSVP status updated notification (e.g., moved from waiting list to attending).
	 *
	 * @since 1.0.0
	 *
	 * @param int    $comment_id The comment ID of the RSVP.
	 * @param string $old_status The previous RSVP status.
	 * @param string $new_status The new RSVP status.
	 *
	 * @return void
	 */
	public function on_rsvp_status_updated( int $comment_id, string $old_status, string $new_status ): void {
		$comment = get_comment( $comment_id );

		if ( ! $comment instanceof \WP_Comment ) {
			return;
		}

		if ( Event::POST_TYPE !== get_post_type( $comment->comment_post_ID) ) {
			return;
		}

		$event = new Event( $comment->comment_post_ID );

		// Get RSVP type.
		$type_terms = wp_get_object_terms( $comment_id, '_gatherpress_rsvp_type', array( 'fields' => 'names' ) );
		$rsvp_type  = ! empty( $type_terms ) ? $type_terms[0] : 'unknown';

		// Notify organizer of status change.
		$this->notify_organizer_rsvp_status_changed( $comment, $event, $old_status, $new_status, $rsvp_type );

		// If promoted from waiting list to attending, notify attendee.
		if ( 'waiting_list' === $old_status && 'attending' === $new_status ) {
			$this->notify_attendee_promoted_from_waitlist( $comment, $event, $rsvp_type );
		}
	}

	/**
	 * Handle RSVP deleted notification.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $comment_id The comment ID of the deleted RSVP.
	 * @param string $rsvp_type  The RSVP type slug.
	 *
	 * @return void
	 */
	public function on_rsvp_deleted( int $comment_id, string $rsvp_type ): void {
		/**
		 * Filter: Type-specific RSVP deleted notification.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $comment_id The comment ID.
		 * @param string $rsvp_type  The RSVP type slug.
		 */
		do_action( "gatherpress_rsvp_deleted_{$rsvp_type}", $comment_id, $rsvp_type );
	}

	/**
	 * Notify event organizer that an RSVP was received.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Comment $comment The RSVP comment.
	 * @param Event       $event   The event object.
	 * @param array       $data    Contextual data.
	 *
	 * @return void
	 */
	private function notify_organizer_rsvp_received( \WP_Comment $comment, Event $event, array $data ): void {
		$status = $data['status'] ?? 'unknown';

		if ( 'not_attending' === $status || 'no_status' === $status ) {
			return; // Don't notify on these statuses.
		}

		$organizer_ids = $event->get_organizers_ids();

		if ( empty( $organizer_ids ) ) {
			return;
		}

		$type = Manager::get_type( $data['type'] );

		if ( ! $type ) {
			return;
		}

		$identifier   = $data['identifier'] ?? null;
		$display_name = $type->get_display_name( $identifier );

		/**
		 * Filter: Organizer RSVP received notification.
		 *
		 * @since 1.0.0
		 *
		 * @param \WP_Comment $comment      The RSVP comment.
		 * @param Event       $event        The event.
		 * @param string      $display_name The RSVP display name.
		 * @param string      $status       The RSVP status.
		 * @param array       $organizer_ids List of organizer user IDs.
		 */
		do_action( 'gatherpress_notify_organizer_rsvp_received', $comment, $event, $display_name, $status, $organizer_ids );
	}

	/**
	 * Notify event organizer that an RSVP status changed.
	 *
	 * @since 1.0. 0
	 *
	 * @param \WP_Comment $comment   The RSVP comment.
	 * @param Event       $event     The event object.
	 * @param string      $old_status The previous status.
	 * @param string      $new_status The new status.
	 * @param string      $rsvp_type  The RSVP type slug.
	 *
	 * @return void
	 */
	private function notify_organizer_rsvp_status_changed( \WP_Comment $comment, Event $event, string $old_status, string $new_status, string $rsvp_type ): void {
		$organizer_ids = $event->get_organizers_ids();

		if ( empty( $organizer_ids ) ) {
			return;
		}

		/**
		 * Filter: Organizer RSVP status changed notification.
		 *
		 * @since 1.0. 0
		 *
		 * @param \WP_Comment $comment     The RSVP comment.
		 * @param Event       $event       The event.
		 * @param string      $old_status  The previous status.
		 * @param string      $new_status  The new status.
		 * @param string      $rsvp_type   The RSVP type slug.
		 * @param array       $organizer_ids List of organizer user IDs.
		 */
		do_action( 'gatherpress_notify_organizer_rsvp_status_changed', $comment, $event, $old_status, $new_status, $rsvp_type, $organizer_ids );
	}

	/**
	 * Notify attendee that they've been promoted from waiting list to attending.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Comment $comment  The RSVP comment.
	 * @param Event       $event    The event object.
	 * @param string      $rsvp_type The RSVP type slug.
	 *
	 * @return void
	 */
	private function notify_attendee_promoted_from_waitlist( \WP_Comment $comment, Event $event, string $rsvp_type ): void {
		/**
		 * Filter: Attendee promoted from waiting list notification.
		 *
		 * @since 1.0.0
		 *
		 * @param \WP_Comment $comment  The RSVP comment.
		 * @param Event       $event    The event object.
		 * @param string      $rsvp_type The RSVP type slug.
		 */
		do_action( 'gatherpress_notify_attendee_promoted', $comment, $event, $rsvp_type );
	}
}