<?php
/**
 * The "Venue" class handles the functionality of the Venue block,
 * ensuring proper rendering.
 *
 * ...
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Shadow_Source;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue as Venue_Core;
use WP_Block;
use WP_Post;

/**
 * Class responsible for managing the "Venue" block and its functionality,
 * including dynamic rendering adjustments.
 *
 * @since 0.34.0
 */
final class Venue {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 0.34.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/venue';

	/**
	 * Class constructor.
	 *
	 * The block needs no hooks: `render.php` calls
	 * {@see self::render_inner_blocks()} directly.
	 *
	 * @since 0.34.0
	 */
	protected function __construct() {
	}

	/**
	 * Renders the block's inner blocks with the resolved source post as
	 * their context.
	 *
	 * WordPress renders a dynamic block's inner blocks before the render
	 * callback runs, using the surrounding post's context — wrong for a
	 * venue, whose children (post-title, venue details, map) must read the
	 * shadow-source post (venue, tour, production, etc.). This re-renders
	 * them from the parsed block with the source post passed as available
	 * context, so every descendant that consumes `postId`/`postType` sees
	 * the source post while nested providers (a query loop inside the
	 * venue, say) still override normally for their own children.
	 *
	 * Called from `render.php` before the wrapper is emitted, which is
	 * what lets core's block-supports pipeline (layout classes among the
	 * rest) decorate the one real wrapper afterward.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Block $instance The venue block instance.
	 *
	 * @return string|null The rendered inner blocks, or null when no
	 *                     source post resolves (the block renders nothing).
	 */
	public function render_inner_blocks( WP_Block $instance ): ?string {
		$source_post = $this->get_source_post( $instance->parsed_block );

		if ( ! $source_post instanceof WP_Post ) {
			return null;
		}

		global $post;
		$original_post = $post;

		/*
		 * Override global $post for core/post-title block compatibility.
		 *
		 * @see https://github.com/WordPress/gutenberg/pull/37622#issuecomment-1000932816
		 */
		$post = $source_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$context = array(
			'postId'   => $source_post->ID,
			'postType' => $source_post->post_type,
		);

		$rendered = '';

		foreach ( $instance->parsed_block['innerBlocks'] ?? array() as $inner_block ) {
			$rendered .= ( new WP_Block( $inner_block, $context ) )->render();
		}

		$post = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		return $rendered;
	}

	/**
	 * Resolves the configured shadow-source post type for this block instance.
	 *
	 * Defaults to `gatherpress_venue` for backward compatibility with content
	 * authored before #1687 introduced the `sourcePostType` attribute.
	 *
	 * @since 0.34.0
	 *
	 * @param array $block The full block, including name and attributes.
	 *
	 * @return string Shadow-source post type slug.
	 */
	private function get_source_post_type( array $block ): string {
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		return ! empty( $attrs['sourcePostType'] ) && is_string( $attrs['sourcePostType'] )
			? $attrs['sourcePostType']
			: Venue_Core::POST_TYPE;
	}

	/**
	 * Gets the source post for the block based on context.
	 *
	 * Checks for a manually selected source post in block attributes first,
	 * then falls back to resolving the source from the current event via the
	 * configured shadow-source post type's taxonomy. Handles query loop,
	 * post ID override, and global contexts.
	 *
	 * @since 0.34.0
	 *
	 * @param array $block The full block, including name and attributes.
	 *
	 * @return WP_Post|null The source post or null if not found.
	 */
	private function get_source_post( array $block ): ?WP_Post {
		$source_post_type = $this->get_source_post_type( $block );
		$source_post      = null;

		// Check for a manually selected source post in block attributes.
		if ( isset( $block['attrs']['selectedPostId'] ) && is_int( $block['attrs']['selectedPostId'] ) ) {
			$selected = get_post( $block['attrs']['selectedPostId'] );

			if ( $selected instanceof WP_Post && $source_post_type === $selected->post_type ) {
				$source_post = $selected;
			}
		}

		if ( null === $source_post ) {
			// Get post ID from block attributes or global (handles query loop, override, global).
			$post_id   = Setup::get_instance()->get_post_id( $block );
			$post_type = get_post_type( $post_id );

			if ( $source_post_type === $post_type ) {
				$source_post = get_post( $post_id );
			} else {
				$candidate = Shadow_Source::get_instance()->get_source_post_from_event_post_id(
					(int) $post_id,
					$source_post_type
				);

				if ( $candidate instanceof WP_Post && $source_post_type === $candidate->post_type ) {
					$source_post = $candidate;
				}
			}
		}

		return $source_post;
	}
}
