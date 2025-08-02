<?php
/**
 * Handles feed improvements for GatherPress events.
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
use WP_Query;

/**
 * Class Feed_Improvements.
 *
 * Manages feed improvements for GatherPress events.
 *
 * @since 1.0.0
 */
class Feed_Improvements {
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
		// Filter events in feeds to show only upcoming events.
		add_action( 'pre_get_posts', array( $this, 'filter_events_feed' ) );

		// Customize RSS excerpts for events.
		add_filter( 'the_excerpt_rss', array( $this, 'customize_event_excerpt' ) );
		add_filter( 'the_content_feed', array( $this, 'customize_event_content' ) );

		// Hook into the main query before it's executed.
		add_action( 'pre_get_posts', array( $this, 'force_events_feed_query' ), 5 );

		// Add custom rewrite rules for events feed.
		add_action( 'init', array( $this, 'add_events_feed_rewrite_rules' ) );
	}

	/**
	 * Filter events in feeds to show only upcoming events ordered by start date.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public function filter_events_feed( WP_Query $query ): void {
		// Only apply to event feeds.
		if ( ! $query->is_feed ) {
			return;
		}

		// Check if this is an events feed by looking at the request URL or post type.
		$is_events_feed = false;

		// Check if post type is set to events.
		if ( Event::POST_TYPE === $query->get( 'post_type' ) ) {
			$is_events_feed = true;
		}

		// Also check if the request URL contains 'events' and this is a feed.
		if ( ! $is_events_feed && isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			if ( strpos( $request_uri, '/events/feed' ) !== false ) {
				$is_events_feed = true;
				// Force the post type to events.
				$query->set( 'post_type', Event::POST_TYPE );
			}
		}

		if ( ! $is_events_feed ) {
			return;
		}

		// Set the query to only show upcoming events.
		$query->set( 'gatherpress_events_query', 'upcoming' );

		// Ensure proper ordering by start date.
		$query->set( 'orderby', 'datetime_start_gmt' );
		$query->set( 'order', 'ASC' );
	}

	/**
	 * Intercept events feed requests to ensure they show events, not comments.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	public function intercept_events_feed( \WP $wp ): void {
		// Check if this is an events feed request.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			// Check if this is /events/feed/.
			if ( strpos( $request_uri, '/events/feed' ) !== false ) {
				// Force this to be an events feed, not a comments feed.
				$wp->query_vars['post_type'] = Event::POST_TYPE;
				$wp->query_vars['feed']      = 'rss2';

				// Remove any comment-related query vars.
				unset( $wp->query_vars['comments'] );
				unset( $wp->query_vars['comment_feed'] );

				// Also try to force the main query to be an events query.
				add_action(
					'wp',
					function () {
						global $wp_query;
						if ( $wp_query->is_feed ) {
							$wp_query->set( 'post_type', Event::POST_TYPE );
							$wp_query->set( 'gatherpress_events_query', 'upcoming' );
							$wp_query->set( 'orderby', 'datetime_start_gmt' );
							$wp_query->set( 'order', 'ASC' );
						}
					}
				);
			}
		}
	}

	/**
	 * Force the feed to show events instead of comments.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function force_events_feed(): void {
		// Check if this is an events feed request.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			// Check if this is /events/feed/.
			if ( strpos( $request_uri, '/events/feed' ) !== false ) {
				// Force the query to be an events query.
				global $wp_query;
				$wp_query->set( 'post_type', Event::POST_TYPE );
				$wp_query->set( 'gatherpress_events_query', 'upcoming' );
				$wp_query->set( 'orderby', 'datetime_start_gmt' );
				$wp_query->set( 'order', 'ASC' );

				// Re-run the query to get events.
				$wp_query->query( $wp_query->query_vars );
			}
		}
	}

	/**
	 * Force the events feed query at the earliest possible point.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function force_events_feed_query( \WP_Query $query ): void {
		// Only run on the main query and if it's a feed.
		if ( ! $query->is_main_query() || ! $query->is_feed ) {
			return;
		}

		// Check if this is an events feed request.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			// Check if this is /events/feed/.
			if ( strpos( $request_uri, '/events/feed' ) !== false ) {
				// Force this to be an events query.
				$query->set( 'post_type', Event::POST_TYPE );
				$query->set( 'gatherpress_events_query', 'upcoming' );
				$query->set( 'orderby', 'datetime_start_gmt' );
				$query->set( 'order', 'ASC' );

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
		$event_info    = array();
		$datetime_data = $event->get_datetime();

		if ( ! empty( $datetime_data['datetime_start'] ) ) {
			$start_timestamp = strtotime( $datetime_data['datetime_start'] );
			$end_timestamp   = ! empty( $datetime_data['datetime_end'] ) ? strtotime( $datetime_data['datetime_end'] ) : null;

			$date_format = get_option( 'date_format' );
			$time_format = get_option( 'time_format' );
			$start_date  = wp_date( $date_format, $start_timestamp );
			$start_time  = wp_date( $time_format, $start_timestamp );

			if ( $end_timestamp && $end_timestamp !== $start_timestamp ) {
				$end_date = wp_date( $date_format, $end_timestamp );
				$end_time = wp_date( $time_format, $end_timestamp );

				// If same day, show time range.
				if ( $start_date === $end_date ) {
					$event_info[] = sprintf(
						/* translators: 1: Date, 2: Start time, 3: End time */
						__( 'Date: %1$s from %2$s to %3$s', 'gatherpress' ),
						$start_date,
						$start_time,
						$end_time
					);
				} else {
					$event_info[] = sprintf(
						/* translators: 1: Start date and time, 2: End date and time */
						__( 'Date: %1$s to %2$s', 'gatherpress' ),
						$start_date . ' ' . $start_time,
						$end_date . ' ' . $end_time
					);
				}
			} else {
				$event_info[] = sprintf(
					/* translators: 1: Date, 2: Time */
					__( 'Date: %1$s at %2$s', 'gatherpress' ),
					$start_date,
					$start_time
				);
			}
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
		if ( Event::POST_TYPE !== $post->post_type ) {
			return $excerpt;
		}

		$event = new Event( $post->ID );
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
		if ( ! empty( $excerpt ) && $excerpt !== $post->post_content ) {
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
		if ( Event::POST_TYPE !== $post->post_type ) {
			return $content;
		}

		$event = new Event( $post->ID );
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

		// Flush rewrite rules only once.
		if ( ! get_option( 'gatherpress_events_feed_rewrite_flushed' ) ) {
			flush_rewrite_rules();
			update_option( 'gatherpress_events_feed_rewrite_flushed', true );
		}
	}
}
