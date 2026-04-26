<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Manager.
 *
 * @package GatherPress\Core\Venue\Map
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Venue\Map;

use GatherPress\Core\Settings;
use GatherPress\Core\Venue\Map\Manager;
use GatherPress\Core\Venue\Map\Provider\Base as Map_Provider;
use GatherPress\Core\Venue\Map\Provider\OSM;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Manager.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Manager
 */
class Test_Manager extends Base {
	/**
	 * Reset the registry between tests so a stub provider added in one
	 * case doesn't bleed into the next case's `get_active()` resolution.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$instance = Manager::get_instance();
		Utility::set_and_get_hidden_property( $instance, 'providers', array() );
		$instance->register_core_providers();

		delete_option( Settings::OPTION_NAME );

		parent::tear_down();
	}

	/**
	 * Coverage for setup_hooks — verifies the companion-plugin
	 * registration action is wired to `init` priority 0.
	 *
	 * Core providers register synchronously in the constructor and are
	 * covered by `test_register_core_providers_registers_osm` rather
	 * than as a hook here.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Manager::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 0,
				'callback' => array( $instance, 'do_register_action' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * `register()` stores the provider keyed by slug, and `is_registered()`
	 * + `get()` see it afterwards.
	 *
	 * @covers ::register
	 * @covers ::is_registered
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_register_stores_provider(): void {
		$instance = Manager::get_instance();
		$provider = $this->make_stub_provider( 'fake' );

		$instance->register( $provider );

		$this->assertTrue( $instance->is_registered( 'fake' ) );
		$this->assertSame( $provider, $instance->get( 'fake' ) );
	}

	/**
	 * Re-registering the same slug must not replace the original instance —
	 * companion-plugin double-load shouldn't silently swap providers.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_is_idempotent(): void {
		$instance = Manager::get_instance();
		$first    = $this->make_stub_provider( 'twice' );
		$second   = $this->make_stub_provider( 'twice' );

		$instance->register( $first );
		$instance->register( $second );

		$this->assertSame( $first, $instance->get( 'twice' ) );
	}

	/**
	 * A provider with an empty slug is silently dropped — registry keys
	 * must be addressable.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_skips_empty_slug(): void {
		$instance = Manager::get_instance();
		$provider = $this->make_stub_provider( '' );

		$instance->register( $provider );

		$this->assertFalse( $instance->is_registered( '' ) );
	}

	/**
	 * `get()` returns null when the slug isn't registered.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_returns_null_for_unknown_slug(): void {
		$this->assertNull( Manager::get_instance()->get( 'nope' ) );
	}

	/**
	 * `get_all()` returns the full registry keyed by slug.
	 *
	 * @covers ::get_all
	 *
	 * @return void
	 */
	public function test_get_all_returns_all_registered_providers(): void {
		$instance = Manager::get_instance();
		$instance->register( $this->make_stub_provider( 'alpha' ) );

		$all = $instance->get_all();

		$this->assertArrayHasKey( 'osm', $all );
		$this->assertArrayHasKey( 'alpha', $all );
	}

	/**
	 * `get_slugs()` returns just the keys, in registration order — OSM
	 * registered first by `register_core_providers()`, then anything the
	 * test added.
	 *
	 * @covers ::get_slugs
	 *
	 * @return void
	 */
	public function test_get_slugs_returns_keys_in_registration_order(): void {
		$instance = Manager::get_instance();
		$instance->register( $this->make_stub_provider( 'late' ) );

		$this->assertSame( array( 'osm', 'late' ), $instance->get_slugs() );
	}

	/**
	 * `register_core_providers()` always registers OSM — it is the
	 * always-available fallback regardless of which platform the site picks.
	 *
	 * @covers ::register_core_providers
	 *
	 * @return void
	 */
	public function test_register_core_providers_registers_osm(): void {
		$instance = Manager::get_instance();
		$osm      = $instance->get( 'osm' );

		$this->assertInstanceOf( OSM::class, $osm );
	}

	/**
	 * `do_register_action()` fires `gatherpress_register_map_providers`
	 * with the manager instance so companion plugins can register their
	 * providers on top of the core ones.
	 *
	 * @covers ::do_register_action
	 *
	 * @return void
	 */
	public function test_do_register_action_fires_companion_action(): void {
		$instance = Manager::get_instance();
		$captured = null;

		$callback = function ( $registry ) use ( &$captured ) {
			$captured = $registry;
		};

		add_action( 'gatherpress_register_map_providers', $callback );

		$instance->do_register_action();

		remove_action( 'gatherpress_register_map_providers', $callback );

		$this->assertSame( $instance, $captured );
	}

	/**
	 * When `map_platform` points at a registered slug, `get_active()`
	 * returns that provider.
	 *
	 * @covers ::get_active
	 *
	 * @return void
	 */
	public function test_get_active_returns_configured_provider(): void {
		$instance = Manager::get_instance();
		$custom   = $this->make_stub_provider( 'custom' );
		$instance->register( $custom );

		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'custom' ) );

		$this->assertSame( $custom, $instance->get_active() );
	}

	/**
	 * Empty `map_platform` setting falls through to OSM — site has never
	 * touched the dropdown.
	 *
	 * @covers ::get_active
	 *
	 * @return void
	 */
	public function test_get_active_falls_back_to_osm_when_setting_empty(): void {
		$instance = Manager::get_instance();

		update_option( Settings::OPTION_NAME, array( 'map_platform' => '' ) );

		$this->assertInstanceOf( OSM::class, $instance->get_active() );
	}

	/**
	 * A configured slug with no matching registered provider (e.g. `google`
	 * before its provider class lands in #1528) triggers `_doing_it_wrong`
	 * and falls back to OSM so the front end keeps rendering.
	 *
	 * @covers ::get_active
	 *
	 * @return void
	 */
	public function test_get_active_falls_back_to_osm_for_unknown_slug(): void {
		$this->setExpectedIncorrectUsage( Manager::class . '::get_active' );

		$instance = Manager::get_instance();

		update_option( Settings::OPTION_NAME, array( 'map_platform' => 'google' ) );

		$this->assertInstanceOf( OSM::class, $instance->get_active() );
	}

	/**
	 * When even OSM isn't registered yet (bootstrap hasn't run),
	 * `get_active()` returns null rather than throwing. The persisted
	 * `map_platform` default is `osm`, so the misconfigured-slug path
	 * fires `_doing_it_wrong` on the way through — expected.
	 *
	 * @covers ::get_active
	 *
	 * @return void
	 */
	public function test_get_active_returns_null_when_no_providers(): void {
		$this->setExpectedIncorrectUsage( Manager::class . '::get_active' );

		$instance = Manager::get_instance();
		Utility::set_and_get_hidden_property( $instance, 'providers', array() );

		$this->assertNull( $instance->get_active() );
	}

	/**
	 * `get_active_slug()` returns the active provider's slug, or empty
	 * string when there's no active provider. The empty-registry leg
	 * trips the misconfigured-slug warning because the persisted
	 * `map_platform` default is `osm` — expected.
	 *
	 * @covers ::get_active_slug
	 *
	 * @return void
	 */
	public function test_get_active_slug_mirrors_get_active(): void {
		$this->setExpectedIncorrectUsage( Manager::class . '::get_active' );

		$instance = Manager::get_instance();
		$this->assertSame( 'osm', $instance->get_active_slug() );

		Utility::set_and_get_hidden_property( $instance, 'providers', array() );
		$this->assertSame( '', $instance->get_active_slug() );
	}

	/**
	 * Build a minimal provider double — anonymous subclass of the abstract
	 * Base with just enough plumbing to be registerable.
	 *
	 * @param string $slug Slug to advertise.
	 * @return Map_Provider
	 */
	private function make_stub_provider( string $slug ): Map_Provider {
		return new class( $slug ) extends Map_Provider {
			/**
			 * Slug to advertise.
			 *
			 * @var string
			 */
			private $slug;

			/**
			 * Capture the slug to return from `get_slug()`.
			 *
			 * @param string $slug Slug.
			 */
			public function __construct( string $slug ) {
				$this->slug = $slug;
			}

			/**
			 * Provider slug.
			 *
			 * @return string
			 */
			public function get_slug(): string {
				return $this->slug;
			}

			/**
			 * Provider label.
			 *
			 * @return string
			 */
			public function get_label(): string {
				return 'Stub';
			}

			/**
			 * No-op render — this stub is only used for registry assertions.
			 *
			 * @param float $latitude  Unused.
			 * @param float $longitude Unused.
			 * @param int   $zoom      Unused.
			 * @param int   $width     Unused.
			 * @param int   $height    Unused.
			 * @param int   $density   Unused.
			 * @return null
			 */
			public function render(
				float $latitude,
				float $longitude,
				int $zoom,
				int $width,
				int $height,
				int $density = 1
			): ?\GdImage {
				return null;
			}
		};
	}
}
