<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Cleanup.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Cleanup;
use GatherPress\Core\Rsvp\Query;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use RuntimeException;

/**
 * Class Test_Cleanup.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Cleanup
 */
class Test_Cleanup extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks() {
		$instance = Cleanup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'schedule_cleanup_cron' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_rsvp_cleanup',
				'priority' => 10,
				'callback' => array( $instance, 'rsvp_cleanup' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'update_option_gatherpress_settings',
				'priority' => 10,
				'callback' => array( $instance, 'reschedule_cleanup_cron' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'delete_comment',
				'priority' => 10,
				'callback' => array( $instance, 'delete_term_relationships' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for schedule_cleanup_cron.
	 *
	 * @covers ::schedule_cleanup_cron
	 * @covers ::convert_to_seconds
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_job_can_be_scheduled_hourly(): void {
		$settings = Settings::get_instance();
		$settings->set( 'rsvp_cleanup_switch', 'enabled' );
		$settings->set( 'rsvp_cleanup_frequency', 'hourly' );

		Cleanup::get_instance()->schedule_cleanup_cron();
		$next_event = wp_next_scheduled( 'gatherpress_rsvp_cleanup' );
		$this->assertNotEquals( false, $next_event );
	}

	/**
	 * Coverage for schedule_cleanup_cron.
	 *
	 * @covers ::schedule_cleanup_cron
	 * @covers ::convert_to_seconds
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_job_can_be_scheduled_daily(): void {
		$settings = Settings::get_instance();
		$settings->set( 'rsvp_cleanup_switch', 'enabled' );

		Cleanup::get_instance()->schedule_cleanup_cron();
		$next_event = wp_next_scheduled( 'gatherpress_rsvp_cleanup' );
		$this->assertNotEquals( false, $next_event );
	}

	/**
	 * Coverage for schedule_cleanup_cron.
	 *
	 * @covers ::schedule_cleanup_cron
	 * @covers ::convert_to_seconds
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_job_can_be_scheduled_weekly(): void {
		$settings = Settings::get_instance();
		$settings->set( 'rsvp_cleanup_switch', 'enabled' );
		$settings->set( 'rsvp_cleanup_frequency', 'weekly' );

		Cleanup::get_instance()->schedule_cleanup_cron();
		$next_event = wp_next_scheduled( 'gatherpress_rsvp_cleanup' );
		$this->assertNotEquals( false, $next_event );
	}

	/**
	 * Coverage for schedule_cleanup_cron.
	 *
	 * @covers ::schedule_cleanup_cron
	 * @covers ::convert_to_seconds
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_job_can_be_scheduled_yearly(): void {
		$settings = Settings::get_instance();
		$settings->set( 'rsvp_cleanup_switch', 'enabled' );
		$settings->set( 'rsvp_cleanup_frequency', 'yearly' );

		Cleanup::get_instance()->schedule_cleanup_cron();
		$next_event = wp_next_scheduled( 'gatherpress_rsvp_cleanup' );
		$this->assertNotEquals( false, $next_event );
	}

	/**
	 * Coverage for schedule_cleanup_cron.
	 *
	 * @covers ::schedule_cleanup_cron
	 * @covers ::convert_to_seconds
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_job_can_be_scheduled_monthly(): void {
		$settings = Settings::get_instance();
		$settings->set( 'rsvp_cleanup_switch', 'enabled' );
		$settings->set( 'rsvp_cleanup_frequency', 'monthly' );

		Cleanup::get_instance()->schedule_cleanup_cron();
		$next_event = wp_next_scheduled( 'gatherpress_rsvp_cleanup' );
		$this->assertNotEquals( false, $next_event );
	}

	/**
	 * Coverage for schedule_cleanup_cron.
	 *
	 * @covers ::schedule_cleanup_cron
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_job_is_not_scheduled_if_switch_is_off(): void {
		$settings = Settings::get_instance();
		$settings->set( 'rsvp_cleanup_frequency', 'hourly' );

		Cleanup::get_instance()->schedule_cleanup_cron();
		$next_event = wp_next_scheduled( 'gatherpress_rsvp_cleanup' );
		$this->assertFalse( $next_event );
	}

	/**
	 * Coverage for schedule_cleanup_cron.
	 *
	 * @covers ::reschedule_cleanup_cron
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_job_is_rescheduled_if_cleanup_settings_change(): void {
		$settings = Settings::get_instance();
		$settings->set( 'rsvp_cleanup_switch', 'enabled' );
		$settings->set( 'rsvp_cleanup_frequency', 'hourly' );

		Cleanup::get_instance()->schedule_cleanup_cron();
		$next_event = wp_next_scheduled( 'gatherpress_rsvp_cleanup' );

		$settings->set( 'rsvp_cleanup_interval', 2 );
		Cleanup::get_instance()->schedule_cleanup_cron();
		$rescheduled_event = wp_next_scheduled( 'gatherpress_rsvp_cleanup' );

		$this->assertNotEquals( $rescheduled_event, $next_event );
	}

	/**
	 * Coverage for schedule_cleanup_cron.
	 *
	 * @covers ::rsvp_cleanup
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_job_deletes_unapproved_rsvps(): void {
		$instance = Cleanup::get_instance();

		$post = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'  => $post->ID,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => 0,
			)
		);
		$rsvp_token = 'test-token-123';
		update_comment_meta( $comment_id, '_gatherpress_rsvp_token', $rsvp_token );

		$new_date = '2023-12-25 10:00:00';
		wp_update_comment(
			array(
				'comment_ID'       => $comment_id,
				'comment_date'     => $new_date,
				'comment_date_gmt' => $new_date,
			)
		);

		$instance->rsvp_cleanup();

		$rsvp_query = Query::get_instance();
		$rsvps      = $rsvp_query->get_rsvps( array() );

		$this->assertCount( 0, $rsvps );
	}

	/**
	 * The cleanup sweep defers term counting across its delete loop.
	 *
	 * Every hard delete now drops term relationships in up to three taxonomies
	 * via `delete_term_relationships()`, and each of those recounts its terms
	 * immediately, so a sweep clearing n stale RSVPs paid on the order of 3n
	 * recount queries. Deferring collapses them into one recount per taxonomy at
	 * the end, which is what core's own bulk paths do.
	 *
	 * Asserted by observing the deferral flag from inside the loop, via the
	 * `delete_comment` hook the sweep fires. That is the only place the state is
	 * visible while it matters. Checking it afterwards would prove nothing,
	 * since the sweep restores it before returning.
	 *
	 * @covers ::rsvp_cleanup
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_defers_term_counting_across_the_delete_loop(): void {
		$instance = Cleanup::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$deferred = array();
		$observe  = static function () use ( &$deferred ): void {
			$deferred[] = wp_defer_term_counting();
		};

		// Two stale RSVPs, so "deferred across the loop" is distinguishable
		// from "deferred around a single delete".
		foreach ( array( 1, 2 ) as $ignored ) {
			$comment_id = $this->factory->comment->create(
				array(
					'comment_post_ID'  => $post->ID,
					'comment_type'     => Rsvp::COMMENT_TYPE,
					'comment_approved' => 0,
				)
			);

			wp_update_comment(
				array(
					'comment_ID'       => $comment_id,
					'comment_date'     => '2023-12-25 10:00:00',
					'comment_date_gmt' => '2023-12-25 10:00:00',
				)
			);
		}

		add_action( 'delete_comment', $observe, 1 );

		$instance->rsvp_cleanup();

		remove_action( 'delete_comment', $observe, 1 );

		$this->assertCount(
			2,
			$deferred,
			'Failed to arrange two hard deletes for the sweep to batch.'
		);
		$this->assertSame(
			array( true, true ),
			$deferred,
			'Failed to assert term counting stays deferred for every delete in the cleanup loop.'
		);
		$this->assertFalse(
			wp_defer_term_counting(),
			'Failed to assert the sweep restores term counting before it returns.'
		);
	}

	/**
	 * The deferral is closed even when a delete throws part way through the sweep.
	 *
	 * The loop runs arbitrary third-party code: `delete_comment`,
	 * `deleted_comment` and the comment-meta hooks all fire inside it. With the
	 * restore sitting after the loop as a plain statement, any of those throwing
	 * skipped it and left term counting deferred for the rest of the request, so
	 * nothing recounted again and every term count read afterwards was stale.
	 * This is a cron callback, where the throw is swallowed and no one sees it.
	 *
	 * @covers ::rsvp_cleanup
	 *
	 * @return void
	 */
	public function test_rsvp_cleanup_restores_term_counting_when_a_delete_throws(): void {
		$instance = Cleanup::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID'  => $post->ID,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => 0,
			)
		);

		wp_update_comment(
			array(
				'comment_ID'       => $comment_id,
				'comment_date'     => '2023-12-25 10:00:00',
				'comment_date_gmt' => '2023-12-25 10:00:00',
			)
		);

		$explode = static function (): void {
			throw new RuntimeException( 'A delete_comment listener threw.' );
		};

		add_action( 'delete_comment', $explode, 1 );

		$thrown = null;

		try {
			$instance->rsvp_cleanup();
		} catch ( RuntimeException $exception ) {
			$thrown = $exception;
		} finally {
			remove_action( 'delete_comment', $explode, 1 );
		}

		$this->assertNotNull(
			$thrown,
			'Failed to arrange a throwing delete for the sweep to unwind from.'
		);
		$this->assertFalse(
			wp_defer_term_counting(),
			'Failed to assert the sweep restores term counting when a delete throws.'
		);
	}
}
