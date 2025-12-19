<?php
/**
 * The "Dropdown" class handles the functionality of the Dropdown block,
 * ensuring proper behavior and rendering of individual dropdowns.
 *
 * This class is responsible for transforming block content to dynamically inject
 * styles and attributes specific to dropdown functionality.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Block;
use GatherPress\Core\Traits\Singleton;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "Dropdown" block and its functionality,
 * including dynamic rendering adjustments.
 *
 * @since 1.0.0
 */
class Dropdown {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/dropdown';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		$render_block_hook = sprintf( 'render_block_%s', self::BLOCK_NAME );

		add_filter( $render_block_hook, array( $this, 'apply_dropdown_attributes' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'apply_select_mode_attributes' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'generate_block_styles' ), 10, 2 );
	}

	/**
	 * Generate and add inline styles for the dropdown block.
	 *
	 * This method generates the necessary styles for the dropdown block and appends
	 * them to the block content. Styles include padding, colors, and hover effects
	 * for dropdown items. Additionally, it handles `hover`-based dropdown behavior.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original block content.
	 * @param array  $block         The parsed block data containing block attributes.
	 * @return string The modified block content with inline styles.
	 */
	public function generate_block_styles( string $block_content, array $block ): string {
		$block_instance         = Block::get_instance();
		$attributes             = $block['attrs'] ?? array();
		$dropdown_id            = $attributes['dropdownId'] ?? '';
		$open_on                = $attributes['openOn'] ?? 'click';
		$item_padding           = $attributes['itemPadding'] ?? array(
			'top'    => 8,
			'right'  => 8,
			'bottom' => 8,
			'left'   => 8,
		);
		$item_text_color        = $attributes['itemTextColor'] ?? '#000000';
		$item_bg_color          = $attributes['itemBgColor'] ?? '#FFFFFF';
		$item_hover_text_color  = $attributes['itemHoverTextColor'] ?? '#000000';
		$item_hover_bg_color    = $attributes['itemHoverBgColor'] ?? '#EEEEEEE';
		$item_divider_color     = $attributes['itemDividerColor'] ?? '#000000';
		$item_divider_thickness = $attributes['itemDividerThickness'] ?? 1;

		// Generate styles.
		$styles = sprintf(
			'
				#%1$s .wp-block-gatherpress-dropdown-item a {
					padding: %2$dpx %3$dpx %4$dpx %5$dpx;
					color: %6$s;
					background-color: %7$s;
				}
				#%1$s .wp-block-gatherpress-dropdown-item a:hover {
					color: %8$s;
					background-color: %9$s;
				}
				#%1$s .wp-block-gatherpress-dropdown-item:not(:first-child) {
					border-top: %10$dpx solid %11$s;
				}
			',
			esc_attr( $dropdown_id ),
			intval( $item_padding['top'] ),
			intval( $item_padding['right'] ),
			intval( $item_padding['bottom'] ),
			intval( $item_padding['left'] ),
			esc_attr( $item_text_color ),
			esc_attr( $item_bg_color ),
			esc_attr( $item_hover_text_color ),
			esc_attr( $item_hover_bg_color ),
			intval( $item_divider_thickness ),
			esc_attr( $item_divider_color )
		);

		// Add hover or focus styles if `openOn` is set to `hover`.
		if ( 'hover' === $open_on ) {
			$styles .= sprintf(
				'
					.%1$s:hover #%2$s,
					.%1$s:focus-within #%2$s {
						display: block;
					}
				',
				esc_attr( $block_instance->get_default_block_class( $block['blockName'] ) ),
				esc_attr( $dropdown_id )
			);
		}

		// Add styles to block content.
		$block_content = sprintf(
			'<style>%s</style>%s',
			$styles,
			$block_content
		);

		return $block_content;
	}

	/**
	 * Adds attributes to dropdown block elements when "Select Mode" is enabled.
	 *
	 * This method modifies the block content to set `data-dropdown-mode="select"`
	 * and adds the `gatherpress--is-disabled` class to the selected dropdown item.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original block content.
	 * @param array  $block         The parsed block data.
	 * @return string The modified block content with select mode attributes.
	 */
	public function apply_select_mode_attributes( string $block_content, array $block ): string {
		$tag            = new WP_HTML_Tag_Processor( $block_content );
		$attributes     = $block['attrs'] ?? array();
		$act_as_select  = $attributes['actAsSelect'] ?? false;
		$selected_index = $attributes['selectedIndex'] ?? 0;

		if ( $tag->next_tag() ) {
			if ( $act_as_select ) {
				$tag->set_attribute( 'data-dropdown-mode', 'select' );
				$tag->next_tag(
					array(
						'tag_name'   => 'div',
						'attributes' => array( 'class' => 'wp-block-gatherpress-dropdown__menu' ),
					)
				);

				// Reset to the parent container and iterate through child items.
				$item_index = 0;
				while ( $tag->next_tag(
					array(
						'tag_name'   => 'div',
						'attributes' => array( 'class' => 'wp-block-gatherpress-dropdown-item' ),
					)
				) ) {
					// When select, all links must act like buttons.
					$tag->next_tag( array( 'tag_name' => 'a' ) );

					$tag->set_attribute( 'href', '#' );
					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-on--click', 'actions.linkHandler' );
					$tag->set_attribute( 'tabindex', '0' );
					$tag->set_attribute( 'role', 'button' );

					// Check if the current item's index matches $select_index.
					if ( $item_index === (int) $selected_index ) {
						$existing_class = $tag->get_attribute( 'class' );
						$new_class      = $existing_class
							? $existing_class . ' gatherpress--is-disabled'
							: 'gatherpress--is-disabled';

						$tag->set_attribute( 'class', $new_class );
						$tag->set_attribute( 'tabindex', '-1' );
						$tag->set_attribute( 'aria-disabled', 'true' );

						break; // Exit the loop once the desired item is found.
					}

					++$item_index;
				}

				$block_content = $tag->get_updated_html();
			}
		}

		return $block_content;
	}

	/**
	 * Modifies the dropdown block's attributes for interactivity.
	 *
	 * Dynamically updates the dropdown block's rendered content to include necessary
	 * attributes for improved functionality and interactivity.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content with updated attributes.
	 */
	public function apply_dropdown_attributes( string $block_content, array $block ): string {
		$tag                       = new WP_HTML_Tag_Processor( $block_content );
		$attributes                = $block['attrs'] ?? array();
		$open_on                   = $attributes['openOn'] ?? 'click';
		$label_color               = $attributes['labelColor'] ?? '#000000';
		$dropdown_id               = $attributes['dropdownId'] ?? '';
		$dropdown_border_thickness = $attributes['dropdownBorderThickness'] ?? 1;
		$dropdown_border_color     = $attributes['dropdownBorderColor'] ?? '#000000';
		$dropdown_border_radius    = $attributes['dropdownBorderRadius'] ?? 8;
		$dropdown_z_index          = $attributes['dropdownZIndex'] ?? 1001;
		$dropdown_width            = $attributes['dropdownWidth'] ?? 240;

		if (
			$tag->next_tag(
				array(
					'tag_name'   => 'a',
					'attributes' => array(
						'class' => 'wp-block-gatherpress-dropdown__trigger',
					),
				),
			)
		) {
			$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
			$tag->set_attribute( 'role', 'button' );

			if ( 'click' === $open_on ) {
				$tag->set_attribute( 'aria-controls', $dropdown_id );
				$tag->set_attribute( 'aria-expanded', 'false' );
				$tag->set_attribute( 'tabindex', '0' );
				$tag->set_attribute( 'data-wp-on--click', 'actions.toggleDropdown' );
			}

			if ( 'hover' === $open_on ) {
				$tag->set_attribute( 'data-wp-on--click', 'actions.preventDefault' );
			}

			$existing_styles = $tag->get_attribute( 'style' );

			if ( ! empty( $label_color ) ) {
				$new_style     = sprintf( 'color: %s;', $label_color );
				$merged_styles = trim( $existing_styles . ' ' . $new_style );

				$tag->set_attribute( 'style', $merged_styles );
			}

			$block_content = $tag->get_updated_html();
		}

		if (
			$tag->next_tag(
				array(
					'tag_name'   => 'div',
					'attributes' => array(
						'class' => 'wp-block-gatherpress-dropdown__menu',
					),
				)
			)
		) {
			$tag->set_attribute( 'role', 'region' );
			$tag->set_attribute( 'id', $dropdown_id );

			$existing_styles = $tag->get_attribute( 'style' );
			$new_styles      = array(
				sprintf(
					'border: %dpx solid %s;',
					intval( $dropdown_border_thickness ),
					esc_attr( $dropdown_border_color )
				),
				sprintf(
					'border-radius: %dpx;',
					intval( $dropdown_border_radius )
				),
				sprintf(
					'z-index: %d;',
					intval( $dropdown_z_index )
				),
				sprintf(
					'width: %dpx;',
					intval( $dropdown_width )
				),
			);

			$merged_styles = trim( $existing_styles . ' ' . implode( ' ', $new_styles ) );

			$tag->set_attribute( 'style', $merged_styles );

			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}
}
