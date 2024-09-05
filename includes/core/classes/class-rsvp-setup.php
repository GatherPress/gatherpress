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
	
			
		/**
		 * Fires when comment-specific meta boxes are added.
		 *
		 * @param \WP_Comment $comment Comment object.
		 */
		\add_action('add_meta_boxes_comment',function( \WP_Comment $comment ) : void {
			
			// Taken from https://developer.wordpress.org/reference/functions/register_and_do_post_meta_boxes/
			// https://github.com/WordPress/wordpress-develop/blob/8e89a9856e3d8369225df0c63cb6911ec884ab4f/src/wp-admin/includes/meta-boxes.php#L1606-L1630
			$taxonomy = \get_taxonomy( Rsvp::TAXONOMY );
			
			if ( ! $taxonomy->show_ui || false === $taxonomy->meta_box_cb ) {
				return;
			}
			
			$label = $taxonomy->labels->name;
			
			if ( ! \is_taxonomy_hierarchical( Rsvp::TAXONOMY ) ) {
				$tax_meta_box_id = 'tagsdiv-' . Rsvp::TAXONOMY;
			} else {
				$tax_meta_box_id = Rsvp::TAXONOMY . 'div';
			}

			require_once \ABSPATH . 'wp-admin/includes/meta-boxes.php';
	
			$amb = \add_meta_box(
				$tax_meta_box_id,
				// $label,
				'RSVP stati',
				$taxonomy->meta_box_cb,
				'comment',
				'normal', // 'side' doesnt work
				'core',
				array(
					'taxonomy'               => Rsvp::TAXONOMY,
					'__back_compat_meta_box' => true,
				)
			);
			global $wp_meta_boxes;
			\do_action('qm/info', $amb );
			\do_action('qm/info', $wp_meta_boxes );
			\do_action('qm/info', $taxonomy );

		} );
	
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
				'hierarchical'       => true,
				'public'             => true,
				'show_ui'            => true,
				'show_admin_column'  => false,
				'query_var'          => true,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
			)
		);


	}

public function meta_box_cb() {
	return '!!! #####';
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

		return $comment_count['approved'] ?? 0;
	}
}
