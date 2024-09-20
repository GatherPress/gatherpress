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

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

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
		add_action( 'init', array( $this, 'register_block_patterns' ) );
		// Priority 11 needed for block.json translations of title and description.
		add_action( 'init', array( $this, 'register_blocks' ), 11 );
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


	/**
	 * Register block patterns.
	 *
	 * This method registers multiple different block-patterns for GatherPress.
	 *
	 * @since 1.0.0
	 * @see   https://developer.wordpress.org/reference/functions/register_block_pattern/
	 *
	 * @return void
	 */
	public function register_block_patterns(): void {

		/**
		 * Made to be used with the 'template' parameter
		 * when registering the 'gatherpress_event' post type
		 * and will not be visible to the editor at any point.
		 */
		\register_block_pattern(
			'gatherpress/event-template',
			array(
				'title'    => 'Invisible Event Template Block Pattern',
				// Even this paragraph seems useless, it's not.
				// It is the entry point for all our hooked blocks
				// and as such absolutely important!
				'content'  => '<!-- gatherpress:event-date /--><!-- wp:pattern {"slug":"gatherpress/venue-details"} /-->', // Other blocks are hooked-in here.
				'inserter' => false,
				'source'   => 'plugin',
			)
		);

		/**
		 * Made to be used with the 'template' parameter
		 * when registering the 'gatherpress_venue' post type
		 * and will not be visible to the editor at any point.
		 */
		\register_block_pattern(
			'gatherpress/venue-template',
			array(
				'title'    => 'Invisible Venue Template Block Pattern',
				// Even this paragraph seems useless, it's not.
				// It is the entry point for all our hooked blocks
				// and as such absolutely important!
				'content'  => '<!-- wp:post-featured-image /--><!-- wp:paragraph {"placeholder":"Add some infos about the venue and maybe a nice picture."} --><p></p><!-- /wp:paragraph -->', // Other blocks are hooked-in here.
				'inserter' => false,
				'source'   => 'plugin',
			)
		);

		/**
		 * Mainly for use with the 'venue-details' block,
		 * which is a group block under the hood
		 * and uses this pattern as innerBlocks template,
		 * it will not be visible to the editor at any point.
		 */
		\register_block_pattern(
			'gatherpress/venue-details',
			array(
				'title'    => 'Invisible Venue Details Block Pattern',
				// Even this post-title seems useless, it's not.
				// It is the entry point for all our hooked blocks
				// and as such absolutely important!
				'content'  => '<!-- wp:post-title /-->', // Other blocks are hooked-in here.
				'inserter' => false,
				'source'   => 'plugin',
			)
		);
	}
}
