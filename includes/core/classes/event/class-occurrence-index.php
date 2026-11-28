<?php
/**
 * An index of every event's dates, for queries.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Occurrence_Index.
 *
 * Keeps one row per occurrence of every event in its own table, so queries
 * can join on an event's dates the way they join on any other row. A
 * repeating event has a row per date, and an event without rules has one.
 *
 * The index is a cache. Rules and the saved dates are the source of truth,
 * any event's rows can be rebuilt from them at any time, and nothing should
 * key on a row: per-date pages and RSVPs find a date by the series and its
 * key through `Occurrences::find()`, so a date past the horizon needs no row.
 * Rows hold dates only, never post content. Past rows are kept as history,
 * and future rows reach only as far as the horizon, so an open-ended series
 * never fills the table.
 *
 * @since TBD
 */
final class Occurrence_Index {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Format of the table name, filled with the site's table prefix.
	 *
	 * @since TBD
	 * @var string
	 */
	const TABLE_FORMAT = '%sgatherpress_occurrence_index';

	/**
	 * Option holding the schema version a site's index was built with.
	 *
	 * @since TBD
	 * @var string
	 */
	const SCHEMA_OPTION = 'gatherpress_occurrence_index_schema';

	/**
	 * The current schema version. A site whose option doesn't match builds its index again.
	 *
	 * @since TBD
	 * @var string
	 */
	const SCHEMA_VERSION = '1';

	/**
	 * Option used as a lock while a site builds its index.
	 *
	 * @since TBD
	 * @var string
	 */
	const LOCK_OPTION = 'gatherpress_occurrence_index_lock';

	/**
	 * Seconds after which a build's lock is treated as abandoned, such as by a request that timed out.
	 *
	 * @since TBD
	 * @var int
	 */
	const LOCK_TIMEOUT = 600;

	/**
	 * Daily cron action that moves repeating events' rows forward with the horizon.
	 *
	 * @since TBD
	 * @var string
	 */
	const CRON_ACTION = 'gatherpress_occurrence_index_extend';

	/**
	 * How many months ahead repeating events are indexed, by default.
	 *
	 * @since TBD
	 * @var int
	 */
	const HORIZON_MONTHS = 12;

	/**
	 * Events whose rule meta changed outside a rule write, to rebuild at shutdown, by site ID.
	 *
	 * @since TBD
	 * @var array<int, array<int, true>>
	 */
	protected array $pending = array();

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Register the hooks that keep the index current.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Priority 11: the event post types register on init at 10, and building the index reads them.
		add_action( 'init', array( $this, 'maybe_install' ), 11 );
		// Priority 11: as above, and scheduling is skipped until the index exists.
		add_action( 'init', array( $this, 'schedule_extend' ), 11 );
		add_action( 'gatherpress_event_datetimes_saved', array( $this, 'rebuild' ) );
		add_action( 'deleted_post', array( $this, 'delete' ) );
		add_action( 'added_post_meta', array( $this, 'handle_rule_meta_change' ), 10, 3 );
		add_action( 'updated_post_meta', array( $this, 'handle_rule_meta_change' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'handle_rule_meta_change' ), 10, 3 );
		add_action( 'shutdown', array( $this, 'rebuild_pending' ) );
		add_action( self::CRON_ACTION, array( $this, 'extend' ) );
	}

	/**
	 * The current site's index table name.
	 *
	 * @since TBD
	 *
	 * @return string The table name.
	 */
	public static function table(): string {
		global $wpdb;

		return sprintf( self::TABLE_FORMAT, $wpdb->prefix );
	}

	/**
	 * Whether the current site's index has been built, so queries can join it.
	 *
	 * @since TBD
	 *
	 * @return bool True once the index is built with the current schema.
	 */
	public function is_ready(): bool {
		return self::SCHEMA_VERSION === get_option( self::SCHEMA_OPTION );
	}

	/**
	 * Build the current site's index if it hasn't been built yet.
	 *
	 * Runs on every request rather than only on an admin visit, so the front
	 * end never waits for one, and each site of a network builds its own.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function maybe_install(): void {
		// Only one request builds, so the first visits after an update don't all repeat the work.
		if ( $this->is_ready() || ! $this->lock() ) {
			return;
		}

		try {
			$this->create_table();
			$this->copy_events();
			// Ready before the rule pass, which rebuilds through rebuild().
			update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION );
			$this->expand_repeating();
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * Take the build lock, or a lock left by a build that never finished.
	 *
	 * Written with `INSERT IGNORE`, so of two requests at once only one gets it.
	 *
	 * @since TBD
	 *
	 * @return bool True if this request holds the lock.
	 */
	protected function lock(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- An atomic lock, as WP_Upgrader::create_lock() takes one.
		$taken = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)',
				$wpdb->options,
				self::LOCK_OPTION,
				(string) time(),
				'no'
			)
		);

		if ( $taken ) {
			return true;
		}

		wp_cache_delete( self::LOCK_OPTION, 'options' );

		if ( time() - (int) get_option( self::LOCK_OPTION ) < self::LOCK_TIMEOUT ) {
			return false;
		}

		update_option( self::LOCK_OPTION, (string) time(), false );

		return true;
	}

	/**
	 * Create or update the current site's index table.
	 *
	 * The key is the event, the occurrence's key and its start, so an
	 * occurrence is stored once however often it is written.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function create_table(): void {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
					post_id bigint(20) unsigned NOT NULL default '0',
					recurrence_id varchar(15) NOT NULL default '',
					datetime_start datetime NOT NULL default '0000-00-00 00:00:00',
					datetime_start_gmt datetime NOT NULL default '0000-00-00 00:00:00',
					datetime_end datetime NOT NULL default '0000-00-00 00:00:00',
					datetime_end_gmt datetime NOT NULL default '0000-00-00 00:00:00',
					timezone varchar(64) NOT NULL default '',
					PRIMARY KEY  (post_id,recurrence_id,datetime_start_gmt),
					KEY datetime_start_gmt (datetime_start_gmt),
					KEY datetime_end_gmt (datetime_end_gmt)
				) {$charset_collate};";

		// Loading WordPress core file for dbDelta function, not importing a class.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php'; // NOSONAR.

		dbDelta( $sql );
	}

	/**
	 * Fill the index from the events table.
	 *
	 * An event without rules is exactly its row in the events table, so one
	 * statement copies all of them, building each key from its local start.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function copy_events(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Building a plugin-owned cache table.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i
					(post_id, recurrence_id, datetime_start, datetime_start_gmt,
					datetime_end, datetime_end_gmt, timezone)
				SELECT e.post_id,
					IF( m.all_day = %s, DATE_FORMAT( e.datetime_start, %s ), DATE_FORMAT( e.datetime_start, %s ) ),
					e.datetime_start, e.datetime_start_gmt, e.datetime_end, e.datetime_end_gmt,
					COALESCE( e.timezone, %s )
				FROM %i e
				LEFT JOIN (
					SELECT post_id, MAX( meta_value ) AS all_day FROM %i WHERE meta_key = %s GROUP BY post_id
				) m ON m.post_id = e.post_id',
				self::table(),
				'1',
				'%Y%m%d',
				'%Y%m%dT%H%i%s',
				'',
				Event::table(),
				$wpdb->postmeta,
				'gatherpress_is_all_day'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Rebuild every event with rules.
	 *
	 * Used once the events are copied in, and by the daily cron.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function expand_repeating(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Listing events with rules.
		$repeating = $wpdb->get_col(
			$wpdb->prepare( 'SELECT DISTINCT post_id FROM %i WHERE meta_key = %s', $wpdb->postmeta, Rules::META_KEY )
		);

		foreach ( $repeating as $post_id ) {
			$this->rebuild( (int) $post_id );
		}
	}

	/**
	 * Rebuild an event's rows from its rules or saved dates.
	 *
	 * A published event with rules keeps its past rows, since a schedule
	 * change never touches dates before tomorrow, and rebuilds from today on,
	 * or from its last indexed date if that is earlier. Any other event
	 * rebuilds in full.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The event's post ID.
	 *
	 * @return void
	 */
	public function rebuild( int $post_id ): void {
		unset( $this->pending[ get_current_blog_id() ][ $post_id ] );

		if ( ! $this->is_ready() ) {
			return;
		}

		$event = new Event( $post_id );
		$data  = $event->post ? $event->get_saved_datetime() : array();
		$first = empty( $data['datetime_start_gmt'] ) ? null : $this->utc( $data['datetime_start_gmt'] );
		$from  = $this->rebuild_from( $event );

		$this->delete( $post_id, $from );

		if ( $first ) {
			// The saved dates are the series' first occurrence, so it is indexed even past the horizon.
			$last = max( $this->horizon(), $this->utc( $data['datetime_end_gmt'] )->modify( '+1 day' ) );

			$this->insert( $event, max( $first, $from ?? $first ), $last );
		}
	}

	/**
	 * Rebuild the events whose rule meta changed outside a rule write.
	 *
	 * Hooked to `shutdown`. A rule write rebuilds its event when it saves the
	 * dates, but rows written directly, such as by an import, need this.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function rebuild_pending(): void {
		$current = get_current_blog_id();

		foreach ( $this->pending as $site_id => $post_ids ) {
			// The meta may have been written inside switch_to_blog().
			$switched = $site_id !== $current;

			if ( $switched ) {
				switch_to_blog( $site_id );
			}

			foreach ( array_keys( $post_ids ) as $post_id ) {
				$this->rebuild( $post_id );
			}

			if ( $switched ) {
				restore_current_blog();
			}
		}

		$this->pending = array();
	}

	/**
	 * Note an event to rebuild when its rule meta changes.
	 *
	 * Hooked to `added_post_meta`, `updated_post_meta` and `deleted_post_meta`.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param mixed  $meta_id   The meta ID, or IDs when deleted. Required by the hook's signature.
	 * @param int    $object_id The post ID.
	 * @param string $meta_key  The meta key.
	 *
	 * @return void
	 */
	public function handle_rule_meta_change( mixed $meta_id, int $object_id, string $meta_key ): void {
		if ( Rules::META_KEY === $meta_key ) {
			$this->pending[ get_current_blog_id() ][ $object_id ] = true;
		}
	}

	/**
	 * Move every repeating event's rows forward to the horizon.
	 *
	 * Hooked to the daily cron.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function extend(): void {
		$this->expand_repeating();
	}

	/**
	 * Schedule the daily extension once the index exists.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function schedule_extend(): void {
		if ( ! $this->is_ready() || wp_next_scheduled( self::CRON_ACTION ) ) {
			return;
		}

		wp_schedule_event( time(), 'daily', self::CRON_ACTION );
	}

	/**
	 * Delete an event's rows, all of them or those starting from a moment on.
	 *
	 * Hooked to `deleted_post`.
	 *
	 * @since TBD
	 *
	 * @param int                    $post_id The event's post ID.
	 * @param DateTimeImmutable|null $from    Delete only rows starting at or after this, if given.
	 *
	 * @return void
	 */
	public function delete( int $post_id, ?DateTimeImmutable $from = null ): void {
		global $wpdb;

		$where = $wpdb->prepare( 'post_id = %d', $post_id );

		if ( $from ) {
			$where .= $wpdb->prepare( ' AND datetime_start_gmt >= %s', $from->format( Event::DATETIME_FORMAT ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared above.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE ', self::table() ) . $where );
	}

	/**
	 * How far ahead repeating events are indexed.
	 *
	 * @since TBD
	 *
	 * @return DateTimeImmutable The moment, in UTC.
	 */
	public function horizon(): DateTimeImmutable {
		/**
		 * Filters how many months ahead repeating events are indexed for queries.
		 *
		 * Lists and calendars show a repeating event's dates up to this far
		 * ahead. A longer horizon stores more rows; dates beyond it still
		 * resolve by key for per-date pages and RSVPs.
		 *
		 * @since TBD
		 *
		 * @param int $months The number of months. Defaults to 12.
		 *
		 * @return int The number of months, at least 1.
		 */
		$months = (int) apply_filters( 'gatherpress_occurrence_index_horizon', self::HORIZON_MONTHS );

		return $this->utc( 'now' )->modify( sprintf( '+%d months', max( 1, $months ) ) );
	}

	/**
	 * Where a rebuild starts.
	 *
	 * A published event with rules starts today, or at its last indexed date
	 * if that is earlier, so a gap left while the cron didn't run is filled.
	 * Any other event, or one not indexed yet, rebuilds in full.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 *
	 * @return DateTimeImmutable|null Where to start, in UTC, or null to rebuild in full.
	 */
	protected function rebuild_from( Event $event ): ?DateTimeImmutable {
		global $wpdb;

		$post_id   = $event->post->ID ?? 0;
		$published = in_array( get_post_status( $post_id ), Rules::PUBLISHED_STATUSES, true );

		if ( ! $published || ! $event->rules()->all() ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading this cache table.
		$last = $wpdb->get_var(
			$wpdb->prepare( 'SELECT MAX( datetime_start_gmt ) FROM %i WHERE post_id = %d', self::table(), $post_id )
		);

		return $last ? min( $this->utc( 'today' ), $this->utc( $last ) ) : null;
	}

	/**
	 * Insert an event's occurrences in a range, a month at a time.
	 *
	 * Reading a month at a time keeps each read under the occurrence cap.
	 * An occurrence two months both reach is stored once.
	 *
	 * @since TBD
	 *
	 * @param Event             $event The event.
	 * @param DateTimeImmutable $from  Start of the range.
	 * @param DateTimeImmutable $to    End of the range.
	 *
	 * @return void
	 */
	protected function insert( Event $event, DateTimeImmutable $from, DateTimeImmutable $to ): void {
		$occurrences = $event->occurrences();

		for ( $cursor = $from; $cursor < $to; $cursor = $cursor->modify( '+1 month' ) ) {
			$this->insert_rows( $occurrences->in_range( $cursor, min( $to, $cursor->modify( '+1 month' ) ) ) );
		}
	}

	/**
	 * Insert occurrences, skipping any already stored.
	 *
	 * @since TBD
	 *
	 * @param Occurrence[] $occurrences The occurrences.
	 *
	 * @return void
	 */
	protected function insert_rows( array $occurrences ): void {
		global $wpdb;

		$values = array();
		$utc    = new DateTimeZone( 'UTC' );

		foreach ( $occurrences as $occurrence ) {
			$values[] = $wpdb->prepare(
				'(%d, %s, %s, %s, %s, %s, %s)',
				$occurrence->series_post_id,
				$occurrence->recurrence_id,
				$occurrence->start->format( Event::DATETIME_FORMAT ),
				$occurrence->start->setTimezone( $utc )->format( Event::DATETIME_FORMAT ),
				$occurrence->end->format( Event::DATETIME_FORMAT ),
				$occurrence->end->setTimezone( $utc )->format( Event::DATETIME_FORMAT ),
				$occurrence->start->getTimezone()->getName()
			);
		}

		if ( ! $values ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Each row is prepared above.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i
					(post_id, recurrence_id, datetime_start, datetime_start_gmt,
					datetime_end, datetime_end_gmt, timezone)
				VALUES ',
				self::table()
			) . implode( ', ', $values )
		);
	}

	/**
	 * A moment in UTC.
	 *
	 * @since TBD
	 *
	 * @param string $datetime The moment, such as a stored GMT datetime or `now`.
	 *
	 * @return DateTimeImmutable The moment.
	 */
	protected function utc( string $datetime ): DateTimeImmutable {
		return new DateTimeImmutable( $datetime, new DateTimeZone( 'UTC' ) );
	}
}
