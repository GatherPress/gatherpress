<?php
/**
 * Event reminder emails.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use WP_Query;

/**
 * Class Reminder.
 *
 * Emails attendees a reminder before an event starts. An hourly sweep finds
 * the events starting within the reminder window and queues each one's
 * reminder once, through Email.
 *
 * @since TBD
 */
final class Reminder {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Post meta key holding the start time the last reminder was queued for.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const SENT_META_KEY = 'gatherpress_event_reminder_sent';

	/**
	 * WP-Cron hook name for the hourly reminder sweep.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const SWEEP_ACTION = 'gatherpress_event_reminder_sweep';

	/**
	 * The reminder timing used when the setting holds no known option.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const DEFAULT_TIMING = 'day_before';

	/**
	 * How many events the sweep reads at a time.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	const BATCH_SIZE = 100;

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'schedule_sweep' ) );
		add_action( self::SWEEP_ACTION, array( $this, 'sweep' ) );
	}

	/**
	 * Whether reminder emails are turned on for the site.
	 *
	 * @since TBD
	 *
	 * @return bool True when reminders are on.
	 */
	public function is_enabled(): bool {
		return (bool) Settings::get_instance()->get( 'enable_event_reminders' );
	}

	/**
	 * The choices for when a reminder goes out, keyed by setting value.
	 *
	 * @since TBD
	 *
	 * @return array<string, array{label: string, seconds: int}> Each choice's label and seconds before the start.
	 */
	public function get_timings(): array {
		/**
		 * Filters the choices for when an event reminder goes out.
		 *
		 * Lets a site offer another timing, such as a week before, in the
		 * Reminder Timing setting. The sweep runs hourly, so a reminder goes
		 * out within an hour after its time.
		 *
		 * @since TBD
		 *
		 * @param array<string, array{label: string, seconds: int}> $timings Each choice's label and seconds
		 *                                                                    before the start, keyed by setting value.
		 *
		 * @return array<string, array{label: string, seconds: int}> The choices.
		 */
		return (array) apply_filters(
			'gatherpress_event_reminder_timings',
			array(
				self::DEFAULT_TIMING => array(
					'label'   => __( 'A day before', 'gatherpress' ),
					'seconds' => DAY_IN_SECONDS,
				),
				'hour_before'        => array(
					'label'   => __( 'An hour before', 'gatherpress' ),
					'seconds' => HOUR_IN_SECONDS,
				),
			)
		);
	}

	/**
	 * How many seconds before an event's start its reminder is due.
	 *
	 * An unknown setting value, such as one whose filtered choice is gone,
	 * falls back to a day before.
	 *
	 * @since TBD
	 *
	 * @return int Seconds before the start.
	 */
	public function get_lead_time(): int {
		$timings = $this->get_timings();
		$timing  = (string) Settings::get_instance()->get( 'event_reminder_timing' );

		return max( 1, (int) ( $timings[ $timing ]['seconds'] ?? DAY_IN_SECONDS ) );
	}

	/**
	 * Keep the hourly sweep scheduled while reminders are on, and remove it once they are off.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function schedule_sweep(): void {
		$scheduled = false !== wp_next_scheduled( self::SWEEP_ACTION );

		if ( $this->is_enabled() === $scheduled ) {
			return;
		}

		if ( $scheduled ) {
			wp_unschedule_hook( self::SWEEP_ACTION );

			return;
		}

		wp_schedule_event( time(), 'hourly', self::SWEEP_ACTION );
	}

	/**
	 * Queue the reminders of events starting within the reminder window.
	 *
	 * Runs hourly, so a reminder goes out within an hour after it is due, and
	 * an event published inside the window still gets one. The events come
	 * from the events table a page at a time, and queue_reminder() skips an
	 * event whose reminder is already queued.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function sweep(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$now  = time();
		$page = 1;

		do {
			$query    = new WP_Query(
				array(
					'post_type'              => get_post_types_by_support( Event::SUPPORT ),
					'post_status'            => 'publish',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'posts_per_page'         => self::BATCH_SIZE,
					'paged'                  => $page,
					Query::EVENT_QUERY_PARAM => 'upcoming',
					'orderby'                => 'datetime',
					'order'                  => 'ASC',
					// Event queries read a date query against the events table, in site time.
					'date_query'             => array(
						array(
							'column'    => 'datetime_start_gmt',
							'after'     => wp_date( 'Y-m-d H:i:s', $now ),
							'before'    => wp_date( 'Y-m-d H:i:s', $now + $this->get_lead_time() ),
							'inclusive' => true,
						),
					),
				)
			);
			$post_ids = wp_parse_id_list( $query->posts );

			$is_full = self::BATCH_SIZE === count( $post_ids );

			foreach ( $post_ids as $post_id ) {
				$this->queue_reminder( $post_id );
			}

			++$page;
		} while ( $is_full );
	}

	/**
	 * Queue the reminder for an event about to start.
	 *
	 * The start time it was queued for is stored, so the next sweep for the
	 * same start sends nothing, while a moved start can send again. An event
	 * that has already started gets nothing.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Event post ID.
	 *
	 * @return void
	 */
	public function queue_reminder( int $post_id ): void {
		$start = (string) get_post_meta( $post_id, Scheduler::START_META_KEY, true );

		if (
			! $this->is_enabled()
			|| ! ( new Rsvp( $post_id ) )->is_enabled()
			|| (int) strtotime( $start ) <= time()
			|| get_post_meta( $post_id, self::SENT_META_KEY, true ) === $start
		) {
			return;
		}

		if ( ! Email::get_instance()->queue_reminder_email( $post_id, $start ) ) {
			return;
		}

		update_post_meta( $post_id, self::SENT_META_KEY, $start );
	}
}
