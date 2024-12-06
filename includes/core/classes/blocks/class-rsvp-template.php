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
	}

	public function render_block( $attributes, $content, $block ): string {
		// Fetch RSVP responses for the event.
		$event = new Event( get_the_ID() );
		if ( ! $event->rsvp ) {
			return $content;
		}

		$responses = $event->rsvp->responses()['attending']['responses'];
		$content = '';

		//					if ( empty( $responses ) ) {
		//						return '<p>No RSVPs found.</p>';
		//					}

		$responses[] = ['commentId' => -1];
		$rsvp_response_template = '';

		// Start capturing the output.
		foreach ( $responses as $response ) {
			$response_id = intval( $response['commentId'] );

			if ( $response_id === -1 ) {

				$parsed_block = wp_json_encode( $block->parsed_block );
				$rsvp_response_template = '<div class="gatherpress-rsvp-template__parsed-blocks-data" data-parsed-block="' . esc_attr( $parsed_block ) . '"></div>';
				continue;
			}

			$block_content = ( new \WP_Block( $block->parsed_block, array( 'commentId' => $response_id ) ) )->render( array( 'dynamic' => false ) );
			$content .= sprintf( '<div data-id="rsvp-%1$d">%2$s</div>', $response_id, $block_content );
		}

		$content .= '<div data-wp-interactive="gatherpress/rsvp-interactivity" data-wp-context=\'{ "isOpen": false }\' data-wp-watch="callbacks.logIsOpen"></div>';

		return $content . $rsvp_response_template;
	}
}
