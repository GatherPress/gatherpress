<?php
/**
 * One entry in an occurrence-aware list.
 *
 * Occurrence identity travels on this object and never by list position, array
 * index, or a side-channel map keyed by query. A null `recurrence_id` means a
 * non-recurring event, so a single ordered list interleaves both kinds and
 * every entry knows which it is.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Core\Event\Recurrence;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Occurrence_Ref.
 *
 * Immutable value object. Identity is the composite
 * `(post_id, recurrence_id)`, never insertion order and never an autoincrement.
 *
 * @since 0.36.0
 */
final class Occurrence_Ref {

	/**
	 * Class constructor.
	 *
	 * @since 0.36.0
	 *
	 * @param int         $post_id            Series post ID.
	 * @param string|null $recurrence_id      Occurrence identifier in `Ymd\THis` form, or null when not recurring.
	 * @param string      $datetime_start_gmt Occurrence start in GMT, `Y-m-d H:i:s` form.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly ?string $recurrence_id,
		public readonly string $datetime_start_gmt
	) {
	}
}
