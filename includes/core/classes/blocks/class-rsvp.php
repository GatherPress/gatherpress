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
				 * Dynamically render inner blocks based on the saved RSVP status.
				 *
				 * This ensures that the block correctly renders the inner blocks
				 * for the currently selected RSVP status and updates the serialized
				 * inner blocks attribute. It addresses the issue of inner blocks
				 * not persisting properly when switching between statuses.
				 *
				 * The method generates dynamic markup for all statuses, wrapping each
				 * rendered inner block set in a container with a `data-rsvp-status`
				 * attribute.
				 */
				$saved_status = $attributes['selectedStatus'] ?? 'no_status';

				// Decode serialized inner blocks from attributes.
				$serialized_inner_blocks = $attributes['serializedInnerBlocks'] ?? '';
				$serialized_inner_blocks = json_decode( $serialized_inner_blocks, true );

				// Serialize the current inner blocks for the saved status.
				$serialized_inner_blocks[ $saved_status ] = serialize_blocks( $inner_blocks );

				// Render inner blocks for all statuses.
				$inner_blocks_markup = '';
				foreach ( $serialized_inner_blocks as $status => $serialized_inner_block ) {
					$inner_blocks_markup .= sprintf(
						'<div style="display:none;" data-rsvp-status="%s">%s</div>',
						esc_attr( $status ),
						do_blocks( $serialized_inner_block )
					);
				}

				// Set dynamic attributes for interactivity.
				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-context', wp_json_encode( array( 'postId' => get_the_ID() ) ) );
				$tag->set_attribute( 'data-wp-watch', 'callbacks.renderRsvpBlock' );

				// Get the updated block content.
				$block_content = $tag->get_updated_html();

				// @todo: Replace this workaround with a method to properly update inner blocks
				// when https://github.com/WordPress/gutenberg/issues/60397 is resolved.
				preg_match( '/<div\b[^>]*>/i', $block_content, $matches );

				// Use the matched opening <div> tag or fallback to a generic <div>.
				$opening_div = $matches[0] ?? '<div>';

				// Close the block with a standard </div>.
				$closing_div = '</div>';

				// Construct the updated block content with the new inner blocks markup.
				$block_content = $opening_div . $inner_blocks_markup . $closing_div;
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

		if (
			isset( $block['attrs']['className'] ) &&
			false !== strpos( $block['attrs']['className'], 'gatherpress--has-registration-url' ) &&
			! get_option( 'users_can_register' )
		) {
			return '';
		}

		return $block_content;
	}
}
