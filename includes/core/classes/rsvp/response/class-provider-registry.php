<?php
/**
 * RSVP Provider Registry - Singleton registry for managing RSVP providers.
 *
 * This class provides a centralized registry for RSVP types, allowing plugins
 * to register new types without modifying core code. It uses the Singleton
 * pattern to ensure only one registry instance exists.
 *
 * @package GatherPress\Core\Rsvp\Response
 * @since 0.35.0
 */

namespace GatherPress\Core\Rsvp\Response;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Response\Provider\Provider;
use GatherPress\Core\Rsvp\Response\Provider\Email;
use GatherPress\Core\Rsvp\Response\Provider\User;
use GatherPress\Core\Traits\Singleton;
use InvalidArgumentException;

/**
 * Class RSVP Provider Registry - Singleton registry for managing RSVP providers.
 *
 * Manages registration and retrieval of RSVP type handlers (instances of Rsvp_Type).
 * Uses Singleton pattern for global access.
 *
 * @since 0.35.0
 */
final class Provider_Registry {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Array of registered RSVP type instances.
	 *
	 * @since 0.35.0
	 *
	 * @var array
	 */
	private array $providers = array();

	/**
	 * Class constructor.
	 *
	 * Initializes the registry and sets up hooks for registration.
	 *
	 * @since 0.35.0
	 */
	protected function __construct() {
		$this->setup_hooks();
		$this->register_core_types();
	}

	/**
	 * Set up hooks for RSVP type registration.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_loaded', array( $this, 'register_rsvp_providers' ), 5 );
	}

	/**
	 * Get a registered RSVP type by slug.
	 *
	 * @since 0.35.0
	 *
	 * @param string $slug The RSVP type slug.
	 *
	 * @return Provider|null The provider instance, or null if not registered.
	 */
	public static function from_slug( string $slug ): ?Provider {
		return self::get_instance()->get( $slug );
	}

	/**
	 * Register an RSVP type instance.
	 *
	 * Registers a type handler instance with the registry. If a type with the
	 * same slug already exists, it will be overwritten (allowing for type extension).
	 *
	 * @since 0.35.0
	 * @throws InvalidArgumentException If type instance is invalid.
	 *
	 * @param Provider $provider The RSVP provider instance to register.
	 *
	 * @return bool True if the provider was registered, false if the slug was already registered.
	 */
	public function register( Provider $provider ): bool {
		$slug = $provider->get_slug();

		if ( strlen( $slug ) < 4 ) {
			throw new InvalidArgumentException( 'The Provider\'s slug must string with more than four characters.' );
		}

		if ( $this->is_registered( $slug ) ) {
			return false;
		}

		$this->providers[ $slug ] = $provider;

		return true;
	}

	/**
	 * Check if an RSVP type is registered.
	 *
	 * @since 0.35.0
	 *
	 * @param string $slug The RSVP type slug.
	 *
	 * @return bool True if the type is registered, false otherwise.
	 */
	public function is_registered( string $slug ): bool {
		return isset( $this->providers[ $slug ] );
	}

	/**
	 * Get a registered RSVP type by slug.
	 *
	 * @since 0.35.0
	 *
	 * @param string $slug The RSVP type slug.
	 *
	 * @return Provider|null The type instance, or null if not registered.
	 */
	public function get( string $slug ): ?Provider {
		return $this->providers[ $slug ] ?? null;
	}

	/**
	 * Get all registered RSVP types.
	 *
	 * @since 0.35.0
	 *
	 * @return Provider[] Associative array of registered types, keyed by slug.
	 */
	public function get_all(): array {
		return $this->providers;
	}

	/**
	 * Get all registered RSVP type slugs.
	 *
	 * Useful for iteration or checking which types are available.
	 *
	 * @since 0.35.0
	 *
	 * @return string[] List of registered type slugs.
	 */
	public function get_slugs(): array {
		return array_keys( $this->providers );
	}

	/**
	 * Register core RSVP types (user and email).
	 *
	 * Called early during initialization to register the built-in RSVP types.
	 * Companion plugins should not hook into this; use the 'gatherpress_register_rsvp_types'
	 * action instead.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function register_core_types(): void {
		$this->register( new User() );
		$this->register( new Email() );
	}

	/**
	 * Fire the action for plugins to register their RSVP providers.
	 *
	 * Companion plugins should hook into 'gatherpress_register_rsvp_types' to register
	 * their types.  This method is called after core types are registered.
	 *
	 * @since 0.35.0
	 *
	 * @return void
	 */
	public function register_rsvp_providers(): void {
		/**
		 * Fires when RSVP providers are being registered.
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
		 * @since 0.35.0
		 *
		 * @param Provider_Registry $provider_registry The RSVP type registry instance.
		 */
		do_action( 'gatherpress_register_rsvp_types', $this );
	}
}
