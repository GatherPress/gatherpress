<?php
/**
 * Unit tests for GatherPress\Core\Venue\Geo_Sync.
 *
 * @package GatherPress\Core\Venue
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Venue;

use GatherPress\Core\Venue;
use GatherPress\Core\Venue\Geo_Sync;
use GatherPress\Tests\Base;

/**
 * Class Test_Geo_Sync.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Geo_Sync
 */
class Test_Geo_Sync extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Geo_Sync::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 10,
				'callback' => array( $instance, 'on_post_saved' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * `on_post_saved()` derives geo_* meta from the venue's own fields on
	 * a published venue.
	 *
	 * @covers ::on_post_saved
	 *
	 * @return void
	 */
	public function test_on_post_saved_derives_geo_meta_for_published_venue(): void {
		$venue = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get();

		update_post_meta( $venue->ID, 'gatherpress_latitude', '40.7128' );
		update_post_meta( $venue->ID, 'gatherpress_longitude', '-74.006' );
		update_post_meta( $venue->ID, 'gatherpress_address', '123 Main St, New York, NY' );

		Geo_Sync::get_instance()->on_post_saved( $venue->ID, $venue );

		$this->assertSame( '40.7128', get_post_meta( $venue->ID, 'geo_latitude', true ) );
		$this->assertSame( '-74.006', get_post_meta( $venue->ID, 'geo_longitude', true ) );
		$this->assertSame(
			'123 Main St, New York, NY',
			get_post_meta( $venue->ID, 'geo_address', true )
		);
		// get_post_meta() always returns strings — DB storage is text
		// regardless of the registered 'integer' meta type.
		$this->assertSame( '1', get_post_meta( $venue->ID, 'geo_public', true ) );
	}

	/**
	 * `on_post_saved()` writes `geo_public` as `0` for a venue that isn't
	 * published (e.g. a draft).
	 *
	 * @covers ::on_post_saved
	 *
	 * @return void
	 */
	public function test_on_post_saved_writes_not_public_for_draft_venue(): void {
		$venue = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'draft',
			)
		)->get();

		Geo_Sync::get_instance()->on_post_saved( $venue->ID, $venue );

		$this->assertSame( '0', get_post_meta( $venue->ID, 'geo_public', true ) );
	}

	/**
	 * `on_post_saved()` is a no-op for a revision or autosave.
	 *
	 * @covers ::on_post_saved
	 *
	 * @return void
	 */
	public function test_on_post_saved_skips_revisions_and_autosaves(): void {
		$venue = $this->mock->post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get();

		$revision_id = wp_save_post_revision( $venue->ID );
		$this->assertIsInt( $revision_id, 'A revision should have been created.' );

		$revision = get_post( $revision_id );

		Geo_Sync::get_instance()->on_post_saved( $revision_id, $revision );

		$this->assertSame(
			'',
			get_post_meta( $revision_id, 'geo_latitude', true ),
			'A revision save must not write geo meta.'
		);
	}

	/**
	 * `on_post_saved()` ignores a post type without venue-information
	 * support.
	 *
	 * @covers ::on_post_saved
	 *
	 * @return void
	 */
	public function test_on_post_saved_ignores_unrelated_post_type(): void {
		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		Geo_Sync::get_instance()->on_post_saved( $post->ID, $post );

		$this->assertSame( '', get_post_meta( $post->ID, 'geo_latitude', true ) );
	}
}
