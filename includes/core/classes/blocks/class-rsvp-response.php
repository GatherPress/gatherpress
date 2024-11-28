<?php
/**
 * The "Add to calendar" class manages the core-block-variation,
 * it mainly prepares the output of the block.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "Add to calendar" block,
 * which is a block-variation of 'core/buttons'.
 *
 * @since 1.0.0
 */
class Rsvp_Response {
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
		add_filter( 'get_avatar_data', array( $this, 'modify_avatar_for_gatherpress_rsvp' ), 10, 2 );
		add_filter( 'block_type_metadata', array( $this, 'add_rsvp_to_comment_ancestor' ) );
	}

	/**
	 * Fixes an issue where the REST API does not return avatar URLs for custom comment types.
	 *
	 * This method addresses a limitation in the REST API by ensuring that the `gatherpress_rsvp`
	 * custom comment type includes a valid avatar URL. It checks if the current request is a
	 * REST API request (`REST_REQUEST`) and if the provided comment is of type `gatherpress_rsvp`.
	 * If these conditions are met, it modifies the avatar data `$args` to include the user's
	 * avatar URL based on their user ID.
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
			defined( 'REST_REQUEST' ) &&
			REST_REQUEST &&
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
				$metadata['ancestor'] = [ 'gatherpress/rsvp-template' ];
			}
		}

		return $metadata;
	}
}
