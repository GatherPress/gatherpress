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

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
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
		$render_block_hook = sprintf( 'render_block_%s', self::BLOCK_NAME );

		add_filter( $render_block_hook, array( $this, 'attach_modal_open_behavior' ) );
		add_filter( $render_block_hook, array( $this, 'attach_modal_close_behavior' ) );
	}

	/**
	 * Attaches modal open behavior to elements with the class 'gatherpress-modal--trigger-open'.
	 *
	 * This method scans the block content for elements containing the 'gatherpress-modal--trigger-open'
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
	 *
	 * @return string The modified block content with modal open behavior attributes applied.
	 */
	public function attach_modal_open_behavior( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		// Process only tags with the specific class 'gatherpress-modal--trigger-open'.
		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			if ( Utility::has_css_class( $class_attr, 'gatherpress-modal--trigger-open' ) ) {
				// Check if current element is an anchor or button.
				$is_actionable_element = in_array( $tag->get_tag(), array( 'A', 'BUTTON' ), true );

				if ( ! $is_actionable_element ) {
					// If not, check if the next element is an anchor or button.
					// @phpstan-ignore-next-line.
					$is_actionable_element = $tag->next_tag() && in_array( $tag->get_tag(), array( 'A', 'BUTTON' ), true );
				}

				$target_found = $is_actionable_element;

				// Apply modal attributes if target was found.
				if ( $target_found ) {
					// Links only get role="button", others get full keyboard handling.
					if ( 'A' === $tag->get_tag() ) {
						$tag->set_attribute( 'role', 'button' ); // For links acting as buttons.
					} else {
						$tag->set_attribute( 'data-wp-on--keydown', 'actions.openModalOnEnter' );
						$tag->set_attribute( 'tabindex', '0' );
						$tag->set_attribute( 'role', 'button' );
					}

					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-on--click', 'actions.openModal' );
				}
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Attaches modal close behavior to elements with the class 'gatherpress-modal--trigger-close'.
	 *
	 * This method scans the block content for elements containing the 'gatherpress-modal--trigger-close'
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
	 *
	 * @return string The modified block content with modal close behavior attributes applied.
	 */
	public function attach_modal_close_behavior( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		// Process only tags with the specific class 'gatherpress-modal--trigger-close'.
		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			if ( Utility::has_css_class( $class_attr, 'gatherpress-modal--trigger-close' ) ) {
				if (
					// @phpstan-ignore-next-line
					$tag->next_tag() &&
					in_array( $tag->get_tag(), array( 'A' ), true )
				) {
					$tag->set_attribute( 'role', 'button' ); // For links acting as buttons.
				} else {
					$tag->set_attribute( 'data-wp-on--keydown', 'actions.closeModalOnEnter' );
					$tag->set_attribute( 'tabindex', '0' );
					$tag->set_attribute( 'role', 'button' );
				}

				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-on--click', 'actions.closeModal' );
				$tag->set_attribute( 'data-close-modal', true );
			}
		}

		return $tag->get_updated_html();
	}
}
