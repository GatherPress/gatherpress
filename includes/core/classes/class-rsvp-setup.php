<?php
/**
 * File comment block for Rsvp_Setup class.
 *
 * This file contains the definition of the Rsvp_Setup class, which handles
 * setup tasks related to RSVP functionality within the GatherPress plugin.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Handles setup tasks related to RSVP functionality.
 *
 * The Rsvp_Setup class initializes necessary hooks and configurations for managing RSVPs.
 * It registers a custom taxonomy for RSVPs and adjusts comment counts specifically for events.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */
class Rsvp_Setup {
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
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_filter( 'get_comments_number', array( $this, 'adjust_comments_number' ), 10, 2 );
		add_filter( 'get_avatar_data', array( $this, 'modify_avatar_for_gatherpress_rsvp' ), 10, 2 );
	}

	/**
	 * Register custom comment taxonomy for RSVPs.
	 *
	 * Registers a custom taxonomy 'gatherpress_rsvp' for managing RSVP related functionalities specifically for comments.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_taxonomy(): void {
		register_taxonomy(
			Rsvp::TAXONOMY,
			'comment',
			array(
				'labels'             => array(),
				'hierarchical'       => false,
				'public'             => true,
				'show_ui'            => false,
				'show_admin_column'  => false,
				'query_var'          => true,
				'publicly_queryable' => false,
				'show_in_rest'       => true,
			)
		);
	}

	/**
	 * Adjusts the number of comments displayed for event posts.
	 *
	 * Retrieves and returns the count of approved RSVP comments for event posts.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comments_number The original number of comments.
	 * @param int $post_id         The ID of the post.
	 * @return int Adjusted number of comments.
	 */
	public function adjust_comments_number( int $comments_number, int $post_id ): int {
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return $comments_number;
		}

		$comment_count = get_comment_count( $post_id );

		return $comment_count['approved'];
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
}
