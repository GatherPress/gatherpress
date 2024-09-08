<?php
/**
 * Endpoint Class for Custom Rewrite Rules and Query Handling in GatherPress.
 *
 * This file defines the `Endpoint` class, which is responsible for managing
 * custom rewrite rules, query variables, and template redirects for endpoints
 * tied to specific post types or taxonomies in the GatherPress plugin.
 *
 * @package GatherPress\Core\Endpoints
 * @since 1.0.0
 */

namespace GatherPress\Core\Endpoints;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Manages Custom Endpoints for Post Types and Taxonomies in GatherPress.
 *
 * The `Endpoint` class provides functionality for registering custom endpoints
 * based on post types or taxonomies. It defines rewrite rules, adds custom query
 * variables, and handles both template redirects and custom templates. This class
 * is responsible for:
 *
 * The class supports registering endpoints for custom slugs, handling validation
 * callbacks, and dynamically redirecting or rendering templates based on the request.
 *
 * @since 1.0.0
 */
class Endpoint {

	/**
	 * Internal, non-public, name of the query variable used to identify the endpoint.
	 *
	 * This property holds the custom query variable name that will be appended to the
	 * endpoint's URL. It is used to differentiate the various types of endpoints.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $query_var;

	/**
	 * Holds the registered WP_Post_Type or WP_Taxonomy object.
	 *
	 * Depending on the `object_type` parameter, this property will either store
	 * a `WP_Post_Type` object for a post type or a `WP_Taxonomy` object for a taxonomy.
	 * This is used to generate the correct rewrite rules and handle URL matching.
	 *
	 * @since 1.0.0
	 *
	 * @var WP_Post_Type|WP_Taxonomy
	 */
	public $type_object;

	/**
	 * Callback function used to validate requests made to the endpoint.
	 *
	 * The validation callback is used during template redirects and query validation
	 * to ensure that the endpoint request meets certain conditions before proceeding.
	 *
	 * @since 1.0.0
	 *
	 * @var callable
	 */
	public $validation_callback;

	/**
	 * List of configured endpoint resolvers.
	 *
	 * This property holds an array of endpoint types such as `Endpoint_Redirect` and
	 * `Endpoint_Template`, which determine how the endpoint behaves (e.g., whether it
	 * redirects or serves a template).
	 *
	 * @since 1.0.0
	 *
	 * @var Endpoint_Type[]
	 */
	public $types;

	/**
	 * Regular expression used to match the endpoint URL structure.
	 *
	 * This property holds the regular expression pattern that will be used to define the
	 * endpoint URL structure. It is combined with the post type or taxonomy rewrite base
	 * and the slug of the endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $reg_ex;

	/**
	 * Class constructor.
	 *
	 * Initializes the endpoint by setting up necessary properties and ensuring
	 * that the provided object type is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param string          $query_var           The query variable used in WP_Query to identify the endpoint.
	 * @param string          $type_name           The name of the post type or taxonomy this endpoint operates on.
	 * @param callable        $validation_callback Callback function used to validate requests made to the endpoint.
	 * @param Endpoint_Type[] $types               List of endpoint types (such as redirects or templates) supported.
	 * @param string          $reg_ex              Regular expression pattern for matching URLs handled by the endpoint.
	 * @param string          $object_type         Type of object, either 'post' (default) or 'taxonomy'.
	 */
	public function __construct(
		string $query_var,
		string $type_name,
		callable $validation_callback,
		array $types,
		string $reg_ex,
		string $object_type = 'post',
	) {
		// ...
		if ( $this->is_valid_registration( $type_name, $types, $object_type ) ) {

			$this->query_var           = $query_var;
			$this->validation_callback = $validation_callback;
			$this->types               = $types;
			$this->reg_ex              = $reg_ex;

			$this->hook_prio = 11; // @todo make dynamic: current-prio + 1

			// ..
			$this->setup_hooks();
		}
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'init', array( $this, 'init' ), $this->hook_prio );
	}

	/**
	 * Initializes the endpoint by registering rewrite rules and handling query variables.
	 *
	 * The method generates rewrite rules for the endpoint based on the post type or taxonomy rewrite base
	 * and matches against the provided slugs. It also filters allowed query variables to include the custom query variable for the endpoint.
	 * The method hooks into the `template_redirect` action to handles template loading
	 * or redirecting based on the endpoint type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {

		// Retrieve the rewrite base (slug) for the post type or taxonomy.
		$rewrite_base = $this->type_object->rewrite['slug'];
		$slugs        = join( '|', $this->get_slugs() );
		// Build the regular expression pattern for matching the custom endpoint URL structure.
		$reg_ex = sprintf(
			$this->reg_ex,
			$rewrite_base,
			$slugs
		);
		// Define the URL structure for handling matched requests via query vars.
		// Example result: 'index.php?gatherpress_event=$matches[1]&gatherpress_ext_calendar=$matches[2]'.
		$rewrite_url = $this->get_rewrite_url();

		// Add the rewrite rule to WordPress.
		add_rewrite_rule( $reg_ex, $rewrite_url, 'top' );

		// Allow the custom query variable by filtering the public query vars.
		add_filter( 'query_vars', array( $this, 'allow_query_vars' ) );

		// Handle whether to include a template or redirect the request.
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
	}

	public function get_rewrite_url() : string {
		return add_query_arg(
			array(
				$this->type_object->name => '$matches[1]',
				$this->query_var         => '$matches[2]',
			),
			'index.php'
		);
	}

	/**
	 * Validates the registration of the endpoint based on timing, object type and given endpoint types.
	 *
	 * This method ensures that:
	 * - The action `init` has been fired, meaning the WordPress environment is fully set up.
	 * - The provided object type (post type or taxonomy) is registered.
	 * - Rewrites are enabled for the object type (e.g., post type or taxonomy) to support custom endpoints.
	 *
	 * If the validation fails, appropriate warnings are triggered using `wp_trigger_error()`.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type_name   The name of the post type or taxonomy to validate.
	 * @param array  $types       Array of endpoint types to register (redirects/templates).
	 * @param string $object_type The type of object ('post' or 'taxonomy').
	 * @return bool               Returns true if registration is valid, false otherwise.
	 */
	private function is_valid_registration( string $type_name, array $types, string $object_type ): bool {
		if ( 0 === did_action( 'init' ) ) {
			wp_trigger_error(
				__CLASS__,
				'was called too early! Run on init:11 to make all the rewrite-vodoo work.',
				E_USER_WARNING
			);
			return false;
		}
		if ( empty( $types ) ) {
			wp_trigger_error(
				__CLASS__,
				'can not be called without endpoint types. Add at least one of either "Endpoint_Redirect" or "Endpoint_Template" to the list of types.',
				E_USER_WARNING
			);
			return false;
		}
		switch ( $object_type ) {
			case 'taxonomy':
				if ( 0 === did_action( sprintf( 'registered_taxonomy_%s', $type_name ) ) ) {
					wp_trigger_error(
						__CLASS__,
						"was called too early! Make sure '$type_name' is already registered.",
						E_USER_WARNING
					);
					return false;
				}
				if ( false === get_taxonomy( $type_name )->rewrite ) {
					wp_trigger_error(
						__CLASS__,
						"called on '$type_name' doesn't work, because this taxonomy has rewrites disabled.",
						E_USER_WARNING
					);
					return false;
				}
				// Store the validated taxonomy object for later use.
				$this->type_object = get_taxonomy( $type_name );

			case 'post':
			default:
				if ( 0 === did_action( sprintf( 'registered_post_type_%s', $type_name ) ) ) {
					wp_trigger_error(
						__CLASS__,
						"was called too early! Make sure '$type_name' is already registered.",
						E_USER_WARNING
					);
					return false;
				}
				if ( false === get_post_type_object( $type_name )->rewrite ) {
					wp_trigger_error(
						__CLASS__,
						"called on '$type_name' doesn't work, because this post type has rewrites disabled.",
						E_USER_WARNING
					);
					return false;
				}
				// Store the validated post type object for later use.
				$this->type_object = get_post_type_object( $type_name );
		}
		return true;
	}

	/**
	 * Filters the query variables allowed before processing.
	 *
	 * Adds the custom query variable used by the endpoint to the list of allowed
	 * public query variables so that it can be recognized and used by WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $public_query_vars The array of allowed query variable names.
	 * @return string[]                   The updated array of allowed query variable names.
	 */
	public function allow_query_vars( array $public_query_vars ): array {
		$public_query_vars[] = $this->query_var;
		return $public_query_vars;
	}

	/**
	 * Fires before determining which template to load or whether to redirect.
	 *
	 * This method is responsible for:
	 * - Validating the query to ensure the endpoint is correctly matched.
	 * - Performing redirects if the current endpoint has associated redirects.
	 * - Loading a custom template if the endpoint defines one.
	 *
	 * @since 1.0.0
	 *
	 * @see https://developer.wordpress.org/reference/hooks/template_redirect/
	 *
	 * @return void
	 */
	public function template_redirect(): void {

		if ( ! $this->is_valid_query() ) {
			return;
		}

		$endpoint_type = current(
			wp_list_filter(
				$this->types,
				array(
					'slug' => get_query_var( $this->query_var ),
				)
			)
		);
		if ( $this->has_redirects() ) {
			$endpoint_type->redirect_to();
		}

		if ( $this->has_templates() ) {
			// Filters the path of the current template before including it.
			add_filter( 'template_include', array( $endpoint_type, 'template_include' ) );
		}
	}

	/**
	 * Checks if the current query is valid for this endpoint.
	 *
	 * This method uses the validation callback provided during construction
	 * to ensure that the query is valid. It also checks if the custom query
	 * variable is populated.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the query is valid, false otherwise.
	 */
	public function is_valid_query(): bool {
		return call_user_func( $this->validation_callback ) && ! empty( get_query_var( $this->query_var ) );
	}

	/**
	 * Checks if the currently requested endpoint has redirects attached.
	 *
	 * This method determines if the endpoint has an associated redirect based on
	 * the custom query variable and the list of available redirect slugs.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the endpoint has a redirect, false otherwise.
	 */
	public function has_redirects(): bool {
		return in_array(
			get_query_var( $this->query_var ),
			$this->get_slugs( __NAMESPACE__ . '\Endpoint_Redirect' ),
			true
		);
	}

	/**
	 * Checks if the currently requested endpoint has templates to load.
	 *
	 * This method determines if the endpoint has an associated template based on
	 * the custom query variable and the list of available template slugs.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the endpoint has a template, false otherwise.
	 */
	public function has_templates(): bool {
		return in_array(
			get_query_var( $this->query_var ),
			$this->get_slugs( __NAMESPACE__ . '\Endpoint_Template' ),
			true
		);
	}

	/**
	 * Retrieves the slugs of the specified endpoint types.
	 *
	 * This method filters the `types` array to get the slugs for either a specific type of endpoint
	 * (e.g., `Endpoint_Redirect` or `Endpoint_Template`) or returns slugs for all types if no type
	 * is specified.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $entity Optional. The class name of the endpoint type to filter by (e.g., 'Endpoint_Redirect' or 'Endpoint_Template').
	 *                            If null, it retrieves slugs for all types.
	 * @return string[]           An array of slugs for the specified or all types.
	 */
	protected function get_slugs( string|null $entity = null ): array {
		// Determine Enpoint_Types to get slug names from.
		$types = ( null === $entity )
			// All?
			? $this->types
			// Or a specific type?
			: array_filter(
				$this->types,
				function ( $type ) use ( $entity ) {
					return $type instanceof $entity;
				}
			);
		return wp_list_pluck( $types, 'slug' );
	}
}
