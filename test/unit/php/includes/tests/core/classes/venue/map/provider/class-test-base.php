<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map\Provider\Base.
 *
 * Covers the abstract provider's default `attribution_html`,
 * `supports_map_type`, and `supported_map_types` implementations — the
 * three methods that subclasses can lean on without overriding.
 *
 * @package GatherPress\Core\Venue\Map\Provider
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core\Venue\Map\Provider;

use GatherPress\Core\Venue\Map\Provider\Base;
use GatherPress\Tests\Base as GatherPress_Test_Base;

/**
 * Class Test_Base.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map\Provider\Base
 */
class Test_Base extends GatherPress_Test_Base {

	/**
	 * Default `attribution_html()` returns an empty string — providers that
	 * bake attribution into the rendered PNG (Google) inherit this; OSM
	 * overrides it.
	 *
	 * @covers ::attribution_html
	 *
	 * @return void
	 */
	public function test_attribution_html_default_is_empty(): void {
		$provider = $this->make_minimal_provider();

		$this->assertSame( '', $provider->attribution_html() );
	}

	/**
	 * Default `supported_map_types()` returns just `['roadmap']` —
	 * a minimal provider only ships the basemap. Subclasses that ship
	 * satellite/hybrid/terrain override this.
	 *
	 * @covers ::supported_map_types
	 *
	 * @return void
	 */
	public function test_supported_map_types_default(): void {
		$provider = $this->make_minimal_provider();

		$this->assertSame( array( 'roadmap' ), $provider->supported_map_types() );
	}

	/**
	 * `supports_map_type()` returns true only for types declared by
	 * `supported_map_types()`.
	 *
	 * @covers ::supports_map_type
	 *
	 * @return void
	 */
	public function test_supports_map_type_matches_supported_list(): void {
		$provider = $this->make_minimal_provider();

		$this->assertTrue( $provider->supports_map_type( 'roadmap' ) );
		$this->assertFalse( $provider->supports_map_type( 'satellite' ) );
		$this->assertFalse( $provider->supports_map_type( 'hybrid' ) );
		$this->assertFalse( $provider->supports_map_type( 'terrain' ) );
	}

	/**
	 * Subclasses that override `supported_map_types()` flow through
	 * `supports_map_type()` automatically — the default impl uses
	 * `in_array()` against whatever the subclass returns.
	 *
	 * @covers ::supports_map_type
	 *
	 * @return void
	 */
	public function test_supports_map_type_honors_subclass_override(): void {
		$provider = new class() extends Base {
			/**
			 * Slug.
			 *
			 * @return string
			 */
			public function get_slug(): string {
				return 'multi';
			}

			/**
			 * Label.
			 *
			 * @return string
			 */
			public function get_label(): string {
				return 'Multi';
			}

			/**
			 * No-op render — this stub is only used for type-list assertions.
			 *
			 * @param float $latitude  Unused.
			 * @param float $longitude Unused.
			 * @param int   $zoom      Unused.
			 * @param int   $width     Unused.
			 * @param int   $height    Unused.
			 * @param int   $density   Unused.
			 *
			 * @return null
			 */
			public function render(
				float $latitude,
				float $longitude,
				int $zoom,
				int $width,
				int $height,
				int $density = 1
			) {
				return null;
			}

			/**
			 * Override the default to expose extra map types.
			 *
			 * @return string[]
			 */
			public function supported_map_types(): array {
				return array( 'roadmap', 'satellite' );
			}
		};

		$this->assertTrue( $provider->supports_map_type( 'satellite' ) );
		$this->assertFalse( $provider->supports_map_type( 'terrain' ) );
	}

	/**
	 * Build a minimal anonymous Base subclass — the smallest legal
	 * provider that exercises only the default method bodies.
	 *
	 * @return Base
	 */
	private function make_minimal_provider(): Base {
		return new class() extends Base {
			/**
			 * Slug.
			 *
			 * @return string
			 */
			public function get_slug(): string {
				return 'minimal';
			}

			/**
			 * Label.
			 *
			 * @return string
			 */
			public function get_label(): string {
				return 'Minimal';
			}

			/**
			 * No-op render — this stub never produces a PNG.
			 *
			 * @param float $latitude  Unused.
			 * @param float $longitude Unused.
			 * @param int   $zoom      Unused.
			 * @param int   $width     Unused.
			 * @param int   $height    Unused.
			 * @param int   $density   Unused.
			 *
			 * @return null
			 */
			public function render(
				float $latitude,
				float $longitude,
				int $zoom,
				int $width,
				int $height,
				int $density = 1
			) {
				return null;
			}
		};
	}
}
