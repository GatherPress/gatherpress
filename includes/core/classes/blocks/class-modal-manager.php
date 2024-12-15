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
		add_filter( 'render_block', array( $this, 'inject_modal_behavior' ), 10, 2 );
	}

	/**
	 * Injects modal interactivity behavior into block content.
	 *
	 * This method enhances `core/button` blocks with specific classes by injecting
	 * attributes necessary for modal interactivity. It supports both `<button>`
	 * and `<a>` elements and applies the corresponding interactivity attributes
	 * based on the class names `gatherpress--open-modal` and `gatherpress--close-modal`.
	 *
	 * If a block contains the `gatherpress--open-modal` class, it adds attributes
	 * to handle opening the modal. Similarly, for the `gatherpress--close-modal`
	 * class, it adds attributes for closing the modal.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The updated block content with interactivity attributes.
	 */
	public function inject_modal_behavior( string $block_content, array $block ): string {
		$block_instance = Block::get_instance();

		if (
			'core/button' === $block['blockName'] &&
			isset( $block['attrs']['className'] )
		) {
			$tag        = new WP_HTML_Tag_Processor( $block_content );
			$button_tag = $block_instance->locate_button_tag( $tag, 'button' );

			if ( empty( $button_tag ) ) {
				$tag        = new WP_HTML_Tag_Processor( $block_content );
				$button_tag = $block_instance->locate_button_tag( $tag, 'a' );
			}

			if (
				$button_tag &&
				false !== strpos( $block['attrs']['className'], 'gatherpress--open-modal' )
			) {
				$button_tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$button_tag->set_attribute( 'data-wp-on--click', 'actions.openModal' );
			}

			if (
				$button_tag &&
				false !== strpos( $block['attrs']['className'], 'gatherpress--close-modal' )
			) {
				$button_tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$button_tag->set_attribute( 'data-wp-on--click', 'actions.closeModal' );
			}

			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}
}
