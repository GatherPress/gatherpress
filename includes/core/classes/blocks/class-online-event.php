<?php
/**
 * The "Online_Event" class handles the functionality of the Online Event block,
 * ensuring proper context for its inner blocks.
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

/**
 * Class responsible for managing the "Online Event" block and its functionality,
 * including providing postId context to inner blocks when an override is set.
 *
 * @since 1.0.0
 */
class Online_Event {
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
	const BLOCK_NAME = 'gatherpress/online-event-v2';

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
	 * Renders the online event block with appropriate context.
	 *
	 * When the block has a postId attribute (override), this method
	 * ensures that postId is passed as context to inner blocks.
	 * The block is hidden if the event doesn't have the online-event term.
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
		if ( is_null( $block_content ) || is_null( $block ) ) {
			return is_string( $block_content ) ? $block_content : '';
		}

		// Check if block has a postId override attribute.
		$post_id = isset( $block['attrs']['postId'] ) ? intval( $block['attrs']['postId'] ) : get_the_ID();

		// Don't render if the event doesn't have the online-event term.
		if ( ! $this->has_online_event_term( $post_id ) ) {
			return '';
		}

		// No override - render as-is.
		if ( ! isset( $block['attrs']['postId'] ) ) {
			return $block_content;
		}

		// Re-render inner blocks with the override postId as context.
		return $this->render_with_post_context( $post_id, $instance );
	}

	/**
	 * Checks if an event has the online-event term.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check.
	 *
	 * @return bool True if the event has the online-event term, false otherwise.
	 */
	private function has_online_event_term( int $post_id ): bool {
		// Only check for event post types.
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return true;
		}

		$venue_terms = get_the_terms( $post_id, Venue::TAXONOMY );

		if ( ! is_array( $venue_terms ) ) {
			return false;
		}

		foreach ( $venue_terms as $term ) {
			if ( 'online-event' === $term->slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Renders the block with post context override.
	 *
	 * Sets up the block context to use the override postId,
	 * then renders the inner blocks with that context.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id  The post ID to use as context.
	 * @param WP_Block $instance The block instance.
	 *
	 * @return string The rendered block content.
	 */
	private function render_with_post_context( int $post_id, WP_Block $instance ): string {
		// Use 'core/null' to prevent rendering block supports on inner blocks.
		$block_instance              = $instance->parsed_block;
		$block_instance['blockName'] = 'core/null';

		$filter_block_context = static function ( $context ) use ( $post_id ) {
			$context['postId'] = $post_id;
			return $context;
		};

		add_filter( 'render_block_context', $filter_block_context, PHP_INT_MIN );
		$block_content = ( new WP_Block( $block_instance ) )->render();
		remove_filter( 'render_block_context', $filter_block_context, PHP_INT_MIN );

		return $block_content;
	}
}
