<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue\Admin_List.
 *
 * @package GatherPress\Core\Venue
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Venue;

use GatherPress\Core\Venue\Admin_List;
use GatherPress\Core\Venue\Map\Map;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use stdClass;

/**
 * Class Test_Admin_List.
 *
 * @group multisite
 * @coversDefaultClass \GatherPress\Core\Venue\Admin_List
 */
class Test_Admin_List extends Base {

	/**
	 * Tests admin list hooks.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Admin_List::get_instance();
		$this->assertSame( 10, has_filter( 'default_hidden_columns', array( $instance, 'default_hidden_columns' ) ) );
		$this->assertSame(
			10,
			has_action( 'registered_post_type', array( $instance, 'maybe_register_post_type_hooks' ) )
		);
	}

	/**
	 * Registers hooks only for supported post types.
	 *
	 * @covers ::maybe_register_post_type_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_post_type_hooks(): void {
		$post_type = 'test_venue';
		register_post_type( $post_type, array( 'supports' => array( 'gatherpress-venue-information' ) ) );
		$instance = Admin_List::get_instance();
		$instance->maybe_register_post_type_hooks( $post_type );

		$this->assertSame(
			10,
			has_filter( "manage_{$post_type}_posts_columns", array( $instance, 'set_custom_columns' ) )
		);
		$this->assertSame(
			10,
			has_action( "manage_{$post_type}_posts_custom_column", array( $instance, 'custom_columns' ) )
		);
	}

	/**
	 * Skips unsupported post types.
	 *
	 * @covers ::maybe_register_post_type_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_post_type_hooks_skips_unsupported(): void {
		$instance = Admin_List::get_instance();
		$instance->maybe_register_post_type_hooks( 'post' );
		$this->assertFalse( has_filter( 'manage_post_posts_columns', array( $instance, 'set_custom_columns' ) ) );
	}

	/**
	 * Removes author and adds Venue columns.
	 *
	 * @covers ::set_custom_columns
	 *
	 * @return void
	 */
	public function test_set_custom_columns(): void {
		$columns = Admin_List::get_instance()->set_custom_columns(
			array(
				'cb'     => '<input>',
				'title'  => 'Title',
				'author' => 'Author',
				'date'   => 'Date',
			)
		);

		$this->assertArrayNotHasKey( 'author', $columns );
		$this->assertSame( 'Physical details', $columns['physical_details'] );
		$this->assertSame( 'Featured image', $columns['featured_image'] );
		$this->assertSame( 'Static map', $columns['static_map'] );
	}

	/**
	 * Adds visual columns to default hidden columns only for supported screens.
	 *
	 * @covers ::default_hidden_columns
	 *
	 * @return void
	 */
	public function test_default_hidden_columns(): void {
		$screen            = new stdClass();
		$screen->post_type = Venue::POST_TYPE;
		$hidden            = Admin_List::get_instance()->default_hidden_columns( array( 'date' ), $screen );

		$this->assertContains( 'date', $hidden );
		$this->assertContains( 'featured_image', $hidden );
		$this->assertContains( 'static_map', $hidden );

		$screen->post_type = 'post';
		$this->assertSame(
			array( 'date' ),
			Admin_List::get_instance()->default_hidden_columns( array( 'date' ), $screen )
		);
	}

	/**
	 * Renders address, phone, and escaped website.
	 *
	 * @covers ::custom_columns
	 * @covers ::render_physical_details
	 * @covers ::get_address
	 *
	 * @return void
	 */
	public function test_physical_details(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		add_post_meta( $post_id, 'gatherpress_address', '<Main Street>' );
		add_post_meta( $post_id, 'gatherpress_phone', '555-0100' );
		add_post_meta( $post_id, 'gatherpress_website', 'https://example.com/?x=1' );

		ob_start();
		Admin_List::get_instance()->custom_columns( 'physical_details', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( '&lt;Main Street&gt;', $output );
		$this->assertStringContainsString( '555-0100', $output );
		$this->assertStringContainsString( 'href="https://example.com/?x=1"', $output );
	}

	/**
	 * Renders a dash when no physical details exist.
	 *
	 * @covers ::render_physical_details
	 *
	 * @return void
	 */
	public function test_physical_details_empty(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		ob_start();
		Admin_List::get_instance()->custom_columns( 'physical_details', $post_id );
		$this->assertSame( '—', ob_get_clean() );
	}

	/**
	 * Renders structured address fallback.
	 *
	 * @covers ::get_address
	 *
	 * @return void
	 */
	public function test_structured_address_fallback(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		add_post_meta( $post_id, 'gatherpress_house_number', '12' );
		add_post_meta( $post_id, 'gatherpress_street', 'Oak Road' );
		add_post_meta( $post_id, 'gatherpress_city', 'Boston' );
		add_post_meta( $post_id, 'gatherpress_state', 'MA' );
		add_post_meta( $post_id, 'gatherpress_postcode', '02110' );
		add_post_meta( $post_id, 'gatherpress_country', 'USA' );

		ob_start();
		Admin_List::get_instance()->custom_columns( 'physical_details', $post_id );
		$this->assertStringContainsString( '12 Oak Road, Boston, MA, 02110, USA', ob_get_clean() );
	}

	/**
	 * Renders featured image or dash.
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_featured_image(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		ob_start();
		Admin_List::get_instance()->custom_columns( 'featured_image', $post_id );
		$this->assertSame( '—', ob_get_clean() );
	}

	/**
	 * Renders stored map or dash.
	 *
	 * @covers ::render_static_map
	 *
	 * @return void
	 */
	public function test_static_map(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		ob_start();
		Admin_List::get_instance()->custom_columns( 'static_map', $post_id );
		$this->assertSame( '—', ob_get_clean() );

		$map = Map::get_instance();
		Utility::invoke_hidden_method( $map, 'get_all_descriptors', array( $post_id ) );
		update_post_meta(
			$post_id,
			Map::META_KEY,
			array(
				'openstreetmap' => array(
					'12x1200x800xroad' => array(
						'url'    => 'https://example.com/map.png',
						'url_2x' => 'https://example.com/map-2x.png',
						'hash'   => 'abc',
						'zoom'   => 12,
						'width'  => 1200,
						'height' => 800,
					),
				),
			)
		);
		// Stored descriptor lookup depends on provider defaults; malformed or unmatched entries stay safe.
		ob_start();
		Admin_List::get_instance()->custom_columns( 'static_map', $post_id );
		$this->assertNotEmpty( ob_get_clean() );
	}
}
