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
	 * Tests the constructor.
	 *
	 * @covers ::__construct
	 * @return void
	 */
	public function test_construct(): void {
		$list_table = new RSVP_List_Table();

		$this->assertInstanceOf(
			RSVP_List_Table::class,
			$list_table,
			'Failed to assert instance is RSVP_List_Table.'
		);
	}

	/**
	 * Tests get_columns method.
	 *
	 * @covers ::get_columns
	 * @return void
	 */
	public function test_get_columns(): void {
		$columns = $this->list_table->get_columns();

		$this->assertIsArray(
			$columns,
			'Failed to assert get_columns returns an array.'
		);
		$this->assertArrayHasKey(
			'attendee',
			$columns,
			'Failed to assert columns contain attendee.'
		);
		$this->assertArrayHasKey(
			'response',
			$columns,
			'Failed to assert columns contain response.'
		);
		$this->assertArrayHasKey(
			'event',
			$columns,
			'Failed to assert columns contain event.'
		);
		$this->assertArrayHasKey(
			'approved',
			$columns,
			'Failed to assert columns contain approved.'
		);
		$this->assertArrayHasKey(
			'date',
			$columns,
			'Failed to assert columns contain date.'
		);
	}

	/**
	 * Tests get_hideable_columns method.
	 *
	 * @covers ::get_hideable_columns
	 * @return void
	 */
	public function test_get_hideable_columns(): void {
		$hideable = $this->list_table->get_hideable_columns();

		$this->assertIsArray(
			$hideable,
			'Failed to assert get_hideable_columns returns an array.'
		);
		$this->assertArrayNotHasKey(
			'attendee',
			$hideable,
			'Failed to assert attendee column is not hideable.'
		);
	}

	/**
	 * Tests get_hidden_columns method without screen.
	 *
	 * @covers ::get_hidden_columns
	 * @return void
	 */
	public function test_get_hidden_columns_no_screen(): void {
		$hidden = $this->list_table->get_hidden_columns();

		$this->assertIsArray(
			$hidden,
			'Failed to assert get_hidden_columns returns an array.'
		);
	}

	/**
	 * Tests get_hidden_columns method with screen.
	 *
	 * @covers ::get_hidden_columns
	 * @return void
	 */
	public function test_get_hidden_columns_with_screen(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );

		$hidden = $this->list_table->get_hidden_columns();

		$this->assertIsArray(
			$hidden,
			'Failed to assert get_hidden_columns returns an array with screen.'
		);
	}

	/**
	 * Tests column_default method for event column.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default_event(): void {
		$event_col = $this->list_table->column_default( $this->rsvp, 'event' );

		$this->assertStringContainsString(
			'Test Event',
			$event_col,
			'Failed to assert event column contains event title.'
		);
	}

	/**
	 * Tests column_default method for approved column.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default_approved(): void {
		$this->rsvp['comment_approved'] = '1';
		$approved_col                   = $this->list_table->column_default( $this->rsvp, 'approved' );

		$this->assertStringContainsString(
			'Approved',
			$approved_col,
			'Failed to assert approved column shows Approved status.'
		);

		$this->rsvp['comment_approved'] = '0';
		$approved_col                   = $this->list_table->column_default( $this->rsvp, 'approved' );

		$this->assertStringContainsString(
			'Pending',
			$approved_col,
			'Failed to assert approved column shows Pending status.'
		);

		$this->rsvp['comment_approved'] = 'spam';
		$approved_col                   = $this->list_table->column_default( $this->rsvp, 'approved' );

		$this->assertStringContainsString(
			'Spam',
			$approved_col,
			'Failed to assert approved column shows Spam status.'
		);
	}

	/**
	 * Tests column_default method for date column.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default_date(): void {
		$date_col = $this->list_table->column_default( $this->rsvp, 'date' );

		$this->assertNotEmpty(
			$date_col,
			'Failed to assert date column is not empty.'
		);
	}

	/**
	 * Tests column_default method for response column with no terms.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default_response_no_terms(): void {
		$response_col = $this->list_table->column_default( $this->rsvp, 'response' );

		$this->assertSame(
			'-',
			$response_col,
			'Failed to assert response column shows dash when no terms.'
		);
	}

	/**
	 * Tests column_default method for response column with attending.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default_response_attending(): void {
		wp_set_object_terms( $this->rsvp['comment_ID'], 'attending', Rsvp::TAXONOMY );
		$response_col = $this->list_table->column_default( $this->rsvp, 'response' );

		$this->assertSame(
			'Attending',
			$response_col,
			'Failed to assert response column shows Attending.'
		);
	}

	/**
	 * Tests column_default method for response column with not attending.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default_response_not_attending(): void {
		wp_set_object_terms( $this->rsvp['comment_ID'], 'not_attending', Rsvp::TAXONOMY );
		$response_col = $this->list_table->column_default( $this->rsvp, 'response' );

		$this->assertSame(
			'Not Attending',
			$response_col,
			'Failed to assert response column shows Not Attending.'
		);
	}

	/**
	 * Tests column_default method for response column with waiting list.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default_response_waiting_list(): void {
		wp_set_object_terms( $this->rsvp['comment_ID'], 'waiting_list', Rsvp::TAXONOMY );
		$response_col = $this->list_table->column_default( $this->rsvp, 'response' );

		$this->assertSame(
			'Waiting List',
			$response_col,
			'Failed to assert response column shows Waiting List.'
		);
	}

	/**
	 * Tests column_default method for unknown column.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default_unknown_column(): void {
		$this->rsvp['custom_column'] = 'custom value';
		$result                      = $this->list_table->column_default( $this->rsvp, 'custom_column' );

		$this->assertSame(
			'custom value',
			$result,
			'Failed to assert unknown column returns item value.'
		);

		$result = $this->list_table->column_default( $this->rsvp, 'nonexistent_column' );

		$this->assertSame(
			'-',
			$result,
			'Failed to assert nonexistent column returns dash.'
		);
	}

	/**
	 * Tests column_cb method.
	 *
	 * @covers ::column_cb
	 * @return void
	 */
	public function test_column_cb(): void {
		$cb_col = $this->list_table->column_cb( $this->rsvp );

		$this->assertStringContainsString(
			'type="checkbox"',
			$cb_col,
			'Failed to assert checkbox column contains checkbox input.'
		);
		$this->assertStringContainsString(
			'gatherpress_rsvp_id[]',
			$cb_col,
			'Failed to assert checkbox has correct name.'
		);
		$this->assertStringContainsString(
			(string) $this->rsvp['comment_ID'],
			$cb_col,
			'Failed to assert checkbox has comment ID as value.'
		);
	}

	/**
	 * Tests column_attendee method.
	 *
	 * @covers ::column_attendee
	 * @return void
	 */
	public function test_column_attendee(): void {
		$attendee_col = $this->list_table->column_attendee( $this->rsvp );

		$this->assertStringContainsString(
			$this->rsvp['comment_author'],
			$attendee_col,
			'Failed to assert attendee column contains author name.'
		);
	}

	/**
	 * Tests column_attendee method with user.
	 *
	 * @covers ::column_attendee
	 * @return void
	 */
	public function test_column_attendee_with_user(): void {
		$user_id = $this->factory->user->create(
			array(
				'display_name' => 'Test User',
				'user_email'   => 'test@example.com',
			)
		);

		$this->rsvp['user_id'] = $user_id;
		$attendee_col          = $this->list_table->column_attendee( $this->rsvp );

		$this->assertStringContainsString(
			'Test User',
			$attendee_col,
			'Failed to assert attendee column contains user display name.'
		);
	}

	/**
	 * Tests column_attendee method with approved RSVP.
	 *
	 * @covers ::column_attendee
	 * @return void
	 */
	public function test_column_attendee_approved(): void {
		$this->rsvp['comment_approved'] = '1';
		$attendee_col                   = $this->list_table->column_attendee( $this->rsvp );

		$this->assertStringContainsString(
			'Unapprove',
			$attendee_col,
			'Failed to assert attendee column contains Unapprove action for approved RSVP.'
		);
		$this->assertStringNotContainsString(
			'>Approve<',
			$attendee_col,
			'Failed to assert attendee column does not contain Approve action for approved RSVP.'
		);
	}

	/**
	 * Tests column_attendee method with pending RSVP.
	 *
	 * @covers ::column_attendee
	 * @return void
	 */
	public function test_column_attendee_pending(): void {
		$this->rsvp['comment_approved'] = '0';
		$attendee_col                   = $this->list_table->column_attendee( $this->rsvp );

		$this->assertStringContainsString(
			'>Approve<',
			$attendee_col,
			'Failed to assert attendee column contains Approve action for pending RSVP.'
		);
	}

	/**
	 * Tests column_attendee method with spam RSVP.
	 *
	 * @covers ::column_attendee
	 * @return void
	 */
	public function test_column_attendee_spam(): void {
		$this->rsvp['comment_approved'] = 'spam';
		$attendee_col                   = $this->list_table->column_attendee( $this->rsvp );

		$this->assertStringContainsString(
			'Not Spam',
			$attendee_col,
			'Failed to assert attendee column contains Not Spam action for spam RSVP.'
		);
		$this->assertStringNotContainsString(
			'>Spam<',
			$attendee_col,
			'Failed to assert attendee column does not contain Spam action for spam RSVP.'
		);
	}

	/**
	 * Tests get_bulk_actions method.
	 *
	 * @covers ::get_bulk_actions
	 * @return void
	 */
	public function test_get_bulk_actions(): void {
		// Set user with capability.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$actions = $this->list_table->get_bulk_actions();

		$this->assertIsArray(
			$actions,
			'Failed to assert get_bulk_actions returns an array.'
		);
		$this->assertArrayHasKey(
			'approve',
			$actions,
			'Failed to assert bulk actions contain approve.'
		);
		$this->assertArrayHasKey(
			'unapprove',
			$actions,
			'Failed to assert bulk actions contain unapprove.'
		);
		$this->assertArrayHasKey(
			'delete',
			$actions,
			'Failed to assert bulk actions contain delete.'
		);
	}

	/**
	 * Tests get_bulk_actions method without capability.
	 *
	 * @covers ::get_bulk_actions
	 * @return void
	 */
	public function test_get_bulk_actions_no_capability(): void {
		// Set user without capability.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$actions = $this->list_table->get_bulk_actions();

		$this->assertEmpty(
			$actions,
			'Failed to assert bulk actions are empty without capability.'
		);
	}

	/**
	 * Tests single_row method.
	 *
	 * @covers ::single_row
	 * @return void
	 */
	public function test_single_row(): void {
		// Set user with capability.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		$this->list_table->single_row( $this->rsvp );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'<tr',
			$output,
			'Failed to assert single_row outputs tr element.'
		);
		$this->assertStringContainsString(
			'gatherpress-rsvp-' . $this->rsvp['comment_ID'],
			$output,
			'Failed to assert single_row outputs correct ID.'
		);
	}

	/**
	 * Tests single_row method with approved status.
	 *
	 * @covers ::single_row
	 * @return void
	 */
	public function test_single_row_approved(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->rsvp['comment_approved'] = '1';

		ob_start();
		$this->list_table->single_row( $this->rsvp );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'approved',
			$output,
			'Failed to assert single_row includes approved class.'
		);
	}

	/**
	 * Tests single_row method with spam status.
	 *
	 * @covers ::single_row
	 * @return void
	 */
	public function test_single_row_spam(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->rsvp['comment_approved'] = 'spam';

		ob_start();
		$this->list_table->single_row( $this->rsvp );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'spam',
			$output,
			'Failed to assert single_row includes spam class.'
		);
	}

	/**
	 * Tests single_row method without capability.
	 *
	 * @covers ::single_row
	 * @return void
	 */
	public function test_single_row_no_capability(): void {
		// Set user without capability.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		$this->list_table->single_row( $this->rsvp );
		$output = ob_get_clean();

		$this->assertEmpty(
			$output,
			'Failed to assert single_row outputs nothing without capability.'
		);
	}

	/**
	 * Tests process_bulk_action method without nonce.
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_no_nonce(): void {
		// Should not throw any errors.
		$this->list_table->process_bulk_action();

		$this->assertTrue(
			true,
			'Failed to assert process_bulk_action handles missing nonce gracefully.'
		);
	}

	/**
	 * Tests get_views method.
	 *
	 * @covers ::get_views
	 * @return void
	 */
	public function test_get_views(): void {
		$_REQUEST['_wpnonce'] = wp_create_nonce( Rsvp::COMMENT_TYPE );

		$views = $this->list_table->get_views();

		$this->assertIsArray(
			$views,
			'Failed to assert get_views returns an array.'
		);
		$this->assertArrayHasKey(
			'all',
			$views,
			'Failed to assert views contain all.'
		);
	}

	/**
	 * Tests prepare_items method.
	 *
	 * @covers ::prepare_items
	 * @return void
	 */
	public function test_prepare_items(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->list_table->prepare_items();

		$this->assertIsArray(
			$this->list_table->items,
			'Failed to assert items is an array after prepare_items.'
		);
	}

	/**
	 * Tests register_column_options method without screen.
	 *
	 * @covers ::register_column_options
	 * @return void
	 */
	public function test_register_column_options_no_screen(): void {
		// Should not throw any errors.
		$this->list_table->register_column_options();

		$this->assertTrue(
			true,
			'Failed to assert register_column_options handles missing screen gracefully.'
		);
	}

	/**
	 * Tests register_column_options method with screen.
	 *
	 * @covers ::register_column_options
	 * @return void
	 */
	public function test_register_column_options_with_screen(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );

		$this->list_table->register_column_options();

		$this->assertTrue(
			true,
			'Failed to assert register_column_options works with screen.'
		);
	}
}
