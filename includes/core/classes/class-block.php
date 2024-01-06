<?php
/**
 * Main class for managing custom blocks in GatherPress.
 *
 * This class handles the registration and management of custom blocks used in the GatherPress plugin.
 *
 * @package GatherPress/Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use GatherPress\Core\Traits\Singleton;

/**
 * Class Block.
 *
 * Core class for handling blocks in GatherPress.
 *
 * @since 1.0.0
 */
class Block {
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
		// Priority 11 needed for block.json translations of title and description.
		add_action( 'init', array( $this, 'register_blocks' ), 11 );
		add_filter( 'load_script_translation_file', array( $this, 'fix_translation_location' ), 10, 3 );
	}

	/**
	 * Fix translation file location for a specific Gutenberg block.
	 *
	 * @todo A fix will come eventually. See Issue #1: .json-file is not loaded.
	 *       More info: https://awhitepixel.com/how-to-translate-custom-gutenberg-blocks-with-block-json/.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file    The current translation file path.
	 * @param string $handle  The script or style's registered handle.
	 * @param string $domain  The translation text domain.
	 *
	 * @return string The modified translation file path.
	 */
	public function fix_translation_location( string $file, string $handle, string $domain ): string {
		if ( false !== strpos( $handle, 'gatherpress' ) && 'gatherpress' === $domain ) {
			$file = str_replace( WP_LANG_DIR . '/plugins', GATHERPRESS_CORE_PATH . '/languages', $file );
		}

		return $file;
	}

	/**
	 * Register custom blocks.
	 *
	 * This method scans a directory for custom block definitions and registers them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$blocks_directory = sprintf( '%1$s/build/blocks/', GATHERPRESS_CORE_PATH );
		$blocks           = array_diff( scandir( $blocks_directory ), array( '..', '.' ) );
		foreach ( $blocks as $block ) {
			register_block_type(
				sprintf( '%1$s/build/blocks/%2$s', GATHERPRESS_CORE_PATH, $block )
			);
		}
	}
}
