<?php
/**
 * The "General_Block" class handles general-purpose block functionality,
 * providing a catch-all for block-related logic that is not specific to any single block.
 *
 * This class ensures proper behavior and rendering adjustments for blocks
 * that do not belong to a specific block type but require additional processing.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Tag_Processor;
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
 * @since 0.33.0
 */
final class General_Block {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Class on the injected notice, mirrored by the front-end script.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const NEW_TAB_CLASS = 'gatherpress-new-tab-notice';

	/**
	 * Class that hides screen-reader text wherever GatherPress renders it.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SCREEN_READER_CLASS = 'gatherpress--screen-reader-text';

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
		add_filter( 'render_block', array( $this, 'process_login_block' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'process_registration_block' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'process_venue_detail_field' ), 10, 2 );
		add_filter( 'render_block_core/button', array( $this, 'convert_submit_button' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'announce_new_tab_links' ), 10, 2 );
	}

	/**
	 * Processes blocks with the `gatherpress--has-login-url` class.
	 *
	 * This method performs two functions:
	 * 1. Removes the block entirely if the user is already logged in
	 * 2. Dynamically replaces the placeholder login URL with the actual login URL
	 *    for users who are not logged in
	 *
	 * @since 0.33.0
	 *
	 * @param string               $block_content The HTML content of the block.
	 * @param array<string, mixed> $block         The parsed block data.
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
	 * @since 0.33.0
	 *
	 * @param string               $block_content The HTML content of the block.
	 * @param array<string, mixed> $block         The parsed block data.
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
	 * Hides a block (and its inner content) when the associated venue meta is
	 * empty. The class suffix maps directly to the venue meta key suffix:
	 * `gatherpress--has-venue-{field}` checks `gatherpress_{field}` for the
	 * known venue fields (address, phone, website). This lets wrapper blocks
	 * like Groups/Rows that contain an icon and a venue-detail block be hidden
	 * together when the underlying field has no value.
	 *
	 * @since 0.34.0
	 *
	 * @param string               $block_content The HTML content of the block.
	 * @param array<string, mixed> $block         The parsed block data.
	 *
	 * @return string The modified block content or an empty string if the block should be removed.
	 */
	public function process_venue_detail_field( string $block_content, array $block ): string {
		$class_name = $block['attrs']['className'] ?? '';

		// Bail when the block isn't tagged with a recognized venue field
		// suffix — anything outside the allow-list stays as-is.
		if ( ! preg_match( '/gatherpress--has-venue-([a-z-]+)/', $class_name, $matches )
			|| ! in_array( $matches[1], array( 'address', 'phone', 'website' ), true ) ) {
			return $block_content;
		}

		$field_name = $matches[1];

		// Get the venue post ID from the current context (block context first,
		// fall back to the current post). Verify it's actually a venue.
		$venue_post_id = $block['attrs']['postId'] ?? get_the_ID();

		if ( ! post_type_supports( (string) get_post_type( $venue_post_id ), Venue::SUPPORT ) ) {
			return $block_content;
		}

		$field_value = (string) get_post_meta( $venue_post_id, Utility::prefix_key( $field_name ), true );

		// Hide the entire block when the venue field is empty.
		return ( '' === $field_value ) ? '' : $block_content;
	}

	/**
	 * Converts button blocks with the `gatherpress-submit-button` class to submit buttons.
	 *
	 * This method performs two functions:
	 * 1. Converts anchor tags (`<a>`) to button elements and removes href/role attributes
	 * 2. Adds `type="submit"` attribute to both converted anchors and existing button elements
	 *
	 * @since 0.33.0
	 *
	 * @param string               $block_content The HTML content of the block.
	 * @param array<string, mixed> $block         The parsed block data.
	 *
	 * @return string The modified block content with submit button functionality.
	 */
	public function convert_submit_button( string $block_content, array $block ): string {
		// Check if the button has the gatherpress-submit-button class.
		if ( ! Utility::has_css_class( $block['attrs']['className'] ?? '', 'gatherpress-submit-button' ) ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );
		$content   = $block_content;

		while ( $processor->next_tag() ) {
			$tag_name = $processor->get_tag();

			if ( 'A' === $tag_name ) {
				// Handle anchor tags - convert to button.
				$processor->set_attribute( 'type', 'submit' );
				$processor->remove_attribute( 'href' );
				$processor->remove_attribute( 'role' );

				$content = $processor->get_updated_html();
				$content = (string) preg_replace( '/<a\b/', '<button', $content );
				$content = str_replace( '</a>', '</button>', $content );
				break;
			}

			if ( 'BUTTON' === $tag_name ) {
				// Handle button tags - just add type="submit".
				$processor->set_attribute( 'type', 'submit' );
				$content = $processor->get_updated_html();
				break;
			}
		}

		return $content;
	}

	/**
	 * Process guest count form field based on event settings.
	 *
	 * Hides the guest count field when max guest limit is 0.
	 *
	 * @since 0.33.0
	 *
	 * @param string               $block_content The block content.
	 * @param array<string, mixed> $block         The block data.
	 *
	 * @return string The processed block content.
	 */
	public function process_guests_field( string $block_content, array $block ): string {
		// Get the correct post ID using override logic.
		$block_instance = Setup::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Only process if the post type supports RSVP.
		if (
			! post_type_supports( (string) get_post_type( $post_id ), Rsvp::SUPPORT ) ||
			! Event::is_viewable( $post_id )
		) {
			return $block_content;
		}

		if ( ! ( new Rsvp( $post_id ) )->is_enabled() ) {
			return '';
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
	 * @since 0.33.0
	 *
	 * @param string               $block_content The block content.
	 * @param array<string, mixed> $block         The block data.
	 *
	 * @return string The processed block content.
	 */
	public function process_anonymous_field( string $block_content, array $block ): string {
		// Get the correct post ID using override logic.
		$block_instance = Setup::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Only process if the post type supports RSVP.
		if (
			! post_type_supports( (string) get_post_type( $post_id ), Rsvp::SUPPORT ) ||
			! Event::is_viewable( $post_id )
		) {
			return $block_content;
		}

		if ( ! ( new Rsvp( $post_id ) )->is_enabled() ) {
			return '';
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
		// Only GatherPress blocks, and only when there is a candidate to find.
		// Target keywords beginning with an underscore are case-insensitive,
		// so `_BLANK` opens a new tab just as `_blank` does.
		if (
			! str_starts_with( (string) ( $block['blockName'] ?? '' ), 'gatherpress/' )
			|| false === stripos( $block_content, '_blank' )
		) {
			return $block_content;
		}

		$processor = new Tag_Processor( $block_content );
		$offsets   = array();
		$open      = false;
		$announced = false;

		// The parser decides what is a tag and where each one ends, so case,
		// whitespace, comments and attribute text are its problem rather than
		// a string search's. Anchors cannot nest, so an opener starts a fresh
		// candidate and its closer settles it.
		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$tag = $processor->get_tag();

			if ( 'A' === $tag && ! $processor->is_tag_closer() ) {
				$open      = '_blank' === strtolower( (string) $processor->get_attribute( 'target' ) );
				$announced = false;

				continue;
			}

			if ( 'A' === $tag ) {
				if ( $open && ! $announced ) {
					$offsets[] = $processor->get_token_start();
				}

				$open = false;

				continue;
			}

			// Leave an anchor alone when it is already announced.
			if ( $open && ! $processor->is_tag_closer() && $processor->has_class( self::NEW_TAB_CLASS ) ) {
				$announced = true;
			}
		}

		return $this->insert_new_tab_notices( $block_content, array_filter( $offsets, 'is_int' ) );
	}

	/**
	 * Splice a notice in at each offset, which is where a closing tag starts.
	 *
	 * @since 0.36.0
	 *
	 * @param string $html    Rendered block markup.
	 * @param int[]  $offsets Byte offsets of the closers to announce before.
	 *
	 * @return string Markup with the notices in place.
	 */
	private function insert_new_tab_notices( string $html, array $offsets ): string {
		// The space sits in the markup rather than the string, as core does, so
		// the label and the notice cannot run together in the accessible name
		// and translators have no leading whitespace to preserve.
		$notice = sprintf(
			'<span class="screen-reader-text %1$s %2$s"> %3$s</span>',
			esc_attr( self::SCREEN_READER_CLASS ),
			esc_attr( self::NEW_TAB_CLASS ),
			esc_html__( '(opens in a new tab)', 'gatherpress' )
		);

		// Last first, so each splice leaves the offsets before it untouched.
		foreach ( array_reverse( $offsets ) as $offset ) {
			$html = substr( $html, 0, $offset ) . $notice . substr( $html, $offset );
		}

		return $html;
	}
}
