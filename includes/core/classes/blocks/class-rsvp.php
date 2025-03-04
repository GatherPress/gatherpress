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
use GatherPress\Core\Event;
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
	 * Constant representing the Block Name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/rsvp';

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

		add_filter( $render_block_hook, array( $this, 'transform_block_content' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'apply_rsvp_button_interactivity' ) );
		// Priority 11 ensures this runs after transform_block_content which modifies the block structure.
		add_filter( $render_block_hook, array( $this, 'apply_guest_count_watch' ), 11 );
	}

	/**
	 * Dynamically transforms and renders the content of the RSVP block and its inner blocks.
	 *
	 * This method processes the RSVP block content, applying dynamic attributes and interactivity
	 * settings. It also handles the rendering of inner blocks based on the current RSVP status
	 * and ensures proper serialization of inner block states for persistence. The method
	 * dynamically adjusts the block content based on the event's status (e.g., past or active).
	 *
	 * Key functionalities:
	 * - Adds `data-wp-interactive` attributes for interactivity.
	 * - Dynamically renders and serializes inner blocks for each RSVP status.
	 * - Ensures proper markup updates based on the RSVP status and event state.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original HTML content of the block.
	 * @param array  $block         An associative array containing block data, including `blockName` and `attrs`.
	 *
	 * @return string The updated block content with dynamically rendered inner blocks and attributes.
	 */
	public function transform_block_content( string $block_content, array $block ): string {
		$block_instance = Block::get_instance();
		$post_id        = $block_instance->get_post_id( $block );
		$event          = new Event( $post_id );
		$inner_blocks   = isset( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
		$tag            = new WP_HTML_Tag_Processor( $block_content );
		$attributes     = isset( $block['attrs'] ) ? $block['attrs'] : array();

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

			if ( ! is_array( $serialized_inner_blocks ) ) {
				$serialized_inner_blocks = array();
			}

			// Serialize the current inner blocks for the saved status.
			$serialized_inner_blocks[ $saved_status ] = serialize_blocks( $inner_blocks );

			$user_data = array();

			if ( $event->rsvp ) {
				$user_data = $event->rsvp->get( get_current_user_id() );
			}

			$filtered_data   = array_intersect_key( $user_data, array_flip( array( 'status', 'guests', 'anonymous' ) ) );
			$filtered_status = ! empty( $filtered_data['status'] ) ? $filtered_data['status'] : 'no_status';

			if ( $event->has_event_past() ) {
				$inner_blocks_markup = do_blocks( $serialized_inner_blocks['past'] ?? '' );
			} else {
				unset( $serialized_inner_blocks['past'] );
				// Render inner blocks for all statuses.
				$inner_blocks_markup = '';

				foreach ( $serialized_inner_blocks as $status => $serialized_inner_block ) {
					$class                = $status !== $filtered_status ? 'gatherpress--is-not-visible' : '';
					$inner_blocks_markup .= sprintf(
						'<div class="%s" data-rsvp-status="%s">%s</div>',
						esc_attr( $class ),
						esc_attr( $status ),
						do_blocks( $serialized_inner_block )
					);
				}

				// Set dynamic attributes for interactivity.
				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-context', wp_json_encode( array( 'postId' => $post_id ) ) );
				$tag->set_attribute( 'data-user-details', wp_json_encode( $filtered_data ) );
				$tag->set_attribute( 'data-wp-watch', 'callbacks.renderRsvpBlock' );
			}

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

		return $block_content;
	}

	/**
	 * Adds interactivity to RSVP buttons within the block content.
	 *
	 * This method scans the block content for elements with the class
	 * `gatherpress--update-rsvp`. If such an element is found, it checks for a
	 * nested `<a>` or `<button>` tag. The appropriate attributes for interactivity
	 * are added to either the nested tag or the containing element if no nested tag
	 * exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original block content as a string.
	 *
	 * @return string The modified block content with interactivity attributes added.
	 */
	public function apply_rsvp_button_interactivity( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		// Process only tags with the specific class 'gatherpress--update-rsvp'.
		$rsvp_class = 'gatherpress--update-rsvp';

		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			if ( $class_attr && false !== strpos( $class_attr, $rsvp_class ) ) {
				$classes        = explode( ' ', $class_attr );
				$statuses       = array( 'attending', 'waiting-list', 'not-attending' );
				$matched_status = null;

				foreach ( $classes as $class ) {
					if ( false !== strpos( $class, $rsvp_class ) ) {
						foreach ( $statuses as $status ) {
							if ( sprintf( '%s__%s', $rsvp_class, $status ) === $class ) {
								$matched_status = $status;
								break 2;
							}
						}
					}
				}

				if (
					// @phpstan-ignore-next-line
					$tag->next_tag() &&
					in_array( $tag->get_tag(), array( 'A' ), true )
				) {
					$tag->set_attribute( 'role', 'button' ); // For links acting as buttons.
				} else {
					$tag->set_attribute( 'tabindex', '0' );
					$tag->set_attribute( 'role', 'button' );
				}

				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-on--click', 'actions.updateRsvp' );

				if ( ! empty( $matched_status ) ) {
					$tag->set_attribute( 'data-set-status', str_replace( '-', '_', $matched_status ) );
				}
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Adds a data-wp-watch attribute to the Guest Count Display Block.
	 *
	 * This method processes the block content of the Guest Count Display Block and
	 * adds the `data-wp-watch` attribute to enable dynamic updates using the
	 * specified callback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original block content.
	 *
	 * @return string The modified block content with the data-wp-watch attribute applied.
	 */
	public function apply_guest_count_watch( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );
		$tag->next_tag();
		$user_details = ! empty( $tag->get_attribute( 'data-user-details' ) ) ?
			json_decode( $tag->get_attribute( 'data-user-details' ), true ) :
			array();

		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			if ( $class_attr && false !== strpos( $class_attr, 'wp-block-gatherpress-rsvp-guest-count-display' ) ) {
				$tag->set_attribute( 'data-wp-watch', 'callbacks.updateGuestCountDisplay' );

				if ( empty( $user_details['guests'] ) ) {
					$tag->set_attribute( 'class', $class_attr . ' gatherpress--is-not-visible' );
				}
			}
		}

		return $tag->get_updated_html();
	}
}
