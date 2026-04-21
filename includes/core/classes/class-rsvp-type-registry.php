<?php
/**
 * RSVP Type Registry - Singleton registry for managing RSVP types.
 *
 * This class provides a centralized registry for RSVP types, allowing plugins
 * to register new types without modifying core code. It uses the Singleton
 * pattern to ensure only one registry instance exists.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Rsvp_Type_Registry.
 *
 * Manages registration and retrieval of RSVP type handlers (instances of Rsvp_Type).
 * Uses Singleton pattern for global access.
 *
 * @since 1. 0.0
 */
class Rsvp_Type_Registry {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Array of registered RSVP type instances.
	 *
	 * @since 1.0. 0
	 * @var Rsvp_Type[]
	 */
	private array $types = array();

	/**
	 * Class constructor.
	 *
	 * Initializes the registry and sets up hooks for registration.
	 *
	 * @since 1.0. 0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for RSVP type registration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_loaded', array( $this, 'register_core_types' ), 1 );
		add_action( 'gatherpress_loaded', array( $this, 'do_register_action' ), 5 );
	}

	/**
	 * Register an RSVP type instance.
	 *
	 * Registers a type handler instance with the registry. If a type with the
	 * same slug already exists, it will be overwritten (allowing for type extension).
	 *
	 * @since 1.0.0
	 *
	 * @param Rsvp_Type $type The RSVP type instance to register.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If type instance is invalid.
	 */
	public function register( Rsvp_Type $type ): void {
		$slug = $type->get_slug();

		if ( empty( $slug ) || ! is_string( $slug ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'RSVP type slug must be a non-empty string. ', 'gatherpress' ),
				'gatherpress'
			);
			return;
		}

		if ( isset( $this->types[ $slug ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s is the type slug */
					esc_html__( 'RSVP type "%s" is already registered.  Overwriting... ', 'gatherpress' ),
					esc_html( $slug )
				),
				'gatherpress'
			);
		}

		$this->types[ $slug ] = $type;
	}

	/**
	 * Check if an RSVP type is registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The RSVP type slug.
	 *
	 * @return bool True if the type is registered, false otherwise.
	 */
	public function is_registered( string $slug ): bool {
		return isset( $this->types[ $slug ] );
	}

	/**
	 * Get a registered RSVP type by slug.
	 *
	 * @since 1.0. 0
	 *
	 * @param string $slug The RSVP type slug.
	 *
	 * @return Rsvp_Type|null The type instance, or null if not registered.
	 */
	public function get( string $slug ): ? Rsvp_Type {
		return $this->types[ $slug ] ?? null;
	}

	/**
	 * Get all registered RSVP types.
	 *
	 * @since 1.0.0
	 *
	 * @return Rsvp_Type[] Associative array of registered types, keyed by slug.
	 */
	public function get_all(): array {
		return $this->types;
	}

	/**
	 * Get all registered RSVP type slugs.
	 *
	 * Useful for iteration or checking which types are available.
	 *
	 * @since 1.0. 0
	 *
	 * @return string[] List of registered type slugs.
	 */
	public function get_slugs(): array {
		return array_keys( $this->types );
	}

	/**
	 * Register core RSVP types (user and email).
	 *
	 * Called early during initialization to register the built-in RSVP types.
	 * Companion plugins should not hook into this; use the 'gatherpress_register_rsvp_types'
	 * action instead.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_core_types(): void {
		$this->register( new Rsvp_Types\User_Type() );
		$this->register( new Rsvp_Types\Email_Type() );
	}

	/**
	 * Fire the action for plugins to register their RSVP types.
	 *
	 * Companion plugins should hook into 'gatherpress_register_rsvp_types' to register
	 * their types.  This method is called after core types are registered.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function do_register_action(): void {
		/**
		 * Fires when RSVP types are being registered.
		 *
		 * Plugins should use this hook to register their custom RSVP types by calling
		 * $registry->register( new My_Custom_Rsvp_Type() ).
		 *
		 * Example:
		 * ```php
		 * add_action( 'gatherpress_register_rsvp_types', function( $registry ) {
		 *     $registry->register( new My_Plugin\Rsvp_Type() );
		 * });
		 * ```
		 *
		 * @since 1.0.0
		 *
		 * @param Rsvp_Type_Registry $registry The RSVP type registry instance.
		 */
		do_action( 'gatherpress_register_rsvp_types', $this );
	}

	/**
	 * Alias for backward compatibility / deprecated filter access.
	 *
	 * Returns registered types as a flat array for plugins using the legacy
	 * 'gatherpress_rsvp_types' filter.  Internal use only.
	 *
	 * @since 1. 0.0
	 * @deprecated Use get_all() instead
	 * @internal
	 *
	 * @return array Array of types in legacy format.
	 */
	public function to_legacy_array(): array {
		$types = array();

		foreach ( $this->types as $type ) {
			$types[ $type->get_slug() ] = array(
				'label'              => $type->get_label(),
				'description'        => $type->get_description(),
				'icon'               => $type->get_icon(),
				'supports_guests'    => $type->supports_guests(),
				'supports_anonymous' => $type->supports_anonymous(),
				// Callbacks point to type methods
				'get_display_name'   => array( $type, 'get_display_name' ),
				'get_avatar_url'     => array( $type, 'get_avatar_url' ),
				'get_profile_url'    => array( $type, 'get_profile_url' ),
				'is_valid_identifier' => array( $type, 'is_valid_identifier' ),
				// Direct instance access for advanced use cases
				'instance'           => $type,
			);
		}

		/**
		 * Filter: Modify RSVP types (deprecated).
		 *
		 * @deprecated Use 'gatherpress_register_rsvp_types' action instead.
		 * @param array $types Associative array of RSVP types.
		 */
		return apply_filters( 'gatherpress_rsvp_types', $types );
	}
}