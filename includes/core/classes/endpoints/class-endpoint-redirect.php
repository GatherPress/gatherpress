<?php
/**
 * Class responsible for handling redirect-based endpoints in GatherPress.
 *
 * This file defines the `Endpoint_Redirect` class, which extends the base `Endpoint_Type`
 * class and manages URL redirection for specific endpoints. It safely redirects users
 * to external URLs based on the logic provided by the callback function, while ensuring
 * the redirection is secure and follows WordPress's allowed hosts policy.
 *
 * @package GatherPress\Core\Endpoints
 * @since 1.0.0
 */

namespace GatherPress\Core\Endpoints;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Handles safe URL redirection for custom endpoints in GatherPress.
 *
 * The `Endpoint_Redirect` class extends the `Endpoint_Type` class and is responsible
 * for managing URL redirection for specific endpoints. It handles:
 * - Getting the redirect URL through a callback.
 * - Safely redirecting users using `wp_safe_redirect()`.
 * - Filtering allowed redirect hosts to ensure security.
 *
 * @since 1.0.0
 */
class Endpoint_Redirect extends Endpoint_Type {

	/**
	 * The target URL for the redirection.
	 *
	 * This property stores the URL that the user will be redirected to when the endpoint
	 * is triggered. The URL is generated by calling the callback function provided during
	 * the endpoint's construction.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Activate Endpoint_Type by hooking into relevant parts.
	 *
	 * Safely redirects the user to the specified URL.
	 *
	 * This method gets the target URL by calling the callback function and then
	 * safely redirects the user to that URL using `wp_safe_redirect()`.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function activate(): void {
		$this->url = call_user_func( $this->callback );
		if ( $this->url ) {
			// Add the target host to the list of allowed redirect hosts.
			add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_redirect_hosts' ) );
			// Perform a safe redirection to the target URL. Defaults to a 302 status code.
			wp_safe_redirect( $this->url );
			exit; // Always exit after redirecting.
		}
	}

	/**
	 * Filters the list of allowed hosts to include the redirect target.
	 *
	 * This method ensures that the host of the target URL is added to the list of allowed
	 * redirect hosts, allowing the redirection to proceed safely. It is hooked into the
	 * `allowed_redirect_hosts` filter, which controls the domains that `wp_safe_redirect()`
	 * is allowed to redirect to.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/allowed_redirect_hosts/
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $hosts An array of allowed host names.
	 * @return string[]       The updated array of allowed host names, including the redirect target.
	 */
	public function allowed_redirect_hosts( array $hosts ): array {
		return array_merge(
			$hosts,
			array(
				wp_parse_url( $this->url, PHP_URL_HOST ),
			)
		);
	}
}
