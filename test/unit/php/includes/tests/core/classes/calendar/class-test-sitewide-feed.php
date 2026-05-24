<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Sitewide_Feed.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Sitewide_Feed;
use GatherPress\Core\Calendar\Template;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Sitewide_Feed.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Sitewide_Feed
 * @group              endpoints
 */
class Test_Sitewide_Feed extends Base {

	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$query_var = 'gatherpress_ext_calendar';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Sitewide_Feed( $types, $query_var );

		$this->assertSame(
			$query_var,
			$instance->query_var,
			'Failed to assert that query_var is persisted.'
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
			'feed/(%s)/?$',
			$instance->reg_ex,
			'Failed to assert that reg_ex matches the sitewide feed pattern.'
		);
		$this->assertSame(
			'sitewide',
			$instance->object_type,
			'Failed to assert that object_type is set to sitewide.'
		);
	}

	/**
	 * Coverage for get_regex_pattern method.
	 *
	 * @covers ::get_regex_pattern
	 *
	 * @return void
	 */
	public function test_get_regex_pattern(): void {
		$query_var = 'gatherpress_ext_calendar';
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Sitewide_Feed( $types, $query_var );

		$this->assertSame(
			'feed/(ical)/?$',
			Utility::invoke_hidden_method( $instance, 'get_regex_pattern' ),
			'Failed to assert that the generated sitewide regex pattern matches.'
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
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Sitewide_Feed( $types, $query_var );

		// Positive branch: bare sitewide feed (no singular/tax/archive context).
		$this->go_to( home_url( '/' ) );
		$wp_query                       = $GLOBALS['wp_query'];
		$wp_query->is_feed              = true;
		$wp_query->is_singular          = false;
		$wp_query->is_tax               = false;
		$wp_query->is_post_type_archive = false;

		$this->assertTrue(
			$instance->is_valid(),
			'Failed to assert that is_valid returns true for a bare sitewide feed request.'
		);

		// Negative branch: singular event short-circuits sitewide validity.
		$event_id = $this->mock->post(
			array( 'post_type' => 'gatherpress_event' )
		)->get()->ID;
		$this->go_to( get_permalink( $event_id ) );

		$this->assertFalse(
			$instance->is_valid(),
			'Failed to assert that is_valid returns false when the request is singular.'
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
		$types     = array(
			new Template(
				'ical',
				static function () {
					return array( 'file_name' => 'foo.php' );
				}
			),
		);
		$instance  = new Sitewide_Feed( $types, $query_var );

		$this->assertSame(
			array(
				'feed'     => '$matches[1]',
				$query_var => '$matches[1]',
			),
			$instance->get_rewrite_atts(),
			'Failed to assert that rewrite attributes match the sitewide feed shape.'
		);
	}
}
