<?php
/**
 * Class manages the RSVP Response block for GatherPress, preparing its output and
 * handling associated hooks for customizing functionality.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Traits\Singleton;
use WP_Block;

/**
 * Class Rsvp_Response.
 *
 * This class manages the RSVP Response block for GatherPress, handling the
 * preparation of block output and adding hooks for customizations.
 *
 * It ensures smooth integration with WordPress's block editor and REST API.
 *
 * @since 1.0.0
 */
class Rsvp_Template {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Renders the RSVP Template block dynamically based on the event's RSVP responses.
	 *
	 * This method fetches RSVP responses for the current event and renders
	 * the block content dynamically for each response. If no valid responses
	 * are available, a default template is appended to enable front-end API
	 * interactions and maintain the block structure.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $attributes The block's attributes.
	 * @param string   $content    The initial content of the block.
	 * @param WP_Block $block      The block instance, including parsed block data.
	 *
	 * @return string The rendered block content, including responses and a default template.
	 */
	public function render_block( array $attributes, string $content, WP_Block $block ): string {
		$event = new Event( get_the_ID() );

		if ( ! $event->rsvp ) {
			return $content;
		}

		$responses = $event->rsvp->responses()['attending']['responses'];
		$content   = '';

		// Used for generating a parsed block for calls to API on the front end.
		$responses[]            = array( 'commentId' => -1 );
		$rsvp_response_template = '';

		foreach ( $responses as $response ) {
			$response_id = intval( $response['commentId'] );

			if ( -1 === $response_id ) {
				$blocks                 = wp_json_encode( $block->parsed_block );
				$rsvp_response_template = '<div data-wp-interactive="gatherpress/rsvp" data-wp-context=\'{ "postId": ' . intval( get_the_ID() ) . ' }\' data-wp-watch="callbacks.renderBlocks" data-blocks="' . esc_attr( $blocks ) . '"></div>';
				continue;
			}

			$content .= $this->get_block_content( $block->parsed_block, $response_id );
		}

		return $content . $rsvp_response_template;
	}

	/**
	 * Generates the content for an RSVP block based on the parsed block data and a response ID.
	 *
	 * This method renders a block with the specified parsed block data and attaches
	 * the given response ID as a context. It wraps the rendered block in a div with
	 * a data-id attribute for identification.
	 *
	 * @since 1.0.0
	 *
	 * @param array $parsed_block The parsed block data, typically from a block's JSON structure.
	 * @param int   $response_id  The ID of the response used to populate the block's context.
	 *
	 * @return string The rendered block content wrapped in a div with a data-id attribute.
	 */
	public function get_block_content( array $parsed_block, int $response_id ): string {
		$block_content = (
			new \WP_Block(
				$parsed_block,
				array( 'commentId' => $response_id )
			)
		)->render( array( 'dynamic' => false ) );

		return sprintf( '<div data-id="rsvp-%1$d">%2$s</div>', $response_id, $block_content );
	}
}
