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
}
