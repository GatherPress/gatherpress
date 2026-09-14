<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Flag.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Flag;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Error;

/**
 * Class Test_Flag.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Flag
 */
class Test_Flag extends Base {

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
	 * Coverage for __construct.
	 *
	 * The instance is built during plugin bootstrap, so the constructor only
	 * runs inside a test once the stored instance is cleared. The bootstrap
	 * instance is put back afterwards: its hooks are the registered ones, and
	 * the hooks the fresh instance adds are dropped when the test ends.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_construct_builds_the_instance(): void {
		$bootstrap = Flag::get_instance();

		Utility::set_and_get_hidden_static_property( Flag::class, 'instance', null );

		$built = Flag::get_instance();

		Utility::set_and_get_hidden_static_property( Flag::class, 'instance', $bootstrap );

		$this->assertInstanceOf(
			Flag::class,
			$built,
			'Failed to assert that the constructor returns a Flag instance.'
		);
		$this->assertNotSame(
			$bootstrap,
			$built,
			'Failed to assert that the constructor ran rather than returning the stored instance.'
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
		$instance = Flag::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'deleted_comment',
				'priority' => 10,
				'callback' => array( $instance, 'delete_flags' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for add, has, and get on an RSVP: the flag is stored, reads
	 * back, and is announced once even when added twice.
	 *
	 * @covers ::add
	 * @covers ::has
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_add_records_a_flag_and_announces_it_once(): void {
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();
		$fired    = array();
		$listener = static function ( int $rsvp_id, string $flag ) use ( &$fired ): void {
			$fired[] = array( $rsvp_id, $flag );
		};

		add_action( 'gatherpress_rsvp_flag_added', $listener, 10, 2 );

		$this->assertFalse(
			$instance->has( $rsvp['rsvp_id'], 'walk-in' ),
			'A fresh RSVP should not carry the flag.'
		);
		$this->assertTrue(
			$instance->add( $rsvp['rsvp_id'], 'walk-in' ),
			'Adding a flag to an RSVP should succeed.'
		);
		$this->assertTrue(
			$instance->add( $rsvp['rsvp_id'], 'walk-in' ),
			'Adding a flag the RSVP already carries should still report success.'
		);

		remove_action( 'gatherpress_rsvp_flag_added', $listener, 10 );

		$this->assertTrue(
			$instance->has( $rsvp['rsvp_id'], 'walk-in' ),
			'The RSVP should carry the flag afterwards.'
		);
		$this->assertSame(
			array( 'walk-in' ),
			$instance->get( $rsvp['rsvp_id'] ),
			'The flag should read back exactly as it was added.'
		);
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
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_add_keeps_every_other_flag(): void {
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->add( $rsvp['rsvp_id'], 'host' );
		$instance->add( $rsvp['rsvp_id'], 'checked-in' );

		$this->assertEqualsCanonicalizing(
			array( 'host', 'checked-in' ),
			$instance->get( $rsvp['rsvp_id'] ),
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
		$instance   = Flag::get_instance();
		$rsvp       = $this->make_rsvp();
		$comment_id = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $rsvp['event_id'],
				'comment_approved' => '1',
			)
		);

		$this->assertFalse( $instance->add( $comment_id, 'host' ), 'A plain comment should not take a flag.' );
		$this->assertFalse(
			$instance->remove( $comment_id, 'host' ),
			'A plain comment should not have a flag removed.'
		);
		$this->assertFalse( $instance->add( 0, 'host' ), 'A comment ID that does not exist should not take a flag.' );

		foreach ( array( '', 'Walk In', 'walk in', 'HOST', 'no-show!' ) as $slug ) {
			$this->assertFalse(
				$instance->add( $rsvp['rsvp_id'], $slug ),
				sprintf( 'The slug %s should be refused on add.', wp_json_encode( $slug ) )
			);
			$this->assertFalse(
				$instance->remove( $rsvp['rsvp_id'], $slug ),
				sprintf( 'The slug %s should be refused on remove.', wp_json_encode( $slug ) )
			);
		}

		$this->assertSame( array(), $instance->get( $rsvp['rsvp_id'] ), 'Nothing should have been stored.' );
		$this->assertSame(
			array(),
			$instance->get( $comment_id ),
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
	 * @covers ::can_write
	 *
	 * @return void
	 */
	public function test_add_and_remove_refuse_before_the_taxonomy_exists(): void {
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->add( $rsvp['rsvp_id'], 'host' );
		unregister_taxonomy( Flag::TAXONOMY );

		$added   = $instance->add( $rsvp['rsvp_id'], 'walk-in' );
		$removed = $instance->remove( $rsvp['rsvp_id'], 'host' );

		Setup::get_instance()->register_taxonomy();

		$this->assertFalse( $added, 'Adding a flag before the taxonomy exists should report failure.' );
		$this->assertFalse( $removed, 'Removing a flag before the taxonomy exists should report failure.' );
		$this->assertSame(
			array( 'host' ),
			$instance->get( $rsvp['rsvp_id'] ),
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
		$instance = Flag::get_instance();
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

		$result = $instance->add( $rsvp['rsvp_id'], 'host' );

		remove_filter( 'pre_insert_term', $refuse );
		remove_action( 'gatherpress_rsvp_flag_added', $listener );

		$this->assertFalse( $result, 'A flag that could not be stored should report failure.' );
		$this->assertFalse( $fired, 'Nothing should be announced when nothing was stored.' );
		$this->assertFalse( $instance->has( $rsvp['rsvp_id'], 'host' ), 'The RSVP should not carry the flag.' );
	}

	/**
	 * Coverage for remove: only the named flag goes, and its removal is
	 * announced with the RSVP and the slug.
	 *
	 * @covers ::remove
	 *
	 * @return void
	 */
	public function test_remove_drops_only_that_flag_and_announces_it(): void {
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();
		$fired    = array();
		$listener = static function ( int $rsvp_id, string $flag ) use ( &$fired ): void {
			$fired[] = array( $rsvp_id, $flag );
		};

		$instance->add( $rsvp['rsvp_id'], 'host' );
		$instance->add( $rsvp['rsvp_id'], 'checked-in' );

		add_action( 'gatherpress_rsvp_flag_removed', $listener, 10, 2 );

		$result = $instance->remove( $rsvp['rsvp_id'], 'checked-in' );

		remove_action( 'gatherpress_rsvp_flag_removed', $listener, 10 );

		$this->assertTrue( $result, 'Removing a flag should succeed.' );
		$this->assertSame(
			array( 'host' ),
			$instance->get( $rsvp['rsvp_id'] ),
			'Removing one flag should leave the others in place.'
		);
		$this->assertSame(
			array( array( $rsvp['rsvp_id'], 'checked-in' ) ),
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
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();
		$fired    = false;
		$listener = static function () use ( &$fired ): void {
			$fired = true;
		};

		add_action( 'gatherpress_rsvp_flag_removed', $listener );

		$result = $instance->remove( $rsvp['rsvp_id'], 'host' );

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

		$instance = Flag::get_instance();
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

		$instance->add( $rsvp['rsvp_id'], 'host' );

		add_action( 'gatherpress_rsvp_flag_removed', $listener );
		add_action( 'delete_term_relationships', $break );
		add_action( 'deleted_term_relationships', $restore );
		$suppressed = $wpdb->suppress_errors( true );

		$result = $instance->remove( $rsvp['rsvp_id'], 'host' );

		$wpdb->suppress_errors( $suppressed );
		$wpdb->term_relationships = $table;
		remove_action( 'delete_term_relationships', $break );
		remove_action( 'deleted_term_relationships', $restore );
		remove_action( 'gatherpress_rsvp_flag_removed', $listener );

		$this->assertFalse( $result, 'A removal that failed should report failure.' );
		$this->assertFalse( $fired, 'Nothing should be announced when nothing was removed.' );
		$this->assertTrue( $instance->has( $rsvp['rsvp_id'], 'host' ), 'The flag should still stand.' );
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
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->add( $rsvp['rsvp_id'], 'host' );

		$term_id = (string) get_term_by( 'slug', 'host', Flag::TAXONOMY )->term_id;

		$this->assertTrue(
			is_object_in_term( $rsvp['rsvp_id'], Flag::TAXONOMY, $term_id ),
			'Core should match the term ID given as a string, which is the trap has() avoids.'
		);
		$this->assertFalse(
			$instance->has( $rsvp['rsvp_id'], $term_id ),
			'A numeric string should not match a flag by its term ID.'
		);
	}

	/**
	 * Coverage for get returning an empty list when the taxonomy cannot be
	 * read, where core returns a WP_Error.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_returns_empty_when_the_taxonomy_cannot_be_read(): void {
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->add( $rsvp['rsvp_id'], 'host' );
		unregister_taxonomy( Flag::TAXONOMY );

		$flags = $instance->get( $rsvp['rsvp_id'] );

		Setup::get_instance()->register_taxonomy();

		$this->assertSame( array(), $flags, 'An unreadable taxonomy should read as no flags.' );
	}

	/**
	 * Coverage for count, including the approved-only scoping and an invalid slug.
	 *
	 * @covers ::count
	 *
	 * @return void
	 */
	public function test_count_counts_approved_rsvps_only(): void {
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();
		$event_id = $rsvp['event_id'];

		$this->assertSame( 0, $instance->count( $event_id, 'host' ), 'An event with no flags should count zero.' );

		$instance->add( $rsvp['rsvp_id'], 'host' );

		$this->assertSame( 1, $instance->count( $event_id, 'host' ), 'A flagged approved RSVP should be counted.' );

		$pending_id = (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '0',
				'user_id'          => 2,
			)
		);

		$instance->add( $pending_id, 'host' );

		$this->assertSame(
			1,
			$instance->count( $event_id, 'host' ),
			'A flagged RSVP awaiting moderation should not be counted.'
		);
		$this->assertSame( 0, $instance->count( $event_id, 'HOST' ), 'An invalid slug should count zero.' );
	}

	/**
	 * Coverage for delete_flags: deleting an RSVP sweeps every flag on it.
	 *
	 * @covers ::delete_flags
	 *
	 * @return void
	 */
	public function test_delete_flags_sweeps_every_flag_from_a_deleted_rsvp(): void {
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();

		$instance->add( $rsvp['rsvp_id'], 'host' );
		$instance->add( $rsvp['rsvp_id'], 'checked-in' );

		wp_delete_comment( $rsvp['rsvp_id'], true );

		$this->assertSame(
			array(),
			wp_get_object_terms( $rsvp['rsvp_id'], Flag::TAXONOMY, array( 'fields' => 'slugs' ) ),
			'Deleting an RSVP should leave no flag relationships behind.'
		);
	}

	/**
	 * Coverage for delete_flags skipping other comment types without touching
	 * the taxonomy.
	 *
	 * @covers ::delete_flags
	 *
	 * @return void
	 */
	public function test_delete_flags_skips_other_comment_types(): void {
		$instance   = Flag::get_instance();
		$post_id    = $this->mock->post()->get()->ID;
		$comment_id = (int) wp_insert_comment( array( 'comment_post_ID' => $post_id ) );

		// Written directly, since the API refuses comments that are not RSVPs.
		wp_set_object_terms( $comment_id, 'host', Flag::TAXONOMY );

		$instance->delete_flags( $comment_id, get_comment( $comment_id ) );

		$this->assertSame(
			array( 'host' ),
			wp_get_object_terms( $comment_id, Flag::TAXONOMY, array( 'fields' => 'slugs' ) ),
			'A comment that is not an RSVP should be left alone.'
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
		$instance = Flag::get_instance();
		$rsvp     = $this->make_rsvp();

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'can_write', array( $rsvp['rsvp_id'], 'host' ) ),
			'A valid slug on an RSVP with the taxonomy registered should be writable.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'can_write', array( $rsvp['rsvp_id'], 'HOST' ) ),
			'An invalid slug should not be writable.'
		);
		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'can_write', array( 0, 'host' ) ),
			'A comment ID that does not exist should not be writable.'
		);

		unregister_taxonomy( Flag::TAXONOMY );

		$before_init = Utility::invoke_hidden_method( $instance, 'can_write', array( $rsvp['rsvp_id'], 'host' ) );

		Setup::get_instance()->register_taxonomy();

		$this->assertFalse( $before_init, 'Nothing should be writable before the taxonomy is registered.' );
	}

	/**
	 * Coverage for is_valid_flag on each branch.
	 *
	 * @covers ::is_valid_flag
	 *
	 * @return void
	 */
	public function test_is_valid_flag(): void {
		$instance = Flag::get_instance();

		foreach ( array( 'checked-in', 'first_timer', 'walk-in', 'host2' ) as $slug ) {
			$this->assertTrue(
				Utility::invoke_hidden_method( $instance, 'is_valid_flag', array( $slug ) ),
				sprintf( 'The slug %s should be accepted.', $slug )
			);
		}

		foreach ( array( '', 'Walk In', 'HOST', 'no-show!' ) as $slug ) {
			$this->assertFalse(
				Utility::invoke_hidden_method( $instance, 'is_valid_flag', array( $slug ) ),
				sprintf( 'The slug %s should be refused.', wp_json_encode( $slug ) )
			);
		}
	}
}
