<?php
/**
 * RSVP Response intent.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit;

use GatherPress\Core\Rsvp\Response\Provider\Provider;

/**
 * RSVP Response request.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.35.0
 */
final class Intent {
	/**
	 * Compose a request of an RSVP response (not saved, but the intent).
	 *
	 * @param Data     $data      The data value object of the RSVP response.
	 * @param Provider $provider  The RSVP provider that issues the RSVP response/intent.
	 */
	public function __construct(
		public readonly Data $data,
		public readonly Provider $provider
	) {}

	/**
	 * Generate an intent to change the state of an saved RSVP.
	 *
	 * @param State $state The desired state.
	 *
	 * @return Intent
	 */
	public static function attend( State $state ): self {
		return new self(
			$state->data->with_status( Status::ATTENDING ),
			$state->provider,
		);
	}
}
