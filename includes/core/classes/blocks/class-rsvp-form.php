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
		add_action( 'save_post', array( $this, 'save_form_schema' ) );
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
		$unique_form_id = $this->generate_form_id();
		$schema_form_id = $this->get_form_schema_id( $post_id, $block );
		$block_content  = trim( $block_content );
		$block_content  = preg_replace( '/^<div\b/', '<form', $block_content );
		$block_content  = preg_replace(
			'/(<\/div>)$/',
			'<input type="hidden" name="comment_post_ID" value="' . intval( $post_id ) . '">' .
			'<input type="hidden" name="' . esc_attr( Rsvp::COMMENT_TYPE ) . '" value="1">' .
			'<input type="hidden" name="gatherpress_rsvp_form_id" value="' . esc_attr( $unique_form_id ) . '">' .
			'<input type="hidden" name="gatherpress_form_schema_id" value="' . esc_attr( $schema_form_id ) . '">' .
			'</form>',
			$block_content
		);
		$tag            = new WP_HTML_Tag_Processor( $block_content );

		$tag->next_tag();
		$tag->set_attribute( 'action', site_url( 'wp-comments-post.php' ) );
		$tag->set_attribute( 'method', 'post' );
		$tag->set_attribute( 'id', $unique_form_id );

		// Add interactivity attributes for Ajax form handling.
		$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
		$tag->set_attribute( 'data-wp-init', 'callbacks.initRsvpForm' );
		$tag->set_attribute( 'data-wp-on--submit', 'actions.handleRsvpFormSubmit' );
		$tag->set_attribute( 'data-wp-context', wp_json_encode( array( 'postId' => $post_id ) ) );

		$updated_html = $tag->get_updated_html();

		// Force hide success message blocks on frontend.
		$updated_html = $this->hide_success_message_blocks( $updated_html );

		return $updated_html;
	}

	/**
	 * Hide success message blocks in the form HTML.
	 *
	 * Forces all elements with the gatherpress-rsvp-form-message class
	 * to be hidden on the frontend. They will be shown via JavaScript
	 * when the form is successfully submitted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html The form HTML content.
	 * @return string The modified HTML with hidden message blocks.
	 */
	private function hide_success_message_blocks( string $html ): string {
		$tag = new WP_HTML_Tag_Processor( $html );

		// Loop through all tags to find elements with the message class.
		while ( $tag->next_tag() ) {
			$class_attribute = $tag->get_attribute( 'class' );

			if ( $class_attribute && str_contains( $class_attribute, 'gatherpress-rsvp-form-message' ) ) {
				// Get existing styles and add display:none.
				$existing_styles       = $tag->get_attribute( 'style' ) ?? '';
				$existing_styles_array = explode( ';', rtrim( $existing_styles, ';' ) );
				$existing_styles_clean = implode( ';', array_filter( $existing_styles_array ) ) . ';';
				$updated_styles        = trim( $existing_styles_clean . ' display: none;' );

				$tag->set_attribute( 'style', $updated_styles );
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Save the form schema when a post is saved.
	 *
	 * Extracts the form field configuration from RSVP Form blocks and stores
	 * it as post meta. This schema is later used to validate form submissions
	 * and prevent unauthorized field injection.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public function save_form_schema( int $post_id ): void {
		// Check if this is an autosave or revision.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check if user has permission to edit the post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Get the post content.
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return;
		}

		// Parse blocks and extract schemas for each RSVP Form.
		$blocks  = parse_blocks( $post->post_content );
		$schemas = $this->extract_form_schemas_from_blocks( $blocks );

		if ( ! empty( $schemas ) ) {
			// Save schemas as post meta.
			update_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );
		} else {
			// Remove schema meta if no RSVP forms found.
			delete_post_meta( $post_id, 'gatherpress_rsvp_form_schemas' );
		}
	}

	/**
	 * Extract form schemas from parsed blocks.
	 *
	 * Searches through blocks to find RSVP Form blocks and creates
	 * a separate schema for each form based on its position.
	 *
	 * @since 1.0.0
	 *
	 * @param array $blocks Array of parsed blocks.
	 * @return array Array of form schemas keyed by form ID.
	 */
	private function extract_form_schemas_from_blocks( array $blocks ): array {
		$schemas = array();

		foreach ( $blocks as $index => $block ) {
			if ( self::BLOCK_NAME === $block['blockName'] ) {
				$form_id = 'form_' . $index;
				$fields  = $this->extract_form_fields_from_inner_blocks( $block['innerBlocks'] ?? array() );

				if ( ! empty( $fields ) ) {
					$schemas[ $form_id ] = array(
						'fields' => $fields,
						'hash'   => wp_hash( wp_json_encode( $fields ) ),
					);
				}
			}

			// Recursively check inner blocks for nested RSVP forms.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$nested_schemas = $this->extract_form_schemas_from_blocks( $block['innerBlocks'] );
				foreach ( $nested_schemas as $nested_form_id => $nested_schema ) {
					// Prefix nested forms with parent block index to maintain uniqueness.
					$prefixed_form_id             = $index . '_' . $nested_form_id;
					$schemas[ $prefixed_form_id ] = $nested_schema;
				}
			}
		}

		return $schemas;
	}

	/**
	 * Extract form fields from inner blocks of RSVP Form.
	 *
	 * Processes the inner blocks of an RSVP Form block to identify
	 * form field blocks and extract their configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param array $inner_blocks Array of inner blocks.
	 * @return array Array of form field configurations.
	 */
	private function extract_form_fields_from_inner_blocks( array $inner_blocks ): array {
		$fields = array();

		foreach ( $inner_blocks as $inner_block ) {
			// Check for GatherPress form field blocks.
			if ( 'gatherpress/form-field' === $inner_block['blockName'] ) {
				$attrs = $inner_block['attrs'] ?? array();

				if ( ! empty( $attrs['fieldName'] ) ) {
					$field_config = array(
						'name'        => sanitize_key( $attrs['fieldName'] ),
						'type'        => sanitize_text_field( $attrs['fieldType'] ?? 'text' ),
						'required'    => (bool) ( $attrs['required'] ?? false ),
						'label'       => sanitize_text_field( $attrs['label'] ?? '' ),
						'placeholder' => sanitize_text_field( $attrs['placeholder'] ?? '' ),
					);

					// Add type-specific validation rules.
					switch ( $field_config['type'] ) {
						case 'email':
							$field_config['validation'] = 'email';
							break;
						case 'select':
						case 'radio':
							$field_config['options'] = array_map( 'sanitize_text_field', $attrs['options'] ?? array() );
							break;
						case 'textarea':
							$field_config['max_length'] = intval( $attrs['maxLength'] ?? 1000 );
							break;
					}

					$fields[ $field_config['name'] ] = $field_config;
				}
			}

			// Recursively check inner blocks (e.g., group blocks containing form fields).
			if ( ! empty( $inner_block['innerBlocks'] ) ) {
				$nested_fields = $this->extract_form_fields_from_inner_blocks( $inner_block['innerBlocks'] );
				$fields        = array_merge( $fields, $nested_fields );
			}
		}

		return $fields;
	}

	/**
	 * Get the form schema ID for a specific RSVP Form block.
	 *
	 * Determines the position-based schema ID for this form block
	 * by parsing the current post content and finding its index.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id The post ID.
	 * @param array $block   The current block being rendered.
	 * @return string The form schema ID (e.g., 'form_0', 'form_2').
	 */
	private function get_form_schema_id( int $post_id, array $block ): string {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return 'form_0'; // Fallback.
		}

		$blocks     = parse_blocks( $post->post_content );
		$form_index = $this->find_form_index_in_blocks( $blocks, $block );

		return 'form_' . $form_index;
	}

	/**
	 * Find the index of the current form block in the blocks array.
	 *
	 * Recursively searches through blocks to find the current RSVP Form
	 * block and returns its position index.
	 *
	 * @since 1.0.0
	 *
	 * @param array $blocks       Array of parsed blocks.
	 * @param array $target_block The block we're looking for.
	 * @param int   $base_index   Base index for nested blocks.
	 * @return int The index of the form block.
	 */
	private function find_form_index_in_blocks( array $blocks, array $target_block, int $base_index = 0 ): int {
		foreach ( $blocks as $index => $block ) {
			if ( self::BLOCK_NAME === $block['blockName'] ) {
				// Compare block content or attributes to identify the same block.
				if ( $this->blocks_match( $block, $target_block ) ) {
					return $base_index + $index;
				}
			}

			// Check nested blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$nested_index = $this->find_form_index_in_blocks(
					$block['innerBlocks'],
					$target_block,
					$base_index + $index * 100 // Use larger offset for nested blocks.
				);
				if ( -1 !== $nested_index ) {
					return $nested_index;
				}
			}
		}

		return 0; // Fallback to first form.
	}

	/**
	 * Check if two blocks match based on their content.
	 *
	 * Compares blocks to determine if they are the same instance.
	 * Uses inner HTML content as the primary comparison method.
	 *
	 * @since 1.0.0
	 *
	 * @param array $block1 First block to compare.
	 * @param array $block2 Second block to compare.
	 * @return bool True if blocks match.
	 */
	private function blocks_match( array $block1, array $block2 ): bool {
		// Compare inner HTML content as a way to identify the same block.
		$content1 = $block1['innerHTML'] ?? '';
		$content2 = $block2['innerHTML'] ?? '';

		return $content1 === $content2;
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
