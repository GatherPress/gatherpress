<?php
/**
 * Main class for managing custom blocks in GatherPress.
 *
 * This class handles the registration and management of custom blocks used in the GatherPress plugin.
 *
 * @package GatherPress\Core\Blocks
 * @since 0.27.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_Block_Template;
use WP_HTML_Tag_Processor;
use WP_Post;

/**
 * Class Setup.
 *
 * Core class for handling blocks in GatherPress.
 *
 * @since 0.34.0
 */
final class Setup {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Attribute used to carry the parser's decisions to the splice.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const NEW_TAB_ATTRIBUTE = 'data-gatherpress-new-tab';

	/**
	 * Class on the injected notice, also read by online-event-link's view.js.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const NEW_TAB_CLASS = 'gatherpress-new-tab-notice';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.34.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		// Called from here because Init:10 from inside Blocks/Event_Query would be too late for that filter to work.
		add_filter( 'register_block_type_args', array( $this, 'enable_context_for_core_query_block' ), 10, 2 );
		add_action( 'init', array( $this, 'register_block_classes' ) );
		add_action( 'init', array( $this, 'register_block_patterns' ) );
		// Priority 11 needed for block.json translations of title and description.
		add_action( 'init', array( $this, 'register_blocks' ), 11 );
		// Run on priority 9 to allow extenders to use the hooks with the default of 10.
		add_filter( 'hooked_block_types', array( $this, 'hook_blocks_into_patterns' ), 9, 4 );
		add_filter( 'hooked_block_core/paragraph', array( $this, 'modify_hooked_blocks_in_patterns' ), 9, 5 );
		add_filter( 'render_block', array( $this, 'announce_new_tab_links' ), 10, 2 );
	}

	/**
	 * Register custom blocks.
	 *
	 * This method scans a directory for custom block definitions and registers them.
	 *
	 * @since 0.34.0
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		$blocks_directory = sprintf( '%1$s/build/blocks/', GATHERPRESS_CORE_PATH );
		$blocks           = is_dir( $blocks_directory ) ? scandir( $blocks_directory ) : false;

		// Absent when run from source. Untestable: the test bootstrap mounts a built plugin.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
		// @codeCoverageIgnoreStart
		if ( false === $blocks ) {
			return;
		}
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
		// @codeCoverageIgnoreEnd

		foreach ( array_diff( $blocks, array( '..', '.' ) ) as $block ) {
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
	 * @since 0.34.0
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
	 * @param string[]                               $hooked_block_types The list of hooked block types.
	 * @param string                                 $relative_position  The relative position of the hooked
	 *                                                                   blocks. Can be one of 'before', 'after',
	 *                                                                   'first_child', or 'last_child'.
	 * @param string                                 $anchor_block_type  The anchor block type.
	 * @param WP_Block_Template|array<string, mixed> $context            The block template, template part, or
	 *                                                                   pattern that the anchor block belongs to.
	 * @return string[]                              The list of hooked block types.
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
	 * @param array<string, mixed>|null                      $parsed_hooked_block The parsed block array for the
	 *                                                                            given hooked block type, or null
	 *                                                                            to suppress the block.
	 * @param string                                         $hooked_block_type   The hooked block type name.
	 * @param string                                         $relative_position   The relative position of the
	 *                                                                            hooked block.
	 * @param array<string, mixed>                           $parsed_anchor_block The anchor block, in parsed block
	 *                                                                            array format.
	 * @param WP_Block_Template|WP_Post|array<string, mixed> $context             The block template, template
	 *                                                                            part, `wp_navigation` post type,
	 *                                                                            or pattern that the anchor block
	 *                                                                            belongs to.
	 * @return array<string, mixed>|null                     The parsed block array for the given hooked block type,
	 *                                                       or null to suppress the block.
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
	 * @since 0.34.0
	 *
	 * @param array<string, mixed> $block The block data.
	 *
	 * @return int The resolved post ID.
	 */
	public function get_post_id( array $block ): int {
		$post_id = isset( $block['attrs']['postId'] ) ? intval( $block['attrs']['postId'] ) : 0;

		if ( $post_id > 0 ) {
			return $post_id;
		}

		$current_post_id = get_the_ID();

		return false !== $current_post_id ? $current_post_id : 0;
	}

	/**
	 * Enables block context for the core Query block.
	 *
	 * This allows the block to receive context values like postType and postId,
	 * which are necessary for some of the custom Event Query block controls.
	 *
	 * @since 0.34.0
	 *
	 * @param array<string, mixed> $args       The arguments for registering the block type.
	 * @param string               $block_type The name of the block type being registered.
	 *
	 * @return array<string, mixed> Modified arguments for registering the block type.
	 */
	public function enable_context_for_core_query_block( array $args, string $block_type ): array {
		// Only modify the Query block.
		if ( 'core/query' === $block_type ) {
			$args['uses_context'] = array_merge(
				$args['uses_context'] ?? array(),
				array( 'postType', 'postId' )
			);
		}
		return $args;
	}

	/**
	 * Announce GatherPress links that open in a new tab to screen readers.
	 *
	 * Sighted users get a visual cue from the new tab itself; screen-reader
	 * users get nothing unless the link says so. One filter covers every
	 * GatherPress block rather than each template repeating the markup.
	 *
	 * @since 0.36.0
	 *
	 * @param string              $block_content Rendered block markup.
	 * @param array<string,mixed> $block         Parsed block.
	 *
	 * @return string Markup with a notice inside each new-tab link.
	 */
	public function announce_new_tab_links( string $block_content, array $block ): string {
		if ( ! str_starts_with( (string) ( $block['blockName'] ?? '' ), 'gatherpress/' ) ) {
			return $block_content;
		}

		if ( ! str_contains( $block_content, '_blank' ) ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );
		$found     = false;

		// The parser decides which anchors qualify, so attribute order and
		// quoting are its problem rather than a regex's.
		while ( $processor->next_tag( array( 'tag_name' => 'a' ) ) ) {
			if ( '_blank' === $processor->get_attribute( 'target' ) ) {
				$processor->set_attribute( self::NEW_TAB_ATTRIBUTE, '1' );
				$found = true;
			}
		}

		if ( ! $found ) {
			return $block_content;
		}

		return $this->insert_new_tab_notices( $processor->get_updated_html() );
	}

	/**
	 * Splice a notice in before each marked anchor's closing tag.
	 *
	 * WP_HTML_Tag_Processor sets attributes but cannot insert markup, so the
	 * marked anchors are closed by hand. Anchors cannot nest, so the next
	 * `</a>` closes the marked one.
	 *
	 * @since 0.36.0
	 *
	 * @param string $html Markup carrying the marker attribute.
	 *
	 * @return string Markup with the notices in place and the markers gone.
	 */
	private function insert_new_tab_notices( string $html ): string {
		$marker = sprintf( ' %s="1"', self::NEW_TAB_ATTRIBUTE );
		$notice = sprintf(
			'<span class="screen-reader-text %1$s">%2$s</span>',
			esc_attr( self::NEW_TAB_CLASS ),
			esc_html__( '(opens in a new tab)', 'gatherpress' )
		);

		$output = '';
		$offset = 0;

		$marked = strpos( $html, $marker );

		while ( false !== $marked ) {
			$close = strpos( $html, '</a>', $marked );

			if ( false === $close ) {
				break;
			}

			$output .= substr( $html, $offset, $close - $offset );

			// Leave an anchor alone when it is already announced.
			if ( ! str_contains( substr( $html, $marked, $close - $marked ), self::NEW_TAB_CLASS ) ) {
				$output .= $notice;
			}

			$offset = $close;
			$marked = strpos( $html, $marker, $offset );
		}

		return str_replace( $marker, '', $output . substr( $html, $offset ) );
	}
}
