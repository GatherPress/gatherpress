<?php
/**
 * One date of an event.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use DateTimeImmutable;

/**
 * Class Occurrence.
 *
 * An event post is a series, and an occurrence is one of its dates. An event
 * without a repeat rule is a series of one, so it has exactly one occurrence.
 *
 * @since TBD
 */
final class Occurrence {
	/**
	 * Build an occurrence.
	 *
	 * @since TBD
	 *
	 * @param int               $series_post_id The event post the occurrence belongs to.
	 * @param string            $recurrence_id  The occurrence's key within its series.
	 * @param DateTimeImmutable $start          The start, in the event's timezone.
	 * @param DateTimeImmutable $end            The end, in the event's timezone.
	 * @param bool              $is_all_day     Whether the occurrence lasts all day.
	 * @param string            $status         The occurrence's status, one of the event status slugs.
	 */
	public function __construct(
		public readonly int $series_post_id,
		public readonly string $recurrence_id,
		public readonly DateTimeImmutable $start,
		public readonly DateTimeImmutable $end,
		public readonly bool $is_all_day = false,
		public readonly string $status = Status::STATUS_SCHEDULED
	) {}

	/**
	 * Whether the occurrence is canceled.
	 *
	 * @since TBD
	 *
	 * @return bool True when the occurrence is canceled.
	 */
	public function is_canceled(): bool {
		return Status::STATUS_CANCELED === $this->status;
	}
}
