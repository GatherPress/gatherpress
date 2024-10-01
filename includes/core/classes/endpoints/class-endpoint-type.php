<?php
/**
 * Abstract class for defining custom endpoint types in GatherPress.
 *
 * This file contains the `Endpoint_Type` abstract class, which serves as a base
 * class for custom endpoint types (e.g., redirects, templates) in GatherPress.
 * Subclasses are expected to define specific behavior for different types of endpoints.
 *
 * @package GatherPress\Core\Endpoints
 * @since 1.0.0
 */

namespace GatherPress\Core\Endpoints;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Abstract class for defining custom endpoint behavior.
 *
 * The `Endpoint_Type` class is an abstract base class that provides the foundational
 * structure for custom endpoint types in GatherPress. It defines the core properties
 * of an endpoint, such as the `slug` and the `callback`, and requires subclasses
 * to implement their own specific behavior.
 *
 * This class is designed to be extended by specific endpoint types, such as:
 * - `Endpoint_Redirect`: Handles URL redirection for endpoints.
 * - `Endpoint_Template`: Handles loading custom templates for endpoints.
 *
 * @since 1.0.0
 * @package GatherPress\Core
 * @subpackage Endpoints
 */
abstract class Endpoint_Type {

	/**
	 * The publicly visible name (slug) of the endpoint.
	 *
	 * This property stores the slug used in the URL to identify the endpoint. It is
	 * what differentiates one type of endpoint from another in the URL structure (e.g.,
	 * `event/my-sample-event/googlecalendar` or `event/my-sample-event/ical`).
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Callback function to handle the endpoint behavior.
	 *
	 * This property holds a callable (callback) that is executed when the endpoint is
	 * matched. The specific behavior of the endpoint (e.g., redirecting or rendering a
	 * template) is handled by this callback, which is passed when the endpoint is created.
	 *
	 * @since 1.0.0
	 *
	 * @var callable
	 */
	protected $callback;

	/**
	 * Class constructor.
	 *
	 * Initializes the `Endpoint_Type` object by setting the publicly visible slug for
	 * the endpoint and the callback function that defines the endpoint's behavior.
	 * Subclasses will use this constructor to set up specific types of endpoints.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $slug     The slug used to identify the endpoint in the URL.
	 * @param callable $callback The callback function that handles the endpoint logic.
	 */
	public function __construct(
		string $slug,
		callable $callback
	) {
		$this->slug     = $slug;
		$this->callback = $callback;
	}

	/**
	 * Activate Endpoint_Type by hooking into relevant parts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	abstract public function activate(): void;


	/**
	 * Checks if the given endpoint type is an instance of the specified class.
	 *
	 * This method verifies whether the provided `$type` is an instance of the `$entity`
	 * class. It first checks if the `$entity` exists in the defined list of valid endpoint
	 * classes by calling `is_in_class()`. If the entity is valid, it further checks if the
	 * `$type` is an instance of that class.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $entity The class name of the entity to check against (e.g., 'Endpoint_Redirect' or 'Endpoint_Template').
	 * @return bool                  True if the `$type` is an instance of the `$entity` class, false otherwise.
	 */
	public function is_of_class( string $entity ): bool {
		return self::is_in_class( $entity ) && $this instanceof $entity;
	}

	/**
	 * Checks if the given entity is a valid endpoint class in the current namespace.
	 *
	 * This method verifies whether the provided `$entity` exists in the predefined list
	 * of valid endpoint classes within the current namespace. It helps ensure that only
	 * valid classes (like `Endpoint_Redirect` or `Endpoint_Template`) are used when
	 * checking endpoint types.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $entity The class name of the entity to check (e.g., 'Endpoint_Redirect' or 'Endpoint_Template').
	 * @return bool           True if the `$entity` is a valid endpoint class, false otherwise.
	 */
	private static function is_in_class( string $entity ): bool {
		return in_array(
			$entity,
			array(
				__NAMESPACE__ . '\Endpoint_Redirect',
				__NAMESPACE__ . '\Endpoint_Template',
			),
			true
		);
	}
}
