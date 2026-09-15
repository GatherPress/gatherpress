<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Flag\Base.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Rsvp\Flag;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Flag\Base;
use GatherPress\Core\Rsvp\Flag\Check_In;
use GatherPress\Core\Rsvp\Flag\Setup;
use GatherPress\Tests\Base as Base_Unit_Test;
use PMC\Unit_Test\Utility;
use WP_Error;

/**
 * Class Test_Base.
 *
 * Check_In stands in as a second flag wherever a test needs two.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Flag\Base
 */
class Test_Base extends Base_Unit_Test {

	/**
	 * Set up the test environment before each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Setup::get_instance()->register_taxonomy();
	}

	/**
	 * Create an event and an RSVP against it.
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
	 * Coverage for __construct: the ID is kept only when it belongs to an RSVP.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_keeps_the_id_of_an_rsvp_only(): void {
		$rsvp       = $this->make_rsvp();
		$comment_id = (int) wp_insert_comment( array( 'comment_post_ID' => $rsvp['event_id'] ) );

		$this->assertSame(
			$rsvp['rsvp_id'],
			Utility::get_hidden_property( new Test_Base_Concrete( $rsvp['rsvp_id'] ), 'rsvp_id' ),
			'An RSVP ID should be kept.'
		);
		$this->assertSame(
			0,
			Utility::get_hidden_property( new Test_Base_Concrete( $comment_id ), 'rsvp_id' ),
			'A comment that is not an RSVP should not be kept.'
		);
		$this->assertSame(
			0,
			Utility::get_hidden_property( new Test_Base_Concrete( 0 ), 'rsvp_id' ),
			'A comment ID that does not exist should not be kept.'
		);
	}

	/**
	 * Coverage for add and has on an RSVP: the flag is stored, reads back, and
	 * is announced once, with its slug, even when added twice.
	 *
	 * @covers ::add
	 * @covers ::has
	 *
	 * @return void
	 */
	public function test_add_records_a_flag_and_announces_it_once(): void {
		$rsvp     = $this->make_rsvp();
		$flag     = new Test_Base_Concrete( $rsvp['rsvp_id'] );
		$fired    = array();
		$listener = static function ( int $rsvp_id, string $slug ) use ( &$fired ): void {
			$fired[] = array( $rsvp_id, $slug );
		};

		add_action( 'gatherpress_rsvp_flag_added', $listener, 10, 2 );

		$this->assertFalse( $flag->has(), 'A fresh RSVP should not carry the flag.' );
		$this->assertTrue( $flag->add(), 'Adding a flag to an RSVP should succeed.' );
		$this->assertTrue( $flag->add(), 'Adding a flag the RSVP already carries should still report success.' );

		remove_action( 'gatherpress_rsvp_flag_added', $listener, 10 );

		$this->assertTrue( $flag->has(), 'The RSVP should carry the flag afterwards.' );
		$this->assertSame(
			array( array( $rsvp['rsvp_id'], Test_Base_Concrete::SLUG ) ),
			$fired,
			'The flag should be announced once, with the RSVP and the slug.'
		);
	}

	/**
	 * Coverage for add appending: a second flag leaves the first in place.
	 *
	 * @covers ::add
	 *
	 * @return void
	 */
	public function test_add_keeps_every_other_flag(): void {
		$rsvp = $this->make_rsvp();

		( new Test_Base_Concrete( $rsvp['rsvp_id'] ) )->add();
		( new Check_In( $rsvp['rsvp_id'] ) )->add();

		$this->assertEqualsCanonicalizing(
			array( Test_Base_Concrete::SLUG, Check_In::SLUG ),
			Setup::get_instance()->get_flags( $rsvp['rsvp_id'] ),
			'Adding a flag should not replace the flags already on the RSVP.'
		);
	}

	/**
	 * Coverage for a flag built from a comment that is not an RSVP, or an ID
	 * that does not exist: both writers refuse and has() reports false.
	 *
	 * @covers ::add
	 * @covers ::remove
	 * @covers ::has
	 *
	 * @return void
	 */
	public function test_flags_refuse_comments_that_are_not_rsvps(): void {
		$rsvp       = $this->make_rsvp();
		$comment_id = (int) wp_insert_comment( array( 'comment_post_ID' => $rsvp['event_id'] ) );

		foreach ( array( $comment_id, 0 ) as $id ) {
			$flag = new Test_Base_Concrete( $id );

			$this->assertFalse( $flag->add(), sprintf( 'Comment %d should not take a flag.', $id ) );
			$this->assertFalse( $flag->remove(), sprintf( 'Comment %d should not have a flag removed.', $id ) );
			$this->assertFalse( $flag->has(), sprintf( 'Comment %d should never carry a flag.', $id ) );
		}

		$this->assertSame(
			array(),
			Setup::get_instance()->get_flags( $comment_id ),
			'Nothing should have been stored on the plain comment.'
		);
	}

	/**
	 * Coverage for a subclass that never declares a slug: both writers refuse
	 * and nothing is stored as an empty term.
	 *
	 * @covers ::add
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_flags_refuse_a_class_without_a_slug(): void {
		$rsvp = $this->make_rsvp();
		$flag = new Test_Base_Without_Slug( $rsvp['rsvp_id'] );

		$this->assertFalse( $flag->add(), 'A flag without a slug should not be added.' );
		$this->assertFalse( $flag->remove(), 'A flag without a slug should not be removed.' );
		$this->assertSame(
			array(),
			Setup::get_instance()->get_flags( $rsvp['rsvp_id'] ),
			'Nothing should have been stored on the RSVP.'
		);
	}

	/**
	 * Coverage for both writers before the taxonomy is registered: they refuse
	 * rather than act on reads that come back empty, so remove() does not report
	 * a stored flag as gone.
	 *
	 * @covers ::add
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_add_and_remove_refuse_before_the_taxonomy_exists(): void {
		$rsvp = $this->make_rsvp();
		$flag = new Test_Base_Concrete( $rsvp['rsvp_id'] );

		$flag->add();
		unregister_taxonomy( Base::TAXONOMY );

		$added   = ( new Check_In( $rsvp['rsvp_id'] ) )->add();
		$removed = $flag->remove();

		Setup::get_instance()->register_taxonomy();

		$this->assertFalse( $added, 'Adding a flag before the taxonomy exists should report failure.' );
		$this->assertFalse( $removed, 'Removing a flag before the taxonomy exists should report failure.' );
		$this->assertSame(
			array( Test_Base_Concrete::SLUG ),
			Setup::get_instance()->get_flags( $rsvp['rsvp_id'] ),
			'The stored flag should still be there once the taxonomy is back.'
		);
	}

	/**
	 * Coverage for the term write failing with the taxonomy registered, here
	 * because inserting the new term is refused: the call reports failure and
	 * announces nothing.
	 *
	 * @covers ::add
	 *
	 * @return void
	 */
	public function test_add_fails_when_the_flag_cannot_be_stored(): void {
		$rsvp     = $this->make_rsvp();
		$flag     = new Test_Base_Concrete( $rsvp['rsvp_id'] );
		$fired    = false;
		$listener = static function () use ( &$fired ): void {
			$fired = true;
		};
		$refuse   = static function () {
			return new WP_Error( 'gatherpress_test_refused', 'Refused.' );
		};

		add_action( 'gatherpress_rsvp_flag_added', $listener );
		add_filter( 'pre_insert_term', $refuse );

		$result = $flag->add();

		remove_filter( 'pre_insert_term', $refuse );
		remove_action( 'gatherpress_rsvp_flag_added', $listener );

		$this->assertFalse( $result, 'A flag that could not be stored should report failure.' );
		$this->assertFalse( $fired, 'Nothing should be announced when nothing was stored.' );
		$this->assertFalse( $flag->has(), 'The RSVP should not carry the flag.' );
	}

	/**
	 * Coverage for remove: only this flag goes, and its removal is announced
	 * with the RSVP and the slug.
	 *
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_remove_drops_only_that_flag_and_announces_it(): void {
		$rsvp     = $this->make_rsvp();
		$flag     = new Test_Base_Concrete( $rsvp['rsvp_id'] );
		$fired    = array();
		$listener = static function ( int $rsvp_id, string $slug ) use ( &$fired ): void {
			$fired[] = array( $rsvp_id, $slug );
		};

		$flag->add();
		( new Check_In( $rsvp['rsvp_id'] ) )->add();

		add_action( 'gatherpress_rsvp_flag_removed', $listener, 10, 2 );

		$result = $flag->remove();

		remove_action( 'gatherpress_rsvp_flag_removed', $listener, 10 );

		$this->assertTrue( $result, 'Removing a flag should succeed.' );
		$this->assertSame(
			array( Check_In::SLUG ),
			Setup::get_instance()->get_flags( $rsvp['rsvp_id'] ),
			'Removing one flag should leave the others in place.'
		);
		$this->assertSame(
			array( array( $rsvp['rsvp_id'], Test_Base_Concrete::SLUG ) ),
			$fired,
			'The removal should be announced with the RSVP and the slug.'
		);
	}

	/**
	 * Coverage for remove on a flag the RSVP does not carry: the state already
	 * holds, so it succeeds and announces nothing.
	 *
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_remove_is_idempotent_when_the_flag_is_absent(): void {
		$rsvp     = $this->make_rsvp();
		$fired    = false;
		$listener = static function () use ( &$fired ): void {
			$fired = true;
		};

		add_action( 'gatherpress_rsvp_flag_removed', $listener );

		$result = ( new Test_Base_Concrete( $rsvp['rsvp_id'] ) )->remove();

		remove_action( 'gatherpress_rsvp_flag_removed', $listener );

		$this->assertTrue( $result, 'Removing a flag that is not there should count as done.' );
		$this->assertFalse( $fired, 'Nothing should be announced when nothing was removed.' );
	}

	/**
	 * Coverage for the term removal failing: the flag stands, the call reports
	 * failure, and nothing is announced. The DELETE is pointed at a table that
	 * does not exist for that one statement, which core reports as a failed
	 * removal.
	 *
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_remove_fails_when_the_flag_cannot_be_removed(): void {
		global $wpdb;

		$rsvp     = $this->make_rsvp();
		$flag     = new Test_Base_Concrete( $rsvp['rsvp_id'] );
		$fired    = false;
		$table    = $wpdb->term_relationships;
		$listener = static function () use ( &$fired ): void {
			$fired = true;
		};
		$break    = static function () use ( $wpdb ): void {
			$wpdb->term_relationships = 'gatherpress_missing_table';
		};
		$restore  = static function () use ( $wpdb, $table ): void {
			$wpdb->term_relationships = $table;
		};

		$flag->add();

		add_action( 'gatherpress_rsvp_flag_removed', $listener );
		add_action( 'delete_term_relationships', $break );
		add_action( 'deleted_term_relationships', $restore );
		$suppressed = $wpdb->suppress_errors( true );

		$result = $flag->remove();

		$wpdb->suppress_errors( $suppressed );
		$wpdb->term_relationships = $table;
		remove_action( 'delete_term_relationships', $break );
		remove_action( 'deleted_term_relationships', $restore );
		remove_action( 'gatherpress_rsvp_flag_removed', $listener );

		$this->assertFalse( $result, 'A removal that failed should report failure.' );
		$this->assertFalse( $fired, 'Nothing should be announced when nothing was removed.' );
		$this->assertTrue( $flag->has(), 'The flag should still stand.' );
	}

	/**
	 * Coverage for has matching slugs, not term names. A term named like the
	 * flag but carrying another slug makes `is_object_in_term()` report a
	 * match; has() must not.
	 *
	 * @covers ::has
	 *
	 * @return void
	 */
	public function test_has_matches_slugs_not_term_names(): void {
		$rsvp = $this->make_rsvp();
		$term = wp_insert_term( Test_Base_Concrete::SLUG, Base::TAXONOMY, array( 'slug' => 'another-flag' ) );

		wp_set_object_terms( $rsvp['rsvp_id'], (int) $term['term_id'], Base::TAXONOMY );

		$this->assertTrue(
			is_object_in_term( $rsvp['rsvp_id'], Base::TAXONOMY, Test_Base_Concrete::SLUG ),
			'Core should match the term by its name, which is the trap has() avoids.'
		);
		$this->assertFalse(
			( new Test_Base_Concrete( $rsvp['rsvp_id'] ) )->has(),
			'A term that only shares the flag slug as its name should not count.'
		);
	}

	/**
	 * Coverage for count, including the approved-only scoping and a class
	 * without a slug.
	 *
	 * @covers ::count
	 *
	 * @return void
	 */
	public function test_count_counts_approved_rsvps_only(): void {
		$rsvp     = $this->make_rsvp();
		$event_id = $rsvp['event_id'];

		$this->assertSame( 0, Test_Base_Concrete::count( $event_id ), 'An event with no flags should count zero.' );

		( new Test_Base_Concrete( $rsvp['rsvp_id'] ) )->add();

		$this->assertSame( 1, Test_Base_Concrete::count( $event_id ), 'A flagged approved RSVP should be counted.' );

		$pending_id = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '0',
				'user_id'          => 2,
			)
		);

		( new Test_Base_Concrete( $pending_id ) )->add();

		$this->assertSame(
			1,
			Test_Base_Concrete::count( $event_id ),
			'A flagged RSVP awaiting moderation should not be counted.'
		);
		$this->assertSame(
			0,
			Test_Base_Without_Slug::count( $event_id ),
			'A flag without a slug should count zero.'
		);
	}

	/**
	 * Coverage for can_write on each condition.
	 *
	 * @covers ::can_write
	 *
	 * @return void
	 */
	public function test_can_write(): void {
		$rsvp = $this->make_rsvp();
		$flag = new Test_Base_Concrete( $rsvp['rsvp_id'] );

		$this->assertTrue(
			Utility::invoke_hidden_method( $flag, 'can_write' ),
			'A valid flag on an RSVP with the taxonomy registered should be writable.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( new Test_Base_Concrete( 0 ), 'can_write' ),
			'A flag on something that is not an RSVP should not be writable.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( new Test_Base_Without_Slug( $rsvp['rsvp_id'] ), 'can_write' ),
			'A flag without a slug should not be writable.'
		);

		unregister_taxonomy( Base::TAXONOMY );

		$before_init = Utility::invoke_hidden_method( $flag, 'can_write' );

		Setup::get_instance()->register_taxonomy();

		$this->assertFalse( $before_init, 'Nothing should be writable before the taxonomy is registered.' );
	}

	/**
	 * Coverage for is_valid_slug on each branch.
	 *
	 * @covers ::is_valid_slug
	 *
	 * @return void
	 */
	public function test_is_valid_slug(): void {
		foreach ( array( 'checked-in', 'first_timer', 'walk-in', 'host2' ) as $slug ) {
			$this->assertTrue(
				Utility::invoke_hidden_static_method( Test_Base_Concrete::class, 'is_valid_slug', array( $slug ) ),
				sprintf( 'The slug %s should be accepted.', $slug )
			);
		}

		foreach ( array( '', 'Walk In', 'HOST', 'no-show!' ) as $slug ) {
			$this->assertFalse(
				Utility::invoke_hidden_static_method( Test_Base_Concrete::class, 'is_valid_slug', array( $slug ) ),
				sprintf( 'The slug %s should be refused.', wp_json_encode( $slug ) )
			);
		}
	}
}
