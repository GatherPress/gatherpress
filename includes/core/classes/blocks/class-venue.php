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
class Venue {

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
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.34.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.34.0
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
	 * @since 0.34.0
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

		$source_post = $this->get_source_post( $block );

		// No source post resolved — don't render.
		if ( ! $source_post instanceof WP_Post ) {
			return '';
		}

		// Has source post — render with source context.
		return $this->render_with_source_context( $source_post, $instance );
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

	/**
	 * Renders the block with source post context.
	 *
	 * Sets up the global post and block context to the resolved shadow-source
	 * post (venue, tour, production, etc.), renders the inner blocks, then
	 * restores the original context.
	 *
	 * @since 0.34.0
	 *
	 * @param WP_Post  $source_post The source post to use as context.
	 * @param WP_Block $instance    The block instance.
	 *
	 * @return string The rendered block content.
	 */
	private function render_with_source_context( WP_Post $source_post, WP_Block $instance ): string {
		global $post;
		$original_post = $post;

		/*
		 * Override global $post for core/post-title block compatibility.
		 *
		 * @see https://github.com/WordPress/gutenberg/pull/37622#issuecomment-1000932816
		 */
		$post = $source_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Use 'core/null' to prevent rendering block supports on inner blocks.
		$block_instance              = $instance->parsed_block;
		$block_instance['blockName'] = 'core/null';

		$post_id   = $source_post->ID;
		$post_type = $source_post->post_type;

		$filter_block_context = static function ( array $context ) use ( $post_id, $post_type ): array {
			$context['postType'] = $post_type;
			$context['postId']   = $post_id;
			return $context;
		};

		// Use PHP_INT_MAX to ensure our context runs last and overrides query loop context.
		add_filter( 'render_block_context', $filter_block_context, PHP_INT_MAX );
		$block_content = ( new WP_Block( $block_instance ) )->render();
		remove_filter( 'render_block_context', $filter_block_context, PHP_INT_MAX );

		$post = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Build wrapper classes from block instance attributes.
		$classes = array( 'wp-block-gatherpress-venue' );

		if ( ! empty( $instance->attributes['align'] ) ) {
			$classes[] = 'align' . $instance->attributes['align'];
		}

		if ( ! empty( $instance->attributes['className'] ) ) {
			$classes[] = $instance->attributes['className'];
		}

		return sprintf(
			'<div class="%s">%s</div>',
			esc_attr( implode( ' ', $classes ) ),
			$block_content
		);
	}
}
