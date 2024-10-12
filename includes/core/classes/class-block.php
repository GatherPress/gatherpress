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
		// Priority 9 needed to allow the Block_Variation(s) to register their assets on init:10, without worries.
		add_action( 'init', array( $this, 'register_block_variations' ), 9 );
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

<<<<<<< HEAD
	/**
	 * Require files & instantiate block-variation classes.
	 *
	 * @return void
	 */
	public function register_block_variations(): void {
		foreach ( $this->get_block_variations() as $block ) {
			// Prepare namespaced class-name
			// in the following shape: "GatherPress\Core\Blocks\Block_Variation"  (example).
			$name = join(
				'\\',
				array(
					__NAMESPACE__,
					'Blocks',
					$this->get_classname_from_foldername( $block ),
				)
			);

			if ( class_exists( $name ) ) {
				$name::get_instance();
			}
		}
	}

	/**
	 * Get list of all block variations based on the build directory.
	 *
	 * @return string[] List of block-variations foldernames.
	 */
	protected static function get_block_variations(): array {
		$blocks_directory = sprintf( '%1$s/build/variations/', GATHERPRESS_CORE_PATH );

		if ( ! file_exists( $blocks_directory ) ) {
			return array();
		}

		$blocks = array_values(
			array_diff(
				scandir( $blocks_directory ),
				array( '..', '.' )
			)
		);

		return $blocks; // maybe cache in var.
	}

	/**
	 * Get class name from folder name.
	 *
	 * @todo maybe better in the Utility class?
	 *
	 * @param  string $foldername String with name of a folder.
	 *
	 * @return string Class name that reflects the given foldername.
	 */
	protected static function get_classname_from_foldername( string $foldername ): string {
		$foldername = basename( $foldername );
		return ucwords( str_replace( '-', '_', $foldername ), '_' );
	}

=======
>>>>>>> 9baff99061fdf6481e786eb1b17611779fd391db
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
		$block_patterns = array(
			array(
				'gatherpress/event-template',
				array(
					'title'    => __( 'Invisible Event Template Block Pattern', 'gatherpress' ),
					// Even this paragraph seems useless, it's not.
					// It is the entry point for all our hooked blocks
					// and as such absolutely important!
					'content'  => '<!-- gatherpress:event-date /--><!-- wp:pattern {"slug":"gatherpress/venue-details"} /-->', // Other blocks are hooked-in here.
					'inserter' => false,
					'source'   => 'plugin',
				),
			),
			array(
				'gatherpress/venue-template',
				array(
					'title'    => __( 'Invisible Venue Template Block Pattern', 'gatherpress' ),
					// Even this paragraph seems useless, it's not.
					// It is the entry point for all our hooked blocks
					// and as such absolutely important!
					'content'  => '<!-- wp:post-featured-image /--><!-- wp:paragraph {"placeholder":"Add some infos about the venue and maybe a nice picture."} --><p></p><!-- /wp:paragraph -->', // Other blocks are hooked-in here.
					'inserter' => false,
					'source'   => 'plugin',
				),
			),
			array(
				'gatherpress/venue-details',
				array(
					'title'    => __( 'Invisible Venue Details Block Pattern', 'gatherpress' ),
					// Even this post-title seems useless, it's not.
					// It is the entry point for all our hooked blocks
					// and as such absolutely important!
					'content'  => '<!-- wp:post-title /-->', // Other blocks are hooked-in here.
					'inserter' => false,
					'source'   => 'plugin',
				),
			),
		);

		foreach ( $block_patterns as $block_pattern ) {
			/**
			 * Made to be used with the 'template' parameter
			 * when registering the 'gatherpress_event' post type
			 * and will not be visible to the editor at any point.
			 */
			register_block_pattern( $block_pattern[0], $block_pattern[1] );
		}
	}
}
