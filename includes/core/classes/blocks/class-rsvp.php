<?php
/**
 * The "Add to calendar" class manages the core-block-variation,
 * it mainly prepares the output of the block.
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
 * Class responsible for managing the "Add to calendar" block,
 * which is a block-variation of 'core/buttons'.
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
		add_filter( 'render_block', array( $this, 'transform_inner_block_content' ), 10, 2 );
	}

	/**
	 * @param string $block_content
	 * @param array  $block
	 *
	 * @return string
	 */
	public function transform_inner_block_content( string $block_content, array $block ): string {
		if ( 'gatherpress/rsvp-v2' === $block['blockName'] ) {
			$p = new WP_HTML_Tag_Processor( $block_content );
			$p->set_attribute(
				'data-gatherpress-no-status-label',
				$block['attrs']['noStatusLabel'] ?? __( 'RSVP', 'gatherpress' )
			);
			$p->set_attribute(
				'data-gatherpress-attending-label',
				$block['attrs']['attendingLabel'] ?? __( 'Edit RSVP', 'gatherpress' )
			);
			$p->set_attribute(
				'data-gatherpress-waiting-list-label',
				$block['attrs']['waitingListLabel'] ?? __( 'Edit RSVP', 'gatherpress' )
			);
			$p->set_attribute(
				'data-gatherpress-not-attending-label',
				$block['attrs']['notAttendingLabel'] ?? __( 'Edit RSVP', 'gatherpress' )
			);
		}
		if (
			$block['blockName'] === 'core/button' &&
			isset( $block['attrs']['className'] ) &&
			false !== strpos( $block['attrs']['className'], 'gatherpress-rsvp--js-open-modal' )
		) {
			$p = new WP_HTML_Tag_Processor( $block_content );

			// Locate the <button> tag and set the attributes
			if ( $p->next_tag() && $p->next_tag() ) {
				$p->set_attribute( 'data-wp-interactive', 'gatherpress/rsvp-interactivity' );
				$p->set_attribute( 'data-wp-on--click', 'actions.rsvpOpenModal' );
			}

			// Update the block content with new attributes
			$block_content = $p->get_updated_html();
		}

		if (
			$block['blockName'] === 'core/button' &&
			isset( $block['attrs']['className'] ) &&
			false !== strpos( $block['attrs']['className'], 'gatherpress-rsvp--js-close-modal' )
		) {
			$p = new WP_HTML_Tag_Processor( $block_content );

			// Locate the <button> tag and set the attributes
			if ( $p->next_tag() && $p->next_tag() ) {
				$p->set_attribute( 'data-wp-interactive', 'gatherpress/rsvp-interactivity' );
				$p->set_attribute( 'data-wp-on--click', 'actions.rsvpCloseModal' );
			}

			// Update the block content with new attributes
			$block_content = $p->get_updated_html();
		}

		if (
			$block['blockName'] === 'core/button' &&
			isset( $block['attrs']['className'] ) &&
			false !== strpos( $block['attrs']['className'], 'gatherpress-rsvp--js-status-attending' )
		) {
			$p = new WP_HTML_Tag_Processor( $block_content );

			// Locate the <button> tag and set the attributes
			if ( $p->next_tag() && $p->next_tag() ) {
				$p->set_attribute( 'data-wp-interactive', 'gatherpress/rsvp-interactivity' );
				$p->set_attribute( 'data-wp-on--click', 'actions.rsvpStatusAttending' );
			}

			// Update the block content with new attributes
			$block_content = $p->get_updated_html();
		}

		return $block_content;
	}
}
