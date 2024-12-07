<?php
/**
 * The "RSVP" class manages the RSVP block and its variations,
 * primarily transforming block content and preparing it for output.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "RSVP" block and its variations,
 * including dynamic transformations and enhancements for interactive functionality.
 *
 * @since 1.0.0
 */
class Rsvp {
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
		add_filter( 'render_block', array( $this, 'transform_block_content' ), 10, 2 );
	}

	/**
	 * Modifies the content of the RSVP block and its inner blocks before rendering.
	 *
	 * This method dynamically applies specific modifications to the block content
	 * based on its name and attributes, ensuring the appropriate attributes and
	 * interactivity settings are added where necessary.
	 *
	 * @param string $block_content The original HTML content of the block.
	 * @param array  $block         An associative array containing block data, including `blockName` and `attrs`.
	 *
	 * @return string The updated block content with the applied transformations.
	 */
	public function transform_block_content( string $block_content, array $block ): string {
		if ( 'gatherpress/rsvp-v2' === $block['blockName'] ) {
			$tag = new WP_HTML_Tag_Processor( $block_content );
			$tag->set_attribute(
				'data-gatherpress-no-status-label',
				$block['attrs']['noStatusLabel'] ?? __( 'RSVP', 'gatherpress' )
			);
			$tag->set_attribute(
				'data-gatherpress-attending-label',
				$block['attrs']['attendingLabel'] ?? __( 'Edit RSVP', 'gatherpress' )
			);
			$tag->set_attribute(
				'data-gatherpress-waiting-list-label',
				$block['attrs']['waitingListLabel'] ?? __( 'Edit RSVP', 'gatherpress' )
			);
			$tag->set_attribute(
				'data-gatherpress-not-attending-label',
				$block['attrs']['notAttendingLabel'] ?? __( 'Edit RSVP', 'gatherpress' )
			);
		}
		if (
			'core/button' === $block['blockName'] &&
			isset( $block['attrs']['className'] ) &&
			false !== strpos( $block['attrs']['className'], 'gatherpress-rsvp--js-open-modal' )
		) {
			$tag = new WP_HTML_Tag_Processor( $block_content );

			// Locate the <button> tag and set the attributes.
			$button_tag = $this->locate_button_tag( $tag );
			if ( $button_tag ) {
				$button_tag->set_attribute( 'data-wp-interactive', 'gatherpress/rsvp' );
				$button_tag->set_attribute( 'data-wp-on--click', 'actions.rsvpOpenModal' );
			}

			// Update the block content with new attributes.
			$block_content = $tag->get_updated_html();
		}

		if (
			'core/button' === $block['blockName'] &&
			isset( $block['attrs']['className'] ) &&
			false !== strpos( $block['attrs']['className'], 'gatherpress-rsvp--js-close-modal' )
		) {
			$tag = new WP_HTML_Tag_Processor( $block_content );

			// Locate the <button> tag and set the attributes.
			$button_tag = $this->locate_button_tag( $tag );
			if ( $button_tag ) {
				$button_tag->set_attribute( 'data-wp-interactive', 'gatherpress/rsvp' );
				$button_tag->set_attribute( 'data-wp-on--click', 'actions.rsvpCloseModal' );
			}

			// Update the block content with new attributes.
			$block_content = $tag->get_updated_html();
		}

		if (
			'core/button' === $block['blockName'] &&
			isset( $block['attrs']['className'] ) &&
			false !== strpos( $block['attrs']['className'], 'gatherpress-rsvp--js-status-attending' )
		) {
			$tag = new WP_HTML_Tag_Processor( $block_content );

			// Locate the <button> tag and set the attributes.
			$button_tag = $this->locate_button_tag( $tag );
			if ( $button_tag ) {
				$button_tag->set_attribute( 'data-wp-interactive', 'gatherpress/rsvp' );
				$button_tag->set_attribute( 'data-wp-on--click', 'actions.rsvpStatusAttending' );
			}

			// Update the block content with new attributes.
			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}

	/**
	 * Locates the button tag within a specific block structure.
	 *
	 * This method searches for a button tag following a div tag within
	 * the given HTML tag processor instance. If both tags are found,
	 * the processor is returned for further manipulation.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_HTML_Tag_Processor $tag The HTML tag processor instance for the block content.
	 *
	 * @return WP_HTML_Tag_Processor|null The tag processor instance if the button is located, or null otherwise.
	 */
	private function locate_button_tag( WP_HTML_Tag_Processor $tag ): ?WP_HTML_Tag_Processor {
		if ( $tag->next_tag( array( 'tag_name' => 'div' ) ) && $tag->next_tag( array( 'tag_name' => 'button' ) ) ) {
			return $tag;
		}

		return null;
	}
}
