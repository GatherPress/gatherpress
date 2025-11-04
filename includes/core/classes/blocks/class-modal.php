<?php
/**
 * The "Modal" class handles the functionality of the Modal block,
 * ensuring proper behavior and rendering of individual modals.
 *
 * This class is responsible for transforming block content to dynamically inject
 * styles and attributes.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Block;
use GatherPress\Core\Rsvp_Setup;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "Modal" block and its functionality,
 * including dynamic rendering adjustments.
 *
 * @since 1.0.0
 */
class Modal {
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
	const BLOCK_NAME = 'gatherpress/modal';

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

		add_filter( $render_block_hook, array( $this, 'apply_modal_attributes' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'adjust_block_z_index' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'filter_login_modal' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'filter_rsvp_modal' ), 10, 2 );
	}

	/**
	 * Modifies the modal block's attributes for accessibility.
	 *
	 * Dynamically updates the modal block's rendered content to include necessary
	 * attributes for improved accessibility and functionality.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content with updated attributes.
	 */
	public function apply_modal_attributes( string $block_content, array $block ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		if ( $tag->next_tag() ) {
			$modal_name = $block['attrs']['metadata']['name'] ?? __( 'Modal', 'gatherpress' );

			$tag->set_attribute( 'role', 'dialog' );
			$tag->set_attribute( 'aria-modal', 'true' );
			$tag->set_attribute( 'aria-hidden', 'true' );
			$tag->set_attribute( 'aria-label', esc_attr( $modal_name ) );
			$tag->set_attribute( 'tabindex', '-1' );

			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}

	/**
	 * Adjusts the block's `z-index` dynamically.
	 *
	 * This method processes the block's rendered HTML content and applies
	 * the `z-index` value from the block's attributes to the block's `style` attribute.
	 * If no `z-index` is specified, a default value of `1000` is used.
	 *
	 * This ensures proper stacking behavior for the block in the DOM.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The updated block content with the applied `z-index` styling.
	 */
	public function adjust_block_z_index( string $block_content, array $block ): string {
		$block_instance = Block::get_instance();
		$tag            = new WP_HTML_Tag_Processor( $block_content );

		if ( $tag->next_tag() ) {
			$z_index               = $block['attrs']['zIndex'] ?? 1000;
			$existing_styles       = $tag->get_attribute( 'style' ) ?? '';
			$existing_styles_array = explode( ';', rtrim( $existing_styles, ';' ) );
			$existing_styles_clean = implode( ';', array_filter( $existing_styles_array ) ) . ';';
			$updated_styles        = trim(
				sprintf( $existing_styles_clean . ' z-index: %d;', $z_index )
			);

			$tag->set_attribute( 'style', $updated_styles );
		}

		$block_content = $tag->get_updated_html();

		return $block_content;
	}

	/**
	 * Filters the output of login modals for logged-in users.
	 *
	 * This method checks if the block is a `gatherpress/modal` block with the
	 * `gatherpress-modal--type-login` class. If the user is logged in, it removes
	 * the block's output.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content. Returns an empty string if the block should be removed.
	 */
	public function filter_login_modal( string $block_content, array $block ): string {
		if (
			Utility::has_css_class( $block['attrs']['className'] ?? null, 'gatherpress-modal--type-login' ) &&
			is_user_logged_in()
		) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Filters the output of RSVP modals for non-logged-in users.
	 *
	 * This method checks if the block is a `gatherpress/modal` block with the
	 * `gatherpress-modal--type-rsvp` class. If the user is not logged in, it removes
	 * the block's output.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content. Returns an empty string if the block should be removed.
	 */
	public function filter_rsvp_modal( string $block_content, array $block ): string {
		if (
			Utility::has_css_class( $block['attrs']['className'] ?? null, 'gatherpress-modal--type-rsvp' ) &&
			! Rsvp_Setup::get_instance()->get_user_identifier()
		) {
			return '';
		}

		return $block_content;
	}
}
