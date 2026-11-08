<?php
/**
 * The "Modal" class handles the functionality of the Modal block,
 * ensuring proper behavior and rendering of individual modals.
 *
 * This class is responsible for transforming block content to dynamically inject
 * styles and attributes.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Setup;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "Modal" block and its functionality,
 * including dynamic rendering adjustments.
 *
 * @since 0.33.0
 */
final class Modal {

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
	const BLOCK_NAME = 'gatherpress/modal';

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

		add_filter( $render_block_hook, array( $this, 'render_as_dialog' ) );
		add_filter( $render_block_hook, array( $this, 'apply_modal_attributes' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'adjust_block_z_index' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'filter_login_modal' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'filter_rsvp_modal' ), 10, 2 );
	}

	/**
	 * Renders the modal as a native `<dialog>`.
	 *
	 * The block saves a `<div>`, so this runs at render time and existing
	 * content needs no migration. Opened with `showModal()`, the browser
	 * keeps focus inside the dialog, makes the page behind it inert, and
	 * closes it on Escape.
	 *
	 * @since TBD
	 *
	 * @param string $block_content The HTML content of the block.
	 *
	 * @return string The block content as a `<dialog>`, or unchanged when it is not a `<div>`.
	 */
	public function render_as_dialog( string $block_content ): string {
		$block_content = trim( $block_content );

		if ( ! preg_match( '/^<div\b/', $block_content ) || ! str_ends_with( $block_content, '</div>' ) ) {
			return $block_content;
		}

		return '<dialog' . substr( $block_content, 4, -6 ) . '</dialog>';
	}

	/**
	 * Modifies the modal block's attributes for accessibility.
	 *
	 * Dynamically updates the modal block's rendered content to include necessary
	 * attributes for improved accessibility and functionality. The `<dialog>`
	 * element carries the dialog role itself, and `showModal()` makes it modal.
	 *
	 * A modal with a custom name (`metadata.name`) uses it as its `aria-label`.
	 * A modal without one gets the generic "Modal" label and the
	 * `data-gatherpress-default-label` marker. When the modal opens, the view
	 * script points `aria-labelledby` at the first heading the user can see,
	 * which only the browser knows. An RSVP modal without a custom name, for
	 * example, shows and hides its headings after the page loads.
	 *
	 * The modals of the RSVP block templates are the exception. Their custom
	 * names ("RSVP Modal", "Login Modal") are names for the editor, and the
	 * #2366 heading lookup does not reach them. Such a modal is named by its
	 * title, the first paragraph or heading (a paragraph in the templates),
	 * and described by the next paragraph (the instructions), with the custom
	 * name kept as the `aria-label` fallback. This runs at render time, so it
	 * also covers the markup that existing events saved.
	 *
	 * @since 0.33.0
	 * @since 0.36.0 Marks modals that have no custom name.
	 * @since 0.36.1    Names RSVP block modals after their title paragraph.
	 * @since TBD       No longer sets `role`, `aria-modal`, `aria-hidden` or `tabindex`, which the `<dialog>` covers.
	 *
	 * @param string               $block_content The HTML content of the block.
	 * @param array<string, mixed> $block         The parsed block data.
	 *
	 * @return string The modified block content with updated attributes.
	 */
	public function apply_modal_attributes( string $block_content, array $block ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		if ( $tag->next_tag() ) {
			$modal_name = $block['attrs']['metadata']['name'] ?? '';
			$modal_name = is_string( $modal_name ) ? trim( $modal_name ) : '';

			if ( '' === $modal_name ) {
				$modal_name = __( 'Modal', 'gatherpress' );

				$tag->set_attribute( 'data-gatherpress-default-label', 'true' );
			}

			$tag->set_attribute( 'aria-label', esc_attr( $modal_name ) );

			if ( $this->is_rsvp_block_modal( $block ) ) {
				$this->label_by_paragraphs( $tag );
			}

			$block_content = $tag->get_updated_html();
		}

		return $block_content;
	}

	/**
	 * Checks whether a modal block is one of the RSVP block template modals.
	 *
	 * The RSVP modal has the `gatherpress-modal--type-rsvp` class, and the
	 * Login modal is matched by `is_login_modal()`.
	 *
	 * @since 0.36.1
	 *
	 * @param array<string, mixed> $block The parsed block data.
	 *
	 * @return bool True if the modal comes from the RSVP block templates.
	 */
	private function is_rsvp_block_modal( array $block ): bool {
		return Utility::has_css_class( $block['attrs']['className'] ?? null, 'gatherpress-modal--type-rsvp' )
			|| $this->is_login_modal( $block );
	}

	/**
	 * Checks whether a modal block is the Login modal.
	 *
	 * The RSVP block template and saved events use `gatherpress-modal--login`.
	 * `gatherpress-modal--type-login` is the name from the `--type-` rename,
	 * which did not reach the template, so both count.
	 *
	 * @since 0.36.1
	 *
	 * @param array<string, mixed> $block The parsed block data.
	 *
	 * @return bool True if the modal is the Login modal.
	 */
	private function is_login_modal( array $block ): bool {
		$class_name = $block['attrs']['className'] ?? null;

		return Utility::has_css_class( $class_name, 'gatherpress-modal--login' )
			|| Utility::has_css_class( $class_name, 'gatherpress-modal--type-login' );
	}

	/**
	 * Points the modal's `aria-labelledby` and `aria-describedby` at its title and instructions.
	 *
	 * The first paragraph or heading (the title) names the dialog, and the
	 * next paragraph describes it, so a title changed to a Heading block
	 * still names it. An element without an id gets a unique one; an
	 * existing id is reused. When an element is missing, the attribute that
	 * would point at it is not set.
	 *
	 * @since 0.36.1
	 *
	 * @param WP_HTML_Tag_Processor $tag The processor, stopped on the modal's opening tag.
	 *
	 * @return void
	 */
	private function label_by_paragraphs( WP_HTML_Tag_Processor $tag ): void {
		$ids     = array();
		$targets = array(
			'gatherpress-modal-title-'       => array( 'P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6' ),
			'gatherpress-modal-description-' => array( 'P' ),
		);

		$tag->set_bookmark( 'gatherpress-modal' );

		foreach ( $targets as $prefix => $tag_names ) {
			do {
				$found = $tag->next_tag();
			} while ( $found && ! in_array( $tag->get_tag(), $tag_names, true ) );

			if ( ! $found ) {
				break;
			}

			$id = $tag->get_attribute( 'id' );

			// A valueless attribute reads back as true.
			if ( ! is_string( $id ) || '' === trim( $id ) ) {
				$id = wp_unique_id( $prefix );

				$tag->set_attribute( 'id', $id );
			}

			$ids[] = $id;
		}

		$tag->seek( 'gatherpress-modal' );
		$tag->release_bookmark( 'gatherpress-modal' );

		if ( isset( $ids[0] ) ) {
			$tag->set_attribute( 'aria-labelledby', $ids[0] );
		}

		if ( isset( $ids[1] ) ) {
			$tag->set_attribute( 'aria-describedby', $ids[1] );
		}
	}

	/**
	 * Adjusts the block's `z-index` dynamically.
	 *
	 * This method processes the block's rendered HTML content and applies
	 * the `z-index` value from the block's attributes to the block's `style` attribute.
	 * If no `z-index` is specified, a default value of `1000` is used.
	 *
	 * This ensures proper stacking behavior for the block in the DOM.
	 *
	 * @since 0.33.0
	 *
	 * @param string               $block_content The HTML content of the block.
	 * @param array<string, mixed> $block         The parsed block data.
	 *
	 * @return string The updated block content with the applied `z-index` styling.
	 */
	public function adjust_block_z_index( string $block_content, array $block ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		if ( $tag->next_tag() ) {
			$z_index = $block['attrs']['zIndex'] ?? 1000;

			// A valueless attribute reads back as true.
			$existing_styles       = $tag->get_attribute( 'style' );
			$existing_styles       = is_string( $existing_styles ) ? $existing_styles : '';
			$existing_styles_array = explode( ';', rtrim( $existing_styles, ';' ) );
			$existing_styles_clean = implode( ';', array_filter( $existing_styles_array ) ) . ';';
			// Keep existing styles out of the format string, where a gradient stop's `%` reads as a specifier.
			$updated_styles = trim( $existing_styles_clean . sprintf( ' z-index: %d;', $z_index ) );

			$tag->set_attribute( 'style', $updated_styles );
		}

		return $tag->get_updated_html();
	}

	/**
	 * Filters the output of login modals for logged-in users.
	 *
	 * This method checks if the block is the Login modal (see
	 * `is_login_modal()`). If the user is logged in, it removes the block's
	 * output.
	 *
	 * @since 0.33.0
	 * @since 0.36.1    Also removes a Login modal with the template's `gatherpress-modal--login` class.
	 *
	 * @param string               $block_content The HTML content of the block.
	 * @param array<string, mixed> $block         The parsed block data.
	 *
	 * @return string The modified block content. Returns an empty string if the block should be removed.
	 */
	public function filter_login_modal( string $block_content, array $block ): string {
		if (
			$this->is_login_modal( $block ) &&
			is_user_logged_in()
		) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Filters the output of RSVP modals for non-logged-in users.
	 *
	 * This method checks if the block is a `gatherpress/modal` block with the
	 * `gatherpress-modal--type-rsvp` class. If the user is not logged in, it removes
	 * the block's output.
	 *
	 * @since 0.33.0
	 *
	 * @param string               $block_content The HTML content of the block.
	 * @param array<string, mixed> $block         The parsed block data.
	 *
	 * @return string The modified block content. Returns an empty string if the block should be removed.
	 */
	public function filter_rsvp_modal( string $block_content, array $block ): string {
		if (
			Utility::has_css_class( $block['attrs']['className'] ?? null, 'gatherpress-modal--type-rsvp' ) &&
			! Setup::get_instance()->get_user_identifier()
		) {
			return '';
		}

		return $block_content;
	}
}
