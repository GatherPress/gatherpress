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

use GatherPress\Core\Block;
use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue;
use WP_Block;
use WP_Post;
use WP_Query;
use WP_Term;

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
		$render_block_hook = sprintf( 'render_block_%s', self::BLOCK_NAME );

		add_filter( $render_block_hook, array( $this, 'render_venue_v2_block' ), 10, 3 );
	}

	/**
	 * Filters the content of the core/group block.
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
	public function render_venue_v2_block( ?string $block_content, ?array $block, WP_Block $instance ): string {
		// $block_content and $block can become null,
		// so be sure to handle these cases.
		// https://developer.wordpress.org/reference/hooks/render_block/#comment-6606
		if ( is_null( $block_content ) || is_null( $block ) ) {
			return is_string( $block_content ) ? $block_content : '';
		}

		$current_post  = get_post();
		$venue_post_id = null;
		$venue_post    = null;

		// Check that this is either an event,
		// which should have some venue data.
		//
		// Or alternatively, if this another post type,
		// look for the existence of a manually selected ID inside the blocks' attributes.
		if ( Event::POST_TYPE !== get_post_type( $current_post ) && ( ! isset( $block['attrs']['selectedPostId'] ) ) ) {
			return $block_content;
		}

		// Variant A: The block is somehow within an event.
		if ( Event::POST_TYPE === get_post_type( $current_post ) ) {
			$venue      = Venue::get_instance();
			$venue_post = $venue->get_venue_post_from_event_post_id( $current_post->ID );

			// Variant B: The block is NOT within an event, but has a venue selected to create a context of.
		} elseif (
			isset( $block['attrs'], $block['attrs']['selectedPostId'] ) &&
			is_int( $block['attrs']['selectedPostId'] )
		) {
			$venue_post_id = $block['attrs']['selectedPostId'];
		}

		if ( is_int( $venue_post_id ) && (int) $venue_post_id > 0 ) {
			$venue_post = get_post( $venue_post_id );
		}

		if ( ! $venue_post instanceof WP_Post || Venue::POST_TYPE !== $venue_post->post_type ) {
			// This might be an online-only event.
			return '';
		}

		/*
		 * Using "setup_postdata( $venue_post );" was not enough to make this work for all blocks,
		 * because the "core/post-title" block is a special edge case.
		 *
		 * "The `$post` argument is intentionally omitted" on the core/post-title block."
		 * (source: render_block_core_post_title())
		 *
		 * That's the reason for overwriting globals over here.
		 * @see https://github.com/WordPress/gutenberg/pull/37622#issuecomment-1000932816
		 */
		global $post;
		$post = $venue_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Get an instance of the current Post Template block.
		$block_instance = $instance->parsed_block;

		// Set the block name to one that does not correspond to an existing registered block.
		// This ensures that for the inner instances of the Post Template block, we do not render any block supports.
		$block_instance['blockName'] = 'core/null';

		$post_id              = $venue_post->ID;
		$post_type            = $venue_post->post_type;
		$filter_block_context = static function ( $context ) use ( $post_id, $post_type ) {
			$context['postType'] = $post_type;
			$context['postId']   = $post_id;
			return $context;
		};

		// Use an early priority to so that other 'render_block_context' filters have access to the values.
		add_filter( 'render_block_context', $filter_block_context, 1 );

		/*
		 * Render the inner blocks of the Post Template block with `dynamic` set to `false` to prevent calling
		 * `render_callback` and ensure that no wrapper markup is included.
		 *
		 * @todo Find out why I removed:
		 *       "$block_content = ( new WP_Block( $block_instance ) )->render( array( 'dynamic' => false ) );".
		 */
		$block_content = ( new WP_Block( $block_instance ) )->render();
		remove_filter( 'render_block_context', $filter_block_context, 1 );

		/*
		 * When using 'setup_postdata()' this would be the place
		 * to restore the context from the secondary query loop
		 * back to the main query loop, as it's always safest to restore.
		 *
		 * @see wp_reset_postdata()
		 */
		$post = $current_post;  // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		return $block_content;
	}
}
