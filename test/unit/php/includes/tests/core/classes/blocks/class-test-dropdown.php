<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Dropdown.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Dropdown;
use GatherPress\Tests\Base;

/**
 * Class Test_Dropdown.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Dropdown
 */
class Test_Dropdown extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Dropdown block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Dropdown::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Dropdown::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'apply_dropdown_attributes' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'apply_select_mode_attributes' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'generate_block_styles' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Tests default values for block styles.
	 *
	 * @since 1.0.0
	 * @covers ::generate_block_styles
	 *
	 * @return void
	 */
	public function test_generate_block_styles_defaults(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
			'attrs'     => array(
				'dropdownId' => 'test-dropdown',
			),
		);
		$block_content = '<div class="dropdown-content">Test</div>';
		$result        = $instance->generate_block_styles( $block_content, $block );

		$this->assertStringContainsString(
			'#test-dropdown .wp-block-gatherpress-dropdown-item a {',
			$result,
			'Default dropdown styles are not being applied correctly.'
		);
		$this->assertStringContainsString(
			'padding: 8px 8px 8px 8px',
			$result,
			'Default padding is not being applied correctly.'
		);
		$this->assertStringContainsString(
			'color: #000000',
			$result,
			'Default text color is not being applied correctly.'
		);
		$this->assertStringContainsString(
			'background-color: #FFFFFF',
			$result,
			'Default background color is not being applied correctly.'
		);
		$this->assertStringContainsString(
			'border-top: 1px solid #000000',
			$result,
			'Default border styles are not being applied correctly.'
		);
	}

	/**
	 * Tests hover behavior styles.
	 *
	 * @since 1.0.0
	 * @covers ::generate_block_styles
	 *
	 * @return void
	 */
	public function test_generate_block_styles_hover(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
			'attrs'     => array(
				'dropdownId' => 'hover-dropdown',
				'openOn'     => 'hover',
			),
		);
		$block_content = '<div class="dropdown-content">Test</div>';
		$result        = $instance->generate_block_styles( $block_content, $block );

		$this->assertStringContainsString(
			'.wp-block-gatherpress-dropdown:hover #hover-dropdown',
			$result,
			'Hover styles are not being applied correctly.'
		);
		$this->assertStringContainsString(
			'.wp-block-gatherpress-dropdown:focus-within #hover-dropdown',
			$result,
			'Focus styles are not being applied correctly.'
		);
		$this->assertStringContainsString(
			'display: block',
			$result,
			'Display property is not being set correctly on hover.'
		);
	}

	/**
	 * Tests custom attribute values.
	 *
	 * @since 1.0.0
	 * @covers ::generate_block_styles
	 *
	 * @return void
	 */
	public function test_generate_block_styles_custom_attributes(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
			'attrs'     => array(
				'dropdownId'           => 'custom-dropdown',
				'itemPadding'          => array(
					'top'    => 10,
					'right'  => 20,
					'bottom' => 10,
					'left'   => 20,
				),
				'itemTextColor'        => '#FF0000',
				'itemBgColor'          => '#CCCCCC',
				'itemHoverTextColor'   => '#00FF00',
				'itemHoverBgColor'     => '#333333',
				'itemDividerColor'     => '#0000FF',
				'itemDividerThickness' => 2,
			),
		);
		$block_content = '<div class="dropdown-content">Test</div>';
		$result        = $instance->generate_block_styles( $block_content, $block );

		$this->assertStringContainsString(
			'padding: 10px 20px 10px 20px',
			$result,
			'Custom padding is not being applied correctly.'
		);
		$this->assertStringContainsString(
			'color: #FF0000',
			$result,
			'Custom text color is not being applied correctly.'
		);
		$this->assertStringContainsString(
			'background-color: #CCCCCC',
			$result,
			'Custom background color is not being applied correctly.'
		);
		$this->assertStringContainsString(
			'color: #00FF00',
			$result,
			'Custom hover text color is not being applied correctly.'
		);
		$this->assertStringContainsString(
			'background-color: #333333',
			$result,
			'Custom hover background color is not being applied correctly.'
		);
		$this->assertStringContainsString(
			'border-top: 2px solid #0000FF',
			$result,
			'Custom border styles are not being applied correctly.'
		);
	}

	/**
	 * Tests missing block attributes.
	 *
	 * @since 1.0.0
	 * @covers ::generate_block_styles
	 *
	 * @return void
	 */
	public function test_generate_block_styles_missing_attributes(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
		);
		$block_content = '<div class="dropdown-content">Test</div>';
		$result        = $instance->generate_block_styles( $block_content, $block );

		$this->assertStringContainsString(
			'<style>',
			$result,
			'Style tag is not being added correctly when attributes are missing.'
		);
		$this->assertStringContainsString(
			'</style>',
			$result,
			'Style closing tag is not being added correctly when attributes are missing.'
		);
		$this->assertStringContainsString(
			$block_content,
			$result,
			'Original block content is not being preserved when attributes are missing.'
		);
	}

	/**
	 * Tests select mode disabled.
	 *
	 * @since 1.0.0
	 * @covers ::apply_select_mode_attributes
	 *
	 * @return void
	 */
	public function test_apply_select_mode_attributes_disabled(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
			'attrs'     => array(
				'actAsSelect' => false,
			),
		);
		$block_content = '<div class="wp-block-gatherpress-dropdown"><div class="wp-block-gatherpress-dropdown__menu">
<div class="wp-block-gatherpress-dropdown-item"><a href="#">Item 1</a></div></div></div>';
		$result        = $instance->apply_select_mode_attributes( $block_content, $block );

		$this->assertStringNotContainsString(
			'data-dropdown-mode="select"',
			$result,
			'Select mode attribute should not be added when actAsSelect is false.'
		);
	}

	/**
	 * Tests select mode enabled.
	 *
	 * @since 1.0.0
	 * @covers ::apply_select_mode_attributes
	 *
	 * @return void
	 */
	public function test_apply_select_mode_attributes_enabled(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
			'attrs'     => array(
				'actAsSelect'   => true,
				'selectedIndex' => 0,
			),
		);
		$block_content = '<div class="wp-block-gatherpress-dropdown"><div class="wp-block-gatherpress-dropdown__menu">
<div class="wp-block-gatherpress-dropdown-item"><a href="#">Item 1</a></div></div></div>';
		$result        = $instance->apply_select_mode_attributes( $block_content, $block );

		$this->assertStringContainsString(
			'data-dropdown-mode="select"',
			$result,
			'Select mode attribute should be added when actAsSelect is true.'
		);
		$this->assertStringContainsString(
			'gatherpress--is-disabled',
			$result,
			'Disabled class should be added to selected item.'
		);
		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'Interactive attribute should be added for select mode items.'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.linkHandler"',
			$result,
			'Click handler attribute should be added for select mode items.'
		);
		$this->assertStringContainsString(
			'aria-disabled="true"',
			$result,
			'Aria-disabled attribute should be added to selected item.'
		);
		$this->assertStringContainsString(
			'tabindex="-1"',
			$result,
			'Tabindex should be set to -1 for selected item.'
		);
	}

	/**
	 * Tests select mode with multiple items.
	 *
	 * @since 1.0.0
	 * @covers ::apply_select_mode_attributes
	 *
	 * @return void
	 */
	public function test_apply_select_mode_attributes_multiple_items(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
			'attrs'     => array(
				'actAsSelect'   => true,
				'selectedIndex' => 1,
			),
		);
		$block_content = '<div class="wp-block-gatherpress-dropdown"><div class="wp-block-gatherpress-dropdown__menu">
<div class="wp-block-gatherpress-dropdown-item"><a href="#">Item 1</a></div>
<div class="wp-block-gatherpress-dropdown-item"><a href="#">Item 2</a></div></div></div>';
		$result        = $instance->apply_select_mode_attributes( $block_content, $block );

		$this->assertStringContainsString(
			'data-dropdown-mode="select"',
			$result,
			'Select mode attribute should be added when actAsSelect is enabled.'
		);
		$this->assertStringContainsString(
			'gatherpress--is-disabled',
			$result,
			'Disabled class should be added to selected dropdown item.'
		);
		$this->assertStringNotContainsString(
			'<a href="#">Item 1</a>',
			$result,
			'Original link markup should be transformed for select mode items.'
		);
		$this->assertStringContainsString(
			'href="#"',
			$result,
			'Hash href should be added to select mode links.'
		);
	}

	/**
	 * Tests click interactions.
	 *
	 * @since 1.0.0
	 * @covers ::apply_dropdown_attributes
	 *
	 * @return void
	 */
	public function test_apply_dropdown_attributes_click(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
			'attrs'     => array(
				'openOn'     => 'click',
				'dropdownId' => 'test-dropdown',
			),
		);
		$block_content = '<div><a class="wp-block-gatherpress-dropdown__trigger">Click</a></div>';
		$result        = $instance->apply_dropdown_attributes( $block_content, $block );

		$this->assertStringContainsString(
			'aria-controls="test-dropdown"',
			$result,
			'Click trigger should control dropdown by ID'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.toggleDropdown"',
			$result,
			'Click trigger should have toggle action'
		);
		$this->assertStringContainsString(
			'aria-expanded="false"',
			$result,
			'Click trigger should have initial expanded state'
		);
	}

	/**
	 * Tests hover interactions.
	 *
	 * @since 1.0.0
	 * @covers ::apply_dropdown_attributes
	 *
	 * @return void
	 */
	public function test_apply_dropdown_attributes_hover(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
			'attrs'     => array(
				'openOn' => 'hover',
			),
		);
		$block_content = '<div><a class="wp-block-gatherpress-dropdown__trigger">Hover</a></div>';
		$result        = $instance->apply_dropdown_attributes( $block_content, $block );

		$this->assertStringContainsString(
			'data-wp-on--click="actions.preventDefault"',
			$result,
			'Hover trigger should prevent default click'
		);
		$this->assertStringNotContainsString(
			'data-wp-on--click="actions.toggleDropdown"',
			$result,
			'Hover trigger should not have toggle action'
		);
	}

	/**
	 * Tests custom styles.
	 *
	 * @since 1.0.0
	 * @covers ::apply_dropdown_attributes
	 *
	 * @return void
	 */
	public function test_apply_dropdown_attributes_custom_styles(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
			'attrs'     => array(
				'labelColor'              => '#FF0000',
				'dropdownBorderThickness' => 2,
				'dropdownBorderColor'     => '#0000FF',
				'dropdownBorderRadius'    => 4,
				'dropdownZIndex'          => 20,
				'dropdownWidth'           => 300,
			),
		);
		$block_content = '<div><a class="wp-block-gatherpress-dropdown__trigger">Styled</a>
<div class="wp-block-gatherpress-dropdown__menu"></div></div>';
		$result        = $instance->apply_dropdown_attributes( $block_content, $block );

		$this->assertStringContainsString(
			'color: #FF0000',
			$result,
			'Label color should be applied'
		);
		$this->assertStringContainsString(
			'border: 2px solid #0000FF',
			$result,
			'Border styles should be applied'
		);
		$this->assertStringContainsString(
			'border-radius: 4px',
			$result,
			'Border radius should be applied'
		);
		$this->assertStringContainsString(
			'z-index: 20',
			$result,
			'Z-index should be applied'
		);
		$this->assertStringContainsString(
			'width: 300px',
			$result,
			'Width should be applied'
		);
	}

	/**
	 * Tests default attributes.
	 *
	 * @since 1.0.0
	 * @covers ::apply_dropdown_attributes
	 *
	 * @return void
	 */
	public function test_apply_dropdown_attributes_defaults(): void {
		$instance      = Dropdown::get_instance();
		$block         = array(
			'blockName' => 'gatherpress/dropdown',
		);
		$block_content = '<div><a class="wp-block-gatherpress-dropdown__trigger">Default</a>
<div class="wp-block-gatherpress-dropdown__menu"></div></div>';
		$result        = $instance->apply_dropdown_attributes( $block_content, $block );

		$this->assertStringContainsString(
			'border: 1px solid #000000',
			$result,
			'Default border styles should be applied'
		);
		$this->assertStringContainsString(
			'border-radius: 8px',
			$result,
			'Default border radius should be applied'
		);
		$this->assertStringContainsString(
			'z-index: 10',
			$result,
			'Default z-index should be applied'
		);
		$this->assertStringContainsString(
			'width: 240px',
			$result,
			'Default width should be applied'
		);
	}
}
