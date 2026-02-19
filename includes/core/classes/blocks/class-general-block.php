<?php
/**
 * The "General_Block" class handles general-purpose block functionality,
 * providing a catch-all for block-related logic that is not specific to any single block.
 *
 * This class ensures proper behavior and rendering adjustments for blocks
 * that do not belong to a specific block type but require additional processing.
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
use GatherPress\Core\Venue;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing general block-related functionality
 * and applying modifications that are not tied to specific block types.
 *
 * This class acts as a central handler for non-specific block logic,
 * such as filtering or injecting attributes for blocks with certain characteristics.
 *
 * @since 1.0.0
 */
class General_Block {
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
		add_filter( 'render_block', array( $this, 'process_login_block' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'process_registration_block' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'process_venue_detail_field' ), 10, 2 );
		add_filter( 'render_block_core/button', array( $this, 'convert_submit_button' ), 10, 2 );
	}

	/**
	 * Processes blocks with the `gatherpress--has-login-url` class.
	 *
	 * This method performs two functions:
	 * 1. Removes the block entirely if the user is already logged in
	 * 2. Dynamically replaces the placeholder login URL with the actual login URL
	 *    for users who are not logged in
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content or an empty string if the block should be removed.
	 */
	public function process_login_block( string $block_content, array $block ): string {
		if (
			Utility::has_css_class( $block['attrs']['className'] ?? '', 'gatherpress--has-login-url' ) &&
			is_user_logged_in()
		) {
			return '';
		}

		$tag = new WP_HTML_Tag_Processor( $block_content );

		while ( $tag->next_tag( array( 'tag_name' => 'a' ) ) ) {
			if ( '#gatherpress-login-url' === $tag->get_attribute( 'href' ) ) {
				$tag->set_attribute( 'href', Utility::get_login_url() );
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Processes blocks with the `gatherpress--has-registration-url` class.
	 *
	 * This method performs two functions:
	 * 1. Removes the block entirely if user registration is disabled in WordPress settings
	 * 2. For enabled registration, dynamically replaces the placeholder registration URL with the actual
	 *    registration URL
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content or an empty string if the block should be removed.
	 */
	public function process_registration_block( string $block_content, array $block ): string {
		if (
			Utility::has_css_class( $block['attrs']['className'] ?? '', 'gatherpress--has-registration-url' ) &&
			! get_option( 'users_can_register' )
		) {
			return '';
		}

		$tag = new WP_HTML_Tag_Processor( $block_content );

		while ( $tag->next_tag( array( 'tag_name' => 'a' ) ) ) {
			if ( '#gatherpress-registration-url' === $tag->get_attribute( 'href' ) ) {
				$tag->set_attribute( 'href', Utility::get_registration_url() );
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Processes blocks with venue conditional classes.
	 *
	 * This method hides blocks (and their contents) when the associated venue field
	 * is empty. It uses a naming convention to automatically map class names to JSON fields:
	 *
	 * - Class: `gatherpress--has-venue-phone` → JSON field: `phoneNumber`
	 * - Class: `gatherpress--has-venue-address` → JSON field: `fullAddress`
	 * - Class: `gatherpress--has-venue-website` → JSON field: `website`
	 *
	 * This allows wrapper blocks (like Groups/Rows) containing icons and venue-detail blocks
	 * to be hidden together when the field has no value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content or an empty string if the block should be removed.
	 */
	public function process_venue_detail_field( string $block_content, array $block ): string {
		$class_name = $block['attrs']['className'] ?? '';

		// Check if the block has a venue conditional class.
		if ( ! preg_match( '/gatherpress--has-venue-([a-z-]+)/', $class_name, $matches ) ) {
			return $block_content;
		}

		// Extract field name from class (e.g., "phone" from "gatherpress--has-venue-phone").
		$field_name = $matches[1];

		// Map class name to JSON field name.
		$field_mapping = array(
			'phone'   => 'phoneNumber',
			'address' => 'fullAddress',
			'website' => 'website',
		);

		if ( ! isset( $field_mapping[ $field_name ] ) ) {
			return $block_content;
		}

		$json_field = $field_mapping[ $field_name ];

		// Get the venue post ID from the current context.
		// First try to get it from block context, then fall back to current post.
		$venue_post_id = $block['attrs']['postId'] ?? get_the_ID();

		// Verify this is actually a venue post.
		if ( Venue::POST_TYPE !== get_post_type( $venue_post_id ) ) {
			return $block_content;
		}

		// Get the venue information JSON and parse it.
		$venue_info_json = get_post_meta( $venue_post_id, 'gatherpress_venue_information', true );
		$venue_info      = json_decode( $venue_info_json, true );

		if ( ! is_array( $venue_info ) ) {
			return '';
		}

		// Check if the specific field is empty.
		$field_value = $venue_info[ $json_field ] ?? '';

		// If the field is empty, hide the entire block.
		if ( empty( $field_value ) ) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Converts button blocks with the `gatherpress-submit-button` class to submit buttons.
	 *
	 * This method performs two functions:
	 * 1. Converts anchor tags (`<a>`) to button elements and removes href/role attributes
	 * 2. Adds `type="submit"` attribute to both converted anchors and existing button elements
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block         The parsed block data.
	 *
	 * @return string The modified block content with submit button functionality.
	 */
	public function convert_submit_button( string $block_content, array $block ): string {
		// Check if the button has the gatherpress-submit-button class.
		if ( ! Utility::has_css_class( $block['attrs']['className'] ?? '', 'gatherpress-submit-button' ) ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );

		while ( $processor->next_tag() ) {
			$tag_name = $processor->get_tag();

			if ( 'A' === $tag_name ) {
				// Handle anchor tags - convert to button.
				$processor->set_attribute( 'type', 'submit' );
				$processor->remove_attribute( 'href' );
				$processor->remove_attribute( 'role' );

				// Replace tag names.
				$content = $processor->get_updated_html();
				$content = preg_replace( '/<a\b/', '<button', $content );
				$content = str_replace( '</a>', '</button>', $content );

				return $content;
			} elseif ( 'BUTTON' === $tag_name ) {
				// Handle button tags - just add type="submit".
				$processor->set_attribute( 'type', 'submit' );

				return $processor->get_updated_html();
			}
		}

		return $block_content;
	}

	/**
	 * Process guest count form field based on event settings.
	 *
	 * Hides the guest count field when max guest limit is 0.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 *
	 * @return string The processed block content.
	 */
	public function process_guests_field( string $block_content, array $block ): string {
		// Get the correct post ID using override logic.
		$block_instance = Block::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Only process if we have a valid event post.
		// Only check publish status if not in preview mode.
		if (
			Event::POST_TYPE !== get_post_type( $post_id ) ||
			( ! is_preview() && 'publish' !== get_post_status( $post_id ) )
		) {
			return $block_content;
		}

		// Get max guest limit from event settings.
		$max_guest_limit = (int) get_post_meta( $post_id, 'gatherpress_max_guest_limit', true );

		// Mark the field for removal if guest limit is 0.
		if ( 0 === $max_guest_limit ) {
			$tag = new WP_HTML_Tag_Processor( $block_content );

			while ( $tag->next_tag() ) {
				$class_attr = $tag->get_attribute( 'class' );

				if ( Utility::has_css_class( $class_attr, 'gatherpress-rsvp-field-guests' ) ) {
					$existing_classes = $class_attr ? $class_attr . ' ' : '';
					$tag->set_attribute( 'class', $existing_classes . 'gatherpress--is-hidden' );
				}
			}

			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}

	/**
	 * Process anonymous form field based on event settings.
	 *
	 * Hides the anonymous field when anonymous RSVP is disabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 *
	 * @return string The processed block content.
	 */
	public function process_anonymous_field( string $block_content, array $block ): string {
		// Get the correct post ID using override logic.
		$block_instance = Block::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Only process if we have a valid event post.
		// Only check publish status if not in preview mode.
		if (
			Event::POST_TYPE !== get_post_type( $post_id ) ||
			( ! is_preview() && 'publish' !== get_post_status( $post_id ) )
		) {
			return $block_content;
		}

		// Get anonymous RSVP setting from event.
		$enable_anonymous_rsvp = get_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', true );

		// Mark the field for removal if anonymous RSVP is disabled.
		if ( empty( $enable_anonymous_rsvp ) ) {
			$tag = new WP_HTML_Tag_Processor( $block_content );

			while ( $tag->next_tag() ) {
				$class_attr = $tag->get_attribute( 'class' );

				if ( Utility::has_css_class( $class_attr, 'gatherpress-rsvp-field-anonymous' ) ) {
					$existing_classes = $class_attr ? $class_attr . ' ' : '';
					$tag->set_attribute( 'class', $existing_classes . 'gatherpress--is-hidden' );
				}
			}

			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}
}
