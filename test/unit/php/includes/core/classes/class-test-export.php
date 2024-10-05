<?php
/**
 * Class handles unit tests for GatherPress\Core\Export.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Export;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Export.
 *
 * @coversDefaultClass \GatherPress\Core\Export
 * @group migrate
 */
class Test_Export extends Base {
	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Export::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'export_wp',
				'priority' => 10,
				'callback' => array( $instance, 'export' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for validate.
	 *
	 * @covers ::validate
	 *
	 * @return void
	 */
	public function test_validate(): void {
		$instance = Export::get_instance();

		$post = $this->mock->post()->get();
		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'validate', array( $post ) ),
			'Failed to assert that validation fails for non-validating post data.'
		);

		$post = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'validate', array( $post ) ),
			'Failed to assert that validation passes for valid post data.'
		);
	}

	/**
	 * Coverage for extend.
	 *
	 * @covers ::extend
	 *
	 * @return void
	 */
	public function test_extend(): void {
		$instance = Export::get_instance();

		$post_id  = $this->mock->post()->get()->post_id;
		$meta_key = '';
		$meta     = (object) [
			"post_id" => $post_id
		];
		$this->assertTrue(
			$instance->extend( true, $meta_key, $meta ),
			'Failed to assert the method accepts wether to "skip" saving the current post meta, independent from the data to save.'
		);
		$this->assertFalse(
			$instance->extend( false, $meta_key, $meta ),
			'Failed to assert the method accepts wether to "skip" saving the current post meta, independent from the data to save.'
		);

		$skip     = false;
		$meta_key = Export::POST_META;

		$this->assertSame(
			'gatherpress_extend_export',
			$meta_key,
			'Failed to assert the post meta key hasn\'t changed.'
		);

		// Add temporary marker.
		add_post_meta( $post_id, $meta_key, 'temp-unit-test' );

		$this->assertTrue(
			$instance->extend( $skip, $meta_key, $meta ),
			'Failed to assert the method returns true, even with false given, because the "meta_key" matches.'
		);
		$this->assertFalse(
			get_post_meta( $post_id, $meta_key ),
			'Failed to assert the temporary marker was deleted from post meta.'
		);
	}

	/**
	 * Coverage for datetime_callback method.
	 *
	 * @covers ::datetimes_callback
	 *
	 * @return void
	 */
	public function test_datetime_callback(): void {
		$export = Export::get_instance();
		$post   = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event  = new Event( $post->ID );

		$event->save_datetimes(
			array(
				'datetime_start' => '2020-05-11 15:00:00',
				'datetime_end'   => '2020-05-11 17:00:00',
				'timezone'       => 'America/New_York',
			)
		);

		$datetimes_data = 'a:5:{s:14:"datetime_start";s:19:"2020-05-11 15:00:00";s:18:"datetime_start_gmt";s:19:"2020-05-11 19:00:00";s:12:"datetime_end";s:19:"2020-05-11 17:00:00";s:16:"datetime_end_gmt";s:19:"2020-05-11 21:00:00";s:8:"timezone";s:16:"America/New_York";}';

		$this->assertSame(
			$datetimes_data,
			$export->datetimes_callback( $post ),
			'Failed to assert that datetimes data matches'
		);
	}
}
