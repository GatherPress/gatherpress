<?php
/**
 * Class responsible for managing the RSVP Template block for GatherPress,
 * including preparation of its output and handling hooks for customization and interactivity.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Block;
use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_Block;
use WP_Block_Type_Registry;
use WP_HTML_Tag_Processor;

/**
 * Class Rsvp_Template.
 *
 * This class manages the RSVP Template block for GatherPress, handling the
 * preparation of block output and adding hooks for customizations.
 *
 * It ensures seamless integration with WordPress's block editor and dynamic functionality.
 *
 * @since 1.0.0
 */
class Rsvp_Template {
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
	const BLOCK_NAME = 'gatherpress/rsvp-template';

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

		add_filter( $render_block_hook, array( $this, 'ensure_block_styles_loaded' ) );
		add_filter( $render_block_hook, array( $this, 'generate_rsvp_template_block' ), 10, 3 );
	}

	/**
	 * Ensures that the required block styles are loaded for the `gatherpress/rsvp-template` block.
	 *
	 * This function checks if the `gatherpress/rsvp-template` block contains inner blocks and retrieves
	 * their block names. It then enqueues the associated styles for each inner block dynamically.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The content of the current block being rendered.
	 *
	 * @return string The filtered block content.
	 */
	public function ensure_block_styles_loaded( string $block_content ): string {
		$block_instance = Block::get_instance();
		$tag            = new WP_HTML_Tag_Processor( $block_content );

		if ( $tag->next_tag() && ! empty( $tag->get_attribute( 'data-blocks' ) ) ) {
			$inner_blocks = (array) json_decode( $tag->get_attribute( 'data-blocks' ), true );
			$inner_blocks = $block_instance->get_block_names( $inner_blocks );

			foreach ( $inner_blocks as $inner_block ) {
				$block_registry = WP_Block_Type_Registry::get_instance();
				$block_type     = $block_registry->get_registered( $inner_block );

				if ( $block_type && ! empty( $block_type->style ) ) {
					wp_enqueue_style( $block_type->style );
				}
			}
		}

		return $block_content;
	}

	/**
	 * Dynamically generates the RSVP Template block content based on event responses.
	 *
	 * This method checks if the current block is the RSVP Template block and dynamically
	 * renders its content using the event's RSVP responses. If no valid responses are
	 * found, a default template is added to maintain the block structure and enable
	 * front-end API interactions.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $block_content The original block content.
	 * @param array    $block         The parsed block data.
	 * @param WP_Block $instance      The block instance.
	 *
	 * @return string The dynamically generated block content.
	 */
	public function generate_rsvp_template_block( string $block_content, array $block, WP_Block $instance ): string {
		$post_id = (int) $instance->context['postId'];
		$event   = new Event( $post_id );

		// Only process if we have a valid, published event post.
		if (
			Event::POST_TYPE !== get_post_type( $post_id ) ||
			'publish' !== get_post_status( $post_id )
		) {
			return $block_content;
		}

		$responses     = $event->rsvp->responses()['attending']['records'];
		$block_content = '';
		$args          = array(
			'limit_enabled' => isset( $instance->context['gatherpress/rsvpLimitEnabled'] )
				? (bool) $instance->context['gatherpress/rsvpLimitEnabled']
				: false,
			'limit'         => isset( $instance->context['gatherpress/rsvpLimit'] )
				? (int) $instance->context['gatherpress/rsvpLimit']
				: 0,
		);

		foreach ( $responses as $key => $record ) {
			$args['index']  = $key;
			$response_id    = intval( $record['commentId'] );
			$block_content .= $this->get_block_content( $block, $response_id, $args );
		}

		// Used for generating a parsed block for calls to API on the front end.
		$blocks                 = wp_json_encode( $block, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		$rsvp_response_template = sprintf(
			'<script type="application/json" data-wp-interactive="gatherpress" data-wp-watch="callbacks.renderBlocks">%s</script>',
			$blocks
		);

		return $block_content . $rsvp_response_template;
	}

	/**
	 * Generates the content for an RSVP block based on the parsed block data, response ID, and additional arguments.
	 *
	 * This method renders a block with the specified parsed block data and attaches
	 * the given response ID as a context. Additional arguments can be used to control
	 * rendering behavior. The block content is wrapped in a `div` with a `data-id` attribute
	 * for identification.
	 *
	 * @since 1.0.0
	 *
	 * @param array $parsed_block The parsed block data, typically from a block's JSON structure.
	 * @param int   $response_id  The ID of the response used to populate the block's context.
	 * @param array $args         Optional. Additional arguments for rendering. Default empty array.
	 *
	 * @return string The rendered block content wrapped in a `div` with a `data-id` attribute.
	 */
	public function get_block_content( array $parsed_block, int $response_id, array $args = array() ): string {
		$render_block_hook = sprintf( 'render_block_%s', self::BLOCK_NAME );

		// Remove the filter to prevent an infinite loop caused by the filter being called within WP_Block.
		remove_filter( $render_block_hook, array( $this, 'generate_rsvp_template_block' ) );

		// Ensure proper user authentication for anonymity checks.
		Utility::ensure_user_authentication();

		// Apply anonymization if the RSVP is marked as anonymous AND the current user
		// doesn't have edit_posts capability. Users with edit_posts can see all real names.
		if (
			intval( get_comment_meta( $response_id, 'gatherpress_rsvp_anonymous', true ) ) &&
			! current_user_can( 'edit_posts' )
		) {
			$this->anonymize_rsvp_blocks( $parsed_block['innerBlocks'], $response_id );
		}

		// Render the block content with the provided parsed block and response ID.
		$block_content = (
			new WP_Block(
				$parsed_block,
				array( 'commentId' => $response_id )
			)
		)->render( array( 'dynamic' => false ) );

		// Re-add the filter after rendering to ensure it continues to apply to other blocks.
		add_filter( $render_block_hook, array( $this, 'generate_rsvp_template_block' ), 10, 3 );
		$class_name = '';

		if ( ! empty( $args ) && ! empty( $args['limit_enabled'] ) ) {
			if ( isset( $args['limit'], $args['index'] ) ) {
				// Check if the RSVP limit has been reached.
				if ( $args['index'] >= $args['limit'] ) {
					$class_name = 'gatherpress--is-hidden';
				}
			}
		}

		// Wrap the rendered block content in a container div with a unique data ID for the RSVP response.
		return sprintf( '<div class="%1$s" data-id="rsvp-%2$d">%3$s</div>', $class_name, $response_id, $block_content );
	}

	/**
	 * Anonymizes specific RSVP blocks by modifying their attributes and content.
	 *
	 * This method processes blocks recursively, updating attributes and content
	 * to anonymize user information for RSVP responses. Specifically:
	 * - Disables linking for `core/avatar` blocks by setting `isLink` to 0.
	 * - Replaces the `core/comment-author-name` block's text with "Anonymous"
	 *   and converts it into a `core/paragraph` block.
	 *
	 * @since 1.0.0
	 *
	 * @param array $blocks      The array of blocks to process, passed by reference.
	 * @param int   $response_id The ID of the response, used for rendering context.
	 */
	public function anonymize_rsvp_blocks( array &$blocks, int $response_id ) {
		foreach ( $blocks as &$block ) {
			// Handle `core/avatar` block.
			if ( 'core/avatar' === $block['blockName'] ) {
				$block['attrs']['isLink'] = 0;
			}

			// Handle `core/comment-author-name` block.
			if ( 'core/comment-author-name' === $block['blockName'] ) {
				// Set `isLink` to 0 to disable linking for the block.
				$block['attrs']['isLink'] = 0;

				// Render the block with context for commentId.
				$block_html = ( new WP_Block( $block, array( 'commentId' => $response_id ) ) )->render( array( 'dynamic' => true ) );

				// Process HTML to update text.
				$tag = new WP_HTML_Tag_Processor( $block_html );
				$tag->next_tag();
				$tag->next_token();

				$tag->set_modifiable_text(
					esc_html_x( 'Anonymous', 'Label for users who wish to remain anonymous in RSVP responses.', 'gatherpress' )
				);
				$block_html = $tag->get_updated_html();

				// Convert to `core/paragraph` block.
				$block['blockName']    = 'core/paragraph';
				$block['innerContent'] = array( $block_html );
			}

			// Recursively process inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->anonymize_rsvp_blocks( $block['innerBlocks'], $response_id );
			}
		}
	}
}
