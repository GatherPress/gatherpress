<?php
/**
 * The repeat rules of an event.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Event;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Rules.
 *
 * An event's repeat rules, from its `gatherpress_rule` meta, one row per rule.
 * An event without rules is a series of one, read from its saved dates.
 *
 * @since TBD
 */
final class Rules {

	/**
	 * Post meta key holding the rules, one row per rule.
	 *
	 * @since TBD
	 * @var string
	 */
	const META_KEY = 'gatherpress_rule';

	/**
	 * The `FREQ` values a rule supports.
	 *
	 * @since TBD
	 * @var string[]
	 */
	const FREQUENCIES = array( 'DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY' );

	/**
	 * The weekday codes, in week order from Monday.
	 *
	 * @since TBD
	 * @var string[]
	 */
	const WEEKDAYS = array( 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' );

	/**
	 * The valid rules, once read.
	 *
	 * @since TBD
	 * @var Rule[]|null
	 */
	protected ?array $rules = null;

	/**
	 * Build the rules of an event.
	 *
	 * @since TBD
	 *
	 * @param Event $event The event.
	 */
	public function __construct( protected readonly Event $event ) {}

	/**
	 * The event's valid rules, in the order they were saved.
	 *
	 * A row that is not a valid rule is left out.
	 *
	 * @since TBD
	 *
	 * @return Rule[] The rules, empty when the event doesn't repeat.
	 */
	public function all(): array {
		if ( null !== $this->rules ) {
			return $this->rules;
		}

		// Not single: the key holds one row per rule.
		$rows        = $this->event->post ? get_post_meta( $this->event->post->ID, self::META_KEY, false ) : array();
		$this->rules = array_values( array_filter( array_map( array( Rule::class, 'from_meta' ), (array) $rows ) ) );

		return $this->rules;
	}
}
