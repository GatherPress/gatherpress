<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp_Cleanup
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp_Cleanup;
use GatherPress\Core\Rsvp_Query;
use GatherPress\Core\Settings;
use GatherPress\Core\Utility;
use GatherPress\Tests\Base;

/**
 * Class Test_Rsvp.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp_Cleanup
 */
class Test_RSVP_Cleanup extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks() {
		$instance = Rsvp_Cleanup::get_instance();
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
				'name'     => 'update_option_gatherpress_general',
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
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_interval', 1 );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_switch', 'on' );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_frequency', 'hourly' );

		Rsvp_Cleanup::get_instance();
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
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_interval', 1 );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_switch', 'on' );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_frequency', 'daily' );

		Rsvp_Cleanup::get_instance();
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
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_interval', 1 );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_switch', 'on' );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_frequency', 'weekly' );

		Rsvp_Cleanup::get_instance();
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
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_interval', 1 );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_switch', 'on' );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_frequency', 'yearly' );

		Rsvp_Cleanup::get_instance();
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
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_interval', 1 );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_switch', 'on' );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_frequency', 'monthly' );

		Rsvp_Cleanup::get_instance();
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
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_interval', 1 );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_switch', 'off' );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_frequency', 'hourly' );

		Rsvp_Cleanup::get_instance();
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
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_interval', 1 );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_switch', 'on' );
		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_frequency', 'hourly' );

		Rsvp_Cleanup::get_instance();
		$next_event = wp_next_scheduled( 'gatherpress_rsvp_cleanup' );

		$this->set_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_interval', 2 );
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
		$instance = Rsvp_Cleanup::get_instance();

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

		$rsvp_query = Rsvp_Query::get_instance();
		$rsvps      = $rsvp_query->get_rsvps( array() );

		$this->assertCount( 0, $rsvps );
	}

	/**
	 * Set the value of a specific option in plugin settings.
	 *
	 * This method sets/updates the value of a specific option in the plugin settings
	 * based on the provided sub-page, section, and option names.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sub_page The sub-page associated with the value.
	 * @param string $section  The section within the sub-page where the option is located.
	 * @param string $option   The name of the option to retrieve.
	 * @param mixed  $value The value to set or update.
	 * @return void
	 */
	public function set_value( string $sub_page, string $section, string $option, $value ): void {
		$settings                       = Settings::get_instance();
		$sub_page                       = Utility::prefix_key( $sub_page );
		$options                        = $settings->get_options( $sub_page );
		$options[ $section ][ $option ] = $value;

		update_option( $sub_page, $options );
	}
}
