<?php
/**
 * The "General_Block" class handles general-purpose block functionality,
 * providing a catch-all for block-related logic that is not specific to any single block.
 *
 * This class ensures proper behavior and rendering adjustments for blocks
 * that do not belong to a specific block type but require additional processing.
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
 * Class responsible for managing general block-related functionality
 * and applying modifications that are not tied to specific block types.
 *
 * This class acts as a central handler for non-specific block logic,
 * such as filtering or injecting attributes for blocks with certain characteristics.
 *
 * @since 1.0.0
 */
class General_Block {
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
		add_filter( 'render_block', array( $this, 'remove_block_if_user_logged_in' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'remove_block_if_registration_disabled' ), 10, 2 );
	}

	/**
	 * Removes blocks with the `gatherpress--has-login-url` class if the user is logged in.
	 *
	 * This method checks if the block contains the `gatherpress--has-login-url` class
	 * and removes it from rendering if the user is currently logged in.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content or an empty string if the block should be removed.
	 */
	public function remove_block_if_user_logged_in( string $block_content, array $block ): string {
		if (
			false !== strpos( $block['attrs']['className'] ?? '', 'gatherpress--has-login-url' ) &&
			is_user_logged_in()
		) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Removes blocks with the `gatherpress--has-registration-url` class if user registration is disabled.
	 *
	 * This method checks if the block contains the `gatherpress--has-registration-url` class
	 * and removes it from rendering if the WordPress `users_can_register` option is disabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content or an empty string if the block should be removed.
	 */
	public function remove_block_if_registration_disabled( string $block_content, array $block ): string {
		if (
			false !== strpos( $block['attrs']['className'] ?? '', 'gatherpress--has-registration-url' ) &&
			! get_option( 'users_can_register' )
		) {
			return '';
		}

		return $block_content;
	}
}
