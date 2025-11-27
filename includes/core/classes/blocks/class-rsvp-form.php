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
use GatherPress\Core\Blocks\Form_Field;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
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
	 * Built-in field names that should not be processed as custom fields.
	 *
	 * These fields are handled by WordPress core or other parts of the RSVP system.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	const BUILT_IN_FIELDS = array(
		'author',
		'email',
		'gatherpress_rsvp_guests',
		'gatherpress_rsvp_anonymous',
		'gatherpress_event_updates_opt_in',
	);

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
		add_filter( 'render_block_gatherpress/form-field', array( $this, 'conditionally_render_form_fields' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'apply_visibility_attribute' ), 10, 2 );
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
		$event          = new Event( $post_id );

		// Not an event, so return.
		if ( ! $event->event ) {
			return '';
		}

		$unique_form_id = $this->generate_form_id();
		$schema_form_id = $this->get_form_schema_id( $post_id, $block );

		$block_content = trim( $block_content );
		$block_content = preg_replace( '/^<div\b/', '<form', $block_content );
		$block_content = preg_replace(
			'/(<\/div>)$/',
			'<input type="hidden" name="comment_post_ID" value="' . intval( $post_id ) . '">' .
			'<input type="hidden" name="' . esc_attr( Rsvp::COMMENT_TYPE ) . '" value="1">' .
			'<input type="hidden" name="gatherpress_rsvp_form_id" value="' . esc_attr( $unique_form_id ) . '">' .
			'<input type="hidden" name="gatherpress_form_schema_id" value="' . esc_attr( $schema_form_id ) . '">' .
			'</form>',
			$block_content
		);
		$tag           = new WP_HTML_Tag_Processor( $block_content );

		$tag->next_tag();
		$tag->set_attribute( 'action', site_url( 'wp-comments-post.php' ) );
		$tag->set_attribute( 'method', 'post' );
		$tag->set_attribute( 'id', $unique_form_id );

		// Add interactivity attributes for Ajax form handling.
		$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
		$tag->set_attribute( 'data-wp-init', 'callbacks.initRsvpForm' );
		$tag->set_attribute( 'data-wp-on--submit', 'actions.handleRsvpFormSubmit' );
		$tag->set_attribute( 'data-wp-context', wp_json_encode( array( 'postId' => $post_id ) ) );

		// Add event state if the event has passed.
		if ( $event->has_event_past() ) {
			$tag->set_attribute( 'data-gatherpress-event-state', 'past' );
		}

		$updated_html = $tag->get_updated_html();

		// Check if this is a successful form submission redirect.
		$success_param = Utility::get_http_input( INPUT_GET, 'gatherpress_rsvp_success' );
		$is_success    = 'true' === $success_param;

		// Handle visibility of form elements based on success state and data attributes.
		$updated_html = $this->handle_form_visibility( $updated_html, $is_success );

		return $updated_html;
	}

	/**
	 * Apply visibility data attribute to blocks based on metadata.
	 *
	 * This filter runs on every block as it's being rendered. If a block has
	 * metadata.gatherpressRsvpFormVisibility set, adds the corresponding
	 * data attribute(s) to the rendered HTML and potentially hides the block
	 * based on event state.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The rendered block content.
	 * @param array  $block         The block instance array.
	 * @return string The modified block content or empty string if block should be hidden.
	 */
	public function apply_visibility_attribute( string $block_content, array $block ): string {
		// Check if this block has a visibility setting in metadata.
		$visibility = $block['attrs']['metadata']['gatherpressRsvpFormVisibility'] ?? null;

		if ( ! $visibility || empty( $block_content ) ) {
			return $block_content;
		}

		// Check if event has passed (only for object format with whenPast).
		$is_past = false;
		if ( is_array( $visibility ) && isset( $visibility['whenPast'] ) ) {
			// Find the parent RSVP form block to get the event ID.
			$post_id = $this->get_post_id_from_context( $block );
			if ( $post_id ) {
				$event   = new Event( $post_id );
				$is_past = $event->has_event_past();
			}
		}

		// Determine if block should be hidden based on whenPast setting and event state.
		$when_past = $visibility['whenPast'] ?? '';

		if ( ! empty( $when_past ) ) {
			// Helper to determine if block should be shown based on whenPast setting.
			$should_show = 'show' === $when_past ? $is_past : ! $is_past;

			// If block should not be shown, return empty string to hide it completely.
			if ( ! $should_show ) {
				return '';
			}
		}

		// Add the visibility data attribute(s) to the rendered block.
		$tag = new WP_HTML_Tag_Processor( $block_content );

		if ( $tag->next_tag() ) {
			// Store visibility as JSON.
			$tag->set_attribute( 'data-gatherpress-rsvp-form-visibility', wp_json_encode( $visibility ) );

			// Add event state for frontend JavaScript.
			if ( $is_past ) {
				$tag->set_attribute( 'data-gatherpress-event-state', 'past' );
			}

			return $tag->get_updated_html();
		}

		return $block_content;
	}

	/**
	 * Get the post ID from the block context.
	 *
	 * Attempts to find the event post ID by looking for a parent RSVP form block
	 * or from the current global post.
	 *
	 * @since 1.0.0
	 *
	 * @param array $block The block instance array.
	 * @return int|null The post ID or null if not found.
	 */
	private function get_post_id_from_context( array $block ): ?int {
		// Try to get from postId attribute (post ID override support).
		if ( isset( $block['attrs']['postId'] ) ) {
			return intval( $block['attrs']['postId'] );
		}

		// Fall back to current post.
		$post_id = get_the_ID();
		return $post_id ? $post_id : null;
	}

	/**
	 * Handle visibility of form elements based on success state and block attributes.
	 *
	 * Uses the gatherpressRsvpFormVisibility attribute to determine which blocks should
	 * be shown or hidden based on form success state. This provides flexible control
	 * over any inner blocks within the RSVP form.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html       The form HTML content.
	 * @param bool   $is_success Whether the form was successfully submitted.
	 * @return string The modified HTML with appropriate visibility.
	 */
	private function handle_form_visibility( string $html, bool $is_success ): string {
		$tag = new WP_HTML_Tag_Processor( $html );

		// Check if event has passed by looking for the data attribute on the form element.
		$is_past = false;
		if ( $tag->next_tag( array( 'tag_name' => 'form' ) ) ) {
			$event_state = $tag->get_attribute( 'data-gatherpress-event-state' );
			$is_past     = 'past' === $event_state;
		}

		// Reset to beginning and loop through all elements.
		$tag = new WP_HTML_Tag_Processor( $html );
		while ( $tag->next_tag() ) {
			$visibility_attr = $tag->get_attribute( 'data-gatherpress-rsvp-form-visibility' );

			if ( $visibility_attr ) {
				$this->apply_visibility_rule( $tag, $visibility_attr, $is_success, $is_past );
			}
		}

		return $tag->get_updated_html();
	}


	/**
	 * Conditionally render form field blocks based on event settings.
	 *
	 * This method prevents form fields from rendering when their associated
	 * event settings indicate they shouldn't be displayed. For example, guest count
	 * fields are removed when the max guest limit is 0, and anonymous RSVP fields
	 * are removed when anonymous RSVPs are disabled. For guest count fields with
	 * a limit > 0, the max value is enforced in the field attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $block_content The rendered block content.
	 * @param array  $block         The block instance array.
	 *
	 * @return string The modified block content, or an empty string if the field should not render.
	 */
	public function conditionally_render_form_fields( string $block_content, array $block ): string {
		// Get the field name from block attributes.
		$field_name = $block['attrs']['fieldName'] ?? '';

		// Skip if not a conditional field.
		if ( ! in_array( $field_name, array( 'gatherpress_rsvp_guests', 'gatherpress_rsvp_anonymous' ), true ) ) {
			return $block_content;
		}

		// Get the current post ID.
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $block_content;
		}

		$should_remove = false;

		// Check guest count field.
		if ( 'gatherpress_rsvp_guests' === $field_name ) {
			$max_guest_limit = (int) get_post_meta( $post_id, 'gatherpress_max_guest_limit', true );
			$should_remove   = 0 === $max_guest_limit;

			// If there's a max limit, add it to the input field.
			if ( ! $should_remove && 0 < $max_guest_limit ) {
				$tag = new WP_HTML_Tag_Processor( $block_content );

				// Find the input element and add the max attribute.
				if ( $tag->next_tag( array( 'tag_name' => 'input' ) ) ) {
					$tag->set_attribute( 'max', (string) $max_guest_limit );
					// Also set min to 0 for guest count.
					$tag->set_attribute( 'min', '0' );
					$block_content = $tag->get_updated_html();
				}
			}
		}

		// Check anonymous field.
		if ( 'gatherpress_rsvp_anonymous' === $field_name ) {
			$enable_anonymous_rsvp = get_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', true );
			$should_remove         = ! $enable_anonymous_rsvp;
		}

		// Return empty string if the field should not render.
		if ( $should_remove ) {
			return '';
		}

		return $block_content;
	}

	/**
	 * Apply visibility rule to a specific HTML element.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_HTML_Tag_Processor $tag             The HTML tag processor.
	 * @param string                $visibility_rule The visibility rule (JSON object or legacy string format).
	 * @param bool                  $is_success      Whether the form was successfully submitted.
	 * @param bool                  $is_past         Whether the event has passed.
	 * @return void
	 */
	private function apply_visibility_rule( WP_HTML_Tag_Processor $tag, ?string $visibility_rule, bool $is_success, bool $is_past ): void {
		if ( ! $visibility_rule ) {
			return;
		}

		$should_show = $this->determine_visibility( $visibility_rule, $is_success, $is_past );

		// Apply visibility if determined.
		if ( false === $should_show ) {
			// Hide the element with display: none.
			$existing_styles = $tag->get_attribute( 'style' ) ?? '';
			$updated_styles  = trim( $existing_styles . ' display: none;' );
			$tag->set_attribute( 'style', $updated_styles );
			$tag->set_attribute( 'aria-hidden', 'true' );
		} elseif ( true === $should_show ) {
			// Show the element (remove any inline display: none).
			$tag->set_attribute( 'aria-hidden', 'false' );

			// Add accessibility attributes for success messages.
			if ( $is_success ) {
				$tag->set_attribute( 'aria-live', 'polite' );
				$tag->set_attribute( 'role', 'status' );
			}
		}
	}

	/**
	 * Determine if a block should be visible based on visibility rules and current state.
	 *
	 * @since 1.0.0
	 *
	 * @param string $visibility_rule The visibility rule (JSON object or legacy string format).
	 * @param bool   $is_success      Whether the form was successfully submitted.
	 * @param bool   $is_past         Whether the event has passed.
	 * @return bool|null True to show, false to hide, null for no change (always visible).
	 */
	private function determine_visibility( string $visibility_rule, bool $is_success, bool $is_past ): ?bool {
		// Try to decode as JSON (object format).
		$visibility = json_decode( $visibility_rule, true );

		// Legacy string format.
		if ( ! is_array( $visibility ) ) {
			if ( 'showOnSuccess' === $visibility_rule ) {
				return $is_success;
			}
			if ( 'hideOnSuccess' === $visibility_rule ) {
				return ! $is_success;
			}
			return null;
		}

		// Object format with onSuccess and whenPast.
		$on_success = $visibility['onSuccess'] ?? '';
		$when_past  = $visibility['whenPast'] ?? '';

		// Helper to check if a setting matches the current state.
		$matches = function ( $setting, $state ) {
			if ( empty( $setting ) ) {
				return null; // No preference (always visible).
			}
			return 'show' === $setting ? $state : ! $state;
		};

		// Check whenPast first (takes precedence).
		if ( ! empty( $when_past ) ) {
			$when_past_result = $matches( $when_past, $is_past );
			if ( null !== $when_past_result ) {
				return $when_past_result;
			}
		}

		// Check onSuccess.
		if ( ! empty( $on_success ) ) {
			$on_success_result = $matches( $on_success, $is_success );
			if ( null !== $on_success_result ) {
				return $on_success_result;
			}
		}

		return null; // Default: no change (always visible).
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
			if ( Form_Field::BLOCK_NAME === $inner_block['blockName'] ) {
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

	/**
	 * Process custom fields for form submissions.
	 *
	 * Validates and saves custom fields from form submissions
	 * using the same schema validation as the Ajax form handler.
	 *
	 * @since 1.0.0
	 *
	 * @param int $comment_id The comment ID of the RSVP.
	 * @return void
	 */
	public function process_custom_fields_for_form( int $comment_id ): void {
		$comment = get_comment( $comment_id );
		if ( ! $comment || Rsvp::COMMENT_TYPE !== $comment->comment_type ) {
			return;
		}

		$post_id        = (int) $comment->comment_post_ID;
		$form_schema_id = Utility::get_http_input( INPUT_POST, 'gatherpress_form_schema_id' );

		if ( empty( $form_schema_id ) ) {
			return;
		}

		// Get the stored schemas for this post.
		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );
		if ( empty( $schemas ) || ! isset( $schemas[ $form_schema_id ] ) ) {
			return;
		}

		$schema = $schemas[ $form_schema_id ];
		if ( empty( $schema['fields'] ) ) {
			return;
		}

		// Process each custom field from the schema.
		foreach ( $schema['fields'] as $field_name => $field_config ) {
			// Skip built-in fields.
			if ( in_array( $field_name, self::BUILT_IN_FIELDS, true ) ) {
				continue;
			}

			$field_value = Utility::get_http_input( INPUT_POST, $field_name, null );
			if ( empty( $field_value ) ) {
				continue;
			}

			// Validate and sanitize the field value using the same logic as the Ajax handler.
			$validated_value = $this->sanitize_custom_field_value( $field_value, $field_config );
			if ( false !== $validated_value ) {
				update_comment_meta( $comment_id, 'gatherpress_custom_' . $field_name, $validated_value );
			}
		}
	}

	/**
	 * Sanitize a custom field value against its configuration.
	 *
	 * Shared sanitization logic for both traditional and Ajax form submissions.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value  The field value to sanitize.
	 * @param array $config The field configuration from the schema.
	 * @return mixed|false The sanitized value, or false if sanitization fails.
	 */
	public function sanitize_custom_field_value( $value, array $config ) {
		// Handle required field validation.
		if ( ! empty( $config['required'] ) && empty( $value ) ) {
			return false;
		}

		// Handle type-specific validation.
		switch ( $config['type'] ) {
			case 'email':
				$sanitized = sanitize_email( $value );
				return is_email( $sanitized ) ? $sanitized : false;

			case 'url':
				$sanitized = esc_url_raw( $value );
				return filter_var( $sanitized, FILTER_VALIDATE_URL ) ? $sanitized : false;

			case 'number':
				return is_numeric( $value ) ? floatval( $value ) : false;

			case 'select':
			case 'radio':
				$sanitized = sanitize_text_field( $value );
				return in_array( $sanitized, $config['options'] ?? array(), true ) ? $sanitized : false;

			case 'checkbox':
				return ! empty( $value ) ? 1 : 0;

			case 'textarea':
				$sanitized  = sanitize_textarea_field( $value );
				$max_length = $config['max_length'] ?? 1000;
				return strlen( $sanitized ) <= $max_length ? $sanitized : false;

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}
}
