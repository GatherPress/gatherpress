<?php
/**
 * Class manages the RSVP Response block for GatherPress, preparing its output and
 * handling associated hooks for customizing functionality.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Event;
use WP_HTML_Tag_Processor;

/**
 * Class Rsvp_Response.
 *
 * This class manages the RSVP Response block for GatherPress, handling the
 * preparation of block output and adding hooks for customizations.
 *
 * It ensures smooth integration with WordPress's block editor and REST API.
 *
 * @since 1.0.0
 */
class Rsvp_Response {
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
	const BLOCK_NAME = 'gatherpress/rsvp-response-v2';

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
		add_filter( 'render_block', array( $this, 'attach_dropdown_interactivity' ), 10, 2 );
		add_filter( 'get_avatar_data', array( $this, 'modify_avatar_for_gatherpress_rsvp' ), 10, 2 );
		add_filter( 'block_type_metadata', array( $this, 'add_rsvp_to_comment_ancestor' ) );
	}

	/**
	 * Transforms the content of a block before rendering.
	 *
	 * This method modifies the HTML content of the specified block by adding
	 * interactivity attributes if the block matches certain conditions.
	 * It uses the `WP_HTML_Tag_Processor` to locate and update the block's attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original HTML content of the block.
	 * @param array  $block         An associative array containing block data, including `blockName` and attributes.
	 *
	 * @return string The modified block content with updated attributes.
	 */
	public function transform_block_content( string $block_content, array $block ): string {
		if ( self::BLOCK_NAME !== $block['blockName'] ) {
			return $block_content;
		}

		$event = new Event( get_the_ID() );
		$tag   = new WP_HTML_Tag_Processor( $block_content );

		if ( ! $event->rsvp ) {
			return $block_content;
		}

		if ( $tag->next_tag() ) {
			$tag->set_attribute( 'data-wp-interactive', 'gatherpress/rsvp' );

			$responses = (int) $event->rsvp->responses()['attending']['count'];

			do {
				$class_attr = $tag->get_attribute( 'class' );

				if ( $class_attr && false !== strpos( $class_attr, 'gatherpress--empty-rsvp' ) ) {
					if ( ! empty( $responses ) ) {
						$updated_class  = str_replace(
							'gatherpress--is-visible',
							'',
							$class_attr
						);
						$updated_class .= ' gatherpress--is-not-visible';
					} else {
						$updated_class  = str_replace(
							'gatherpress--is-not-visible',
							'',
							$class_attr
						);
						$updated_class .= ' gatherpress--is-visible';
					}

					$tag->set_attribute( 'class', trim( $updated_class ) );
				}
				// @phpstan-ignore-next-line
			} while ( $tag->next_tag() );

			// @phpstan-ignore-next-line
			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}

	/**
	 * Modifies avatar URLs for the `gatherpress_rsvp` custom comment type.
	 *
	 * This method ensures that the `gatherpress_rsvp` custom comment type includes a valid avatar URL.
	 * It checks if the provided comment is of type `gatherpress_rsvp` and modifies the avatar data `$args`
	 * to include the user's avatar URL based on their user ID.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args    Array of arguments for the avatar data.
	 * @param mixed $comment The comment object or other data passed to the filter.
	 *
	 * @return array Modified array of avatar arguments, including the correct URL for the avatar.
	 */
	public function modify_avatar_for_gatherpress_rsvp( array $args, $comment ): array {
		if (
			$comment &&
			is_a( $comment, 'WP_Comment' ) &&
			'gatherpress_rsvp' === $comment->comment_type
		) {
			// Currently, the avatar URL is retrieved based on the user ID.
			// In the future, when non-user RSVPs are supported, the email address can be used as well.
			$args['url'] = get_avatar_url( $comment->user_id );
		}

		return $args;
	}

	/**
	 * Adds the RSVP response block to the list of allowed ancestors for the comment author name block.
	 *
	 * This method modifies the `ancestor` property of the `core/comment-author-name` block's metadata
	 * to include the `gatherpress/rsvp-response-v2` block. This allows the comment author name block
	 * to be used as a child of the RSVP response block.
	 *
	 * @since 1.0.0
	 *
	 * @param array $metadata The block metadata for `core/comment-author-name`.
	 *
	 * @return array The modified block metadata with the updated ancestor property.
	 */
	public function add_rsvp_to_comment_ancestor( array $metadata ): array {
		if ( isset( $metadata['name'] ) && 'core/comment-author-name' === $metadata['name'] ) {
			if ( isset( $metadata['ancestor'] ) && is_array( $metadata['ancestor'] ) ) {
				$metadata['ancestor'][] = 'gatherpress/rsvp-template';
			} else {
				$metadata['ancestor'] = array( 'gatherpress/rsvp-template' );
			}
		}

		return $metadata;
	}

	/**
	 * Attaches interactivity to the dropdown block.
	 *
	 * Adds interactivity attributes to dropdown menu items with specific RSVP-related classes
	 * for use with the WordPress Interactivity API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The block content to modify.
	 * @param array  $block         The parsed block data.
	 * @return string Modified block content with interactivity attributes.
	 */
	public function attach_dropdown_interactivity( string $block_content, array $block ): string {
		if ( self::BLOCK_NAME !== $block['blockName'] ) {
			return $block_content;
		}

		$tag = new WP_HTML_Tag_Processor( $block_content );

		if ( $tag->next_tag(
			array(
				'tag_name'   => 'div',
				'attributes' => array( 'class' => 'wp-block-gatherpress-dropdown__menu' ),
			)
		) ) {
			while ( $tag->next_tag(
				array(
					'tag_name'   => 'div',
					'attributes' => array( 'class' => 'wp-block-gatherpress-dropdown-item' ),
				)
			) ) {
				// Check if the current tag has any of the specified classes.
				$current_class = $tag->get_attribute( 'class' );

				if (
					$current_class &&
					preg_match( '/gatherpress--rsvp-(attending|waiting-list|not-attending)/', $current_class, $matches ) &&
					$tag->next_tag( array( 'tag_name' => 'a' ) )
				) {
					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-watch', 'callbacks.processRsvpDropdown' );
				}
			}

			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}
}
