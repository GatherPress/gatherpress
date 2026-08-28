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
use PMC\Unit_Test\Utility;

/**
 * Class Test_Admin_List.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Admin_List
 */
class Test_Admin_List extends Base {

	/**
	 * Tests admin list hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Admin_List::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_register_post_type_hooks' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
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
	 * Removes author and adds the Venue column.
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
		add_post_meta( $post_id, 'gatherpress_address', '5 & 7 Main St' );
		add_post_meta( $post_id, 'gatherpress_phone', '555-0100' );
		add_post_meta( $post_id, 'gatherpress_website', 'https://example.com/?x=1' );

		$output = Utility::buffer_and_return(
			array( Admin_List::get_instance(), 'custom_columns' ),
			array( 'physical_details', $post_id )
		);

		$this->assertStringContainsString( '5 &amp; 7 Main St', $output );
		$this->assertStringContainsString( '555-0100', $output );
		$this->assertStringContainsString( 'href="https://example.com/?x=1"', $output );
	}

	/**
	 * Renders nothing for columns this class does not handle.
	 *
	 * @covers ::custom_columns
	 *
	 * @return void
	 */
	public function test_custom_columns_ignores_other_columns(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$this->assertSame(
			'',
			Utility::buffer_and_return(
				array( Admin_List::get_instance(), 'custom_columns' ),
				array( 'date', $post_id )
			)
		);
	}

	/**
	 * Renders address alone without phone or website.
	 *
	 * @covers ::render_physical_details
	 * @covers ::get_address
	 *
	 * @return void
	 */
	public function test_physical_details_address_only(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		add_post_meta( $post_id, 'gatherpress_address', '1 Street' );

		$output = Utility::buffer_and_return(
			array( Admin_List::get_instance(), 'custom_columns' ),
			array( 'physical_details', $post_id )
		);

		$this->assertStringContainsString( '1 Street', $output );
		$this->assertStringNotContainsString( '<br>', $output );
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
		$this->assertSame(
			'—',
			Utility::buffer_and_return(
				array( Admin_List::get_instance(), 'custom_columns' ),
				array( 'physical_details', $post_id )
			)
		);
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

		$this->assertStringContainsString(
			'12 Oak Road, Boston, MA, 02110, USA',
			Utility::buffer_and_return(
				array( Admin_List::get_instance(), 'custom_columns' ),
				array( 'physical_details', $post_id )
			)
		);
	}

	/**
	 * Directly invokes get_address when the address meta is present.
	 *
	 * Xdebug does not reliably trace same-class protected helpers called from a
	 * public method, so invoke the helper directly to keep it covered.
	 *
	 * @covers ::get_address
	 *
	 * @return void
	 */
	public function test_get_address_with_address_meta(): void {
		$information = array_merge(
			array_fill_keys( array( 'house_number', 'street', 'city', 'state', 'postcode', 'country' ), '' ),
			array( 'address' => '5 & 7 Main St' )
		);

		$result = Utility::invoke_hidden_method(
			Admin_List::get_instance(),
			'get_address',
			array( $information )
		);

		$this->assertSame( '5 & 7 Main St', $result );
	}

	/**
	 * Directly invokes get_address for the structured-address fallback.
	 *
	 * @covers ::get_address
	 *
	 * @return void
	 */
	public function test_get_address_structured_fallback(): void {
		$information = array(
			'address'      => '',
			'house_number' => '12',
			'street'       => 'Oak Road',
			'city'         => 'Boston',
			'state'        => 'MA',
			'postcode'     => '02110',
			'country'      => 'USA',
		);

		$result = Utility::invoke_hidden_method(
			Admin_List::get_instance(),
			'get_address',
			array( $information )
		);

		$this->assertSame( '12 Oak Road, Boston, MA, 02110, USA', $result );
	}

	/**
	 * Directly invokes get_address with no address pieces at all.
	 *
	 * @covers ::get_address
	 *
	 * @return void
	 */
	public function test_get_address_empty(): void {
		$information = array(
			'address'      => '',
			'house_number' => '',
			'street'       => '',
			'city'         => '',
			'state'        => '',
			'postcode'     => '',
			'country'      => '',
		);

		$result = Utility::invoke_hidden_method(
			Admin_List::get_instance(),
			'get_address',
			array( $information )
		);

		$this->assertSame( '', $result );
	}

	/**
	 * Directly invokes render_physical_details when no details exist.
	 *
	 * @covers ::render_physical_details
	 *
	 * @return void
	 */
	public function test_render_physical_details_empty_direct(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );

		$this->assertSame(
			'—',
			Utility::buffer_and_return_hidden_method(
				Admin_List::get_instance(),
				'render_physical_details',
				array( $post_id )
			)
		);
	}
}
