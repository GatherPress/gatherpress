<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\List_Table.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.33.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\List_Table;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Provider_Registry;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Screen;

/**
 * Class Test_List_Table.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\List_Table
 */
class Test_List_Table extends Base {

	/**
	 * The RSVP list table instance.
	 *
	 * @var List_Table
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

		$this->list_table = new List_Table();

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
		$list_table = new List_Table();

		$this->assertInstanceOf(
			List_Table::class,
			$list_table,
			'Failed to assert instance is List_Table.'
		);
	}

	/**
	 * The table defaults to the event post type and honors a `post_type`
	 * constructor argument (#1849).
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_scopes_post_type(): void {
		$this->assertSame(
			Event::POST_TYPE,
			Utility::get_hidden_property( new List_Table(), 'post_type' ),
			'Table should default to the event post type.'
		);

		$scoped = new List_Table( array( 'post_type' => 'gatherpress_probe' ) );

		$this->assertSame(
			'gatherpress_probe',
			Utility::get_hidden_property( $scoped, 'post_type' ),
			'Table should honor the post_type constructor argument.'
		);
	}

	/**
	 * The table only counts RSVPs belonging to its scoped post type (#1849).
	 *
	 * @covers ::get_rsvp_count
	 *
	 * @return void
	 */
	public function test_get_rsvp_count_only_counts_scoped_post_type(): void {
		register_post_type(
			'gatherpress_probe',
			array(
				'public'   => true,
				'supports' => array( 'title', 'gatherpress-rsvp' ),
			)
		);

		$probe_id = $this->factory->post->create(
			array(
				'post_type'   => 'gatherpress_probe',
				'post_status' => 'publish',
			)
		);

		$this->factory->comment->create(
			array(
				'comment_post_ID' => $probe_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// One RSVP on the probe post plus the event RSVP from set_up().
		$scoped = new List_Table( array( 'post_type' => 'gatherpress_probe' ) );

		$this->assertSame(
			1,
			Utility::invoke_hidden_method( $scoped, 'get_rsvp_count' ),
			'Scoped table should only count RSVPs of its own post type.'
		);

		$this->assertSame(
			1,
			Utility::invoke_hidden_method( new List_Table(), 'get_rsvp_count' ),
			'Default table should only count event RSVPs.'
		);

		unregister_post_type( 'gatherpress_probe' );
	}

	/**
	 * The event column header uses the scoped post type's singular label (#1849).
	 *
	 * @covers ::get_columns
	 *
	 * @return void
	 */
	public function test_get_columns_uses_scoped_post_type_label(): void {
		register_post_type(
			'gatherpress_probe',
			array(
				'public'   => true,
				'supports' => array( 'title', 'gatherpress-rsvp' ),
				'labels'   => array(
					'name'          => 'Probes',
					'singular_name' => 'Probe',
				),
			)
		);

		$scoped = new List_Table( array( 'post_type' => 'gatherpress_probe' ) );

		$this->assertSame(
			'Probe',
			$scoped->get_columns()['event'],
			'Event column header should use the scoped post type singular label.'
		);

		$this->assertSame(
			'Event',
			( new List_Table() )->get_columns()['event'],
			'Event column header should default to the event post type singular label.'
		);

		unregister_post_type( 'gatherpress_probe' );
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
		wp_set_object_terms( $this->rsvp['comment_ID'], Status::ATTENDING->value, Status::TAXONOMY );
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
		wp_set_object_terms( $this->rsvp['comment_ID'], Status::NOT_ATTENDING->value, Status::TAXONOMY );
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
		wp_set_object_terms( $this->rsvp['comment_ID'], Status::WAITING_LIST->value, Status::TAXONOMY );
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
		$this->assertStringContainsString(
			sprintf( 'for="cb-select-%d"', $this->rsvp['comment_ID'] ),
			$cb_col,
			'Failed to assert label is associated with the checkbox.'
		);
		$this->assertStringContainsString(
			sprintf( 'id="cb-select-%d"', $this->rsvp['comment_ID'] ),
			$cb_col,
			'Failed to assert checkbox carries the id the label points at.'
		);
		$this->assertStringContainsString(
			'screen-reader-text',
			$cb_col,
			'Failed to assert label text is visually hidden.'
		);
		$this->assertStringContainsString(
			sprintf( 'Select %s', $this->rsvp['comment_author'] ),
			$cb_col,
			'Failed to assert label names the attendee the checkbox selects.'
		);
	}

	/**
	 * Tests column_cb labels registered users by their display name.
	 *
	 * @covers ::column_cb
	 * @covers ::get_attendee_name
	 * @return void
	 */
	public function test_column_cb_registered_user(): void {
		$user_id = $this->factory->user->create(
			array(
				'display_name' => 'Registered Attendee',
			)
		);

		$rsvp            = $this->rsvp;
		$rsvp['user_id'] = $user_id;

		$cb_col = $this->list_table->column_cb( $rsvp );

		$this->assertStringContainsString(
			'Select Registered Attendee',
			$cb_col,
			'Failed to assert label uses the registered user\'s display name.'
		);
	}

	/**
	 * Tests column_cb falls back to the submitted author name when the
	 * stored user ID no longer resolves to an account.
	 *
	 * @covers ::column_cb
	 * @covers ::get_attendee_name
	 * @return void
	 */
	public function test_column_cb_stale_user_id(): void {
		$rsvp            = $this->rsvp;
		$rsvp['user_id'] = 999999; // No such user.

		$cb_col = $this->list_table->column_cb( $rsvp );

		$this->assertStringContainsString(
			sprintf( 'Select %s', $this->rsvp['comment_author'] ),
			$cb_col,
			'Failed to assert a stale user ID falls back to the submitted author name.'
		);
	}

	/**
	 * Tests column_cb never renders an empty attendee name.
	 *
	 * @covers ::column_cb
	 * @covers ::get_attendee_name
	 * @return void
	 */
	public function test_column_cb_unknown_attendee(): void {
		$rsvp                   = $this->rsvp;
		$rsvp['comment_author'] = '';
		$rsvp['user_id']        = 0;

		$cb_col = $this->list_table->column_cb( $rsvp );

		$this->assertStringContainsString(
			'Select Unknown',
			$cb_col,
			'Failed to assert an empty name resolution falls back to "Unknown".'
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
	 * Tests single_row method with pending/unapproved status.
	 *
	 * @covers ::single_row
	 * @return void
	 */
	public function test_single_row_pending(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->rsvp['comment_approved'] = '0';

		ob_start();
		$this->list_table->single_row( $this->rsvp );
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'unapproved',
			$output,
			'Failed to assert single_row includes unapproved class.'
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
	 * Tests process_bulk_action with empty rsvp_ids.
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_empty_ids(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_REQUEST['_wpnonce'] = wp_create_nonce( Rsvp::COMMENT_TYPE );
		// Don't set any rsvp_ids - they'll be empty.

		// Should not throw any errors.
		$this->list_table->process_bulk_action();

		$this->assertTrue(
			true,
			'Failed to assert process_bulk_action handles empty rsvp_ids gracefully.'
		);
	}

	/**
	 * The bulk form's own nonce, which is core's `bulk-<plural>` nonce, is
	 * accepted. Before #2062 only the comment-type nonce was, and since
	 * `WP_List_Table` emits its nonce under the same `_wpnonce` name, a browser
	 * submit was always rejected and the screen did nothing.
	 *
	 * @covers ::process_bulk_action
	 *
	 * @return void
	 */
	public function test_process_bulk_action_accepts_the_core_bulk_nonce(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$rsvp_id = (int) $this->rsvp['comment_ID'];

		wp_set_comment_status( $rsvp_id, 'approve' );

		// Derive the bulk nonce the same way process_bulk_action() does, so the
		// test stays honest if the list table's plural arg ever changes.
		$plural                          = Utility::get_hidden_property( $this->list_table, '_args' )['plural'];
		$_REQUEST['_wpnonce']            = wp_create_nonce( sprintf( 'bulk-%s', $plural ) );
		$_REQUEST['gatherpress_rsvp_id'] = array( $rsvp_id );
		$_REQUEST['action']              = 'unapprove';

		$this->list_table->process_bulk_action();

		$this->assertSame(
			'0',
			get_comment( $rsvp_id )->comment_approved,
			'Failed to assert the core bulk nonce authorizes a bulk action.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['gatherpress_rsvp_id'], $_REQUEST['action'] );
	}

	/**
	 * An unrelated nonce is still refused.
	 *
	 * @covers ::process_bulk_action
	 *
	 * @return void
	 */
	public function test_process_bulk_action_refuses_an_unrelated_nonce(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$rsvp_id = (int) $this->rsvp['comment_ID'];

		wp_set_comment_status( $rsvp_id, 'approve' );

		$_REQUEST['_wpnonce']            = wp_create_nonce( 'something-else' );
		$_REQUEST['gatherpress_rsvp_id'] = array( $rsvp_id );
		$_REQUEST['action']              = 'unapprove';

		$this->list_table->process_bulk_action();

		$this->assertSame(
			'1',
			get_comment( $rsvp_id )->comment_approved,
			'Failed to assert an unrelated nonce is refused.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['gatherpress_rsvp_id'], $_REQUEST['action'] );
	}

	/**
	 * Tests get_views method.
	 *
	 * @covers ::get_views
	 * @covers ::get_current_class_attr
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
	 * Tests get_current_class_attr method.
	 *
	 * @covers ::get_current_class_attr
	 * @return void
	 */
	public function test_get_current_class_attr(): void {
		// Test when status matches current.
		$result = Utility::invoke_hidden_method(
			$this->list_table,
			'get_current_class_attr',
			array( 'pending', 'pending' )
		);
		$this->assertEquals( ' class="current" aria-current="page"', $result );

		// Test when status does not match current.
		$result = Utility::invoke_hidden_method(
			$this->list_table,
			'get_current_class_attr',
			array( 'pending', 'approved' )
		);
		$this->assertEquals( '', $result );

		// Test with 'all' status.
		$result = Utility::invoke_hidden_method(
			$this->list_table,
			'get_current_class_attr',
			array( 'all', 'all' )
		);
		$this->assertEquals( ' class="current" aria-current="page"', $result );

		// Test with 'mine' status.
		$result = Utility::invoke_hidden_method(
			$this->list_table,
			'get_current_class_attr',
			array( 'mine', 'spam' )
		);
		$this->assertEquals( '', $result );
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

	/**
	 * Tests get_sortable_columns method.
	 *
	 * @covers ::get_sortable_columns
	 * @return void
	 */
	public function test_get_sortable_columns(): void {
		$sortable = Utility::invoke_hidden_method( $this->list_table, 'get_sortable_columns' );

		$this->assertIsArray(
			$sortable,
			'Failed to assert get_sortable_columns returns an array.'
		);
		$this->assertArrayHasKey(
			'attendee',
			$sortable,
			'Failed to assert sortable columns include attendee.'
		);
		$this->assertArrayHasKey(
			'response',
			$sortable,
			'Failed to assert sortable columns include response.'
		);
		$this->assertArrayHasKey(
			'event',
			$sortable,
			'Failed to assert sortable columns include event.'
		);
		$this->assertArrayHasKey(
			'approved',
			$sortable,
			'Failed to assert sortable columns include approved.'
		);
		$this->assertArrayHasKey(
			'date',
			$sortable,
			'Failed to assert sortable columns include date.'
		);
		$this->assertSame(
			array( 'date', true ),
			$sortable['date'],
			'Failed to assert date is the default sort column.'
		);
	}

	/**
	 * Tests process_bulk_action with approve action.
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_approve(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Create an RSVP comment with pending status.
		$rsvp_id = $this->factory->comment->create(
			array(
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => '0',
			)
		);

		$_REQUEST['_wpnonce']            = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['action']              = 'approve';
		$_REQUEST['gatherpress_rsvp_id'] = array( $rsvp_id );

		$this->list_table->process_bulk_action();

		$comment = get_comment( $rsvp_id );
		$this->assertSame(
			'1',
			$comment->comment_approved,
			'Failed to assert RSVP was approved.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['action'], $_REQUEST['gatherpress_rsvp_id'] );
	}

	/**
	 * Tests process_bulk_action with unapprove action.
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_unapprove(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Create an RSVP comment with approved status.
		$rsvp_id = $this->factory->comment->create(
			array(
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => '1',
			)
		);

		$_REQUEST['_wpnonce']            = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['action']              = 'unapprove';
		$_REQUEST['gatherpress_rsvp_id'] = array( $rsvp_id );

		$this->list_table->process_bulk_action();

		$comment = get_comment( $rsvp_id );
		$this->assertSame(
			'0',
			$comment->comment_approved,
			'Failed to assert RSVP was unapproved.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['action'], $_REQUEST['gatherpress_rsvp_id'] );
	}

	/**
	 * Tests process_bulk_action with spam action.
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_spam(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Create an RSVP comment.
		$rsvp_id = $this->factory->comment->create(
			array(
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => '1',
			)
		);

		$_REQUEST['_wpnonce']            = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['action']              = 'spam';
		$_REQUEST['gatherpress_rsvp_id'] = array( $rsvp_id );

		$this->list_table->process_bulk_action();

		$comment = get_comment( $rsvp_id );
		$this->assertSame(
			'spam',
			$comment->comment_approved,
			'Failed to assert RSVP was marked as spam.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['action'], $_REQUEST['gatherpress_rsvp_id'] );
	}

	/**
	 * Tests process_bulk_action with unspam action.
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_unspam(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Create an RSVP comment with spam status.
		$rsvp_id = $this->factory->comment->create(
			array(
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => 'spam',
			)
		);

		$_REQUEST['_wpnonce']            = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['action']              = 'unspam';
		$_REQUEST['gatherpress_rsvp_id'] = array( $rsvp_id );

		$this->list_table->process_bulk_action();

		$comment = get_comment( $rsvp_id );
		$this->assertSame(
			'1',
			$comment->comment_approved,
			'Failed to assert RSVP was unmarked as spam.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['action'], $_REQUEST['gatherpress_rsvp_id'] );
	}

	/**
	 * Tests process_bulk_action with delete action.
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_delete(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Create an RSVP comment.
		$rsvp_id = $this->factory->comment->create(
			array(
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => '1',
			)
		);

		$_REQUEST['_wpnonce']            = wp_create_nonce( 'gatherpress_rsvp_action' );
		$_REQUEST['action']              = 'delete';
		$_REQUEST['gatherpress_rsvp_id'] = array( $rsvp_id );

		$this->list_table->process_bulk_action();

		$comment = get_comment( $rsvp_id );
		$this->assertNull(
			$comment,
			'Failed to assert RSVP was deleted.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['action'], $_REQUEST['gatherpress_rsvp_id'] );
	}

	/**
	 * Tests process_bulk_action with single RSVP ID (not array).
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_single_id(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		// Create an RSVP comment.
		$rsvp_id = $this->factory->comment->create(
			array(
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => '0',
			)
		);

		$_REQUEST['_wpnonce']            = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['action']              = 'approve';
		$_REQUEST['gatherpress_rsvp_id'] = $rsvp_id;

		$this->list_table->process_bulk_action();

		$comment = get_comment( $rsvp_id );
		$this->assertSame(
			'1',
			$comment->comment_approved,
			'Failed to assert single RSVP ID was processed.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['action'], $_REQUEST['gatherpress_rsvp_id'] );
	}

	/**
	 * Tests process_bulk_action with no capability.
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_no_capability(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		// Create an RSVP comment.
		$rsvp_id = $this->factory->comment->create(
			array(
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_post_ID'  => $this->post_id,
				'comment_approved' => '0',
			)
		);

		$_REQUEST['_wpnonce']            = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['action']              = 'approve';
		$_REQUEST['gatherpress_rsvp_id'] = array( $rsvp_id );

		$this->list_table->process_bulk_action();

		$comment = get_comment( $rsvp_id );
		$this->assertSame(
			'0',
			$comment->comment_approved,
			'Failed to assert RSVP was not processed without capability.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['action'], $_REQUEST['gatherpress_rsvp_id'] );
	}

	/**
	 * Tests get_views with post_id filter.
	 *
	 * @covers ::get_views
	 * @return void
	 */
	public function test_get_views_with_post_id(): void {
		$_REQUEST['_wpnonce'] = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['post_id']  = $this->post_id;

		$views = $this->list_table->get_views();

		$this->assertIsArray(
			$views,
			'Failed to assert get_views returns an array with post_id filter.'
		);
		$this->assertArrayHasKey(
			'all',
			$views,
			'Failed to assert views contain all with post_id filter.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['post_id'] );
	}

	/**
	 * Tests get_views with user_id filter.
	 *
	 * @covers ::get_views
	 * @return void
	 */
	public function test_get_views_with_user_id(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['_wpnonce'] = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['user_id']  = $user_id;

		$views = $this->list_table->get_views();

		$this->assertIsArray(
			$views,
			'Failed to assert get_views returns an array with user_id filter.'
		);
		$this->assertArrayHasKey(
			'all',
			$views,
			'Failed to assert views contain all with user_id filter.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['user_id'] );
	}

	/**
	 * Tests column_default with unknown response.
	 *
	 * @covers ::column_default
	 * @return void
	 */
	public function test_column_default_response_unknown(): void {
		// Create a term with an unknown slug.
		$term_id = wp_insert_term( 'Unknown', Status::TAXONOMY, array( 'slug' => 'unknown_status' ) );
		wp_set_object_terms( $this->rsvp['comment_ID'], $term_id['term_id'], Status::TAXONOMY );

		$output = $this->list_table->column_default( $this->rsvp, 'response' );

		$this->assertSame(
			'-',
			$output,
			'Failed to assert unknown response returns dash.'
		);
	}

	/**
	 * Tests get_views with status filter.
	 *
	 * @covers ::get_views
	 * @return void
	 */
	public function test_get_views_with_status_filter(): void {
		$_REQUEST['_wpnonce'] = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['status']   = 'attending';

		$views = $this->list_table->get_views();

		$this->assertIsArray( $views, 'Failed to assert get_views returns an array with status filter.' );

		unset( $_REQUEST['status'] );
	}

	/**
	 * Tests process_bulk_action with delete action and invalid nonce.
	 *
	 * @covers ::process_bulk_action
	 * @return void
	 */
	public function test_process_bulk_action_delete_with_invalid_nonce(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_REQUEST['gatherpress_rsvp_id'] = array( $this->rsvp['comment_ID'] );
		$_REQUEST['action']              = 'delete';
		$_REQUEST['_wpnonce']            = 'invalid_nonce';

		$this->list_table->process_bulk_action();

		// Verify comment still exists since nonce was invalid.
		$comment = get_comment( $this->rsvp['comment_ID'] );
		$this->assertNotNull( $comment, 'Failed to assert comment still exists after delete with invalid nonce.' );

		unset( $_REQUEST['gatherpress_rsvp_id'], $_REQUEST['action'], $_REQUEST['_wpnonce'] );
	}

	/**
	 * Tests prepare_items with text search.
	 *
	 * @covers ::prepare_items
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_prepare_items_with_text_search(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_REQUEST['s'] = 'Test';

		$this->list_table->prepare_items();

		$this->assertIsArray(
			$this->list_table->items,
			'Failed to assert items is an array after prepare_items with search.'
		);

		unset( $_REQUEST['s'] );
	}

	/**
	 * Tests prepare_items with IP address search.
	 *
	 * @covers ::prepare_items
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_prepare_items_with_ip_search(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_REQUEST['s'] = '192.168.1.1';

		$this->list_table->prepare_items();

		$this->assertIsArray(
			$this->list_table->items,
			'Failed to assert items is an array after prepare_items with IP search.'
		);

		unset( $_REQUEST['s'] );
	}

	/**
	 * Tests prepare_items with user_id filter.
	 *
	 * @covers ::prepare_items
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_prepare_items_with_user_id_filter(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_REQUEST['user_id'] = $user_id;

		$this->list_table->prepare_items();

		$this->assertIsArray(
			$this->list_table->items,
			'Failed to assert items is an array after prepare_items with user_id filter.'
		);

		unset( $_REQUEST['user_id'] );
	}

	/**
	 * Tests prepare_items with post_id filter.
	 *
	 * @covers ::prepare_items
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_prepare_items_with_post_id_filter(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_REQUEST['post_id'] = $this->event_id;

		$this->list_table->prepare_items();

		$this->assertIsArray(
			$this->list_table->items,
			'Failed to assert items is an array after prepare_items with post_id filter.'
		);

		unset( $_REQUEST['post_id'] );
	}

	/**
	 * Tests prepare_items with approved status filter.
	 *
	 * @covers ::prepare_items
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_prepare_items_with_approved_status_filter(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_REQUEST['status'] = 'approved';

		$this->list_table->prepare_items();

		$this->assertIsArray(
			$this->list_table->items,
			'Failed to assert items is an array after prepare_items with approved status filter.'
		);

		unset( $_REQUEST['status'] );
	}

	/**
	 * Tests prepare_items with pending status filter.
	 *
	 * @covers ::prepare_items
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_prepare_items_with_pending_status_filter(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_REQUEST['status'] = 'pending';

		$this->list_table->prepare_items();

		$this->assertIsArray(
			$this->list_table->items,
			'Failed to assert items is an array after prepare_items with pending status filter.'
		);

		unset( $_REQUEST['status'] );
	}

	/**
	 * Tests prepare_items with spam status filter.
	 *
	 * @covers ::prepare_items
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_prepare_items_with_spam_status_filter(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_REQUEST['status'] = 'spam';

		$this->list_table->prepare_items();

		$this->assertIsArray(
			$this->list_table->items,
			'Failed to assert items is an array after prepare_items with spam status filter.'
		);

		unset( $_REQUEST['status'] );
	}

	/**
	 * Tests prepare_items with custom orderby and order.
	 *
	 * @covers ::prepare_items
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_prepare_items_with_custom_order(): void {
		set_current_screen( 'gatherpress_event_page_gatherpress_rsvp' );
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$_REQUEST['orderby'] = 'comment_author';
		$_REQUEST['order']   = 'ASC';

		$this->list_table->prepare_items();

		$this->assertIsArray(
			$this->list_table->items,
			'Failed to assert items is an array after prepare_items with custom order.'
		);

		unset( $_REQUEST['orderby'], $_REQUEST['order'] );
	}

	/**
	 * Tests get_rsvps with null per_page parameter.
	 *
	 * Covers: Fallback to DEFAULT_PER_PAGE when per_page is null.
	 *
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_get_rsvps_null_per_page(): void {
		$result = Utility::invoke_hidden_method(
			$this->list_table,
			'get_rsvps',
			array( null, 1 )
		);

		$this->assertIsArray(
			$result,
			'Failed to assert get_rsvps returns an array with null per_page.'
		);
	}

	/**
	 * Tests get_rsvp_count method directly.
	 *
	 * Covers get_rsvp_count method (private method).
	 *
	 * @covers ::get_rsvp_count
	 * @return void
	 */
	public function test_get_rsvp_count(): void {
		$count = Utility::invoke_hidden_method(
			$this->list_table,
			'get_rsvp_count'
		);

		$this->assertIsInt(
			$count,
			'Failed to assert get_rsvp_count returns an integer.'
		);
		$this->assertGreaterThanOrEqual(
			0,
			$count,
			'Failed to assert get_rsvp_count returns a non-negative number.'
		);
	}

	/**
	 * Tests get_rsvp_count with user_id filter.
	 *
	 * @covers ::get_rsvp_count
	 * @return void
	 */
	public function test_get_rsvp_count_with_user_id_filter(): void {
		$user_id = $this->factory->user->create();

		$_REQUEST['user_id'] = $user_id;

		$count = Utility::invoke_hidden_method(
			$this->list_table,
			'get_rsvp_count'
		);

		$this->assertIsInt(
			$count,
			'Failed to assert get_rsvp_count returns an integer with user_id filter.'
		);

		unset( $_REQUEST['user_id'] );
	}

	/**
	 * Tests get_rsvp_count with search parameter.
	 *
	 * @covers ::get_rsvp_count
	 * @return void
	 */
	public function test_get_rsvp_count_with_search(): void {
		$_REQUEST['s'] = 'test search';

		$count = Utility::invoke_hidden_method(
			$this->list_table,
			'get_rsvp_count'
		);

		$this->assertIsInt(
			$count,
			'Failed to assert get_rsvp_count returns an integer with search.'
		);

		unset( $_REQUEST['s'] );
	}

	/**
	 * Tests get_rsvp_count with post_id filter.
	 *
	 * @covers ::get_rsvp_count
	 * @return void
	 */
	public function test_get_rsvp_count_with_post_id_filter(): void {
		$_REQUEST['post_id'] = $this->event_id;

		$count = Utility::invoke_hidden_method(
			$this->list_table,
			'get_rsvp_count'
		);

		$this->assertIsInt(
			$count,
			'Failed to assert get_rsvp_count returns an integer with post_id filter.'
		);

		unset( $_REQUEST['post_id'] );
	}

	/**
	 * Tests get_rsvp_count with approved status filter.
	 *
	 * @covers ::get_rsvp_count
	 * @return void
	 */
	public function test_get_rsvp_count_with_approved_status(): void {
		$_REQUEST['status'] = 'approved';

		$count = Utility::invoke_hidden_method(
			$this->list_table,
			'get_rsvp_count'
		);

		$this->assertIsInt(
			$count,
			'Failed to assert get_rsvp_count returns an integer with approved status filter.'
		);

		unset( $_REQUEST['status'] );
	}

	/**
	 * Tests get_rsvp_count with spam status filter.
	 *
	 * @covers ::get_rsvp_count
	 * @return void
	 */
	public function test_get_rsvp_count_with_spam_status(): void {
		$_REQUEST['status'] = 'spam';

		$count = Utility::invoke_hidden_method(
			$this->list_table,
			'get_rsvp_count'
		);

		$this->assertIsInt(
			$count,
			'Failed to assert get_rsvp_count returns an integer with spam status filter.'
		);

		unset( $_REQUEST['status'] );
	}

	/**
	 * Tests get_rsvp_count with pending status filter.
	 *
	 * Covers: Sets args['status'] to 'hold' for pending status in get_rsvp_count.
	 *
	 * @covers ::get_rsvp_count
	 * @return void
	 */
	public function test_get_rsvp_count_with_pending_status(): void {
		$_REQUEST['status'] = 'pending';

		$count = Utility::invoke_hidden_method(
			$this->list_table,
			'get_rsvp_count'
		);

		$this->assertIsInt(
			$count,
			'Failed to assert get_rsvp_count returns an integer with pending status filter.'
		);

		unset( $_REQUEST['status'] );
	}

	/**
	 * Tests get_views with valid post_id filter.
	 *
	 * Covers: post_id filter handling in get_views.
	 *
	 * @covers ::get_views
	 * @return void
	 */
	public function test_get_views_with_valid_post_id_filter(): void {
		$_REQUEST['_wpnonce'] = wp_create_nonce( Rsvp::COMMENT_TYPE );
		$_REQUEST['post_id']  = $this->event_id;

		$views = $this->list_table->get_views();

		$this->assertIsArray(
			$views,
			'Failed to assert get_views returns an array with valid post_id filter.'
		);

		// Verify that post_id is included in the view URLs.
		$this->assertStringContainsString(
			'post_id=' . $this->event_id,
			$views['all'],
			'Failed to assert all view contains post_id parameter.'
		);

		unset( $_REQUEST['_wpnonce'], $_REQUEST['post_id'] );
	}

	/**
	 * Tests get_rsvps with pending status filter.
	 *
	 * Covers: Sets args['status'] to 'hold' for pending status.
	 *
	 * @covers ::get_rsvps
	 * @return void
	 */
	public function test_get_rsvps_with_pending_status(): void {
		$_REQUEST['status'] = 'pending';

		$result = Utility::invoke_hidden_method(
			$this->list_table,
			'get_rsvps',
			array( 10, 1 )
		);

		$this->assertIsArray(
			$result,
			'Failed to assert get_rsvps returns an array with pending status filter.'
		);

		unset( $_REQUEST['status'] );
	}

	/**
	 * Create an RSVP comment on the test event and return its ID.
	 *
	 * @return int
	 */
	private function make_rsvp_comment(): int {
		return $this->factory->comment->create(
			array(
				'comment_post_ID' => $this->event_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);
	}

	/**
	 * The type column prefers the stamped provider term and renders its
	 * label, dashes an unknown term, and — when no term is stamped —
	 * infers the provider from the comment's user id or author email.
	 *
	 * @covers ::column_default
	 * @covers ::infer_provider_from_item
	 *
	 * @return void
	 */
	public function test_column_default_type(): void {
		$user_label  = Provider_Registry::get_instance()->get( 'user' )::get_label();
		$email_label = Provider_Registry::get_instance()->get( 'email' )::get_label();

		// A stamped provider term takes precedence and renders its label.
		$stamped = $this->make_rsvp_comment();
		wp_set_object_terms( $stamped, 'user', Provider::TAXONOMY );

		$this->assertSame(
			$user_label,
			$this->list_table->column_default( array( 'comment_ID' => $stamped ), 'type' ),
			'A stamped provider term renders its label.'
		);

		// An unknown term renders a dash.
		wp_set_object_terms( $stamped, 'mystery-provider', Provider::TAXONOMY );

		$this->assertSame(
			'-',
			$this->list_table->column_default( array( 'comment_ID' => $stamped ), 'type' ),
			'An unknown provider term renders a dash.'
		);

		// No term: a real user id infers the user provider.
		$this->assertSame(
			$user_label,
			$this->list_table->column_default(
				array(
					'comment_ID'           => $this->make_rsvp_comment(),
					'user_id'              => 5,
					'comment_author_email' => '',
				),
				'type'
			),
			'A user id infers the user provider when no term is stamped.'
		);

		// No term: a valid author email infers the email provider (the
		// open/email front-end form leaves no provider term).
		$this->assertSame(
			$email_label,
			$this->list_table->column_default(
				array(
					'comment_ID'           => $this->make_rsvp_comment(),
					'user_id'              => 0,
					'comment_author_email' => 'guest@example.test',
				),
				'type'
			),
			'A valid author email infers the email provider when no term is stamped.'
		);

		// No term and nothing to infer from renders empty.
		$this->assertSame(
			'',
			$this->list_table->column_default(
				array(
					'comment_ID'           => $this->make_rsvp_comment(),
					'user_id'              => 0,
					'comment_author_email' => '',
				),
				'type'
			),
			'No term and nothing to infer from renders empty.'
		);
	}

	/**
	 * A screen passed to the constructor is the one the table binds its column
	 * hooks to, instead of whatever screen the request is currently on.
	 *
	 * @since 0.36.0
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_with_screen(): void {
		$screen_id  = 'gatherpress_event_page_gatherpress_rsvp';
		$list_table = new List_Table( array( 'screen' => $screen_id ) );

		$this->assertSame(
			$screen_id,
			$list_table->screen->id,
			'The table adopts the screen named in the constructor arguments.'
		);
		$this->assertSame(
			0,
			has_filter( sprintf( 'manage_%s_columns', $screen_id ), array( $list_table, 'get_columns' ) ),
			'The column hooks bind to the screen named in the constructor arguments.'
		);
	}

	/**
	 * A stored per-page preference that is not a positive integer would divide
	 * the total by a non-positive number, so it falls back to the default.
	 *
	 * @since 0.36.0
	 * @covers ::prepare_items
	 *
	 * @return void
	 */
	public function test_prepare_items_falls_back_to_default_per_page(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $user_id );
		update_user_meta( $user_id, sprintf( '%s_per_page', Rsvp::COMMENT_TYPE ), -5 );

		$this->list_table->prepare_items();

		$this->assertSame(
			List_Table::DEFAULT_PER_PAGE,
			$this->list_table->get_pagination_arg( 'per_page' ),
			'A stored preference below one falls back to the default page size.'
		);
	}

	/**
	 * An RSVP row outlives the post it points at, so a deleted event renders the
	 * stored title as text, or a dash when nothing is left of it.
	 *
	 * @since 0.36.0
	 * @covers ::column_default
	 *
	 * @return void
	 */
	public function test_column_default_event_deleted(): void {
		$item = $this->rsvp;

		wp_delete_post( $this->event_id, true );

		$item['event_title'] = 'Deleted Event';

		$this->assertSame(
			'Deleted Event',
			$this->list_table->column_default( $item, 'event' ),
			'A deleted event renders the stored title without a link.'
		);

		$item['event_title'] = '';

		$this->assertSame(
			'-',
			$this->list_table->column_default( $item, 'event' ),
			'A deleted event with no stored title renders a dash.'
		);
	}

	/**
	 * Core stores comment statuses this table has no label for, and a row can
	 * arrive without the field at all; both render a dash.
	 *
	 * @since 0.36.0
	 * @covers ::column_default
	 *
	 * @return void
	 */
	public function test_column_default_approved_unlabeled_status(): void {
		$item                     = $this->rsvp;
		$item['comment_approved'] = 'trash';

		$this->assertSame(
			'-',
			$this->list_table->column_default( $item, 'approved' ),
			'A status the table has no label for renders a dash.'
		);

		unset( $item['comment_approved'] );

		$this->assertSame(
			'-',
			$this->list_table->column_default( $item, 'approved' ),
			'A row carrying no status at all renders a dash.'
		);
	}

	/**
	 * An unfiltered request reports no event.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_filtered_post_id
	 *
	 * @return void
	 */
	public function test_get_filtered_post_id_unfiltered(): void {
		$this->assertSame(
			0,
			Utility::invoke_hidden_method( $this->list_table, 'get_filtered_post_id' ),
			'Failed to assert an unfiltered request reports no event.'
		);
	}

	/**
	 * The filter control submits the event as `post_id`.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_filtered_post_id
	 *
	 * @return void
	 */
	public function test_get_filtered_post_id_reads_post_id(): void {
		$_REQUEST['post_id'] = (string) $this->event_id;

		$this->assertSame(
			$this->event_id,
			Utility::invoke_hidden_method( $this->list_table, 'get_filtered_post_id' ),
			'Failed to assert the requested event is read from post_id.'
		);

		unset( $_REQUEST['post_id'] );
	}

	/**
	 * An unfiltered request reports no responses.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_filtered_responses
	 *
	 * @return void
	 */
	public function test_get_filtered_responses_unfiltered(): void {
		$this->assertSame(
			array(),
			Utility::invoke_hidden_method( $this->list_table, 'get_filtered_responses' ),
			'Failed to assert an unfiltered request reports no responses.'
		);
	}

	/**
	 * A response parameter that is not a string is ignored.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_filtered_responses
	 *
	 * @return void
	 */
	public function test_get_filtered_responses_ignores_a_non_string(): void {
		// `response[]=attending` arrives as an array, which explode() rejects.
		$_REQUEST['response'] = array( Status::ATTENDING->value );

		$this->assertSame(
			array(),
			Utility::invoke_hidden_method( $this->list_table, 'get_filtered_responses' ),
			'Failed to assert an array response parameter is ignored.'
		);

		unset( $_REQUEST['response'] );
	}

	/**
	 * Several responses arrive as one comma-separated parameter.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_filtered_responses
	 *
	 * @return void
	 */
	public function test_get_filtered_responses_reads_a_list(): void {
		$_REQUEST['response'] = sprintf(
			'%s,%s',
			Status::ATTENDING->value,
			Status::WAITING_LIST->value
		);

		$this->assertSame(
			array( Status::ATTENDING->value, Status::WAITING_LIST->value ),
			Utility::invoke_hidden_method( $this->list_table, 'get_filtered_responses' ),
			'Failed to assert every requested response is read.'
		);

		unset( $_REQUEST['response'] );
	}

	/**
	 * A status the enum does not carry is dropped rather than queried.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_filtered_responses
	 *
	 * @return void
	 */
	public function test_get_filtered_responses_drops_unknown_values(): void {
		// Otherwise a hand-edited URL could widen the filter to any term.
		$_REQUEST['response'] = sprintf( '%s,made-up', Status::ATTENDING->value );

		$this->assertSame(
			array( Status::ATTENDING->value ),
			Utility::invoke_hidden_method( $this->list_table, 'get_filtered_responses' ),
			'Failed to assert an unknown status is dropped.'
		);

		unset( $_REQUEST['response'] );
	}

	/**
	 * An unfiltered request leaves the comment query alone.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::add_response_filter
	 *
	 * @return void
	 */
	public function test_add_response_filter_leaves_args_alone(): void {
		$args = array( 'number' => 20 );

		$this->assertSame(
			$args,
			Utility::invoke_hidden_method(
				$this->list_table,
				'add_response_filter',
				array( $args )
			),
			'Failed to assert an unfiltered request adds no taxonomy query.'
		);
	}

	/**
	 * A filtered request narrows the comment query by response term.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::add_response_filter
	 *
	 * @return void
	 */
	public function test_add_response_filter_adds_a_tax_query(): void {
		$_REQUEST['response'] = sprintf(
			'%s,%s',
			Status::ATTENDING->value,
			Status::WAITING_LIST->value
		);

		$args = Utility::invoke_hidden_method(
			$this->list_table,
			'add_response_filter',
			array( array( 'number' => 20 ) )
		);

		$this->assertSame(
			array(
				array(
					'taxonomy' => Status::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array( Status::ATTENDING->value, Status::WAITING_LIST->value ),
				),
			),
			$args['tax_query'],
			'Failed to assert the requested responses become a taxonomy query.'
		);

		$this->assertSame(
			20,
			$args['number'],
			'Failed to assert the existing arguments survive the filter.'
		);

		unset( $_REQUEST['response'] );
	}

	/**
	 * The controls are written out, not left for script to create.
	 *
	 * The row has to be complete on the first paint; a mount point alone
	 * leaves the tablenav short until the footer script runs.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::extra_tablenav
	 * @covers ::render_event_field
	 * @covers ::render_response_toggle
	 *
	 * @return void
	 */
	public function test_extra_tablenav_writes_the_controls(): void {
		$output = Utility::buffer_and_return(
			static function (): void {
				Utility::invoke_hidden_method(
					new List_Table(),
					'extra_tablenav',
					array( 'top' )
				);
			}
		);

		$this->assertStringContainsString(
			'id="gatherpress-rsvp-event"',
			$output,
			'Failed to assert the event field is written out.'
		);

		$this->assertStringContainsString(
			'<svg',
			$output,
			'Failed to assert the response toggle is written out.'
		);

		$this->assertStringContainsString(
			'Filter',
			$output,
			'Failed to assert the submit control is written out.'
		);

		$this->assertStringContainsString(
			Status::ATTENDING->value,
			$output,
			'Failed to assert the selectable responses reach the script.'
		);
	}

	/**
	 * The bottom tablenav renders nothing.
	 *
	 * A second copy carries the same controls and would show beside the first
	 * below 783px, where the stylesheet keeps the top group visible.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::extra_tablenav
	 *
	 * @return void
	 */
	public function test_extra_tablenav_renders_once(): void {
		$list_table = $this->list_table;

		$this->assertSame(
			'',
			Utility::buffer_and_return(
				static function () use ( $list_table ): void {
					Utility::invoke_hidden_method( $list_table, 'extra_tablenav', array( 'bottom' ) );
				}
			),
			'Failed to assert the bottom tablenav renders nothing.'
		);
	}

	/**
	 * The written event field is an empty, labelled text box.
	 *
	 * The selection's title is left to the script, which resolves it along
	 * with everything else it lists rather than costing a query here.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::render_event_field
	 *
	 * @return void
	 */
	public function test_render_event_field_is_written_empty(): void {
		$field = Utility::invoke_hidden_method( $this->list_table, 'render_event_field' );

		$this->assertStringContainsString(
			'id="gatherpress-rsvp-event"',
			$field,
			'Failed to assert the event field is written out.'
		);

		$this->assertStringNotContainsString(
			'value=',
			$field,
			'Failed to assert the field leaves its value to the script.'
		);
	}

	/**
	 * The written toggle announces what it is filtered to.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::render_response_toggle
	 * @covers ::get_response_label
	 *
	 * @return void
	 */
	public function test_render_response_toggle_announces_the_selection(): void {
		$list_table = $this->list_table;
		$toggle     = static fn( array $responses ): string => Utility::invoke_hidden_method(
			$list_table,
			'render_response_toggle',
			array( $responses )
		);

		$this->assertStringContainsString(
			'Filter by response: all',
			$toggle( array() ),
			'Failed to assert an unfiltered toggle reads as unfiltered.'
		);

		$this->assertStringContainsString(
			'Filter by response: Attending',
			$toggle( array( Status::ATTENDING->value ) ),
			'Failed to assert one response is named.'
		);

		$this->assertStringContainsString(
			'Filter by response: 2 selected',
			$toggle( array( Status::ATTENDING->value, Status::WAITING_LIST->value ) ),
			'Failed to assert several responses are counted.'
		);

		$this->assertStringContainsString(
			'aria-label="Filter by response"',
			$toggle( array( 'made-up' ) ),
			'Failed to assert an unknown response still names the control.'
		);
	}

	/**
	 * The mount carries the filters the current request already applied.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::extra_tablenav
	 *
	 * @return void
	 */
	public function test_extra_tablenav_carries_the_current_filters(): void {
		$_REQUEST['post_id']  = (string) $this->event_id;
		$_REQUEST['response'] = Status::ATTENDING->value;

		$list_table = $this->list_table;
		$output     = Utility::buffer_and_return(
			static function () use ( $list_table ): void {
				Utility::invoke_hidden_method( $list_table, 'extra_tablenav', array( 'top' ) );
			}
		);

		$this->assertStringContainsString(
			sprintf( 'data-post-id="%d"', $this->event_id ),
			$output,
			'Failed to assert the filtered event reaches the mount.'
		);

		$this->assertStringContainsString(
			sprintf( 'data-selected="%s"', Status::ATTENDING->value ),
			$output,
			'Failed to assert the filtered responses reach the mount.'
		);

		unset( $_REQUEST['post_id'], $_REQUEST['response'] );
	}

	/**
	 * Switching view keeps the response filter rather than widening the list.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_views
	 *
	 * @return void
	 */
	public function test_get_views_preserves_the_response_filter(): void {
		$_REQUEST['response'] = Status::ATTENDING->value;

		$views = $this->list_table->get_views();

		$this->assertStringContainsString(
			sprintf( 'response=%s', Status::ATTENDING->value ),
			implode( '', $views ),
			'Failed to assert the view links carry the response filter.'
		);

		unset( $_REQUEST['response'] );
	}

	/**
	 * Filtering by event returns that event's RSVPs and no others.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_rsvps
	 * @covers ::get_rsvp_count
	 *
	 * @return void
	 */
	public function test_post_id_filter_narrows_the_results(): void {
		$other_event = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_title'  => 'Other Event',
				'post_status' => 'publish',
			)
		);

		$this->factory->comment->create(
			array(
				'comment_post_ID' => $other_event,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$_REQUEST['post_id'] = (string) $this->event_id;

		$rsvps = Utility::invoke_hidden_method( $this->list_table, 'get_rsvps', array( 20, 1 ) );
		$posts = array_unique( array_column( $rsvps, 'comment_post_ID' ) );

		$this->assertSame(
			array( (string) $this->event_id ),
			array_values( $posts ),
			'Failed to assert only the filtered event\'s RSVPs are returned.'
		);

		$this->assertSame(
			count( $rsvps ),
			Utility::invoke_hidden_method( $this->list_table, 'get_rsvp_count' ),
			'Failed to assert the count agrees with the filtered rows.'
		);

		unset( $_REQUEST['post_id'] );
	}

	/**
	 * Filtering by response returns those responses and no others.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_rsvps
	 * @covers ::get_rsvp_count
	 *
	 * @return void
	 */
	public function test_response_filter_narrows_the_results(): void {
		$attending = (int) $this->rsvp['comment_ID'];
		$declined  = $this->factory->comment->create(
			array(
				'comment_post_ID' => $this->event_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		wp_set_object_terms( $attending, Status::ATTENDING->value, Status::TAXONOMY );
		wp_set_object_terms( $declined, Status::NOT_ATTENDING->value, Status::TAXONOMY );

		$_REQUEST['response'] = Status::ATTENDING->value;

		$rsvps = Utility::invoke_hidden_method( $this->list_table, 'get_rsvps', array( 20, 1 ) );
		$ids   = array_map( 'intval', array_column( $rsvps, 'comment_ID' ) );

		$this->assertContains(
			$attending,
			$ids,
			'Failed to assert the requested response is returned.'
		);

		$this->assertNotContains(
			$declined,
			$ids,
			'Failed to assert an unrequested response is excluded.'
		);

		$this->assertSame(
			count( $rsvps ),
			Utility::invoke_hidden_method( $this->list_table, 'get_rsvp_count' ),
			'Failed to assert the count agrees with the filtered rows.'
		);

		unset( $_REQUEST['response'] );
	}

	/**
	 * Two responses widen the result rather than narrowing it to nothing.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_rsvps
	 *
	 * @return void
	 */
	public function test_response_filter_reads_as_or(): void {
		$attending = (int) $this->rsvp['comment_ID'];
		$waiting   = $this->factory->comment->create(
			array(
				'comment_post_ID' => $this->event_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		wp_set_object_terms( $attending, Status::ATTENDING->value, Status::TAXONOMY );
		wp_set_object_terms( $waiting, Status::WAITING_LIST->value, Status::TAXONOMY );

		$_REQUEST['response'] = sprintf(
			'%s,%s',
			Status::ATTENDING->value,
			Status::WAITING_LIST->value
		);

		$rsvps = Utility::invoke_hidden_method( $this->list_table, 'get_rsvps', array( 20, 1 ) );
		$ids   = array_map( 'intval', array_column( $rsvps, 'comment_ID' ) );

		$this->assertContains( $attending, $ids, 'Failed to assert the first response is returned.' );
		$this->assertContains( $waiting, $ids, 'Failed to assert the second response is returned.' );

		unset( $_REQUEST['response'] );
	}

	/**
	 * A non-scalar event reads as unfiltered rather than as post 1.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_filtered_post_id
	 *
	 * @return void
	 */
	public function test_get_filtered_post_id_ignores_a_non_scalar(): void {
		// `post_id[]=9` casts to 1, which would filter the screen to whichever
		// post holds that ID.
		$_REQUEST['post_id'] = array( '9' );

		$this->assertSame(
			0,
			$this->list_table->get_filtered_post_id(),
			'Failed to assert an array event parameter is ignored.'
		);

		unset( $_REQUEST['post_id'] );
	}

	/**
	 * A negative event reads as unfiltered.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_filtered_post_id
	 *
	 * @return void
	 */
	public function test_get_filtered_post_id_ignores_a_negative(): void {
		$_REQUEST['post_id'] = '-5';

		$this->assertSame(
			5,
			$this->list_table->get_filtered_post_id(),
			'Failed to assert a negative event parameter is made absolute.'
		);

		unset( $_REQUEST['post_id'] );
	}
}
