<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Post_Type_Feed.
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Post_Type_Feed;
use GatherPress\Core\Calendar\Template;
use GatherPress\Tests\Base;

/**
 * Class Test_Post_Type_Feed.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Post_Type_Feed
 * @group              endpoints
 */
class Test_Post_Type_Feed extends Base {

	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$query_var = 'gatherpress_ext_calendar';
		$post_type = 'gatherpress_event';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Post_Type_Feed( $types, $query_var, $post_type );

		$this->assertSame(
			$query_var,
			$instance->query_var,
			'Failed to assert that query_var is persisted.'
		);
		$this->assertSame(
			get_post_type_object( $post_type ),
			$instance->type_object,
			'Failed to assert that type_object is persisted.'
		);
		$this->assertSame(
			array( $instance, 'is_valid' ),
			$instance->validation_callback,
			'Failed to assert that validation_callback points to is_valid.'
		);
		$this->assertSame(
			$types,
			$instance->types,
			'Failed to assert that endpoint types are persisted.'
		);
		$this->assertSame(
			'%s/feed/(%s)/?$',
			$instance->reg_ex,
			'Failed to assert that reg_ex matches the post-type feed pattern.'
		);
		$this->assertSame(
			'post_type',
			$instance->object_type,
			'Failed to assert that object_type defaults to post_type.'
		);
	}

	/**
	 * Coverage for is_valid method.
	 *
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_is_valid(): void {
		$query_var = 'gatherpress_ext_calendar';
		$post_type = 'gatherpress_event';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Post_Type_Feed( $types, $query_var, $post_type );

		// Positive branch: post type archive in feed mode.
		$this->go_to( get_post_type_archive_link( $post_type ) );
		$GLOBALS['wp_query']->is_feed = true;

		$this->assertTrue(
			$instance->is_valid(),
			'Failed to assert that is_valid returns true for a post-type archive feed.'
		);

		// Negative branch: home page (neither archive nor feed).
		$this->go_to( home_url( '/' ) );

		$this->assertFalse(
			$instance->is_valid(),
			'Failed to assert that is_valid returns false outside an archive feed.'
		);
	}

	/**
	 * Coverage for get_rewrite_atts method.
	 *
	 * @covers ::get_rewrite_atts
	 *
	 * @return void
	 */
	public function test_get_rewrite_atts(): void {
		$query_var = 'gatherpress_ext_calendar';
		$post_type = 'gatherpress_event';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Post_Type_Feed( $types, $query_var, $post_type );

		$this->assertSame(
			array(
				'post_type' => 'gatherpress_event',
				'feed'      => '$matches[1]',
				$query_var  => '$matches[1]',
			),
			$instance->get_rewrite_atts(),
			'Failed to assert that rewrite attributes match the post-type feed shape.'
		);
	}
}
