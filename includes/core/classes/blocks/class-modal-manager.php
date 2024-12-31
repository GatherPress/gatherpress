<?php
/**
 * The "Modal Manager" class handles the functionality of the Modal Manager block,
 * enabling dynamic management of modals and their associated triggers.
 *
 * This class is responsible for transforming block content, ensuring proper behavior
 * of modal interactions, and preparing the block for output.
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
 * Class responsible for managing the "Modal Manager" block and its associated functionality,
 * including dynamic transformations, modal interactions, and enhancements for interactivity.
 *
 * @since 1.0.0
 */
class Modal_Manager {
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
	const BLOCK_NAME = 'gatherpress/modal-manager';

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
		add_filter( 'render_block', array( $this, 'attach_modal_open_behavior' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'attach_modal_close_behavior' ), 10, 2 );
	}

	/**
	 * Attaches modal open behavior to elements with the class 'gatherpress--open-modal'.
	 *
	 * This method scans the block content for elements containing the 'gatherpress--open-modal'
	 * class. If such elements are found, it applies the appropriate interactivity attributes
	 * for opening modals. If the target element is not a link (`<a>`) or button (`<button>`),
	 * it modifies the element to behave as a button by adding relevant ARIA and keyboard support.
	 *
	 * - Adds `data-wp-interactive` for interactivity.
	 * - Adds `data-wp-on--click` to handle click events.
	 * - Adds `data-wp-on--keydown` for keyboard accessibility.
	 * - Sets `role="button"` and `tabindex="0"` for non-native actionable elements.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original block HTML content.
	 * @param array  $block         The block data, including attributes and block name.
	 *
	 * @return string The modified block content with modal open behavior attributes applied.
	 */
	public function attach_modal_open_behavior( string $block_content, array $block ): string {
		if ( self::BLOCK_NAME !== $block['blockName'] ) {
			return $block_content;
		}

		$tag = new WP_HTML_Tag_Processor( $block_content );

		// Process only tags with the specific class 'gatherpress--open-modal'.
		// @phpstan-ignore-next-line
		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			if ( $class_attr && false !== strpos( $class_attr, 'gatherpress--open-modal' ) ) {
				if (
					$tag->next_tag() &&
					in_array( $tag->get_tag(), array( 'A', 'BUTTON' ), true )
				) {
					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-on--click', 'actions.openModal' );
				} else {
					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-on--click', 'actions.openModal' );
					$tag->set_attribute( 'data-wp-on--keydown', 'actions.openModalOnEnter' );
					$tag->set_attribute( 'tabindex', '0' );
					$tag->set_attribute( 'role', 'button' );
				}
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Attaches modal close behavior to elements with the class 'gatherpress--close-modal'.
	 *
	 * This method scans the block content for elements containing the 'gatherpress--close-modal'
	 * class. If such elements are found, it applies the appropriate interactivity attributes
	 * for closing modals. If the target element is not a link (`<a>`) or button (`<button>`),
	 * it modifies the element to behave as a button by adding relevant ARIA and keyboard support.
	 *
	 * - Adds `data-wp-interactive` for interactivity.
	 * - Adds `data-wp-on--click` to handle click events.
	 * - Adds `data-wp-on--keydown` for keyboard accessibility.
	 * - Sets `role="button"` and `tabindex="0"` for non-native actionable elements.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original block HTML content.
	 * @param array  $block         The block data, including attributes and block name.
	 *
	 * @return string The modified block content with modal close behavior attributes applied.
	 */
	public function attach_modal_close_behavior( string $block_content, array $block ): string {
		if ( self::BLOCK_NAME !== $block['blockName'] ) {
			return $block_content;
		}

		$tag = new WP_HTML_Tag_Processor( $block_content );

		// Process only tags with the specific class 'gatherpress--close-modal'.
		// @phpstan-ignore-next-line
		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			if ( $class_attr && false !== strpos( $class_attr, 'gatherpress--close-modal' ) ) {
				if (
					$tag->next_tag() &&
					in_array( $tag->get_tag(), array( 'A', 'BUTTON' ), true )
				) {
					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-on--click', 'actions.closeModal' );
				} else {
					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-on--click', 'actions.closeModal' );
					$tag->set_attribute( 'data-wp-on--keydown', 'actions.closeModalOnEnter' );
					$tag->set_attribute( 'tabindex', '0' );
					$tag->set_attribute( 'role', 'button' );
				}
			}
		}

		return $tag->get_updated_html();
	}
}
