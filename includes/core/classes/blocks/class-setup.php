<?php
/**
 * Main class for managing custom blocks in GatherPress.
 *
 * This class handles the registration and management of custom blocks used in the GatherPress plugin.
 *
 * @package GatherPress\Core\Blocks
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_Block_Template;
use WP_Post;

/**
 * Class Setup.
 *
 * Core class for handling blocks in GatherPress.
 *
 * @since 1.0.0
 */
class Setup {

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
		Add_To_Calendar::get_instance();
		Dropdown::get_instance();
		Dropdown_Item::get_instance();
		Event_Date::get_instance();
		Event_Query::get_instance();
		General_Block::get_instance();
		Modal::get_instance();
		Modal_Manager::get_instance();
		Online_Event::get_instance();
		Rsvp::get_instance();
		Rsvp_Form::get_instance();
		Rsvp_Response::get_instance();
		Rsvp_Template::get_instance();
		Venue::get_instance();
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
		// Register GatherPress pattern category.
		register_block_pattern_category(
			'gatherpress',
			array(
				'label' => __( 'GatherPress', 'gatherpress' ),
			)
		);

		// Pattern category that the Event Query Loop variation chooser scopes to.
		// The category slug must match the variation namespace declared in
		// src/variations/core/query/index.js so core/query's placeholder modal
		// surfaces these patterns.
		register_block_pattern_category(
			'gatherpress-event-query',
			array(
				'label' => __( 'Event Query Loop', 'gatherpress' ),
			)
		);

		// Descriptive note shown to developers who enumerate registered
		// patterns (REST API, pattern registry). These patterns exist as
		// anchors for the Block Hooks API so other plugins can hook blocks
		// before/after the canonical event/venue block — they are not
		// user-facing design patterns, hence `'inserter' => false`.
		$hook_anchor_description = __(
			// phpcs:ignore Generic.Files.LineLength.TooLong -- Translator-facing sentence; keep it on one line for the .pot extractor.
			'Default content seeded into a new post. Anchors the Block Hooks API so other plugins can inject blocks around the core GatherPress block.',
			'gatherpress'
		);

		$block_patterns = array(
			array(
				Event::TEMPLATE_PATTERN,
				array(
					'title'       => __( 'Event Post Default Content', 'gatherpress' ),
					'description' => $hook_anchor_description,
					'content'     => '<!-- wp:gatherpress/event-date /-->',
					'inserter'    => false,
					'source'      => 'plugin',
				),
			),
			array(
				'gatherpress/venue-template',
				array(
					'title'       => __( 'Venue Post Default Content', 'gatherpress' ),
					'description' => $hook_anchor_description,
					// `patternPicked: true` skips the venue block's pattern-picker
					// UI and seeds the default layout directly — this is the
					// canonical content for new venue posts, not a fresh manual
					// insert. The block toolbar's "Choose pattern" action stays
					// available for authors who want a different layout.
					'content'     => '<!-- wp:gatherpress/venue {"patternPicked":true} /-->',
					'inserter'    => false,
					'source'      => 'plugin',
				),
			),
			array(
				'gatherpress/venue-details',
				array(
					'title'       => __( 'Venue Details Default Content', 'gatherpress' ),
					'description' => $hook_anchor_description,
					'content'     => '<!-- wp:post-title /-->',
					'inserter'    => false,
					'source'      => 'plugin',
				),
			),
		);

		foreach ( $block_patterns as $block_pattern ) {
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

		// Hook blocks into the event-template pattern.
		if (
			Event::TEMPLATE_PATTERN === $context['name'] &&
			'gatherpress/event-date' === $anchor_block_type &&
			'after' === $relative_position
		) {
			$hooked_block_types[] = 'gatherpress/add-to-calendar';
			$hooked_block_types[] = 'gatherpress/venue';
			$hooked_block_types[] = 'gatherpress/online-event';
			$hooked_block_types[] = 'gatherpress/rsvp';
			$hooked_block_types[] = 'core/paragraph';
			$hooked_block_types[] = 'gatherpress/rsvp-response';
		}

		// Hook blocks into the "gatherpress/venue-details" pattern.
		if (
			'gatherpress/venue-details' === $context['name'] &&
			'core/post-title' === $anchor_block_type &&
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
		// Bail when a previous filter suppressed the block, when the hook
		// target isn't a pattern, or when the pattern / anchor block /
		// position don't match the event-template anchor we inject the
		// opener paragraph after.
		if ( is_null( $parsed_hooked_block )
			|| ! is_array( $context )
			|| ! isset( $context['name'] )
			|| Event::TEMPLATE_PATTERN !== $context['name']
			|| 'gatherpress/event-date' !== $parsed_anchor_block['blockName']
			|| 'after' !== $relative_position
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
