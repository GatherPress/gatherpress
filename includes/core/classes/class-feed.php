<?php
/**
 * Handles feeds for GatherPress.
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
use GatherPress\Core\Settings;
use WP_Query;

/**
 * Class Feed.
 *
 * Manages feeds for GatherPress.
 *
 * @since 1.0.0
 */
class Feed {
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
		// Add hooks for flexible event feed customization.
		add_filter( 'gatherpress_event_feed_excerpt', array( $this, 'get_default_event_excerpt' ) );
		add_filter( 'gatherpress_event_feed_content', array( $this, 'get_default_event_content' ) );

		// Apply feed customization only if no theme/editor overrides.
		add_filter( 'the_excerpt_rss', array( $this, 'apply_event_excerpt' ) );
		add_filter( 'the_content_feed', array( $this, 'apply_event_content' ) );

		// Hook into the main query to handle events feeds.
		add_action( 'pre_get_posts', array( $this, 'handle_events_feed_query' ) );

		// Modify feed link for past events page.
		add_filter( 'post_type_archive_feed_link', array( $this, 'modify_feed_link_for_past_events' ) );
	}

	/**
	 * Handle events feed queries by setting the appropriate parameters.
	 *
	 * This method works in conjunction with Event_Query::prepare_event_query_before_execution()
	 * to ensure feeds show upcoming events with proper sorting. Supports ?type=past parameter
	 * to show past events instead of upcoming events.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function handle_events_feed_query( WP_Query $query ): void {
		// Only run on the main query and if it's a feed.
		if ( ! $query->is_main_query() || ! is_feed() ) {
			return;
		}

		// Check if this is an events feed request.
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

			// Get the rewrite slug from settings.
			$settings     = Settings::get_instance();
			$rewrite_slug = $settings->get_value( 'general', 'urls', 'events' );

			// Check if this is the events feed URL.
			if ( str_contains( $request_uri, '/' . $rewrite_slug . '/' . $GLOBALS['wp_rewrite']->feed_base ) ) {
				// Set the post type and let Event_Query handle the rest.
				$query->set( 'post_type', Event::POST_TYPE );

				// Check for type parameter to determine if we want past or upcoming events.
				$event_type = 'upcoming';
				// Default to upcoming events.
				if (
					isset( $_GET['type'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public feed URL parameter, nonce not required.
					'past' === sanitize_text_field( wp_unslash( $_GET['type'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public feed URL parameter, nonce not required.
				) {
					// Nonce verification not required for public feed URLs.
					$event_type = 'past';
				}

				$query->set( 'gatherpress_event_query', $event_type );
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
		$event_info       = array();
		$display_datetime = $event->get_display_datetime();

		if ( ! empty( $display_datetime ) && '—' !== $display_datetime ) {
			$event_info[] = sprintf(
				/* translators: %s: Formatted date and time */
				__( 'Date: %s', 'gatherpress' ),
				$display_datetime
			);
		}

		return $event_info;
	}

	/**
	 * Get default event excerpt customization.
	 *
	 * @since 1.0.0
	 *
	 * @param string $excerpt The current excerpt.
	 * @return string The customized excerpt.
	 */
	public function get_default_event_excerpt( string $excerpt ): string {
		// Only apply to events.
		if ( Event::POST_TYPE !== get_post_type() ) {
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
	 * Get default event content customization.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The current content.
	 * @return string The customized content.
	 */
	public function get_default_event_content( string $content ): string {
		// Only apply to events.
		if ( Event::POST_TYPE !== get_post_type() ) {
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
	 * Apply event excerpt customization with flexibility for themes/editors.
	 *
	 * @since 1.0.0
	 *
	 * @param string $excerpt The current excerpt.
	 * @return string The customized excerpt.
	 */
	public function apply_event_excerpt( string $excerpt ): string {
		// Only apply to events.
		if ( Event::POST_TYPE !== get_post_type() ) {
			return $excerpt;
		}

		// Allow themes and editors to customize the excerpt.
		/**
		 * Filters the event excerpt in feeds.
		 *
		 * Allows themes and plugins to modify the event excerpt before it is included in feeds.
		 * This can be used to add custom formatting, additional event information, or modify
		 * how event excerpts appear in RSS and other feeds.
		 *
		 * Example usage:
		 * ```php
		 * add_filter( 'gatherpress_event_feed_excerpt', function( $excerpt ) {
		 *     // Add event location to feed excerpt
		 *     $event = new \GatherPress\Core\Event( get_the_ID() );
		 *     $venue = $event->get_venue();
		 *     if ( $venue ) {
		 *         $excerpt .= "\n\nLocation: " . $venue;
		 *     }
		 *     return $excerpt;
		 * } );
		 * ```
		 *
		 * @since 1.0.0
		 *
		 * @param string $excerpt The event post excerpt.
		 */
		return apply_filters( 'gatherpress_event_feed_excerpt', $excerpt );
	}

	/**
	 * Apply event content customization with flexibility for themes/editors.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content The current content.
	 * @return string The customized content.
	 */
	public function apply_event_content( string $content ): string {
		// Only apply to events.
		if ( Event::POST_TYPE !== get_post_type() ) {
			return $content;
		}

		// Allow themes and editors to customize the content.
		/**
		 * Filters the event content in feeds.
		 *
		 * Allows themes and plugins to modify the event content before it is included in feeds.
		 * This can be used to add custom formatting, additional event information, or modify
		 * how event content appears in RSS and other feeds.
		 *
		 * Example usage:
		 * ```php
		 * add_filter( 'gatherpress_event_feed_content', function( $content ) {
		 *     // Add event location to feed content
		 *     $event = new \GatherPress\Core\Event( get_the_ID() );
		 *     $venue = $event->get_venue();
		 *     if ( $venue ) {
		 *         $content .= "\n\nLocation: " . $venue;
		 *     }
		 *     return $content;
		 * } );
		 * ```
		 *
		 * @since 1.0.0
		 *
		 * @param string $content The event post content.
		 */
		return apply_filters( 'gatherpress_event_feed_content', $content );
	}

	/**
	 * Modify feed link for past events page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $feed_link The feed link URL.
	 * @return string The modified feed link URL.
	 */
	public function modify_feed_link_for_past_events( $feed_link ): string {
		global $wp_query;

		// Check if we're on the past events page by looking for the gatherpress_event_query var.
		if (
			isset( $wp_query->query_vars['gatherpress_event_query'] ) &&
			'past' === $wp_query->query_vars['gatherpress_event_query']
		) {
			// Add type=past parameter.
			return add_query_arg( 'type', 'past', $feed_link );
		}

		return $feed_link;
	}
}
