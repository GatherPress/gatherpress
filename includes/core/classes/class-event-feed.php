<?php
/**
 * Handles event feeds for GatherPress.
 *
 * This class is responsible for improving the default RSS feeds for events,
 * including filtering to show only upcoming events, ordering by start date,
 * and customizing excerpts to show event details.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP;
use WP_Query;

/**
 * Class Event_Feed.
 *
 * Manages event feeds for GatherPress.
 *
 * @since 1.0.0
 */
class Event_Feed {
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
		// Customize RSS excerpts for events.
		add_filter( 'the_excerpt_rss', array( $this, 'customize_event_excerpt' ) );
		add_filter( 'the_content_feed', array( $this, 'customize_event_content' ) );

		// Hook into the main query to handle events feeds.
		add_action( 'pre_get_posts', array( $this, 'handle_events_feed_query' ) );

		// Add custom rewrite rules for events feed.
		add_action( 'init', array( $this, 'add_events_feed_rewrite_rules' ) );
	}



	/**
	 * Handle events feed queries by setting the appropriate parameters.
	 *
	 * This method works in conjunction with Event_Query::prepare_event_query_before_execution()
	 * to ensure feeds show upcoming events with proper sorting.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function handle_events_feed_query( WP_Query $query ): void {
		// Only run on the main query and if it's a feed.
		if ( ! $query->is_main_query() || ! $query->is_feed ) {
			return;
		}

		// Check if this is an events feed request.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			// Check if this is /events/feed/.
			if ( strpos( $request_uri, '/events/feed' ) !== false ) {
				// Set the post type and let Event_Query handle the rest.
				$query->set( 'post_type', Event::POST_TYPE );
				$query->set( 'gatherpress_events_query', 'upcoming' );

				// Remove any comment-related query vars.
				$query->set( 'comments', false );
				$query->set( 'comment_feed', false );

				// Force WordPress to use the default feed template.
				$query->set( 'feed', 'rss2' );
			}
		}
	}



	/**
	 * Get formatted event datetime information for feeds.
	 *
	 * @since 1.0.0
	 *
	 * @param Event $event The event object.
	 * @return array Array of event information strings.
	 */
	private function get_event_datetime_info( Event $event ): array {
		$event_info        = array();
		$display_datetime  = $event->get_display_datetime();

		if ( ! empty( $display_datetime ) && __( '—', 'gatherpress' ) !== $display_datetime ) {
			$event_info[] = sprintf(
				/* translators: %s: Formatted date and time */
				__( 'Date: %s', 'gatherpress' ),
				$display_datetime
			);
		}

		return $event_info;
	}

	/**
	 * Customize RSS excerpts for events to include date and venue information.
	 *
	 * @since 1.0.0
	 *
	 * @param string $excerpt The current excerpt.
	 * @return string The customized excerpt.
	 */
	public function customize_event_excerpt( string $excerpt ): string {
		global $post;

		// Only apply to events.
		if ( Event::POST_TYPE !== get_post_type( $post ) ) {
			return $excerpt;
		}

		$event = new Event( get_the_ID() );
		$venue = $event->get_venue_information();

		$event_info = $this->get_event_datetime_info( $event );

		// Add venue information.
		if ( $venue && ! empty( $venue['name'] ) ) {
			$event_info[] = sprintf(
				/* translators: %s: Venue name */
				__( 'Venue: %s', 'gatherpress' ),
				$venue['name']
			);
		}

		// Build the customized excerpt.
		$custom_excerpt = '';

		if ( ! empty( $event_info ) ) {
			$custom_excerpt .= '<p><strong>' . implode( ' | ', $event_info ) . '</strong></p>';
		}

		// Add the original excerpt if it exists and is different from the content.
		if ( ! empty( $excerpt ) && get_the_content() !== $excerpt ) {
			$clean_excerpt = wp_strip_all_tags( $excerpt );
			$clean_excerpt = preg_replace( '/\s+/', ' ', $clean_excerpt ); // Normalize whitespace.
			$clean_excerpt = trim( $clean_excerpt );

			if ( ! empty( $clean_excerpt ) ) {
				$custom_excerpt .= '<p>' . $clean_excerpt . '</p>';
			}
		}

		return $custom_excerpt;
	}

	/**
	 * Customize RSS content for events to include event details.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The current content.
	 * @return string The customized content.
	 */
	public function customize_event_content( string $content ): string {
		global $post;

		// Only apply to events.
		if ( Event::POST_TYPE !== get_post_type( $post ) ) {
			return $content;
		}

		$event = new Event( get_the_ID() );
		$venue = $event->get_venue_information();

		$event_info = $this->get_event_datetime_info( $event );

		// Add venue information.
		if ( $venue && ! empty( $venue['name'] ) ) {
			$event_info[] = sprintf(
				/* translators: %s: Venue name */
				__( 'Venue: %s', 'gatherpress' ),
				$venue['name']
			);
		}

		// Build the customized content.
		$custom_content = '';

		if ( ! empty( $event_info ) ) {
			$custom_content .= '<p><strong>' . implode( ' | ', $event_info ) . '</strong></p>';
		}

		// For RSS feeds, provide a cleaner version of the content.
		// Strip out complex HTML and keep only essential information.
		$clean_content = wp_strip_all_tags( $content );
		$clean_content = preg_replace( '/\s+/', ' ', $clean_content ); // Normalize whitespace.
		$clean_content = trim( $clean_content );

		if ( ! empty( $clean_content ) ) {
			$custom_content .= '<p>' . $clean_content . '</p>';
		}

		return $custom_content;
	}



	/**
	 * Add custom rewrite rules for events feed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_events_feed_rewrite_rules(): void {
		// Add a rewrite rule for /events/feed/ to be treated as an events feed.
		add_rewrite_rule(
			'^events/feed/?$',
			'index.php?post_type=' . Event::POST_TYPE . '&feed=rss2',
			'top'
		);
	}

}
