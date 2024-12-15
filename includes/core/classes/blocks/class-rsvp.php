<?php
/**
 * The "RSVP" class manages the RSVP block and its variations,
 * primarily transforming block content and preparing it for output.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Block;
use GatherPress\Core\Traits\Singleton;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "RSVP" block and its variations,
 * including dynamic transformations and enhancements for interactive functionality.
 *
 * @since 1.0.0
 */
class Rsvp {
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
		add_filter( 'render_block', array( $this, 'transform_block_content' ), 10, 2 );
	}

	/**
	 * Modifies the content of the RSVP block and its inner blocks before rendering.
	 *
	 * This method dynamically applies specific modifications to the block content
	 * based on its name and attributes, ensuring the appropriate attributes and
	 * interactivity settings are added where necessary.
	 *
	 * @param string $block_content The original HTML content of the block.
	 * @param array  $block         An associative array containing block data, including `blockName` and `attrs`.
	 *
	 * @return string The updated block content with the applied transformations.
	 */
	public function transform_block_content( string $block_content, array $block ): string {
		$block_instance = Block::get_instance();

		if ( 'gatherpress/rsvp-v2' === $block['blockName'] ) {
			$inner_blocks = isset( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
			$tag          = new WP_HTML_Tag_Processor( $block_content );
			$attributes   = isset( $block['attrs'] ) ? $block['attrs'] : array();

			if ( $tag->next_tag() ) {
				/**
				 * Update the serialized inner blocks to ensure the current inner blocks for the saved status
				 * are stored correctly. This addresses the issue where saving blocks for a specific status
				 * doesn't persist changes.
				 *
				 * We retrieve the saved status and dynamically replace the corresponding serialized inner block
				 * with the current inner blocks. The updated serialized inner blocks are then re-encoded and
				 * saved as an attribute.
				 */
				$saved_status                             = $attributes['selectedStatus'] ?? 'no_status';
				$serialized_inner_blocks                  = $attributes['serializedInnerBlocks'] ?? '';
				$serialized_inner_blocks                  = json_decode(
					$serialized_inner_blocks,
					true
				);
				$serialized_inner_blocks[ $saved_status ] = serialize_blocks( $inner_blocks );
				$serialized_inner_blocks                  = wp_json_encode( $serialized_inner_blocks );

				$tag->remove_attribute( 'data-saved-status' );
				$tag->set_attribute(
					'data-serialized-inner-blocks',
					$serialized_inner_blocks
				);
				$tag->set_attribute(
					'data-wp-interactive',
					'gatherpress'
				);
				$tag->set_attribute(
					'data-wp-context',
					wp_json_encode( array( 'postId' => get_the_ID() ) )
				);
				$tag->set_attribute(
					'data-wp-watch',
					'callbacks.renderRsvpBlock'
				);
				$block_content = $tag->get_updated_html();
			}
		}

		if (
			'core/button' === $block['blockName'] &&
			isset( $block['attrs']['className'] ) &&
			false !== strpos( $block['attrs']['className'], 'gatherpress--update-rsvp' )
		) {
			$tag = new WP_HTML_Tag_Processor( $block_content );

			// Locate the <button> tag and set the attributes.
			$button_tag = $block_instance->locate_button_tag( $tag );
			if ( $button_tag ) {
				$button_tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$button_tag->set_attribute( 'data-wp-on--click', 'actions.updateRsvp' );
			}

			// Update the block content with new attributes.
			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}
}
