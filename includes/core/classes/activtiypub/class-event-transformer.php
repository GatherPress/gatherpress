<?php
/**
 * Manages transformation of the GatherPress object to ActivityPub.
 *
 * This class is responsible defining getter functions for all common event
 * properties of an extended ActivityPub event object and governing the
 * transformation process.
 *
 * @package GatherPress\Core\Activitypub
 * @since 1.0.0
 */

namespace GatherPress\Core\Activitypub;

use function Activitypub\get_rest_url_by_path;

/**
 * Class Event_Transformer.
 *
 * Extending the common Post Transformer.
 *
 * @since 1.0.0
 */
class Event_Transformer extends \Activitypub\Transformer\Post {
	/**
	 * The target transformet ActivityPub Event object.
	 *
	 * @var Event
	 */
	protected $ap_object;

	/**
	 * The current GatherPress Event object.
	 *
	 * @var Event
	 */
	protected $gp_event;

	/**
	 * Get transformer name.
	 *
	 * Retrieve the transformers name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget name.
	 */
	public function get_transformer_name() {
		return 'gatherpress/gp-event';
	}

	/**
	 * Get transformer title.
	 *
	 * Retrieve the transformers label.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string Widget title.
	 */
	public function get_transformer_label() {
		return 'GatherPress Event';
	}

	/**
	 * Get supported post types.
	 *
	 * Retrieve the list of supported WordPress post types this transformer widget can handle.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Widget categories.
	 */
	public static function get_supported_post_types() {
		return array( \GatherPress\Core\Event::POST_TYPE );
	}

	/**
	 * Returns the ActivityStreams 2.0 Object-Type for an Event.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-event
	 * @since 1.0.0
	 * @return string The Event Object-Type.
	 */
	protected function get_type() {
		return 'Event';
	}

	/**
	 * Get the event location.
	 *
	 * @return array The Place.
	 */
	public function get_location() {
        $venue  = $this->gp_event->get_venue_information();
		$address = $venue['full_address'];
		$place   = new \Activitypub\Activity\Extended_Object\Place();
		$place->set_type( 'Place' );
		$place->set_name( $address );
		$place->set_address( $address );
		return $place;
	}

	/**
	 * Get the end time from the event object.
	 */
	protected function get_end_time() {
		return $this->gp_event->get_datetime_end( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Get the end time from the event object.
	 */
	protected function get_start_time() {
		return $this->gp_event->get_datetime_start( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Get the event link from the events metadata.
	 */
	private function get_event_link() {
		$event_link = get_post_meta( $this->wp_object->ID, 'event-link', true );
		if ( $event_link ) {
			return array(
				'type'      => 'Link',
				'name'      => 'Website',
				'href'      => \esc_url( $event_link ),
				'mediaType' => 'text/html',
			);
		}
	}

	/**
	 * Overrides/extends the get_attachments function to also add the event Link.
	 */
	protected function get_attachment() {
		$attachments = parent::get_attachment();
		if ( count( $attachments ) ) {
			$attachments[0]['name'] = 'Banner';
		}
		$event_link = $this->get_event_link();
		if ( $event_link ) {
			$attachments[] = $this->get_event_link();
		}
		return $attachments;
	}

	/**
	 * TODO:
	 *
	 * @return string $category
	 */
	protected function get_category() {
		return 'MEETING';
	}

	/**
	 * Create a custom summary.
	 *
	 * It contains also the most important meta-information. The summary is often used when the
	 * ActivityPub object type 'Event' is not supported, e.g. in Mastodon.
	 *
	 * @return string $summary The custom event summary.
	 */
	public function get_summary() {
		if ( $this->wp_object->excerpt ) {
			$excerpt = $this->wp_object->post_excerpt;
		} elseif ( get_post_meta( $this->wp_object->ID, 'event-summary', true ) ) {
			$excerpt = get_post_meta( $this->wp_object->ID, 'event-summary', true );
		} else {
			$excerpt = $this->get_content();
		}

		$address           = get_post_meta( $this->wp_object->ID, 'event-location', true );
		$start_time        = get_post_meta( $this->wp_object->ID, 'event-start-date', true );
		$datetime_format   = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$start_time_string = wp_date( $datetime_format, $start_time );
		$summary           = "📍 {$address}\n📅 {$start_time_string}\n\n{$excerpt}";
		return $summary;
	}

	/**
	 * Transform the WordPress Object into an ActivityPub Object.
	 *
	 * @return Activitypub\Activity\Event
	 */
	public function to_object() {
		$this->ap_object = new \Activitypub\Activity\Extended_Object\Event();
		$this->gp_event  = new \GatherPress\Core\Event( $this->wp_object->ID );
		
		$this->ap_object = parent::to_object();

		$this->ap_object->set_comments_enabled( 'open' === $this->wp_object->comment_status ? true : false );

		$this->ap_object->set_external_participation_url( $this->get_url() );

		$online_event_link = $this->gp_event->maybe_get_online_event_link();

		if ( $online_event_link ) {
			$this->ap_object->set_is_online( true );
		} else {
			$this->ap_object->set_is_online( false );
		}

		$this->ap_object->set_status( 'CONFIRMED' );

		$this->ap_object->set_name( get_the_title( $this->wp_object->ID ) );

		$this->ap_object->set_actor( $this->get_attributed_to() );

        $path = sprintf( 'users/%d/followers', intval( $this->wp_object->post_author ) );

        $this->ap_object->set_to(
			array(
				'https://www.w3.org/ns/activitystreams#Public',
				get_rest_url_by_path( $path ),
			)
		);

		$this->ap_object->set_location();
		return $this->ap_object;
	}
}
