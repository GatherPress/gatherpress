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
use GatherPress\Tests\Base;
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
	 * Coverage for export.
	 *
	 * @covers ::export
	 *
	 * @return void
	 */
	public function test_export(): void {
		$instance = Export::get_instance();

		$this->assertFalse(
			has_action( 'the_post', array( $instance, 'prepare' ) ),
			'Failed to assert that the "the_post" action is not already added.'
		);
		$this->assertFalse(
			has_filter( 'wxr_export_skip_postmeta', array( $instance, 'extend' ) ),
			'Failed to assert that the "wxr_export_skip_postmeta" filter is not already added.'
		);

		$instance->export();

		$this->assertSame(
			10,
			has_action( 'the_post', array( $instance, 'prepare' ) ),
			'Failed to assert that the "the_post" action was added.'
		);

		$this->assertSame(
			10,
			has_filter( 'wxr_export_skip_postmeta', array( $instance, 'extend' ) ),
			'Failed to assert that the "wxr_export_skip_postmeta" filter was added.'
		);
	}

	/**
	 * Coverage for prepare.
	 *
	 * @covers ::prepare
	 *
	 * @return void
	 */
	public function test_prepare(): void {
		$instance = Export::get_instance();
		$post     = $this->mock->post()->get();

		$this->assertEmpty(
			get_post_meta( $post->ID, Export::POST_META, true ),
			'Failed to assert the post meta "gatherpress_extend_export" didn\'t exist yet.'
		);

		// Run method under test with a post.
		$instance->prepare( $post );

		$this->assertEmpty(
			get_post_meta( $post->ID, Export::POST_META, true ),
			'Failed to assert the post meta "gatherpress_extend_export" wasn\'t saved for a regular post.'
		);

		$post = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();

		// Run method under test with a gatherpress_event.
		$instance->prepare( $post );

		$this->assertSame(
			'1',
			get_post_meta( $post->ID, Export::POST_META, true ),
			'Failed to assert the post meta "gatherpress_extend_export" was saved.'
		);

		// Clean up for later tests.
		delete_post_meta( $post->ID, Export::POST_META );
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
		$meta     = (object) array(
			'post_id' => $post_id,
		);
		$this->assertTrue(
			$instance->extend( true, $meta_key, $meta ),
			'Failed to assert the method accepts whether to "skip" saving the current post meta, '
			. 'independent from the data to save.'
		);
		$this->assertFalse(
			$instance->extend( false, $meta_key, $meta ),
			'Failed to assert the method accepts whether to "skip" saving the current post meta, '
			. 'independent from the data to save.'
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
			get_post_meta( $post_id, $meta_key, true ),
			'Failed to assert the temporary marker was deleted from post meta.'
		);
	}

	/**
	 * Coverage for run & render.
	 *
	 * @covers ::run
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_run_and_render(): void {
		$export = Export::get_instance();
		$post   = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();
		$event  = new Event( $post->ID );
		$params = array(
			'datetime_start' => '2020-05-11 15:00:00',
			'datetime_end'   => '2020-05-12 17:00:00',
			'timezone'       => 'America/New_York',
		);
		$event->save_datetimes( $params );

		if ( ! function_exists( 'export_wp' ) ) {
			require_once ABSPATH . 'wp-admin/includes/export.php';
		}

		$output = Utility::buffer_and_return( 'export_wp', array( array( 'content' => 'gatherpress_event' ) ) );

		$this->assertStringContainsString( '<wp:post_name><![CDATA[unit-test-event]]></wp:post_name>', $output );
		$this->assertStringContainsString( '<wp:post_type><![CDATA[gatherpress_event]]></wp:post_type>', $output );

		$this->assertStringContainsString(
			'<wp:meta_key><![CDATA[gatherpress_datetime_start]]></wp:meta_key>',
			$output
		);
		$this->assertStringContainsString( '<wp:meta_value><![CDATA[2020-05-11 15:00:00]]></wp:meta_value>', $output );

		$this->assertStringContainsString( '<wp:meta_key><![CDATA[gatherpress_datetime_end]]></wp:meta_key>', $output );
		$this->assertStringContainsString( '<wp:meta_value><![CDATA[2020-05-12 17:00:00]]></wp:meta_value>', $output );

		$this->assertStringContainsString( '<wp:meta_key><![CDATA[gatherpress_datetimes]]></wp:meta_key>', $output );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertStringContainsString( '<wp:meta_value><![CDATA[a:5:{s:14:"datetime_start";s:19:"2020-05-11 15:00:00";s:18:"datetime_start_gmt";s:19:"2020-05-11 19:00:00";s:12:"datetime_end";s:19:"2020-05-12 17:00:00";s:16:"datetime_end_gmt";s:19:"2020-05-12 21:00:00";s:8:"timezone";s:16:"America/New_York";}]]></wp:meta_value>', $output );
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

		// phpcs:ignore Generic.Files.LineLength.TooLong
		$datetimes_data = 'a:5:{s:14:"datetime_start";s:19:"2020-05-11 15:00:00";s:18:"datetime_start_gmt";s:19:"2020-05-11 19:00:00";s:12:"datetime_end";s:19:"2020-05-11 17:00:00";s:16:"datetime_end_gmt";s:19:"2020-05-11 21:00:00";s:8:"timezone";s:16:"America/New_York";}';

		$this->assertSame(
			$datetimes_data,
			$export->datetimes_callback( $post ),
			'Failed to assert that datetimes data matches'
		);
	}

	/**
	 * Coverage for render method early return conditions.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_early_return_conditions(): void {
		$export = Export::get_instance();
		$post   = $this->mock->post(
			array(
				'post_title'   => 'Unit Test Event',
				'post_type'    => 'gatherpress_event',
				'post_content' => 'Unit Test description.',
			)
		)->get();

		// Test when export_callback is not set.
		ob_start();
		$export->render( array(), 'test_key', $post );
		$output = ob_get_clean();

		$this->assertEmpty(
			$output,
			'Failed to assert that render produces no output when export_callback is not set.'
		);

		// Test when export_callback is not callable.
		ob_start();
		$export->render(
			array(
				'export_callback' => 'nonexistent_function_name',
			),
			'test_key',
			$post
		);
		$output = ob_get_clean();

		$this->assertEmpty(
			$output,
			'Failed to assert that render produces no output when export_callback is not callable.'
		);
	}
}
