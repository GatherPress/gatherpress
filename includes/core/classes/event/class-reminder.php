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
	 * Post meta key holding, for each reminder timing, the start it was last queued for.
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
	 * The timings a reminder can go out at, keyed by name.
	 *
	 * Each one gets a checkbox under Notifications, so an event can be
	 * reminded more than once, for example a day before and an hour before.
	 *
	 * @since TBD
	 *
	 * @return array<string, array{label: string, seconds: int, default: bool}> Each timing's checkbox
	 *         label, seconds before the start, and whether it is checked by default.
	 */
	public function get_timings(): array {
		/**
		 * Filters the timings an event reminder can go out at.
		 *
		 * Lets a site add a timing, such as a week before, or remove one. Each
		 * timing gets its own checkbox under Settings > RSVP > Notifications.
		 * The sweep runs hourly, so a reminder goes out within an hour after
		 * its time.
		 *
		 * @since TBD
		 *
		 * @param array<string, array{label: string, seconds: int, default: bool}> $timings Each timing's
		 *        checkbox label, seconds before the start, and whether it is checked by default, keyed by name.
		 *
		 * @return array<string, array{label: string, seconds: int, default: bool}> The timings.
		 */
		return (array) apply_filters(
			'gatherpress_event_reminder_timings',
			array(
				'day_before'  => array(
					'label'   => __( 'A day before', 'gatherpress' ),
					'seconds' => DAY_IN_SECONDS,
					'default' => true,
				),
				'hour_before' => array(
					'label'   => __( 'An hour before', 'gatherpress' ),
					'seconds' => HOUR_IN_SECONDS,
					'default' => false,
				),
			)
		);
	}

	/**
	 * The settings key for a reminder timing's checkbox.
	 *
	 * @since TBD
	 *
	 * @param string $timing A timing name from {@see self::get_timings()}.
	 *
	 * @return string The settings key.
	 */
	public static function get_timing_setting( string $timing ): string {
		return 'event_reminder_' . sanitize_key( $timing );
	}

	/**
	 * The checked reminder timings, as seconds before the start.
	 *
	 * @since TBD
	 *
	 * @return array<string, int> Seconds before the start, keyed by timing name.
	 */
	public function get_checked_timings(): array {
		$checked = array();

		foreach ( $this->get_timings() as $timing => $config ) {
			if ( Settings::get_instance()->get( self::get_timing_setting( (string) $timing ) ) ) {
				$checked[ (string) $timing ] = max( 1, $config['seconds'] );
			}
		}

		return $checked;
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
	 * Queue the reminders of events starting within the longest checked timing.
	 *
	 * Runs hourly, so a reminder goes out within an hour after it is due, and
	 * an event published inside the window still gets one. The events come
	 * from the events table a page at a time, and queue_reminder() sends only
	 * what is due and not yet sent.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function sweep(): void {
		$timings = $this->get_checked_timings();

		if ( ! $this->is_enabled() || empty( $timings ) ) {
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
							'before'    => wp_date( 'Y-m-d H:i:s', $now + max( $timings ) ),
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
	 * Sends one email when any checked timing is due for the event's start
	 * and has not been sent for it. When several are due at once, such as an
	 * event published an hour before it starts, one email covers them all.
	 * The start each timing was sent for is stored, so the next sweep sends
	 * nothing for the same start, while a moved start can send again. An
	 * event that has already started gets nothing.
	 *
	 * @since TBD
	 *
	 * @param int $post_id Event post ID.
	 *
	 * @return void
	 */
	public function queue_reminder( int $post_id ): void {
		$start = (string) get_post_meta( $post_id, Scheduler::START_META_KEY, true );
		$sent  = get_post_meta( $post_id, self::SENT_META_KEY, true );
		$sent  = is_array( $sent ) ? $sent : array();
		$due   = $this->get_due_timings( $start, $sent );

		if (
			empty( $due )
			|| ! $this->is_enabled()
			|| ! ( new Rsvp( $post_id ) )->is_enabled()
		) {
			return;
		}

		if ( ! Email::get_instance()->queue_reminder_email( $post_id, $start ) ) {
			return;
		}

		foreach ( $due as $timing ) {
			$sent[ $timing ] = $start;
		}

		update_post_meta( $post_id, self::SENT_META_KEY, $sent );
	}

	/**
	 * The checked timings due for a start and not yet sent for it.
	 *
	 * @since TBD
	 *
	 * @param string                $start The event's GMT start.
	 * @param array<string, string> $sent  The start each timing was last sent for.
	 *
	 * @return string[] Timing names.
	 */
	private function get_due_timings( string $start, array $sent ): array {
		$until_start = (int) strtotime( $start ) - time();

		if ( $until_start <= 0 ) {
			return array();
		}

		return array_keys(
			array_filter(
				$this->get_checked_timings(),
				static fn( int $seconds, string $timing ): bool => $until_start <= $seconds
					&& ( $sent[ $timing ] ?? '' ) !== $start,
				ARRAY_FILTER_USE_BOTH
			)
		);
	}
}
