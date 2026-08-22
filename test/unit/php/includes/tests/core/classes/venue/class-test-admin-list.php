<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue\Admin_List.
 *
 * @package GatherPress\Core\Venue
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Venue;

use GatherPress\Core\Venue\Admin_List;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Admin_List.
 *
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
		$this->assertArrayNotHasKey( 'featured_image', $columns );
		$this->assertArrayNotHasKey( 'static_map', $columns );
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
		add_post_meta( $post_id, 'gatherpress_address', 'Main & Street' );
		add_post_meta( $post_id, 'gatherpress_phone', '555-0100' );
		add_post_meta( $post_id, 'gatherpress_website', 'https://example.com/?x=1' );

		ob_start();
		Admin_List::get_instance()->custom_columns( 'physical_details', $post_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Main &amp; Street', $output );
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
}
