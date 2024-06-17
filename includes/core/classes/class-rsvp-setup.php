<?php

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

class Rsvp_Setup{
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
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_filter( 'get_comments_number', array( $this, 'adjust_comments_number' ), 10, 2 );
//		add_action( 'wp', function() {
//			remove_filter( 'pre_get_comments', array( Rsvp_Query::get_instance(), 'exclude_rsvp_from_query') );
//			$foo = get_comments(
//				array(
//					'post_id' => 829,
//					'comment_type' => RSVP::COMMENT_TYPE,
//					'tax_query' => array(
//						array(
//							'taxonomy' => RSVP::TAXONOMY,
//							'terms' => 'attending',
//							'field' => 'slug',
//						)
//					),
//				)
//			);
//
//			echo '<pre>';
//			print_r($foo); die;
//			add_filter( 'pre_get_comments', array( Rsvp_Query::get_instance(), 'exclude_rsvp_from_query') );
//		});
	}

	public function register_taxonomy(): void {
		register_taxonomy(
			RSVP::TAXONOMY,
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

	public function adjust_comments_number( int $comments_number, int $post_id ): int {
		if ( Event::POST_TYPE !== get_post_type( $post_id ) ) {
			return $comments_number;
		}

		$comment_count = get_comment_count( $post_id );

		return $comment_count['approved'] ?? 0;
	}
}
