<?php
/**
 * RSVP Type Manager - Singleton registry for managing RSVP types.
 *
 * This class provides a centralized registry for RSVP types, allowing plugins
 * to register new types without modifying core code. It uses the Singleton
 * pattern to ensure only one registry instance exists.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
\defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Type\Base as Rsvp_Type;
use GatherPress\Core\Traits\Singleton;

/**
 * Class Manager.
 *
 * Manages registration and retrieval of RSVP type handlers (instances of Rsvp_Type).
 * Uses Singleton pattern for global access.
 *
 * @since 1. 0.0
 */
class Manager {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Array of registered RSVP type instances.
	 *
	 * @var Rsvp_Type[]
	 */
	private array $types = array();

	/**
	 * Class constructor.
	 *
	 * Initializes the registry and sets up hooks for registration.
	 *
	 * @since 1.0.0
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

		if ( empty( $slug ) || ! \is_string( $slug ) ) {
			return;
		}

		if ( isset( $this->types[ $slug ] ) ) {
			return;
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
	public function get( string $slug ): ?Rsvp_Type {
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
		$this->register( new Type\User() );
		$this->register( new Type\Email() );
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
		 * @param Registry The RSVP type registry instance.
		 */
		do_action( 'gatherpress_register_rsvp_types', $this );
	}
}
