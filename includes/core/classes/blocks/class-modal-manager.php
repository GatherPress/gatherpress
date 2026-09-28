<?php
/**
 * The "Modal Manager" class handles the functionality of the Modal Manager block,
 * enabling dynamic management of modals and their associated triggers.
 *
 * This class is responsible for transforming block content, ensuring proper behavior
 * of modal interactions, and preparing the block for output.
 *
 * @package GatherPress\Core
 * @since 0.33.0
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
 * @since 0.33.0
 */
final class Modal_Manager {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 0.33.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/modal-manager';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.33.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.33.0
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
	 * - Adds `data-wp-on--keydown` for keyboard accessibility. Links get it too:
	 *   `role="button"` tells assistive technology the element responds to Space,
	 *   which a native link does not.
	 * - Sets `role="button"`, plus `tabindex="0"` on elements that are not focusable on their own
	 *   (anything except a `<button>` or a link with an `href`).
	 *
	 * @since 0.33.0
	 * @since TBD Links acting as buttons also open the modal on Space. Links without an
	 *            `href` get `tabindex`.
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
					$is_actionable_element = $tag->next_tag()
					&& in_array( $tag->get_tag(), array( 'A', 'BUTTON' ), true );
				}

				$target_found = $is_actionable_element;

				// Apply modal attributes if target was found.
				if ( $target_found ) {
					// Links with an href are already focusable, so they skip tabindex,
					// but they still need the keydown handler so Space opens the modal.
					// A link without an href (e.g. a Button block with no URL) is not
					// focusable, so it gets tabindex like any other element.
					$tag->set_attribute( 'data-wp-on--keydown', 'actions.openModalOnEnter' );
					$tag->set_attribute( 'role', 'button' );

					if ( ! $this->is_link_with_href( $tag ) ) {
						$tag->set_attribute( 'tabindex', '0' );
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
	 * - Adds `data-wp-on--keydown` for keyboard accessibility, including on links,
	 *   so Space closes the modal from a link acting as a button.
	 * - Sets `role="button"`, plus `tabindex="0"` on elements that are not focusable on their own
	 *   (anything except a `<button>` or a link with an `href`).
	 *
	 * @since 0.33.0
	 * @since TBD Links acting as buttons also close the modal on Space. Links without an
	 *            `href` get `tabindex`.
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
				// @phpstan-ignore-next-line
				$is_link_with_href = $tag->next_tag() && $this->is_link_with_href( $tag );

				// Links with an href are already focusable, so they skip tabindex,
				// but they still need the keydown handler so Space closes the modal.
				// A link without an href is not focusable, so it gets tabindex.
				$tag->set_attribute( 'data-wp-on--keydown', 'actions.closeModalOnEnter' );
				$tag->set_attribute( 'role', 'button' );

				if ( ! $is_link_with_href ) {
					$tag->set_attribute( 'tabindex', '0' );
				}

				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-on--click', 'actions.closeModal' );
				$tag->set_attribute( 'data-close-modal', true );
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Checks whether the processor's current tag is a link with an `href`.
	 *
	 * Only such a link is focusable without `tabindex`. An `<a>` without an
	 * `href` (a Button block with no URL renders one) is not in the tab order.
	 * A valueless `href` still counts, because browsers treat it as a link.
	 *
	 * @since TBD
	 *
	 * @param WP_HTML_Tag_Processor $tag Processor positioned on the tag to check.
	 *
	 * @return bool True when the tag is an `<a>` with an `href` attribute.
	 */
	private function is_link_with_href( WP_HTML_Tag_Processor $tag ): bool {
		return 'A' === $tag->get_tag() && null !== $tag->get_attribute( 'href' );
	}
}
