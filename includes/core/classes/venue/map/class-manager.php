<?php
/**
 * Venue map provider registry.
 *
 * Singleton registry that owns the provider instances backing the static
 * map pipeline. Mirrors the {@see \GatherPress\Core\Rsvp\Manager} pattern:
 * a per-request registry that hooks `gatherpress_loaded` to register
 * built-in providers (OSM today) and fire a follow-on action so companion
 * plugins can register their own.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 1.0.0
 */

namespace GatherPress\Core\Venue\Map;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Map\Provider\Base as Map_Provider;
use GatherPress\Core\Venue\Map\Provider\OSM;

/**
 * Class Manager.
 *
 * Provider registry. `register_core_providers()` registers OSM as the
 * always-available default. Companion plugins can register additional
 * providers (Google, MapBox, MapTiler, etc.) by hooking
 * `gatherpress_register_map_providers` and calling `register()` on the
 * passed Manager instance — same pattern as RSVP types.
 *
 * The active provider is resolved from the `map_platform` setting at
 * `Settings → Venues → Maps`. If that setting points at a slug that no
 * registered provider claims (e.g. Google before its provider class lands
 * in #1528), the manager logs a `_doing_it_wrong()` and falls back to OSM
 * so the front end keeps rendering.
 *
 * @since 1.0.0
 */
class Manager {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Registered provider instances keyed by slug.
	 *
	 * @since 1.0.0
	 * @var Map_Provider[]
	 */
	private array $providers = array();

	/**
	 * Class constructor.
	 *
	 * Wires the `gatherpress_loaded` listeners that register built-in
	 * providers and fire the companion-plugin registration action.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for provider registration.
	 *
	 * Two-phase: priority 1 registers core providers (always present),
	 * priority 5 fires the companion-plugin action so third-party
	 * providers register on top.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'gatherpress_loaded', array( $this, 'register_core_providers' ), 1 );
		add_action( 'gatherpress_loaded', array( $this, 'do_register_action' ), 5 );
	}

	/**
	 * Register a provider instance.
	 *
	 * Idempotent — re-registering the same slug is a no-op so accidental
	 * double-registration during companion-plugin development doesn't
	 * silently overwrite the original.
	 *
	 * @since 1.0.0
	 *
	 * @param Map_Provider $provider Provider instance.
	 * @return void
	 */
	public function register( Map_Provider $provider ): void {
		$slug = $provider->get_slug();

		if ( '' === $slug ) {
			return;
		}

		if ( isset( $this->providers[ $slug ] ) ) {
			return;
		}

		$this->providers[ $slug ] = $provider;
	}

	/**
	 * Whether a provider with the given slug is registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Provider slug.
	 * @return bool
	 */
	public function is_registered( string $slug ): bool {
		return isset( $this->providers[ $slug ] );
	}

	/**
	 * Get a registered provider by slug, or null when missing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Provider slug.
	 * @return Map_Provider|null
	 */
	public function get( string $slug ): ?Map_Provider {
		return $this->providers[ $slug ] ?? null;
	}

	/**
	 * All registered providers, keyed by slug.
	 *
	 * @since 1.0.0
	 *
	 * @return Map_Provider[]
	 */
	public function get_all(): array {
		return $this->providers;
	}

	/**
	 * Slugs of every registered provider, in registration order.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_slugs(): array {
		return array_keys( $this->providers );
	}

	/**
	 * Resolve the provider active for static-map rendering on this site.
	 *
	 * Reads `map_platform` from settings; falls back to OSM when:
	 *  - the setting is empty
	 *  - the configured slug isn't registered (e.g. Google before #1528 lands)
	 *  - even OSM isn't registered yet (returns null — bootstrap hasn't run)
	 *
	 * Logs a `_doing_it_wrong()` warning when the configured slug isn't
	 * registered so site owners surface the misconfiguration in their
	 * debug log instead of just silently defaulting.
	 *
	 * @since 1.0.0
	 *
	 * @return Map_Provider|null
	 */
	public function get_active(): ?Map_Provider {
		$slug = (string) Settings::get_instance()->get( 'map_platform' );

		if ( '' !== $slug && isset( $this->providers[ $slug ] ) ) {
			return $this->providers[ $slug ];
		}

		// Configured slug isn't registered — surface for site owners and
		// fall back so the front end still renders something.
		if ( '' !== $slug && ! isset( $this->providers[ $slug ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: configured map platform slug. */
					esc_html__( 'No venue map provider registered for slug "%s"; falling back to OSM.', 'gatherpress' ),
					esc_html( $slug )
				),
				'1.0.0'
			);
		}

		return $this->providers['osm'] ?? null;
	}

	/**
	 * Slug of the currently active provider, or empty string when none.
	 *
	 * Convenience for callers (`Map::get_descriptor_for_post`,
	 * `Map::ensure_descriptor_for_combo`) that need the slug as a meta
	 * key without holding onto the provider instance.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_active_slug(): string {
		$active = $this->get_active();

		return null === $active ? '' : $active->get_slug();
	}

	/**
	 * Register the always-available core providers.
	 *
	 * Fires on `gatherpress_loaded` priority 1. Companion plugins should
	 * NOT hook this — use `gatherpress_register_map_providers` (priority 5)
	 * instead so core registration is already complete.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_core_providers(): void {
		$this->register( new OSM() );
	}

	/**
	 * Fire the action for companion plugins to register their providers.
	 *
	 * Companion plugins hook `gatherpress_register_map_providers` and call
	 * `$registry->register( new My_Map_Provider() )` on the passed Manager
	 * instance. Runs at priority 5 — after core providers (priority 1) so
	 * the registry is populated when third-party code observes it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function do_register_action(): void {
		/**
		 * Fires when venue map providers are being registered.
		 *
		 * Companion plugins should hook this and register their providers
		 * by calling `$registry->register( new My_Map_Provider() )`. Core
		 * providers (OSM) are already registered by this point.
		 *
		 * @since 1.0.0
		 *
		 * @param Manager $registry Provider registry.
		 */
		do_action( 'gatherpress_register_map_providers', $this );
	}
}
