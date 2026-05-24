<?php
/**
 * Collection of RSVP objects.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit;

/**
 * Class with methods to serialize RSVP Response objects
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */
final class Collection {
	/**
	 * States.
	 *
	 * @var State[]
	 */
	private array $states = array();

	/**
	 * Constructor.
	 *
	 * @param State[] $states States.
	 */
	public function __construct( array $states = array() ) {
		$this->states = $states;
	}

	/**
	 * Get all states.
	 *
	 * @return State[]
	 */
	public function all(): array {
		return $this->states;
	}

	/**
	 * Get attending states.
	 *
	 * @return State[]
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
	 * @return State[]
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
	 * @return int
	 */
	public function attending_count(): int {
		return \count( $this->attending() );
	}

	/**
	 * Check if there are any responses on waiting list.
	 *
	 * @return bool
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
	 * Count the number of attendees incl. theirs guests that are confirmed.
	 *
	 * @return int
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
	 * @return int
	 */
	public function waiting_list_count(): int {
		return \count( $this->waiting_list() );
	}

	/**
	 * Get available spots.
	 *
	 * @param int|null $limit Attendance limit.
	 *
	 * @return int
	 */
	public function available_spots( ?int $limit ): int {
		if ( empty( $limit ) ) {
			return $this->waiting_list_count();
		}

		return max( 0, $limit - $this->attending_count() );
	}
}
