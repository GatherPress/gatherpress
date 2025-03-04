<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Dropdown_Item.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Dropdown_Item;
use GatherPress\Tests\Base;

/**
 * Class Test_Dropdown_Item.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Dropdown_Item
 */
class Test_Dropdown_Item extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Dropdown_Item block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Dropdown_Item::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Dropdown_Item::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'apply_dropdown_attributes' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Tests apply_dropdown_attributes with an empty href.
	 *
	 * @since 1.0.0
	 * @covers ::apply_dropdown_attributes
	 *
	 * @return void
	 */
	public function test_apply_dropdown_attributes_empty_href(): void {
		$instance      = Dropdown_Item::get_instance();
		$block_content = '<a href="" class="dropdown-link">Click me</a>';
		$result        = $instance->apply_dropdown_attributes( $block_content );

		$this->assertStringContainsString( 'data-wp-interactive="gatherpress"', $result );
		$this->assertStringContainsString( 'data-wp-on--click="actions.linkHandler"', $result );
		$this->assertStringContainsString( 'tabindex="0"', $result );
		$this->assertStringContainsString( 'role="button"', $result );
	}

	/**
	 * Tests apply_dropdown_attributes with a hash href.
	 *
	 * @since 1.0.0
	 * @covers ::apply_dropdown_attributes
	 *
	 * @return void
	 */
	public function test_apply_dropdown_attributes_hash_href(): void {
		$instance      = Dropdown_Item::get_instance();
		$block_content = '<a href="#" class="dropdown-link">Click me</a>';
		$result        = $instance->apply_dropdown_attributes( $block_content );

		$this->assertStringContainsString( 'data-wp-interactive="gatherpress"', $result );
		$this->assertStringContainsString( 'data-wp-on--click="actions.linkHandler"', $result );
		$this->assertStringContainsString( 'tabindex="0"', $result );
		$this->assertStringContainsString( 'role="button"', $result );
	}

	/**
	 * Tests apply_dropdown_attributes with a valid URL href.
	 *
	 * @since 1.0.0
	 * @covers ::apply_dropdown_attributes
	 *
	 * @return void
	 */
	public function test_apply_dropdown_attributes_valid_href(): void {
		$instance      = Dropdown_Item::get_instance();
		$block_content = '<a href="https://example.com" class="dropdown-link">Click me</a>';
		$result        = $instance->apply_dropdown_attributes( $block_content );

		$this->assertStringNotContainsString( 'data-wp-interactive', $result );
		$this->assertStringNotContainsString( 'data-wp-on--click', $result );
		$this->assertStringNotContainsString( 'tabindex="0"', $result );
		$this->assertStringNotContainsString( 'role="button"', $result );
		$this->assertStringContainsString( 'href="https://example.com"', $result );
	}

	/**
	 * Tests apply_dropdown_attributes with no anchor tag.
	 *
	 * @since 1.0.0
	 * @covers ::apply_dropdown_attributes
	 *
	 * @return void
	 */
	public function test_apply_dropdown_attributes_no_anchor(): void {
		$instance      = Dropdown_Item::get_instance();
		$block_content = '<div class="dropdown-link">Click me</div>';
		$result        = $instance->apply_dropdown_attributes( $block_content );

		$this->assertSame( $block_content, $result );
	}
}
