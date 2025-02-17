<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp_Setup.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp_Setup;
use PMC\Unit_Test\Base;

/**
 * Class Test_Rsvp_Setup.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp_Setup
 */
class Test_Rsvp_Setup extends Base {
	/**
	 * Coverage for __construct and setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Rsvp_Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_taxonomy' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_process_waiting_list' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'get_comments_number',
				'priority' => 10,
				'callback' => array( $instance, 'adjust_comments_number' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for register_taxonomy method.
	 *
	 * @covers ::register_taxonomy
	 *
	 * @return void
	 */
	public function test_register_taxonomy(): void {
		$instance = Rsvp_Setup::get_instance();

		unregister_taxonomy( Rsvp::TAXONOMY );

		$this->assertFalse( taxonomy_exists( Rsvp::TAXONOMY ), 'Failed to assert that taxonomy does not exist.' );

		$instance->register_taxonomy();

		$this->assertTrue( taxonomy_exists( Rsvp::TAXONOMY ), 'Failed to assert that taxonomy exists.' );
	}

	/**
	 * Coverage for adjust_comments_number method.
	 *
	 * @covers ::adjust_comments_number
	 *
	 * @return void
	 */
	public function test_adjust_comments_number(): void {
		$instance = Rsvp_Setup::get_instance();
		$post     = $this->mock->post()->get();
		$user     = $this->mock->user()->get();

		$this->assertEquals(
			2,
			$instance->adjust_comments_number( 2, $post->ID ),
			'Failed to assert the comments do not equal 2.'
		);

		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		wp_insert_comment(
			array(
				'comment_post_ID' => $event->ID,
				'user_id'         => $user->ID,
				'comment_content' => 'Test comment',
			)
		);

		wp_insert_comment(
			array(
				'comment_post_ID' => $event->ID,
				'comment_type'    => Rsvp::COMMENT_TYPE,
				'user_id'         => $user->ID,
			)
		);

		$this->assertEquals(
			1,
			$instance->adjust_comments_number( 2, $event->ID ),
			'Failed to assert the comments do not equal 1.'
		);
	}
}
