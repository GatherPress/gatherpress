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
use GatherPress\Core\Rsvp\Flag\Setup;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Tests\Base as Base_Unit_Test;
use PMC\Unit_Test\Utility;
use WP_Error;

/**
 * Class Test_Base.
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
		Rsvp_Setup::get_instance()->register_taxonomy();
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
	 * Coverage for add and has on an RSVP: the flag is stored, reads back, and
	 * is announced once, with its slug, even when added twice.
	 *
	 * @covers ::add
	 * @covers ::has
	 * @covers ::after_add
	 *
	 * @return void
	 */
	public function test_add_records_a_flag_and_announces_it_once(): void {
		$flag     = new Test_Base_Concrete( 'walk-in' );
		$rsvp     = $this->make_rsvp();
		$fired    = array();
		$listener = static function ( int $rsvp_id, string $slug ) use ( &$fired ): void {
			$fired[] = array( $rsvp_id, $slug );
		};

		add_action( 'gatherpress_rsvp_flag_added', $listener, 10, 2 );

		$this->assertFalse( $flag->has( $rsvp['rsvp_id'] ), 'A fresh RSVP should not carry the flag.' );
		$this->assertTrue( $flag->add( $rsvp['rsvp_id'] ), 'Adding a flag to an RSVP should succeed.' );
		$this->assertTrue(
			$flag->add( $rsvp['rsvp_id'] ),
			'Adding a flag the RSVP already carries should still report success.'
		);

		remove_action( 'gatherpress_rsvp_flag_added', $listener, 10 );

		$this->assertTrue( $flag->has( $rsvp['rsvp_id'] ), 'The RSVP should carry the flag afterwards.' );
		$this->assertSame(
			array( array( $rsvp['rsvp_id'], 'walk-in' ) ),
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

		( new Test_Base_Concrete( 'host' ) )->add( $rsvp['rsvp_id'] );
		( new Test_Base_Concrete( 'walk-in' ) )->add( $rsvp['rsvp_id'] );

		$this->assertEqualsCanonicalizing(
			array( 'host', 'walk-in' ),
			Setup::get_instance()->get_flags( $rsvp['rsvp_id'] ),
			'Adding a flag should not replace the flags already on the RSVP.'
		);
	}

	/**
	 * Coverage for the input guards on both writers: a comment that is not an
	 * RSVP, a comment ID that does not exist, and slugs sanitize_key() would
	 * change.
	 *
	 * @covers ::add
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_add_and_remove_refuse_invalid_input(): void {
		$flag       = new Test_Base_Concrete( 'host' );
		$rsvp       = $this->make_rsvp();
		$comment_id = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $rsvp['event_id'],
				'comment_approved' => '1',
			)
		);

		$this->assertFalse( $flag->add( $comment_id ), 'A plain comment should not take a flag.' );
		$this->assertFalse( $flag->remove( $comment_id ), 'A plain comment should not have a flag removed.' );
		$this->assertFalse( $flag->add( 0 ), 'A comment ID that does not exist should not take a flag.' );

		foreach ( array( '', 'Walk In', 'walk in', 'HOST', 'no-show!' ) as $slug ) {
			$invalid = new Test_Base_Concrete( $slug );

			$this->assertFalse(
				$invalid->add( $rsvp['rsvp_id'] ),
				sprintf( 'The slug %s should be refused on add.', wp_json_encode( $slug ) )
			);
			$this->assertFalse(
				$invalid->remove( $rsvp['rsvp_id'] ),
				sprintf( 'The slug %s should be refused on remove.', wp_json_encode( $slug ) )
			);
		}

		$this->assertSame(
			array(),
			Setup::get_instance()->get_flags( $rsvp['rsvp_id'] ),
			'Nothing should have been stored on the RSVP.'
		);
		$this->assertSame(
			array(),
			Setup::get_instance()->get_flags( $comment_id ),
			'Nothing should have been stored on the plain comment.'
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
		$host = new Test_Base_Concrete( 'host' );
		$rsvp = $this->make_rsvp();

		$host->add( $rsvp['rsvp_id'] );
		unregister_taxonomy( Base::TAXONOMY );

		$added   = ( new Test_Base_Concrete( 'walk-in' ) )->add( $rsvp['rsvp_id'] );
		$removed = $host->remove( $rsvp['rsvp_id'] );

		Rsvp_Setup::get_instance()->register_taxonomy();

		$this->assertFalse( $added, 'Adding a flag before the taxonomy exists should report failure.' );
		$this->assertFalse( $removed, 'Removing a flag before the taxonomy exists should report failure.' );
		$this->assertSame(
			array( 'host' ),
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
		$flag     = new Test_Base_Concrete( 'host' );
		$rsvp     = $this->make_rsvp();
		$fired    = false;
		$listener = static function () use ( &$fired ): void {
			$fired = true;
		};
		$refuse   = static function () {
			return new WP_Error( 'gatherpress_test_refused', 'Refused.' );
		};

		add_action( 'gatherpress_rsvp_flag_added', $listener );
		add_filter( 'pre_insert_term', $refuse );

		$result = $flag->add( $rsvp['rsvp_id'] );

		remove_filter( 'pre_insert_term', $refuse );
		remove_action( 'gatherpress_rsvp_flag_added', $listener );

		$this->assertFalse( $result, 'A flag that could not be stored should report failure.' );
		$this->assertFalse( $fired, 'Nothing should be announced when nothing was stored.' );
		$this->assertFalse( $flag->has( $rsvp['rsvp_id'] ), 'The RSVP should not carry the flag.' );
	}

	/**
	 * Coverage for remove: only this flag goes, and its removal is announced
	 * with the RSVP and the slug.
	 *
	 * @covers ::remove
	 * @covers ::after_remove
	 *
	 * @return void
	 */
	public function test_remove_drops_only_that_flag_and_announces_it(): void {
		$host     = new Test_Base_Concrete( 'host' );
		$walk_in  = new Test_Base_Concrete( 'walk-in' );
		$rsvp     = $this->make_rsvp();
		$fired    = array();
		$listener = static function ( int $rsvp_id, string $slug ) use ( &$fired ): void {
			$fired[] = array( $rsvp_id, $slug );
		};

		$host->add( $rsvp['rsvp_id'] );
		$walk_in->add( $rsvp['rsvp_id'] );

		add_action( 'gatherpress_rsvp_flag_removed', $listener, 10, 2 );

		$result = $walk_in->remove( $rsvp['rsvp_id'] );

		remove_action( 'gatherpress_rsvp_flag_removed', $listener, 10 );

		$this->assertTrue( $result, 'Removing a flag should succeed.' );
		$this->assertSame(
			array( 'host' ),
			Setup::get_instance()->get_flags( $rsvp['rsvp_id'] ),
			'Removing one flag should leave the others in place.'
		);
		$this->assertSame(
			array( array( $rsvp['rsvp_id'], 'walk-in' ) ),
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
		$flag     = new Test_Base_Concrete( 'host' );
		$rsvp     = $this->make_rsvp();
		$fired    = false;
		$listener = static function () use ( &$fired ): void {
			$fired = true;
		};

		add_action( 'gatherpress_rsvp_flag_removed', $listener );

		$result = $flag->remove( $rsvp['rsvp_id'] );

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

		$flag     = new Test_Base_Concrete( 'host' );
		$rsvp     = $this->make_rsvp();
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

		$flag->add( $rsvp['rsvp_id'] );

		add_action( 'gatherpress_rsvp_flag_removed', $listener );
		add_action( 'delete_term_relationships', $break );
		add_action( 'deleted_term_relationships', $restore );
		$suppressed = $wpdb->suppress_errors( true );

		$result = $flag->remove( $rsvp['rsvp_id'] );

		$wpdb->suppress_errors( $suppressed );
		$wpdb->term_relationships = $table;
		remove_action( 'delete_term_relationships', $break );
		remove_action( 'deleted_term_relationships', $restore );
		remove_action( 'gatherpress_rsvp_flag_removed', $listener );

		$this->assertFalse( $result, 'A removal that failed should report failure.' );
		$this->assertFalse( $fired, 'Nothing should be announced when nothing was removed.' );
		$this->assertTrue( $flag->has( $rsvp['rsvp_id'] ), 'The flag should still stand.' );
	}

	/**
	 * Coverage for has matching exact slugs. `is_object_in_term()` would treat
	 * a numeric string as a term ID and report a match here.
	 *
	 * @covers ::has
	 *
	 * @return void
	 */
	public function test_has_matches_exact_slugs_only(): void {
		$rsvp = $this->make_rsvp();

		( new Test_Base_Concrete( 'host' ) )->add( $rsvp['rsvp_id'] );

		$term_id = (string) get_term_by( 'slug', 'host', Base::TAXONOMY )->term_id;

		$this->assertTrue(
			is_object_in_term( $rsvp['rsvp_id'], Base::TAXONOMY, $term_id ),
			'Core should match the term ID given as a string, which is the trap has() avoids.'
		);
		$this->assertFalse(
			( new Test_Base_Concrete( $term_id ) )->has( $rsvp['rsvp_id'] ),
			'A numeric slug should not match a flag by its term ID.'
		);
	}

	/**
	 * Coverage for count, including the approved-only scoping and an invalid slug.
	 *
	 * @covers ::count
	 *
	 * @return void
	 */
	public function test_count_counts_approved_rsvps_only(): void {
		$flag     = new Test_Base_Concrete( 'host' );
		$rsvp     = $this->make_rsvp();
		$event_id = $rsvp['event_id'];

		$this->assertSame( 0, $flag->count( $event_id ), 'An event with no flags should count zero.' );

		$flag->add( $rsvp['rsvp_id'] );

		$this->assertSame( 1, $flag->count( $event_id ), 'A flagged approved RSVP should be counted.' );

		$pending_id = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '0',
				'user_id'          => 2,
			)
		);

		$flag->add( $pending_id );

		$this->assertSame(
			1,
			$flag->count( $event_id ),
			'A flagged RSVP awaiting moderation should not be counted.'
		);
		$this->assertSame(
			0,
			( new Test_Base_Concrete( 'HOST' ) )->count( $event_id ),
			'An invalid slug should count zero.'
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
		$flag = new Test_Base_Concrete( 'host' );
		$rsvp = $this->make_rsvp();

		$this->assertTrue(
			Utility::invoke_hidden_method( $flag, 'can_write', array( $rsvp['rsvp_id'] ) ),
			'A valid slug on an RSVP with the taxonomy registered should be writable.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( new Test_Base_Concrete( 'HOST' ), 'can_write', array( $rsvp['rsvp_id'] ) ),
			'An invalid slug should not be writable.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $flag, 'can_write', array( 0 ) ),
			'A comment ID that does not exist should not be writable.'
		);

		unregister_taxonomy( Base::TAXONOMY );

		$before_init = Utility::invoke_hidden_method( $flag, 'can_write', array( $rsvp['rsvp_id'] ) );

		Rsvp_Setup::get_instance()->register_taxonomy();

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
		$flag = new Test_Base_Concrete();

		foreach ( array( 'checked-in', 'first_timer', 'walk-in', 'host2' ) as $slug ) {
			$this->assertTrue(
				Utility::invoke_hidden_method( $flag, 'is_valid_slug', array( $slug ) ),
				sprintf( 'The slug %s should be accepted.', $slug )
			);
		}

		foreach ( array( '', 'Walk In', 'HOST', 'no-show!' ) as $slug ) {
			$this->assertFalse(
				Utility::invoke_hidden_method( $flag, 'is_valid_slug', array( $slug ) ),
				sprintf( 'The slug %s should be refused.', wp_json_encode( $slug ) )
			);
		}
	}
}
