<?php
/**
 * Sitewide Endpoint.
 *
 * This file defines the `Sitewide` class, which extends the base `Endpoint`
 * class and provides support for global endpoints that are not attached to a
 * specific post type or taxonomy.
 *
 * Example:
 * - /feed/ical
 * - /calendar/json
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Core\Calendar;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Endpoint for Sitewide Requests in GatherPress.
 *
 * The `Sitewide` class extends the base `Endpoint` class to provide
 * custom endpoints that exist globally across the site rather than being tied
 * to a specific object type.
 *
 * @since 1.0.0
 */
class Sitewide_Feed extends Endpoint {

	/**
	 * Class constructor.
	 *
	 * Initializes a sitewide endpoint.
	 *
	 * Example:
	 * - /feed/ical
	 * - /calendar/json
	 *
	 * @since 1.0.0
	 *
	 * @param Endpoint_Type[] $types     List of endpoint types (templates/redirects).
	 * @param string          $query_var The query variable used to identify the endpoint.
	 */
	public function __construct(
		array $types,
		string $query_var
	) {
		// Example:
		// 'feed/(ical)(/)'.
		$reg_ex = 'feed/(%s)/?$';

		parent::__construct(
			$query_var,
			'',
			array( $this, 'is_valid' ),
			$types,
			$reg_ex,
			'sitewide',
		);
	}

	/**
	 * Build the regular expression pattern for matching the custom endpoint URL structure,
	 * based on the rewrite base for the site.
	 *
	 * @since 1.0.0
	 *
	 * @return string The compiled regex pattern.
	 */
	protected function get_regex_pattern(): string {
		$slugs = join( '|', $this->get_slugs() );
		return sprintf(
			$this->reg_ex,
			$slugs
		);
	}

	/**
	 * Determines whether the current request is valid.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if this is a valid feed request.
	 */
	public function is_valid(): bool {
		return is_feed() && ! is_singular() && ! is_tax() && ! is_post_type_archive();
	}

	/**
	 * Defines rewrite attributes.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_rewrite_atts(): array {
		return array(
			'feed'           => '$matches[1]',
			$this->query_var => '$matches[1]',
		);
	}
}
