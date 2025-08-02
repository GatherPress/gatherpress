<?php
/**
 * The "Rsvp_Form" class handles the functionality of the RSVP Form block,
 * ensuring proper rendering and behavior for event registration.
 *
 * This class is responsible for transforming block content to convert the
 * container element to a form and processing RSVP submissions. It enables
 * visitors to register for events without requiring a site account.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Block;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Traits\Singleton;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "RSVP Form" block and its functionality,
 * including dynamic rendering and form processing.
 *
 * @since 1.0.0
 */
class Rsvp_Form {
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
	const BLOCK_NAME = 'gatherpress/rsvp-form';

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

		add_filter( $render_block_hook, array( $this, 'transform_block_content' ), 10, 2 );
	}

	/**
	 * Transform block content to create a functional RSVP form.
	 *
	 * Converts the block's div container to a form element and adds necessary
	 * hidden inputs for RSVP processing. Sets the form action to wp-comments-post.php
	 * and method to POST to enable form submission handling through WordPress's
	 * comment system. Generates a unique form ID for redirect handling.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The original block content.
	 * @param array  $block         The block instance array, used to determine the event.
	 *
	 * @return string The modified block content as a functional RSVP form.
	 */
	public function transform_block_content( string $block_content, array $block ): string {
		$block_instance = Block::get_instance();
		$post_id        = $block_instance->get_post_id( $block );
		$form_id        = $this->generate_form_id();
		$block_content  = trim( $block_content );
		$block_content  = preg_replace( '/^<div\b/', '<form', $block_content );
		$block_content  = preg_replace(
			'/(<\/div>)$/',
			'<input type="hidden" name="comment_post_ID" value="' . intval( $post_id ) . '">' .
			'<input type="hidden" name="' . esc_attr( Rsvp::COMMENT_TYPE ) . '" value="1">' .
			'<input type="hidden" name="gatherpress_rsvp_form_id" value="' . esc_attr( $form_id ) . '">' .
			'</form>',
			$block_content
		);
		$tag            = new WP_HTML_Tag_Processor( $block_content );

		$tag->next_tag();
		$tag->set_attribute( 'action', site_url( 'wp-comments-post.php' ) );
		$tag->set_attribute( 'method', 'post' );
		$tag->set_attribute( 'id', $form_id );

		return $tag->get_updated_html();
	}

	/**
	 * Generate a unique form ID for RSVP redirect handling.
	 *
	 * Creates a unique identifier that can be used to track form submissions
	 * and handle redirects back to the correct page location.
	 *
	 * @since 1.0.0
	 *
	 * @return string Unique form ID.
	 */
	private function generate_form_id(): string {
		return uniqid( 'gatherpress_rsvp_' );
	}
}
