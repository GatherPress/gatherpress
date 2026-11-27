<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\External\Post_Type_Single.
 *
 * @package GatherPress\Core\Calendar\External
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Calendar\External;

use GatherPress\Core\Calendar\External\Post_Type_Single;
use GatherPress\Core\Calendar\External\Template;
use GatherPress\Tests\Base;

/**
 * Class Test_Post_Type_Single.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\External\Post_Type_Single
 * @group              endpoints
 */
class Test_Post_Type_Single extends Base {

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
		$instance  = new Post_Type_Single( $types, $query_var, $post_type );

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
			'%s/([^/]+)/(%s)/?$',
			$instance->reg_ex,
			'Failed to assert that reg_ex matches the singular post-type pattern.'
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
		$instance  = new Post_Type_Single( $types, $query_var, $post_type );

		// Positive branch: navigate to a singular event permalink.
		$event_id = $this->mock->post(
			array( 'post_type' => $post_type )
		)->get()->ID;
		$this->go_to( get_permalink( $event_id ) );

		$this->assertTrue(
			$instance->is_valid(),
			'Failed to assert that is_valid returns true for a singular event request.'
		);

		// Negative branch: navigate to the home page (not a singular event).
		$this->go_to( home_url( '/' ) );

		$this->assertFalse(
			$instance->is_valid(),
			'Failed to assert that is_valid returns false outside a singular post-type request.'
		);
	}

	/**
	 * A password-protected event's calendar links only work once the viewer
	 * has entered its password.
	 *
	 * @since 0.36.1
	 *
	 * @covers ::is_valid
	 *
	 * @return void
	 */
	public function test_is_valid_for_a_password_protected_post(): void {
		$instance = new Post_Type_Single(
			array(
				new Template(
					'ical',
					static function () {
						return array( 'file_name' => 'foo.php' );
					}
				),
			),
			'gatherpress_ext_calendar',
			'gatherpress_event'
		);
		$event_id = $this->mock->post(
			array(
				'post_type'     => 'gatherpress_event',
				'post_password' => 'secret',
			)
		)->get()->ID;

		$this->go_to( get_permalink( $event_id ) );

		$this->assertFalse(
			$instance->is_valid(),
			'A viewer without the password should not get the calendar links.'
		);

		add_filter( 'post_password_required', '__return_false' );

		$entered = $instance->is_valid();

		remove_filter( 'post_password_required', '__return_false' );

		$this->assertTrue( $entered, 'A viewer who entered the password should get the calendar links.' );
	}
}
