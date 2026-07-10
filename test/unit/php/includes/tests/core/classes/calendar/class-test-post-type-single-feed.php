<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Post_Type_Single_Feed.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Post_Type_Single_Feed;
use GatherPress\Core\Calendar\Template;
use GatherPress\Tests\Base;

/**
 * Class Test_Post_Type_Single_Feed.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Post_Type_Single_Feed
 * @group              endpoints
 */
class Test_Post_Type_Single_Feed extends Base {

	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$query_var = 'gatherpress_ext_calendar';
		$post_type = 'gatherpress_venue';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		// Default post type defaults to gatherpress_venue when not supplied.
		$instance = new Post_Type_Single_Feed( $types, $query_var );

		$this->assertSame(
			$query_var,
			$instance->query_var,
			'Failed to assert that query_var is persisted.'
		);
		$this->assertSame(
			get_post_type_object( $post_type ),
			$instance->type_object,
			'Failed to assert that type_object defaults to gatherpress_venue.'
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
			'%s/([^/]+)/feed/(%s)/?$',
			$instance->reg_ex,
			'Failed to assert that reg_ex matches the singular post-type feed pattern.'
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
		$post_type = 'gatherpress_venue';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Post_Type_Single_Feed( $types, $query_var, $post_type );

		// Positive branch: singular venue in feed mode. Set is_feed directly
		// since the test env doesn't register the custom `ical` feed.
		$venue_id = $this->mock->post(
			array( 'post_type' => $post_type )
		)->get()->ID;
		$this->go_to( get_permalink( $venue_id ) );
		$GLOBALS['wp_query']->is_feed = true;

		$this->assertTrue(
			$instance->is_valid(),
			'Failed to assert that is_valid returns true for a singular venue feed request.'
		);

		// Negative branch: home page.
		$this->go_to( home_url( '/' ) );

		$this->assertFalse(
			$instance->is_valid(),
			'Failed to assert that is_valid returns false outside a singular venue feed.'
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
		$post_type = 'gatherpress_venue';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Post_Type_Single_Feed( $types, $query_var, $post_type );

		$this->assertSame(
			array(
				'post_type'         => 'gatherpress_venue',
				'gatherpress_venue' => '$matches[1]',
				'feed'              => '$matches[2]',
				$query_var          => '$matches[2]',
			),
			$instance->get_rewrite_atts(),
			'Failed to assert that rewrite attributes match the singular post-type feed shape.'
		);
	}
}
