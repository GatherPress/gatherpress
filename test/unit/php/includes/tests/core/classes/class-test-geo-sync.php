<?php
/**
 * Test class for Geo_Sync.
 *
 * @package GatherPress\Tests\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Geo_Sync;
use GatherPress\Tests\Base;

/**
 * Class Test_Geo_Sync.
 *
 * @since TBD
 *
 * @coversDefaultClass \GatherPress\Core\Geo_Sync
 */
class Test_Geo_Sync extends Base {

	/**
	 * `register_meta()` registers all four Geodata standard keys, read-only.
	 *
	 * @since TBD
	 *
	 * @covers ::register_meta
	 *
	 * @return void
	 */
	public function test_register_meta_registers_all_fields_read_only(): void {
		foreach ( Geo_Sync::FIELDS as $key ) {
			unregister_post_meta( Event::POST_TYPE, $key );
		}

		Geo_Sync::register_meta( Event::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		foreach ( Geo_Sync::FIELDS as $key ) {
			$this->assertArrayHasKey(
				$key,
				$meta,
				sprintf( 'Failed to assert %s is registered.', $key )
			);
			$this->assertFalse(
				call_user_func( $meta[ $key ]['auth_callback'] ),
				sprintf( 'Failed to assert %s is read-only.', $key )
			);
		}
	}

	/**
	 * `register_meta()` registers `geo_public` as an integer, matching
	 * Simple Location's own 0/1/2 semantics; the other three as strings.
	 *
	 * @since TBD
	 *
	 * @covers ::register_meta
	 *
	 * @return void
	 */
	public function test_register_meta_types(): void {
		foreach ( Geo_Sync::FIELDS as $key ) {
			unregister_post_meta( Event::POST_TYPE, $key );
		}

		Geo_Sync::register_meta( Event::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertSame( 'integer', $meta['geo_public']['type'] );
		$this->assertSame( 'string', $meta['geo_latitude']['type'] );
		$this->assertSame( 'string', $meta['geo_longitude']['type'] );
		$this->assertSame( 'string', $meta['geo_address']['type'] );
	}

	/**
	 * Maps venue information onto the `geo_*` keys, with geo_public as 1.
	 *
	 * @since TBD
	 *
	 * @covers ::build_values
	 *
	 * @return void
	 */
	public function test_build_values_from_information(): void {
		$values = Geo_Sync::build_values(
			array(
				'latitude'  => '40.7',
				'longitude' => '-74.0',
				'address'   => '1 Main St',
			),
			true
		);

		$this->assertSame(
			array(
				'geo_latitude'  => '40.7',
				'geo_longitude' => '-74.0',
				'geo_address'   => '1 Main St',
				'geo_public'    => 1,
			),
			$values
		);
	}

	/**
	 * Empty information clears the location, with geo_public as 0.
	 *
	 * @since TBD
	 *
	 * @covers ::build_values
	 *
	 * @return void
	 */
	public function test_build_values_from_empty_information(): void {
		$this->assertSame(
			array(
				'geo_latitude'  => '',
				'geo_longitude' => '',
				'geo_address'   => '',
				'geo_public'    => 0,
			),
			Geo_Sync::build_values( array(), false )
		);
	}

	/**
	 * Writes changed values and reports a change.
	 *
	 * @since TBD
	 *
	 * @covers ::write
	 *
	 * @return void
	 */
	public function test_write_persists_changed_values(): void {
		$post_id = $this->factory->post->create();
		$values  = Geo_Sync::build_values( array( 'latitude' => '1.5' ), true );

		$this->assertTrue( Geo_Sync::write( $post_id, $values ) );
		$this->assertSame( '1.5', get_post_meta( $post_id, 'geo_latitude', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * Stores empty/zero values on a post that has no geo meta yet.
	 *
	 * @since TBD
	 *
	 * @covers ::write
	 *
	 * @return void
	 */
	public function test_write_stores_empty_values_when_missing(): void {
		$post_id = $this->factory->post->create();

		$this->assertTrue( Geo_Sync::write( $post_id, Geo_Sync::build_values( array(), false ) ) );
		$this->assertTrue( metadata_exists( 'post', $post_id, 'geo_public' ) );
		$this->assertSame( '0', get_post_meta( $post_id, 'geo_public', true ) );
	}

	/**
	 * Reports no change when every value is already current.
	 *
	 * @since TBD
	 *
	 * @covers ::write
	 *
	 * @return void
	 */
	public function test_write_skips_current_values(): void {
		$post_id = $this->factory->post->create();
		$values  = Geo_Sync::build_values( array( 'latitude' => '1.5' ), true );

		Geo_Sync::write( $post_id, $values );

		$this->assertFalse( Geo_Sync::write( $post_id, $values ) );
	}
}
