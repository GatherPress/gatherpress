<?php
/**
 * Class responsible for managing the RSVP Template block for GatherPress,
 * including preparation of its output and handling hooks for customization and interactivity.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
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
 * @since 0.33.0
 */
final class Rsvp_Template {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Constant representing the Block Name.
	 *
	 * @since 0.33.0
	 * @var string
	 */
	const BLOCK_NAME = 'gatherpress/rsvp-template';

	/**
	 * Class constructor.
	 *
	 * This method initializes the object and sets up necessary hooks.
	 *
	 * @since 0.33.0
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * This method adds hooks for different purposes as needed.
	 *
	 * @since 0.33.0
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
	 * @since 0.33.0
	 *
	 * @param string $block_content The content of the current block being rendered.
	 *
	 * @return string The filtered block content.
	 */
	public function ensure_block_styles_loaded( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		$blocks_attr = $tag->next_tag() ? $tag->get_attribute( 'data-blocks' ) : null;

		// A valueless attribute reads back as true.
		if ( ! empty( $blocks_attr ) && is_string( $blocks_attr ) ) {
			$inner_blocks = (array) json_decode( $blocks_attr, true );
			$inner_blocks = Utility::get_block_names( $inner_blocks );

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
	 * @since 0.33.0
	 *
	 * @param string               $block_content The original block content.
	 * @param array<string, mixed> $block         The parsed block data.
	 * @param WP_Block             $instance      The block instance.
	 *
	 * @return string The dynamically generated block content.
	 */
	public function generate_rsvp_template_block( string $block_content, array $block, WP_Block $instance ): string {
		$post_id = (int) $instance->context['postId'];

		// Only process if the post type supports RSVP. An unpublished event
		// keeps its responses to viewers allowed to read it, so organizers see
		// the roster on a draft or private event rather than an empty block.
		if (
			! post_type_supports( (string) get_post_type( $post_id ), 'gatherpress-rsvp' ) ||
			! Event::is_viewable( $post_id )
		) {
			return $block_content;
		}

		$rsvp = new Rsvp( $post_id );

		if ( ! $rsvp->is_enabled() ) {
			return '';
		}

		$responses     = $rsvp->responses()['attending']['records'];
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
		$blocks = wp_json_encode(
			$block,
			JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		// Without a template there is nothing to hand the front end, so send the responses alone.
		if ( false === $blocks ) {
			return $block_content;
		}

		$rsvp_response_template = sprintf(
			'<div hidden data-wp-interactive="gatherpress"'
				. ' data-wp-watch="callbacks.renderBlocks"'
				. ' data-block-template="%1$s"'
				. ' data-block-signature="%2$s"></div>',
			esc_attr( $blocks ),
			esc_attr( self::sign_template( $blocks ) )
		);

		return $block_content . $rsvp_response_template;
	}

	/**
	 * Signature for a template this class emitted.
	 *
	 * The front end hands the template back to the REST endpoint verbatim, so
	 * the endpoint only renders what this class wrote in the first place. The
	 * key is the site's nonce salt: stable for the site, the same for every
	 * visitor, and not derived from anything a request can influence.
	 *
	 * @since 0.36.0
	 *
	 * @param string $template The JSON-encoded parsed block.
	 *
	 * @return string The signature.
	 */
	public static function sign_template( string $template ): string {
		return hash_hmac( 'sha256', $template, wp_salt( 'nonce' ) );
	}

	/**
	 * Whether a template and signature pair came from this class.
	 *
	 * @since 0.36.0
	 *
	 * @param string $template  The JSON-encoded parsed block.
	 * @param string $signature The signature that accompanied it.
	 *
	 * @return bool True when the signature matches the template.
	 */
	public static function verify_template( string $template, string $signature ): bool {
		return hash_equals( self::sign_template( $template ), $signature );
	}

	/**
	 * Generates the content for an RSVP block based on the parsed block data, response ID, and additional arguments.
	 *
	 * This method renders a block with the specified parsed block data and attaches
	 * the given response ID as a context. Additional arguments can be used to control
	 * rendering behavior. The block content is wrapped in a `div` with a `data-id` attribute
	 * for identification.
	 *
	 * @since 0.33.0
	 *
	 * @param array<string, mixed>                                  $parsed_block The parsed block data, typically from
	 *                                                                            a block's JSON structure.
	 * @param int                                                   $response_id  The ID of the response used to
	 *                                                                            populate the block's context.
	 * @param array{limit_enabled?: bool, limit?: int, index?: int} $args         Optional. Additional arguments for
	 *                                                                            rendering. Default empty array.
	 *
	 * @return string The rendered block content wrapped in a `div` with a `data-id` attribute.
	 */
	public function get_block_content( array $parsed_block, int $response_id, array $args = array() ): string {
		$render_block_hook = sprintf( 'render_block_%s', self::BLOCK_NAME );

		// Remove the filter to prevent an infinite loop caused by the filter being called within WP_Block.
		remove_filter( $render_block_hook, array( $this, 'generate_rsvp_template_block' ) );

		// Ensure proper user authentication for anonymity checks.
		Utility::ensure_user_authentication();

		// Render the block content with the provided parsed block and response ID.
		$block_content = (
			new WP_Block(
				$parsed_block,
				array( 'commentId' => $response_id )
			)
		)->render( array( 'dynamic' => true ) );

		// Re-add the filter after rendering to ensure it continues to apply to other blocks.
		add_filter( $render_block_hook, array( $this, 'generate_rsvp_template_block' ), 10, 3 );
		$class_name = '';

		// Check if the RSVP limit has been reached.
		if (
			! empty( $args ) &&
			! empty( $args['limit_enabled'] ) &&
			isset( $args['limit'], $args['index'] ) &&
			$args['index'] >= $args['limit']
		) {
			$class_name = 'gatherpress--is-hidden';
		}

		// Wrap the rendered block content in a container div with a unique data ID for the RSVP response.
		return sprintf( '<div class="%1$s" data-id="rsvp-%2$d">%3$s</div>', $class_name, $response_id, $block_content );
	}
}
