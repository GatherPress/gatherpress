<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Flag\Setup.
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

/**
 * Class Test_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Flag\Setup
 */
class Test_Setup extends Base_Unit_Test {

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
	 * Create an event and an approved RSVP against it.
	 *
	 * @return int The RSVP comment ID.
	 */
	private function make_rsvp(): int {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		return (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => '1',
				'user_id'          => 1,
			)
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
		$bootstrap = Setup::get_instance();

		Utility::set_and_get_hidden_static_property( Setup::class, 'instance', null );

		$built = Setup::get_instance();

		Utility::set_and_get_hidden_static_property( Setup::class, 'instance', $bootstrap );

		$this->assertInstanceOf(
			Setup::class,
			$built,
			'Failed to assert that the constructor returns a Setup instance.'
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
		$instance = Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_taxonomy' ),
			),
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
	 * Coverage for register_taxonomy: the flag taxonomy exists on comments and,
	 * being private, generates no rewrite rules (#825).
	 *
	 * @covers ::register_taxonomy
	 *
	 * @return void
	 */
	public function test_register_taxonomy(): void {
		unregister_taxonomy( Base::TAXONOMY );

		Setup::get_instance()->register_taxonomy();

		$this->assertTrue(
			taxonomy_exists( Base::TAXONOMY ),
			'Failed to assert that the RSVP flag taxonomy is registered.'
		);
		$this->assertSame(
			array( 'comment' ),
			get_taxonomy( Base::TAXONOMY )->object_type,
			'Failed to assert that the RSVP flag taxonomy is registered on comments.'
		);
		$this->assertFalse(
			get_taxonomy( Base::TAXONOMY )->rewrite,
			'Failed to assert that the RSVP flag taxonomy registers no rewrite rules.'
		);
	}

	/**
	 * Coverage for get_flags reading every flag on an RSVP, and none on a
	 * fresh one.
	 *
	 * @covers ::get_flags
	 *
	 * @return void
	 */
	public function test_get_flags_reads_every_flag_on_an_rsvp(): void {
		$instance = Setup::get_instance();
		$rsvp_id  = $this->make_rsvp();

		$this->assertSame( array(), $instance->get_flags( $rsvp_id ), 'A fresh RSVP should carry no flags.' );

		( new Test_Base_Concrete( $rsvp_id ) )->add();
		( new Check_In( $rsvp_id ) )->add();

		$this->assertEqualsCanonicalizing(
			array( Test_Base_Concrete::SLUG, Check_In::SLUG ),
			$instance->get_flags( $rsvp_id ),
			'Every flag on the RSVP should be returned.'
		);
	}

	/**
	 * Coverage for get_flags reading through the object term cache: a repeat
	 * read runs no query, and a write clears the cache so the next read is
	 * current.
	 *
	 * @covers ::get_flags
	 *
	 * @return void
	 */
	public function test_get_flags_uses_the_object_term_cache(): void {
		global $wpdb;

		$instance = Setup::get_instance();
		$rsvp_id  = $this->make_rsvp();

		( new Test_Base_Concrete( $rsvp_id ) )->add();
		wp_cache_delete( $rsvp_id, Base::TAXONOMY . '_relationships' );

		$instance->get_flags( $rsvp_id );
		$queries = $wpdb->num_queries;
		$instance->get_flags( $rsvp_id );

		$this->assertSame( $queries, $wpdb->num_queries, 'A repeat read should come from the cache.' );

		( new Check_In( $rsvp_id ) )->add();

		$this->assertEqualsCanonicalizing(
			array( Test_Base_Concrete::SLUG, Check_In::SLUG ),
			$instance->get_flags( $rsvp_id ),
			'A read after a write should see the new flag.'
		);
	}

	/**
	 * Coverage for get_flags returning an empty list when the taxonomy cannot
	 * be read, where core returns a WP_Error.
	 *
	 * @covers ::get_flags
	 *
	 * @return void
	 */
	public function test_get_flags_returns_empty_when_the_taxonomy_cannot_be_read(): void {
		$instance = Setup::get_instance();
		$rsvp_id  = $this->make_rsvp();

		( new Test_Base_Concrete( $rsvp_id ) )->add();
		wp_cache_delete( $rsvp_id, Base::TAXONOMY . '_relationships' );
		unregister_taxonomy( Base::TAXONOMY );

		$flags = $instance->get_flags( $rsvp_id );

		Setup::get_instance()->register_taxonomy();

		$this->assertSame( array(), $flags, 'An unreadable taxonomy should read as no flags.' );
	}

	/**
	 * Coverage for delete_flags: deleting an RSVP sweeps every flag on it. Runs
	 * through wp_delete_comment() so the RSVP check reads the comment the way it
	 * does in production, after the row is gone.
	 *
	 * @covers ::delete_flags
	 *
	 * @return void
	 */
	public function test_delete_flags_sweeps_every_flag_from_a_deleted_rsvp(): void {
		$rsvp_id = $this->make_rsvp();

		( new Test_Base_Concrete( $rsvp_id ) )->add();
		( new Check_In( $rsvp_id ) )->add();

		wp_delete_comment( $rsvp_id, true );

		$this->assertSame(
			array(),
			wp_get_object_terms( $rsvp_id, Base::TAXONOMY, array( 'fields' => 'slugs' ) ),
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
		$post_id    = $this->mock->post()->get()->ID;
		$comment_id = (int) wp_insert_comment( array( 'comment_post_ID' => $post_id ) );

		// Written directly, since flags refuse comments that are not RSVPs.
		wp_set_object_terms( $comment_id, 'host', Base::TAXONOMY );

		Setup::get_instance()->delete_flags( $comment_id );

		$this->assertSame(
			array( 'host' ),
			wp_get_object_terms( $comment_id, Base::TAXONOMY, array( 'fields' => 'slugs' ) ),
			'A comment that is not an RSVP should be left alone.'
		);
	}
}
