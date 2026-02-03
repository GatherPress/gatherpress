<?php
/**
 * Class responsible for scheduling rsvp cleanup cron jobs.
 *
 * @package GatherPress\Core
 * @since   1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Rsvp_Cleanup.
 *
 * This class manages rsvp cleanup events.
 *
 * @since 1.0.0
 */
class Rsvp_Cleanup {


	use Singleton;

	/**
	 * Initializes hooks needed for the cleanup cron event.
	 *
	 * @return  void
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Sets up WordPress action hooks for managing cron scheduling and cleanup operations.
	 *
	 * @return void
	 * @since  1.0.0
	 */
	private function setup_hooks() {
		add_action( 'init', array( $this, 'schedule_cleanup_cron' ) );
		add_action( 'gatherpress_rsvp_cleanup', array( $this, 'rsvp_cleanup' ), 10, 0 );
		add_action( 'update_option_gatherpress_general', array( $this, 'reschedule_cleanup_cron' ), 10, 2 );
	}

	/**
	 * Cleans up old RSVP entries by removing comments and their associated metadata.
	 *
	 * This method performs the following steps:
	 * 1. Retrieves all RSVPs with a comment status of 'hold' and a date before today.
	 * 2. Filters the retrieved RSVPs to include only those that are more than 24 hours old.
	 * 3. Deletes the filtered RSVP comments along with their associated metadata.
	 *
	 * @return void
	 * @since  1.0.0
	 */
	public function rsvp_cleanup(): void {
		// Perform cleanup.
		$rsvp_query = Rsvp_Query::get_instance();
		$rsvps      = $rsvp_query->get_rsvps(
			array(
				// 1. Find all rsvps with comment approved = 0.
				'status'     => 'hold',
				'date_query' => array(
					'before' => array(
						'year'  => gmdate( 'Y' ),
						'month' => gmdate( 'm' ),
						'day'   => gmdate( 'd' ),
					),
				),
			)
		);
		// 2. Further filter by those that are more than 24hrs old.
		$rsvps = array_filter(
			$rsvps,
			function ( $rsvp ) {
				$diff = strtotime( 'now' ) - strtotime( $rsvp->comment_date );
				return $diff >= 86400;
			}
		);
		// 3. Delete RSVP comment + associated meta.
		foreach ( $rsvps as $rsvp ) {
			$meta_keys = array_keys( get_comment_meta( $rsvp->comment_ID ) );
			foreach ( $meta_keys as $meta_key ) {
				delete_comment_meta( $rsvp->comment_ID, $meta_key );
			}
			wp_delete_comment( $rsvp->comment_ID, true );
		}

		// Schedule the next event.
		wp_clear_scheduled_hook( 'gatherpress_rsvp_cleanup' );
		$this->schedule_cleanup_cron();
	}

	/**
	 * Determines if rsvp cleanup is enabled and schedules the next cleanup event.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function schedule_cleanup_cron() {
		$settings = Settings::get_instance();
		$switch   = $settings->get_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_switch' );

		if ( 'on' === $switch ) {
			if ( ! wp_next_scheduled( 'gatherpress_rsvp_cleanup' ) ) {
				$frequency = $settings->get_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_frequency' );
				$interval  = $settings->get_value( 'general', 'rsvp_cleanup', 'rsvp_cleanup_interval' );

				$time_in_seconds = $this->convert_to_seconds( $frequency, $interval );
				wp_schedule_single_event( time() + $time_in_seconds, 'gatherpress_rsvp_cleanup' );
			}
		}
	}

	/**
	 * Converts a frequency and interval into the equivalent number of seconds.
	 *
	 * This method calculates the total seconds for a given frequency and
	 * multiplier interval. Supported frequencies include 'hourly', 'daily',
	 * 'weekly', 'monthly', and 'yearly'.
	 *
	 * @param string $frequency The recurrence frequency (e.g., 'hourly', 'daily').
	 * @param int    $interval The interval multiplier for the frequency.
	 *
	 * @return int The total number of seconds, or 0 if the frequency is not recognized.
	 * @since  1.0.0
	 */
	private function convert_to_seconds( string $frequency, int $interval ): int {
		switch ( $frequency ) {
			case 'hourly':
				return $interval * HOUR_IN_SECONDS;
			case 'daily':
				return $interval * DAY_IN_SECONDS;
			case 'weekly':
				return $interval * WEEK_IN_SECONDS;
			case 'monthly':
				return $interval * MONTH_IN_SECONDS;
			case 'yearly':
				return $interval * YEAR_IN_SECONDS;
			default:
				return 0;
		}
	}

	/**
	 * Reschedules the RSVP cleanup cron job if the interval or frequency has changed.
	 *
	 * This method checks the old and new RSVP cleanup settings for changes in the interval
	 * or frequency. If a change is detected, it clears the existing scheduled cron job
	 * and schedules a new one with the updated settings.
	 *
	 * @param array $old_value The previous RSVP cleanup settings including interval and frequency.
	 * @param array $new_value The updated RSVP cleanup settings including interval and frequency.
	 *
	 * @return void
	 * @since  1.0.0
	 */
	public function reschedule_cleanup_cron( $old_value, $new_value ): void {
		$old_interval = $old_value['rsvp_cleanup']['rsvp_cleanup_interval'] ?? null;
		$new_interval = $new_value['rsvp_cleanup']['rsvp_cleanup_interval'] ?? null;

		$old_frequency = $old_value['rsvp_cleanup']['rsvp_cleanup_frequency'] ?? null;
		$new_frequency = $new_value['rsvp_cleanup']['rsvp_cleanup_frequency'] ?? null;

		if ( $old_interval !== $new_interval || $old_frequency !== $new_frequency ) {
			wp_clear_scheduled_hook( 'gatherpress_rsvp_cleanup' );
			$this->schedule_cleanup_cron();
		}
	}
}
