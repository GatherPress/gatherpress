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
use WP_Block_Template;
use WP_Post;

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
	 * An array used to cache block variation names.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	protected array $block_variation_names = array();

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
		add_action( 'init', array( $this, 'register_block_classes' ) );
		add_action( 'init', array( $this, 'register_block_patterns' ) );
		// Priority 11 needed for block.json translations of title and description.
		add_action( 'init', array( $this, 'register_blocks' ), 11 );
		// Run on priority 9 to allow extenders to use the hooks with the default of 10.
		add_filter( 'hooked_block_types', array( $this, 'hook_blocks_into_patterns' ), 9, 4 );
		add_filter( 'hooked_block_core/paragraph', array( $this, 'modify_hooked_blocks_in_patterns' ), 9, 5 );
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
			$block_metadata_path = sprintf( '%1$s/build/blocks/%2$s', GATHERPRESS_CORE_PATH, $block );

			if ( is_dir( $block_metadata_path ) ) {
				register_block_type( $block_metadata_path );
			}
		}
	}

	/**
	 * Instantiate block classes.
	 *
	 * @return void
	 */
	public function register_block_classes(): void {
		Blocks\Add_To_Calendar::get_instance();
		Blocks\Dropdown::get_instance();
		Blocks\Dropdown_Item::get_instance();
		Blocks\Event_Date::get_instance();
		Blocks\Event_Query::get_instance();
		Blocks\General_Block::get_instance();
		Blocks\Modal::get_instance();
		Blocks\Modal_Manager::get_instance();
		Blocks\Rsvp::get_instance();
		Blocks\Rsvp_Form::get_instance();
		Blocks\Rsvp_Response::get_instance();
		Blocks\Rsvp_Template::get_instance();
	}

	/**
	 * Get a list of subfolder names from the /build/variations/core/ directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] List of block-variations foldernames.
	 */
	public function get_block_variations(): array {
		$variations_directory = sprintf( '%1$s/build/variations/core/', GATHERPRESS_CORE_PATH );

		if ( ! file_exists( $variations_directory ) ) {
			return array();
		}

		if ( empty( $this->block_variation_names ) ) {
			$this->block_variation_names = array_values(
				array_diff(
					scandir( $variations_directory ),
					array( '..', '.' )
				)
			);
		}
		return array_filter( $this->block_variation_names );
	}

	/**
	 * Generate the default CSS class for a block.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_name The block name in the format 'namespace/blockname'.
	 * @return string The default CSS class for the block.
	 */
	public function get_default_block_class( string $block_name ): string {
		return sprintf(
			'wp-block-%s',
			sanitize_key( str_replace( '/', '-', $block_name ) )
		);
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
					'content'  => '<!-- wp:gatherpress/event-date /-->', // Other blocks are hooked-in here.
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
					// Other blocks are hooked-in here.
					'content'  => '<!-- wp:post-featured-image /--><!-- wp:paragraph ' .
						'{"placeholder":"Add some infos about the venue and maybe a nice picture."} -->' .
						'<p></p><!-- /wp:paragraph -->',
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

	/**
	 * Filters the list of hooked block types for a given anchor block type and relative position.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/hooked_block_types/
	 *
	 * @param string[]                $hooked_block_types The list of hooked block types.
	 * @param string                  $relative_position  The relative position of the hooked blocks.
	 *                                                    Can be one of 'before', 'after',
	 *                                                    'first_child', or 'last_child'.
	 * @param string                  $anchor_block_type  The anchor block type.
	 * @param WP_Block_Template|array $context            The block template, template part, or pattern
	 *                                                    that the anchor block belongs to.
	 * @return string[]               The list of hooked block types.
	 */
	public function hook_blocks_into_patterns(
		array $hooked_block_types,
		string $relative_position,
		?string $anchor_block_type,
		$context
	): array {
		// Check that the place to hook into is a pattern.
		if ( ! is_array( $context ) || ! isset( $context['name'] ) ) {
			return $hooked_block_types;
		}

		// Hook blocks into the "gatherpress/event-template" pattern.
		if (
			'gatherpress/event-template' === $context['name'] &&
			'gatherpress/event-date' === $anchor_block_type &&
			'after' === $relative_position
		) {
			$hooked_block_types[] = 'gatherpress/add-to-calendar';

			// @todo As soon as the new venue block is in place,
			// load 'core/patterns' here
			// and fill it with {"slug":"gatherpress/venue-details"} in modify_hooked_blocks_in_patterns() later
			// instead of loading the (old) venue block.
			$hooked_block_types[] = 'gatherpress/venue';

			$hooked_block_types[] = 'gatherpress/rsvp';
			$hooked_block_types[] = 'core/paragraph';
			$hooked_block_types[] = 'gatherpress/rsvp-response';
		}

		// Hook blocks into the "gatherpress/venue-template" pattern.
		if (
			'gatherpress/venue-template' === $context['name'] &&
			'core/paragraph' === $anchor_block_type &&
			'after' === $relative_position
		) {
			$hooked_block_types[] = 'gatherpress/venue';
		}

		return $hooked_block_types;
	}

	/**
	 * Filters the parsed block array for a hooked 'core/paragraph' block.
	 *
	 * @see https://developer.wordpress.org/reference/hooks/hooked_block_hooked_block_type/
	 *
	 * @param array|null                      $parsed_hooked_block The parsed block array for the given
	 *                                                             hooked block type, or null to suppress the block.
	 * @param string                          $hooked_block_type   The hooked block type name.
	 * @param string                          $relative_position   The relative position of the hooked block.
	 * @param array                           $parsed_anchor_block The anchor block, in parsed block array format.
	 * @param WP_Block_Template|WP_Post|array $context             The block template, template part,
	 *                                                             `wp_navigation` post type, or pattern
	 *                                                             that the anchor block belongs to.
	 * @return array|null                     The parsed block array for the given hooked block type,
	 *                                        or null to suppress the block.
	 */
	public function modify_hooked_blocks_in_patterns(
		?array $parsed_hooked_block,
		string $hooked_block_type,
		string $relative_position,
		array $parsed_anchor_block,
		$context
	): ?array {
		// Has the hooked block been suppressed by a previous filter?
		if ( is_null( $parsed_hooked_block ) ) {
			return $parsed_hooked_block;
		}

		// Check that the place to hook into is a pattern.
		if ( ! is_array( $context ) || ! isset( $context['name'] ) ) {
			return $parsed_hooked_block;
		}

		// Conditionally hook the block into the "gatherpress/venue-facts" pattern.
		if (
			'gatherpress/event-template' !== $context['name'] ||
			'gatherpress/event-date' !== $parsed_anchor_block['blockName'] ||
			'after' !== $relative_position
		) {
			return $parsed_hooked_block;
		}

		// The opener text for new Events... a paragraph block.
		if ( 'core/paragraph' === $hooked_block_type ) {
			$parsed_hooked_block['attrs']['placeholder'] = __(
				// phpcs:ignore Generic.Files.LineLength.TooLong
				'Add a description of the event and let people know what to expect, including the agenda, what they need to bring, and how to find the group.',
				'gatherpress'
			);
		}

		return $parsed_hooked_block;
	}

	/**
	 * Recursively retrieves all block names from a given array of blocks.
	 *
	 * This method traverses a nested block structure and collects the block names,
	 * including those of any inner blocks, into a flat array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $blocks An array of block data, typically including `blockName` and `innerBlocks`.
	 *
	 * @return array An array of block names found within the provided block structure.
	 */
	public function get_block_names( array $blocks ): array {
		$block_names = array();

		if ( isset( $blocks['blockName'] ) ) {
			$block_names[] = $blocks['blockName'];
		}

		if ( ! empty( $blocks['innerBlocks'] ) ) {
			foreach ( $blocks['innerBlocks'] as $inner_block ) {
				$block_names = array_merge( $block_names, $this->get_block_names( $inner_block ) );
			}
		}

		return $block_names;
	}

	/**
	 * Get the post ID from block attributes or fallback to the current post ID.
	 *
	 * @since 1.0.0
	 *
	 * @param array $block The block data.
	 *
	 * @return int The resolved post ID.
	 */
	public function get_post_id( array $block ): int {
		$post_id = isset( $block['attrs']['postId'] ) ? intval( $block['attrs']['postId'] ) : 0;

		return $post_id > 0 ? $post_id : get_the_ID();
	}
}
