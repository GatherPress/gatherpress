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

use GatherPress\Core\Block;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_HTML_Tag_Processor;
use WP_User;

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
	const BLOCK_NAME = 'gatherpress/rsvp-response';

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
		// Priority 11 ensures this runs after transform_block_content which modifies the block structure.
		add_filter( $render_block_hook, array( $this, 'attach_dropdown_interactivity' ), 11 );
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
		$block_instance = Block::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Validate that the post ID is an actual published event post type.
		if (
			Event::POST_TYPE !== get_post_type( $post_id ) ||
			'publish' !== get_post_status( $post_id )

		) {
			return '';
		}

		$rsvp               = new Rsvp( $post_id );
		$tag                = new WP_HTML_Tag_Processor( $block_content );
		$rsvp_limit_enabled = isset( $block['attrs']['rsvpLimitEnabled'] ) ? (string) $block['attrs']['rsvpLimitEnabled'] : '0';
		$rsvp_limit         = isset( $block['attrs']['rsvpLimit'] ) ? (string) $block['attrs']['rsvpLimit'] : '8';

		if ( $tag->next_tag() ) {
			$tag->set_attribute( 'data-limit-enabled', $rsvp_limit_enabled );
			$tag->set_attribute( 'data-limit', $rsvp_limit );

			$responses = $rsvp->responses();
			$counts    = array_reduce(
				array_filter(
					array_keys( $responses ),
					function ( $key ) {
						return 'all' !== $key;
					}
				),
				static function ( $collected_counts, $key ) use ( $responses ): array {
					return array_merge( $collected_counts, array( $key => $responses[ $key ]['count'] ) );
				},
				array()
			);

			$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
			$tag->set_attribute( 'data-wp-context', wp_json_encode( array( 'postId' => $post_id ) ) );
			$tag->set_attribute( 'data-counts', wp_json_encode( $counts ) );

			do {
				$class_attr = $tag->get_attribute( 'class' );
				if ( Utility::has_css_class( $class_attr, 'gatherpress-rsvp-response--no-responses' ) ) {
					if ( ! empty( $counts['attending'] ) ) {
						$updated_class  = str_replace(
							'gatherpress--is-visible',
							'',
							$class_attr
						);
						$updated_class .= ' gatherpress--is-hidden';
					} else {
						$updated_class  = str_replace(
							'gatherpress--is-hidden',
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
	 * Attaches interactivity to the dropdown block.
	 *
	 * Adds interactivity attributes to dropdown menu items with specific RSVP-related classes
	 * for use with the WordPress Interactivity API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The block content to modify.
	 *
	 * @return string Modified block content with interactivity attributes.
	 */
	public function attach_dropdown_interactivity( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );
		$tag->next_tag();
		$counts = ! empty( $tag->get_attribute( 'data-counts' ) ) ?
			json_decode( $tag->get_attribute( 'data-counts' ), true ) :
			array();

		if ( $tag->next_tag(
			array(
				'tag_name'   => 'a',
				'attributes' => array( 'class' => 'wp-block-gatherpress-dropdown__trigger' ),
			)
		) ) {
			$class_attr = $tag->get_attribute( 'class' );
			$tag->set_attribute( 'class', $class_attr . ' gatherpress--is-disabled' );

			$tag->next_token();
			$trigger_text = sprintf( $tag->get_modifiable_text(), intval( $counts['attending'] ?? 0 ) );

			$tag->set_modifiable_text( $trigger_text );
		}

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
					preg_match( '/gatherpress--is-(attending|waiting-list|not-attending)/', $current_class, $matches ) &&
					$tag->next_tag( array( 'tag_name' => 'a' ) )
				) {
					// Change for needed format.
					$status = str_replace( '-', '_', sanitize_key( $matches[1] ) );

					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-watch', 'callbacks.processRsvpDropdown' );
					$tag->set_attribute( 'data-wp-on--click', 'actions.processRsvpSelection' );
					$tag->set_attribute( 'data-status', $status );
				}
			}

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
			Rsvp::COMMENT_TYPE === $comment->comment_type
		) {
			$email = $comment->comment_author_email;

			if ( intval( $comment->user_id ) && empty( $email ) ) {
				$user = new WP_User( $comment->user_id );

				if ( $user->exists() ) {
					$email = $user->user_email;
				}
			}

			if (
				intval( get_comment_meta( intval( $comment->comment_ID ), 'gatherpress_rsvp_anonymous', true ) ) &&
				! current_user_can( Rsvp::CAPABILITY )
			) {
				// Set the email to empty if the RSVP is marked as anonymous and the current user
				// does not have permission to edit posts. This ensures the avatar defaults
				// to a generic or placeholder image for anonymous responses.
				$email = '';
			}

			$args['url'] = get_avatar_url( $email, array( 'default' => 'mystery' ) );

			// Clear extra attributes to prevent JavaScript framework conflicts.
			$args['extra_attr'] = '';
		}

		return $args;
	}

	/**
	 * Adds the RSVP response block to the list of allowed ancestors for the comment author name block.
	 *
	 * This method modifies the `ancestor` property of the `core/comment-author-name` block's metadata
	 * to include the `gatherpress/rsvp-response` block. This allows the comment author name block
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
}
