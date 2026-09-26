<?php
/**
 * Unit tests for the shared post type uninstall behavior.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Uninstall\Post_Type;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Post_Type.
 *
 * The helpers are exercised here directly as well as through the tasks that
 * use them, because xdebug does not reliably trace a private helper called
 * from a short delegation in the same class.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Post_Type
 */
class Test_Post_Type extends Base {

	/**
	 * A task that removes events, with no opt-in of its own.
	 *
	 * @since 0.36.0
	 *
	 * @return Post_Type The task double.
	 */
	protected function make_task(): Post_Type {
		return new class() extends Post_Type {

			/**
			 * Always runs, so a test does not have to arm a preference.
			 *
			 * @return bool Always true.
			 */
			public function applies(): bool {
				return true;
			}

			/**
			 * The post type this double removes.
			 *
			 * @return string The event post type.
			 */
			protected function post_type(): string {
				return Event::POST_TYPE;
			}

			/**
			 * Removes the posts and nothing else.
			 *
			 * @return void
			 */
			protected function uninstall_site(): void {
				$this->remove_posts();
			}
		};
	}

	/**
	 * Published posts are counted per term, and drafts are not.
	 *
	 * @covers ::count_published_relationships
	 *
	 * @return void
	 */
	public function test_counts_published_relationships_only(): void {
		register_taxonomy_for_object_type( 'category', Event::POST_TYPE );

		$term_id     = (int) self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		$published   = (int) self::factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$draft       = (int) self::factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		$term        = get_term( $term_id, 'category' );
		$taxonomy_id = (int) $term->term_taxonomy_id;

		wp_set_object_terms( $published, array( $term_id ), 'category' );
		wp_set_object_terms( $draft, array( $term_id ), 'category' );

		$counts = Utility::invoke_hidden_method(
			$this->make_task(),
			'count_published_relationships',
			array( Event::POST_TYPE )
		);

		$this->assertSame(
			1,
			$counts[ $taxonomy_id ] ?? 0,
			'Core only counts published posts, so only those may be subtracted later.'
		);
	}

	/**
	 * An RSVP's terms are not counted, because a comment ID can equal a post ID.
	 *
	 * @covers ::count_published_relationships
	 *
	 * @return void
	 */
	public function test_ignores_the_rsvp_taxonomies(): void {
		$comment_id = (int) self::factory()->comment->create(
			array( 'comment_type' => Rsvp::COMMENT_TYPE )
		);

		wp_set_object_terms( $comment_id, Status::ATTENDING->value, Status::TAXONOMY );

		// Make a published event whose ID matches the comment ID, so a
		// post-only join would pick the comment's relationship up.
		$event = get_post( $comment_id );

		if ( ! $event instanceof \WP_Post ) {
			self::factory()->post->create(
				array(
					'import_id'   => $comment_id,
					'post_type'   => Event::POST_TYPE,
					'post_status' => 'publish',
				)
			);
		}

		$counts = Utility::invoke_hidden_method(
			$this->make_task(),
			'count_published_relationships',
			array( Event::POST_TYPE )
		);

		$status_id = (int) get_term_by( 'slug', Status::ATTENDING->value, Status::TAXONOMY )->term_taxonomy_id;

		$this->assertArrayNotHasKey(
			$status_id,
			$counts,
			'Nothing in the schema separates comment IDs from post IDs, so the RSVP taxonomies are excluded by name.'
		);
	}

	/**
	 * The relationship delete leaves an RSVP's own terms in place.
	 *
	 * @covers ::delete_term_relationships
	 *
	 * @return void
	 */
	public function test_delete_term_relationships_spares_rsvp_terms(): void {
		$comment_id = (int) self::factory()->comment->create(
			array( 'comment_type' => Rsvp::COMMENT_TYPE )
		);

		wp_set_object_terms( $comment_id, Status::ATTENDING->value, Status::TAXONOMY );

		if ( ! get_post( $comment_id ) instanceof \WP_Post ) {
			self::factory()->post->create(
				array(
					'import_id'   => $comment_id,
					'post_type'   => Event::POST_TYPE,
					'post_status' => 'publish',
				)
			);
		}

		Utility::invoke_hidden_method(
			$this->make_task(),
			'delete_term_relationships',
			array( Event::POST_TYPE )
		);

		$this->assertNotEmpty(
			wp_get_object_terms( $comment_id, Status::TAXONOMY ),
			'An RSVP keeps its status when the post that shares its ID is removed.'
		);
	}

	/**
	 * Counts are decremented and clamped at zero.
	 *
	 * @covers ::decrement_term_counts
	 *
	 * @return void
	 */
	public function test_decrement_term_counts_clamps_at_zero(): void {
		$term_id     = (int) self::factory()->term->create( array( 'taxonomy' => 'category' ) );
		$taxonomy_id = (int) get_term( $term_id, 'category' )->term_taxonomy_id;

		Utility::invoke_hidden_method(
			$this->make_task(),
			'decrement_term_counts',
			array( array( $taxonomy_id => 5 ) )
		);

		clean_term_cache( array( $term_id ), 'category' );

		$this->assertSame(
			0,
			(int) get_term( $term_id, 'category' )->count,
			'A stored count that was already low must not go negative.'
		);
	}
}
