<?php
/**
 * The occurrence rows of events.
 *
 * Every event is a series with one or more occurrences. An event without a
 * repeat rule is a series of one, so it has exactly one row here, kept in
 * sync with its row in the event date table. Nothing reads these rows yet:
 * the event date table stays the source of truth until reads move over.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since TBD
 */

namespace GatherPress\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Occurrences.
 *
 * Writes and removes the occurrence rows of events.
 *
 * @since TBD
 */
final class Occurrences {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Format of the occurrence table name, filled with the site's table prefix.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const TABLE_FORMAT = '%sgatherpress_event_occurrences';

	/**
	 * Status of an occurrence that takes place.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const STATUS_SCHEDULED = 'scheduled';

	/**
	 * Format of an occurrence identifier: its local start, as an RFC 5545 `RECURRENCE-ID`.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const RECURRENCE_ID_FORMAT = 'Ymd\THis';

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'delete_post', array( $this, 'delete_for_post' ) );
	}

	/**
	 * The occurrence table of the current site.
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
	 * The `CREATE TABLE` statement for the current site, for `dbDelta()`.
	 *
	 * @since TBD
	 *
	 * @return string The statement.
	 */
	public static function schema(): string {
		global $wpdb;

		$table           = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (
					series_post_id bigint(20) unsigned NOT NULL default '0',
					recurrence_id varchar(20) NOT NULL default '',
					datetime_start datetime NOT NULL default '0000-00-00 00:00:00',
					datetime_start_gmt datetime NOT NULL default '0000-00-00 00:00:00',
					datetime_end datetime NOT NULL default '0000-00-00 00:00:00',
					datetime_end_gmt datetime NOT NULL default '0000-00-00 00:00:00',
					timezone varchar(255) default NULL,
					status varchar(20) NOT NULL default 'scheduled',
					override_post_id bigint(20) unsigned NOT NULL default '0',
					PRIMARY KEY  (series_post_id,recurrence_id),
					KEY start_gmt (datetime_start_gmt),
					KEY series_status_start (series_post_id,status,datetime_start_gmt)
				) {$charset_collate};";
	}

	/**
	 * Derive an occurrence identifier from its local start.
	 *
	 * @since TBD
	 *
	 * @param DateTimeImmutable $start Occurrence start in the event's timezone.
	 *
	 * @return string The occurrence identifier.
	 */
	public static function recurrence_id( DateTimeImmutable $start ): string {
		return $start->format( self::RECURRENCE_ID_FORMAT );
	}

	/**
	 * Write the single occurrence of an event without a repeat rule.
	 *
	 * The event's dates are the ones just written to the event date table, so
	 * its one row is replaced rather than updated: a new start is a new
	 * occurrence identifier. An event without a start has no occurrence.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $fields The fields `Event::save_datetimes()` wrote.
	 *
	 * @return void
	 */
	public function sync_single( array $fields ): void {
		global $wpdb;

		$post_id = intval( $fields['post_id'] ?? 0 );

		if ( 1 > $post_id ) {
			return;
		}

		$this->delete_for_post( $post_id );

		$start = (string) ( $fields['datetime_start'] ?? '' );

		if ( '' === $start ) {
			return;
		}

		try {
			$timezone = Utility::normalize_timezone_string( (string) ( $fields['timezone'] ?? '' ) );
			$local    = new DateTimeImmutable( $start, new DateTimeZone( $timezone ) );
		} catch ( Exception $e ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- A plugin-owned table.
		$wpdb->insert(
			self::table(),
			array(
				'series_post_id'     => $post_id,
				'recurrence_id'      => self::recurrence_id( $local ),
				'datetime_start'     => $start,
				'datetime_start_gmt' => (string) ( $fields['datetime_start_gmt'] ?? '' ),
				'datetime_end'       => (string) ( $fields['datetime_end'] ?? '' ),
				'datetime_end_gmt'   => (string) ( $fields['datetime_end_gmt'] ?? '' ),
				'timezone'           => (string) ( $fields['timezone'] ?? '' ),
				'status'             => self::STATUS_SCHEDULED,
			)
		);
	}

	/**
	 * Remove every occurrence row of an event.
	 *
	 * Runs on `delete_post`, alongside the removal of the event's date row.
	 *
	 * @since TBD
	 *
	 * @param int $post_id An event post ID.
	 *
	 * @return void
	 */
	public function delete_for_post( int $post_id ): void {
		global $wpdb;

		if ( ! post_type_supports( (string) get_post_type( $post_id ), Event::SUPPORT ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A plugin-owned table.
		$wpdb->delete( self::table(), array( 'series_post_id' => $post_id ) );
	}
}
