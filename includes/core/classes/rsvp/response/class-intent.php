<?php
/**
 * RSVP Response intent.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit;

use GatherPress\Core\Rsvp\Response\Provider\Provider;

/**
 * RSVP Response request.
 *
 * @package GatherPress\Core\Rsvp
 * @since 1.0.0
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
}
