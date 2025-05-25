<?php
/**
 * Class handles unit tests for GatherPress\Core\RSVP_List_Table.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\RSVP_List_Table;
use GatherPress\Tests\Base;
use WP_Screen;

/**
 * Class Test_RSVP_List_Table.
 *
 * @coversDefaultClass \GatherPress\Core\RSVP_List_Table
 */
class Test_RSVP_List_Table extends Base {
	/**
	 * The RSVP list table instance.
	 *
	 * @var RSVP_List_Table
	 */
	private $list_table;

	/**
	 * Test event ID.
	 *
	 * @var int
	 */
	private $event_id;

	/**
	 * Test RSVP data.
	 *
	 * @var array
	 */
	private $rsvp;

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->list_table = new RSVP_List_Table();

		// Create a test event.
		$this->event_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Test Event',
				'post_name'   => 'test-event',
				'post_status' => 'publish',
			)
		);

		// Ensure permalinks are set up.
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules();

		// Create a test RSVP.
		$rsvp = $this->factory->comment->create_and_get(
			array(
				'comment_post_ID' => $this->event_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$this->rsvp                = (array) $rsvp;
		$this->rsvp['event_title'] = get_the_title( $this->event_id );
	}

	/**
	 * Test the column_default method.
	 *
	 * This test verifies that the column_default method correctly renders
	 * the event column content by checking if it contains the test event title.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default(): void {
		// Test event column.
		$event_col = $this->list_table->column_default( $this->rsvp, 'event' );
		$this->assertStringContainsString( 'Test Event', $event_col );

		// Test approved column.
	}
}
