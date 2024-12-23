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
use GatherPress\Core\Traits\Singleton;
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
		add_filter( 'render_block', array( $this, 'modify_block_output' ), 10, 2 );
	}

	/**
	 * Modifies the block output to dynamically adjust attributes or styles.
	 *
	 * This method processes the block's rendered HTML content and applies
	 * adjustments to its attributes, styles, or structure based on the block's
	 * attributes or context. It ensures the block behaves as intended when
	 * rendered on the front end.
	 *
	 * Custom modifications, such as dynamically setting styles or attributes,
	 * are handled within this method to enhance the block's functionality or
	 * interactivity.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The updated block content with the applied adjustments.
	 */
	public function modify_block_output( string $block_content, array $block ): string {
		$block_instance = Block::get_instance();

		if ( self::BLOCK_NAME === $block['blockName'] ) {
			$tag = new WP_HTML_Tag_Processor( $block_content );

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
		}

		return $block_content;
	}
}
