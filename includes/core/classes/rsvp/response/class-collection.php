<?php
/**
 * Collection of RSVP objects.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore
/**
 * Collection of RSVP response states.
 *
 * @since 0.35.0
 */
final class Collection {

	/**
	 * States.
	 *
	 * @since 0.35.0
	 *
	 * @var State[]
	 */
	private array $states = array();

	/**
	 * Constructor.
	 *
	 * @since 0.35.0
	 *
	 * @param State[] $states States.
	 */
	public function __construct( array $states = array() ) {
		$this->states = $states;
	}

	/**
	 * Get all states.
	 *
	 * @since 0.35.0
	 *
	 * @return State[] All RSVP response states in the collection.
	 */
	public function all(): array {
		return $this->states;
	}

	/**
	 * Get attending states.
	 *
	 * @since 0.35.0
	 *
	 * @return State[] States with an attending status.
	 */
	public function attending(): array {
		return array_values(
			array_filter(
				$this->states,
				static fn( State $state ): bool => $state->is_attending()
			)
		);
	}

	/**
	 * Get waiting list, sorted by timestamp.
	 *
	 * @since 0.35.0
	 *
	 * @return State[] States with a waiting list status, oldest first.
	 */
	public function waiting_list(): array {
		$waiting_list = array_values(
			array_filter(
				$this->states,
				static fn( State $state ): bool => $state->is_waiting_list()
			)
		);

		usort(
			$waiting_list,
			fn( State $a, State $b )
				=> $a->data->timestamp <=> $b->data->timestamp
		);

		return $waiting_list;
	}

	/**
	 * Get attending count.
	 *
	 * @since 0.35.0
	 *
	 * @return int The number of attending responses.
	 */
	public function attending_count(): int {
		return count( $this->attending() );
	}

	/**
	 * Check if there are any responses on waiting list.
	 *
	 * @since 0.35.0
	 *
	 * @return bool True if at least one response is on the waiting list, false otherwise.
	 */
	public function has_waiting_list(): bool {
		foreach ( $this->states as $state ) {
			if ( $state->is_waiting_list() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Count the number of attendees, including their guests, that are confirmed.
	 *
	 * @since 0.35.0
	 *
	 * @return int The total number of confirmed attendees including guests.
	 */
	public function get_attendee_count(): int {
		$count = 0;

		foreach ( $this->states as $state ) {
			if ( $state->is_attending() ) {
				$count += $state->get_attendee_count();
			}
		}

		return $count;
	}

	/**
	 * Get waiting list count.
	 *
	 * @since 0.35.0
	 *
	 * @return int The number of responses on the waiting list.
	 */
	public function waiting_list_count(): int {
		return count( $this->waiting_list() );
	}

	/**
	 * Get available spots.
	 *
	 * @since 0.35.0
	 *
	 * @param int|null $limit Attendance limit.
	 *
	 * @return int The number of available spots.
	 */
	public function available_spots( ?int $limit ): int {
		if ( empty( $limit ) ) {
			return $this->waiting_list_count();
		}

		return max( 0, $limit - $this->attending_count() );
	}
}
