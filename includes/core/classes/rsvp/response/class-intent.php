<?php
/**
 * RSVP Response intent.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore
use GatherPress\Core\Rsvp\Response\Provider\Provider;

/**
 * RSVP Response intent.
 *
 * @since 0.35.0
 */
final class Intent {
	/**
	 * Compose a request of an RSVP response (not saved, but the intent).
	 *
	 * @since 0.35.0
	 *
	 * @param Data     $data      The data value object of the RSVP response.
	 * @param Provider $provider  The RSVP provider that issues the RSVP response/intent.
	 */
	public function __construct(
		public readonly Data $data,
		public readonly Provider $provider
	) {}

	/**
	 * Generate an intent to change the state of a saved RSVP.
	 *
	 * @since 0.35.0
	 *
	 * @param State $state The desired state.
	 *
	 * @return Intent An intent with the attending status applied.
	 */
	public static function attend( State $state ): self {
		return new self(
			$state->data->with_status( Status::ATTENDING ),
			$state->provider,
		);
	}
}
