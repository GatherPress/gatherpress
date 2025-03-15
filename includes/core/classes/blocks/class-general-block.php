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

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
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
		add_filter( 'render_block', array( $this, 'process_login_block' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'process_registration_block' ), 10, 2 );
	}

	/**
	 * Processes blocks with the `gatherpress--has-login-url` class.
	 *
	 * This method performs two functions:
	 * 1. Removes the block entirely if the user is already logged in
	 * 2. Dynamically replaces the placeholder login URL with the actual login URL
	 *    for users who are not logged in
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content or an empty string if the block should be removed.
	 */
	public function process_login_block( string $block_content, array $block ): string {
		if (
			false !== strpos( $block['attrs']['className'] ?? '', 'gatherpress--has-login-url' ) &&
			is_user_logged_in()
		) {
			return '';
		}

		if (
			false !== strpos( $block['attrs']['className'] ?? '', 'gatherpress--has-login-url' )
		) {
			$tag = new WP_HTML_Tag_Processor( $block_content );

			while ( $tag->next_tag( array( 'tag_name'   => 'a' ) ) ) {
				if ( '#gatherpress-login-url' === $tag->get_attribute( 'href' ) ) {
					$tag->set_attribute( 'href', Utility::get_login_url() );
				}
			}

			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}

	/**
	 * Processes blocks with the `gatherpress--has-registration-url` class.
	 *
	 * This method performs two functions:
	 * 1. Removes the block entirely if user registration is disabled in WordPress settings
	 * 2. For enabled registration, dynamically replaces the placeholder registration URL with the actual
	 *    registration URL
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content or an empty string if the block should be removed.
	 */
	public function process_registration_block( string $block_content, array $block ): string {
		if (
			false !== strpos( $block['attrs']['className'] ?? '', 'gatherpress--has-registration-url' ) &&
			! get_option( 'users_can_register' )
		) {
			return '';
		}

		return $block_content;
	}
}
