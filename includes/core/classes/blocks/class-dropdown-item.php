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
class Dropdown_Item {
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
	const BLOCK_NAME = 'gatherpress/dropdown-item';

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

		add_filter( $render_block_hook, array( $this, 'apply_dropdown_attributes' ) );
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
	 *
	 * @return string The modified block content with inline styles.
	 */
	public function apply_dropdown_attributes( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		if ( $tag->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$href = $tag->get_attribute( 'href' );

			if ( empty( $href ) || '#' === $href ) {
				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-on--click', 'actions.linkHandler' );
				$tag->set_attribute( 'tabindex', '0' );
				$tag->set_attribute( 'role', 'button' );
			}

			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}
}
