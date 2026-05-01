<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue\Meta.
 *
 * @package GatherPress\Core\Venue
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Venue;

use GatherPress\Core\Venue\Meta;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Meta.
 *
 * @group multisite
 * @coversDefaultClass \GatherPress\Core\Venue\Meta
 */
class Test_Meta extends Base {
	/**
	 * Coverage for `__construct` and `setup_hooks`.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Meta::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'register' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for `register()` — meta keys for the editor-writable venue
	 * fields, the static-map descriptor, and the venue-map display
	 * settings all come back from `get_registered_meta_keys()` after the
	 * call.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register(): void {
		$instance = Meta::get_instance();

		$venue_information_keys = array(
			'gatherpress_address',
			'gatherpress_latitude',
			'gatherpress_longitude',
			'gatherpress_phone',
			'gatherpress_website',
			'gatherpress_static_map',
		);

		foreach ( $venue_information_keys as $key ) {
			unregister_post_meta( Venue::POST_TYPE, $key );
		}

		unregister_post_meta( Venue::POST_TYPE, 'gatherpress_map_show' );

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		foreach ( $venue_information_keys as $key ) {
			$this->assertArrayNotHasKey(
				$key,
				$meta,
				sprintf( 'Failed to assert that %s is unregistered before re-registration.', $key )
			);
		}

		$this->assertArrayNotHasKey(
			'gatherpress_map_show',
			$meta,
			'Failed to assert that gatherpress_map_show does not exist.'
		);

		$instance->register( Venue::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		foreach ( $venue_information_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$meta,
				sprintf( 'Failed to assert that %s is registered for gatherpress-venue support.', $key )
			);
		}

		$this->assertArrayHasKey(
			'gatherpress_map_show',
			$meta,
			'Failed to assert that gatherpress_map_show exists for gatherpress-venue-map support.'
		);
	}

	/**
	 * Coverage for `register()` when the venue post type does not support
	 * revisions.
	 *
	 * Registers a throwaway venue post type that declares
	 * `gatherpress-venue` support but omits WordPress
	 * revisions support. Verifies that `register()` silently drops
	 * `revisions_enabled` for that post type and still registers the meta
	 * without triggering a WordPress `_doing_it_wrong` notice.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_without_revisions_support(): void {
		$instance = Meta::get_instance();
		$test_pt  = 'test_venue_no_rev';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Test Venues (no revisions)',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-venue' ),
			)
		);

		$instance->register( $test_pt );

		$meta = get_registered_meta_keys( 'post', $test_pt );

		$expected_keys = array(
			'gatherpress_address',
			'gatherpress_latitude',
			'gatherpress_longitude',
			'gatherpress_phone',
			'gatherpress_website',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$meta,
				sprintf( 'Failed to assert %s is registered for a venue post type without revisions support.', $key )
			);
		}

		unregister_post_type( $test_pt );
	}

	/**
	 * Coverage for `filter_readonly_meta`.
	 *
	 * Verifies that server-managed meta keys (the static map descriptor
	 * blob) are stripped from REST API meta payloads so the editor cannot
	 * write them directly, while editor-writable keys pass through.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta(): void {
		$instance = Meta::get_instance();
		$request  = new \WP_REST_Request();

		$request->set_param(
			'meta',
			array(
				'gatherpress_static_map' => array(
					'15' => array(
						'url'  => 'evil.png',
						'hash' => 'x',
					),
				),
				'gatherpress_address'    => 'Real St',
				'gatherpress_latitude'   => '12.345',
			)
		);

		$prepared = new \stdClass();
		$result   = $instance->filter_readonly_meta( $prepared, $request );

		$this->assertSame( $prepared, $result, 'Filter must return the prepared post object.' );

		$meta = $request->get_param( 'meta' );

		$this->assertArrayNotHasKey(
			'gatherpress_static_map',
			$meta,
			'gatherpress_static_map is server-generated and must not be writable via REST.'
		);
		$this->assertArrayHasKey(
			'gatherpress_address',
			$meta,
			'Editor-writable venue meta should pass through untouched.'
		);
		$this->assertArrayHasKey(
			'gatherpress_latitude',
			$meta,
			'Editor-writable venue meta should pass through untouched.'
		);
	}

	/**
	 * `filter_readonly_meta` returns the prepared post unchanged when the
	 * REST request carries no meta param — the `is_array()` guard short-
	 * circuits before any unset() runs.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta_no_meta_param(): void {
		$instance = Meta::get_instance();
		$request  = new \WP_REST_Request();
		$prepared = new \stdClass();

		$result = $instance->filter_readonly_meta( $prepared, $request );

		$this->assertSame( $prepared, $result, 'Filter must return the prepared post object unchanged.' );
		$this->assertNull( $request->get_param( 'meta' ), 'Missing meta param should remain null.' );
	}

	/**
	 * Coverage for `sanitize_coordinate`.
	 *
	 * Numeric values within the ±180 range pass through; everything else
	 * collapses to the empty-string "no coords yet" sentinel.
	 *
	 * @covers ::sanitize_coordinate
	 *
	 * @return void
	 */
	public function test_sanitize_coordinate(): void {
		$instance = Meta::get_instance();

		$this->assertSame(
			'40.7128',
			$instance->sanitize_coordinate( '40.7128' ),
			'Numeric strings should round-trip through the float cast.'
		);
		$this->assertSame(
			'-74.006',
			$instance->sanitize_coordinate( -74.006 ),
			'Floats should round-trip through the float cast.'
		);
		$this->assertSame(
			'',
			$instance->sanitize_coordinate( 'banana' ),
			'Non-numeric input should collapse to the empty sentinel.'
		);
		$this->assertSame(
			'',
			$instance->sanitize_coordinate( '' ),
			'Empty string should remain empty.'
		);
		$this->assertSame(
			'',
			$instance->sanitize_coordinate( -9999 ),
			'Out-of-range values should collapse to the empty sentinel.'
		);
	}

	/**
	 * Direct coverage for `register_venue_information_meta()`.
	 *
	 * The helper is `protected` and dispatched from `register()`, but
	 * xdebug coverage doesn't credit the body lines reliably when entry
	 * is via `$this->...` from another method on the same instance. Call
	 * it via reflection so the body lines are unambiguously executed
	 * under coverage instrumentation.
	 *
	 * @covers ::register_venue_information_meta
	 *
	 * @return void
	 */
	public function test_register_venue_information_meta_direct(): void {
		$instance = Meta::get_instance();

		// Drop the meta keys this helper registers so we can assert
		// re-registration after the direct call.
		foreach ( Meta::EDITOR_WRITABLE_FIELDS as $field ) {
			unregister_post_meta( Venue::POST_TYPE, 'gatherpress_' . $field );
		}
		unregister_post_meta( Venue::POST_TYPE, 'gatherpress_static_map' );
		foreach ( Meta::STRUCTURED_ADDRESS_FIELDS as $field ) {
			unregister_post_meta( Venue::POST_TYPE, 'gatherpress_' . $field );
		}

		Utility::invoke_hidden_method(
			$instance,
			'register_venue_information_meta',
			array( Venue::POST_TYPE )
		);

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		foreach ( Meta::EDITOR_WRITABLE_FIELDS as $field ) {
			$this->assertArrayHasKey(
				'gatherpress_' . $field,
				$meta,
				sprintf( 'Editor-writable key %s should be registered.', $field )
			);
		}
		$this->assertArrayHasKey( 'gatherpress_static_map', $meta );
		foreach ( Meta::STRUCTURED_ADDRESS_FIELDS as $field ) {
			$this->assertArrayHasKey(
				'gatherpress_' . $field,
				$meta,
				sprintf( 'Structured-address key %s should be registered.', $field )
			);
		}
		$this->assertNotFalse(
			has_filter(
				sprintf( 'rest_pre_insert_%s', Venue::POST_TYPE ),
				array( $instance, 'filter_readonly_meta' )
			),
			'REST readonly-strip filter should be wired.'
		);
	}

	/**
	 * Direct coverage for the `if ( ! $supports_revisions )` branches in
	 * `register_venue_information_meta()`. Registers a custom post type
	 * with venue-information support but without `revisions` and invokes
	 * the helper directly so the unset() lines for both args arrays
	 * execute.
	 *
	 * @covers ::register_venue_information_meta
	 *
	 * @return void
	 */
	public function test_register_venue_information_meta_drops_revisions_when_unsupported(): void {
		$instance = Meta::get_instance();
		$test_pt  = 'test_venue_norev_d';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Test Venues (no revisions)',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-venue' ),
			)
		);

		Utility::invoke_hidden_method(
			$instance,
			'register_venue_information_meta',
			array( $test_pt )
		);

		$meta = get_registered_meta_keys( 'post', $test_pt );

		foreach ( Meta::EDITOR_WRITABLE_FIELDS as $field ) {
			$this->assertArrayHasKey( 'gatherpress_' . $field, $meta );
		}

		unregister_post_type( $test_pt );
	}

	/**
	 * Direct coverage for `register_venue_map_meta()` — the venue-map
	 * display-settings band.
	 *
	 * @covers ::register_venue_map_meta
	 *
	 * @return void
	 */
	public function test_register_venue_map_meta_direct(): void {
		$instance = Meta::get_instance();

		$map_keys = array(
			'gatherpress_map_show',
			'gatherpress_map_zoom',
			'gatherpress_map_height',
		);

		foreach ( $map_keys as $key ) {
			unregister_post_meta( Venue::POST_TYPE, $key );
		}

		Utility::invoke_hidden_method(
			$instance,
			'register_venue_map_meta',
			array( Venue::POST_TYPE )
		);

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		foreach ( $map_keys as $key ) {
			$this->assertArrayHasKey( $key, $meta );
		}
	}
}
