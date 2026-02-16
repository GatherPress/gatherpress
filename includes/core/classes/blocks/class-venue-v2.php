<?php
/**
 * The "Venue_V2" class handles the functionality of the Venue block,
 * ensuring proper rendering.
 *
 * ...
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue;
use WP_Block;
use WP_Post;

/**
 * Class responsible for managing the "Venue_V2" block and its functionality,
 * including dynamic rendering adjustments.
 *
 * @since 1.0.0
 */
class Venue_V2 {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/venue-v2';

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
		add_filter( sprintf( 'render_block_%s', self::BLOCK_NAME ), array( $this, 'render_block' ), 10, 3 );
	}

	/**
	 * Renders the venue block with appropriate context.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/render_block_this-name/
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $block_content The block content.
	 * @param array|null  $block         The full block, including name and attributes.
	 * @param WP_Block    $instance      The block instance.
	 *
	 * @return string
	 */
	public function render_block( ?string $block_content, ?array $block, WP_Block $instance ): string {
		// Handle null inputs early.
		// See https://developer.wordpress.org/reference/hooks/render_block/#comment-6606.
		if ( is_null( $block_content ) || is_null( $block ) ) {
			return is_string( $block_content ) ? $block_content : '';
		}

		$venue_post   = $this->get_venue_post_for_block( $block );
		$current_post = get_post();
		$is_event     = Event::POST_TYPE === get_post_type( $current_post );

		// Has venue - render with venue context.
		if ( $venue_post instanceof WP_Post ) {
			return $this->render_with_venue_context( $venue_post, $instance );
		}

		// No venue, but online-only event - render content as-is.
		if ( $is_event && $this->is_online_only_event( $current_post->ID ) ) {
			return $block_content;
		}

		// No venue and not online-only event - don't render.
		if ( $is_event ) {
			return '';
		}

		// Non-events render as-is.
		return $block_content;
	}

	/**
	 * Gets the venue post for the block based on context.
	 *
	 * Checks for a manually selected venue in block attributes first,
	 * then falls back to getting the venue from the current event.
	 *
	 * @since 1.0.0
	 *
	 * @param array $block The full block, including name and attributes.
	 *
	 * @return WP_Post|null The venue post or null if not found.
	 */
	private function get_venue_post_for_block( array $block ): ?WP_Post {
		// Check for manually selected venue in block attributes.
		if ( isset( $block['attrs']['selectedPostId'] ) && is_int( $block['attrs']['selectedPostId'] ) ) {
			$venue_post = get_post( $block['attrs']['selectedPostId'] );

			if ( $venue_post instanceof WP_Post && Venue::POST_TYPE === $venue_post->post_type ) {
				return $venue_post;
			}
		}

		$current_post = get_post();

		// If not an event, no venue to get.
		if ( Event::POST_TYPE !== get_post_type( $current_post ) ) {
			return null;
		}

		// Check for online-only event.
		if ( $this->is_online_only_event( $current_post->ID ) ) {
			return null;
		}

		// Get venue from event.
		$venue_post = Venue::get_instance()->get_venue_post_from_event_post_id( $current_post->ID );

		if ( $venue_post instanceof WP_Post && Venue::POST_TYPE === $venue_post->post_type ) {
			return $venue_post;
		}

		return null;
	}

	/**
	 * Checks if an event is online-only.
	 *
	 * An event is online-only if it has exactly one venue term
	 * and that term is the 'online-event' term.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id The event post ID.
	 *
	 * @return bool True if online-only, false otherwise.
	 */
	private function is_online_only_event( int $event_id ): bool {
		$venue_terms = get_the_terms( $event_id, Venue::TAXONOMY );

		return is_array( $venue_terms )
			&& 1 === count( $venue_terms )
			&& 'online-event' === $venue_terms[0]->slug;
	}

	/**
	 * Renders the block with venue post context.
	 *
	 * Sets up the global post and block context to the venue post,
	 * renders the inner blocks, then restores the original context.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Post  $venue_post The venue post to use as context.
	 * @param WP_Block $instance   The block instance.
	 *
	 * @return string The rendered block content.
	 */
	private function render_with_venue_context( WP_Post $venue_post, WP_Block $instance ): string {
		global $post;
		$original_post = $post;

		/*
		 * Override global $post for core/post-title block compatibility.
		 *
		 * @see https://github.com/WordPress/gutenberg/pull/37622#issuecomment-1000932816
		 */
		$post = $venue_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Use 'core/null' to prevent rendering block supports on inner blocks.
		$block_instance              = $instance->parsed_block;
		$block_instance['blockName'] = 'core/null';

		$post_id   = $venue_post->ID;
		$post_type = $venue_post->post_type;

		$filter_block_context = static function ( $context ) use ( $post_id, $post_type ) {
			$context['postType'] = $post_type;
			$context['postId']   = $post_id;
			return $context;
		};

		add_filter( 'render_block_context', $filter_block_context, PHP_INT_MIN );
		$block_content = ( new WP_Block( $block_instance ) )->render();
		remove_filter( 'render_block_context', $filter_block_context, PHP_INT_MIN );

		$post = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		return $block_content;
	}
}
