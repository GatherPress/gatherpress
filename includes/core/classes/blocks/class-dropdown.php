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
		add_filter( 'render_block', array( $this, 'apply_dropdown_attributes' ), 10, 2 );
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
		if ( self::BLOCK_NAME === $block['blockName'] ) {
			$tag = new WP_HTML_Tag_Processor( $block_content );

			if ( $tag->next_tag() && $tag->next_tag() ) {
				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-on--click', 'actions.toggleDropdown' );

				$block_content = $tag->get_updated_html();
			}
		}

		return $block_content;
	}
}
