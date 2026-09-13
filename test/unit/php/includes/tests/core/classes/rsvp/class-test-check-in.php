<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Check_In.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Check_In;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Check_In.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Check_In
 */
class Test_Check_In extends Base {

	/**
	 * Set up the test environment before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Setup::get_instance()->register_taxonomy();
	}

	/**
	 * Create an event and an approved RSVP against it.
	 *
	 * @param string $status Comment approval status: 1, 0, or spam.
	 *
	 * @return array{event_id:int, rsvp_id:int} The event and RSVP IDs.
	 */
	private function make_rsvp( string $status = '1' ): array {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$rsvp_id  = wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => $status,
				'user_id'          => 1,
			)
		);

		return array(
			'event_id' => $event_id,
			'rsvp_id'  => (int) $rsvp_id,
		);
	}

	/**
	 * Coverage for __construct.
	 *
	 * The instance is built during plugin bootstrap, so the constructor only
	 * runs inside a test once the stored instance is cleared.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_builds_the_instance(): void {
		Utility::set_and_get_hidden_static_property( Check_In::class, 'instance', null );

		$this->assertInstanceOf(
			Check_In::class,
			Check_In::get_instance(),
			'Failed to assert that the constructor returns a Check_In instance.'
		);
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Check_In::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'deleted_comment',
				'priority' => 10,
				'callback' => array( $instance, 'delete_check_in' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for check_in and is_checked_in on an RSVP.
	 *
	 * @covers ::check_in
	 * @covers ::is_checked_in
	 *
	 * @return void
	 */
	public function test_check_in_records_an_arrival(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();

		$this->assertFalse(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'A fresh RSVP should not be checked in.'
		);
		$this->assertFalse(
			true === is_object_in_term( $rsvp['rsvp_id'], Check_In::TAXONOMY, Check_In::TERM ),
			'A fresh RSVP should not have the check-in taxonomy term.'
		);
		$this->assertTrue(
			$instance->check_in( $rsvp['rsvp_id'] ),
			'Checking in an RSVP should succeed.'
		);
		$this->assertTrue(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'The RSVP should read as checked in afterwards.'
		);
		$this->assertTrue(
			true === is_object_in_term( $rsvp['rsvp_id'], Check_In::TAXONOMY, Check_In::TERM ),
			'The RSVP should have the check-in taxonomy term.'
		);
	}

	/**
	 * Coverage for check_in on an already checked-in RSVP: repeated check-in
	 * is idempotent and continues reporting success.
	 *
	 * @covers ::check_in
	 *
	 * @return void
	 */
	public function test_check_in_is_idempotent_on_already_checked_in_rsvp(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();

		$this->assertTrue(
			$instance->check_in( $rsvp['rsvp_id'] ),
			'First check-in should succeed.'
		);
		$this->assertTrue(
			$instance->check_in( $rsvp['rsvp_id'] ),
			'Repeated check-in should report success.'
		);
		$this->assertTrue(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'The RSVP should remain checked in.'
		);
	}

	/**
	 * Coverage for appending flag terms: check_in should append the term
	 * without clobbering other terms assigned in the flag taxonomy.
	 *
	 * @covers ::check_in
	 *
	 * @return void
	 */
	public function test_check_in_appends_flag_term_without_clobbering_existing_flags(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();

		wp_set_object_terms( $rsvp['rsvp_id'], 'host', Check_In::TAXONOMY );

		$this->assertTrue(
			true === is_object_in_term( $rsvp['rsvp_id'], Check_In::TAXONOMY, 'host' ),
			'The RSVP should have the initial host flag.'
		);

		$instance->check_in( $rsvp['rsvp_id'] );

		$this->assertTrue(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'The RSVP should now be checked in.'
		);
		$this->assertTrue(
			true === is_object_in_term( $rsvp['rsvp_id'], Check_In::TAXONOMY, 'host' ),
			'The existing host flag should not be clobbered.'
		);
	}

	/**
	 * Coverage for uncheck_in.
	 *
	 * @covers ::uncheck_in
	 *
	 * @return void
	 */
	public function test_uncheck_in_removes_a_check_in(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->check_in( $rsvp['rsvp_id'] );

		$this->assertTrue(
			$instance->uncheck_in( $rsvp['rsvp_id'] ),
			'Removing a check-in should succeed.'
		);
		$this->assertFalse(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'The RSVP should no longer read as checked in.'
		);
		$this->assertFalse(
			true === is_object_in_term( $rsvp['rsvp_id'], Check_In::TAXONOMY, Check_In::TERM ),
			'The check-in taxonomy term should be removed.'
		);
	}

	/**
	 * Coverage for the is_comment_type guard on both writers: a comment that is not an
	 * RSVP, and a comment ID that does not exist.
	 *
	 * @covers ::check_in
	 * @covers ::uncheck_in
	 *
	 * @return void
	 */
	public function test_check_in_refuses_comments_that_are_not_rsvps(): void {
		$instance   = Check_In::get_instance();
		$post_id    = $this->mock->post()->get()->ID;
		$comment_id = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $post_id,
				'comment_approved' => '1',
			)
		);

		$this->assertFalse(
			$instance->check_in( $comment_id ),
			'A plain comment should not be checkable.'
		);
		$this->assertFalse(
			$instance->uncheck_in( $comment_id ),
			'A plain comment should not be uncheckable.'
		);
		$this->assertFalse(
			$instance->check_in( 0 ),
			'A comment ID that does not exist should not be checkable.'
		);
		$this->assertFalse(
			$instance->is_checked_in( $comment_id ),
			'A plain comment should never read as checked in.'
		);
	}

	/**
	 * Coverage for count_checked_in, including the approved-only scoping.
	 *
	 * @covers ::count_checked_in
	 *
	 * @return void
	 */
	public function test_count_checked_in_counts_approved_rsvps_only(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();
		$event_id = $rsvp['event_id'];

		$this->assertSame(
			0,
			$instance->count_checked_in( $event_id ),
			'An event with no check-ins should count zero.'
		);

		$instance->check_in( $rsvp['rsvp_id'] );

		$this->assertSame(
			1,
			$instance->count_checked_in( $event_id ),
			'A checked-in approved RSVP should be counted.'
		);

		$pending_id = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '0',
				'user_id'          => 2,
			)
		);

		$instance->check_in( $pending_id );

		$this->assertSame(
			1,
			$instance->count_checked_in( $event_id ),
			'A checked-in RSVP awaiting moderation should not be counted.'
		);
	}

	/**
	 * Coverage for delete_check_in, the orphan sweep on comment deletion.
	 *
	 * @covers ::delete_check_in
	 *
	 * @return void
	 */
	public function test_delete_check_in_removes_the_term(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->check_in( $rsvp['rsvp_id'] );
		$instance->delete_check_in( $rsvp['rsvp_id'] );

		$this->assertFalse(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'Deleting an RSVP should remove its check-in status.'
		);
		$this->assertFalse(
			true === is_object_in_term( $rsvp['rsvp_id'], Check_In::TAXONOMY, Check_In::TERM ),
			'Deleting an RSVP should remove its check-in term.'
		);
	}

	/**
	 * Coverage for the check-in actions, which report the RSVP to consumers.
	 *
	 * @covers ::check_in
	 * @covers ::uncheck_in
	 *
	 * @return void
	 */
	public function test_check_in_fires_actions(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();
		$fired    = array();

		add_action(
			'gatherpress_rsvp_checked_in',
			static function ( int $rsvp_id ) use ( &$fired ): void {
				$fired['checked_in'] = $rsvp_id;
			}
		);
		add_action(
			'gatherpress_rsvp_unchecked_in',
			static function ( int $rsvp_id ) use ( &$fired ): void {
				$fired['unchecked_in'] = $rsvp_id;
			}
		);

		$instance->check_in( $rsvp['rsvp_id'] );
		$instance->uncheck_in( $rsvp['rsvp_id'] );

		remove_all_actions( 'gatherpress_rsvp_checked_in' );
		remove_all_actions( 'gatherpress_rsvp_unchecked_in' );

		$this->assertSame(
			$rsvp['rsvp_id'],
			$fired['checked_in'],
			'The check-in action should report the RSVP.'
		);
		$this->assertSame(
			$rsvp['rsvp_id'],
			$fired['unchecked_in'],
			'The unchecked-in action should report the RSVP.'
		);
	}

	/**
	 * Coverage for the term write failing, here because the taxonomy is not
	 * registered: the call reports failure and announces nothing.
	 *
	 * @covers ::check_in
	 *
	 * @return void
	 */
	public function test_check_in_fails_when_the_term_cannot_be_assigned(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();
		$fired    = false;

		add_action(
			'gatherpress_rsvp_checked_in',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);
		unregister_taxonomy( Check_In::TAXONOMY );

		$result = $instance->check_in( $rsvp['rsvp_id'] );

		Setup::get_instance()->register_taxonomy();
		remove_all_actions( 'gatherpress_rsvp_checked_in' );

		$this->assertFalse(
			$result,
			'A check-in whose term could not be assigned should report failure.'
		);
		$this->assertFalse(
			$fired,
			'Nothing should be announced when nothing was recorded.'
		);
		$this->assertFalse(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'The RSVP should not read as checked in.'
		);
	}

	/**
	 * Coverage for uncheck_in on an RSVP that is not checked in: the state
	 * already holds, so it succeeds and announces nothing.
	 *
	 * @covers ::uncheck_in
	 *
	 * @return void
	 */
	public function test_uncheck_in_is_idempotent_when_not_checked_in(): void {
		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();
		$fired    = false;

		add_action(
			'gatherpress_rsvp_unchecked_in',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$result = $instance->uncheck_in( $rsvp['rsvp_id'] );

		remove_all_actions( 'gatherpress_rsvp_unchecked_in' );

		$this->assertTrue(
			$result,
			'An RSVP that is already not checked in should count as done.'
		);
		$this->assertFalse(
			$fired,
			'Nothing should be announced when nothing was removed.'
		);
	}

	/**
	 * Coverage for the term removal failing: the check-in stands, the call
	 * reports failure, and nothing is announced. The DELETE is pointed at a
	 * table that does not exist for that one statement, which core reports
	 * as a failed removal.
	 *
	 * @covers ::uncheck_in
	 *
	 * @return void
	 */
	public function test_uncheck_in_fails_when_the_term_cannot_be_removed(): void {
		global $wpdb;

		$instance = Check_In::get_instance();
		$rsvp     = $this->make_rsvp();
		$fired    = false;
		$table    = $wpdb->term_relationships;

		$instance->check_in( $rsvp['rsvp_id'] );

		add_action(
			'gatherpress_rsvp_unchecked_in',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$break   = static function () use ( $wpdb ): void {
			$wpdb->term_relationships = 'gatherpress_missing_table';
		};
		$restore = static function () use ( $wpdb, $table ): void {
			$wpdb->term_relationships = $table;
		};

		add_action( 'delete_term_relationships', $break );
		add_action( 'deleted_term_relationships', $restore );
		$suppressed = $wpdb->suppress_errors( true );

		$result = $instance->uncheck_in( $rsvp['rsvp_id'] );

		$wpdb->suppress_errors( $suppressed );
		$wpdb->term_relationships = $table;
		remove_action( 'delete_term_relationships', $break );
		remove_action( 'deleted_term_relationships', $restore );
		remove_all_actions( 'gatherpress_rsvp_unchecked_in' );

		$this->assertFalse(
			$result,
			'A removal that failed should report failure.'
		);
		$this->assertFalse(
			$fired,
			'Nothing should be announced when nothing was removed.'
		);
		$this->assertTrue(
			$instance->is_checked_in( $rsvp['rsvp_id'] ),
			'The check-in should still stand.'
		);
	}
}
