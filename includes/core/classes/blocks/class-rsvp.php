<?php
/**
 * The "RSVP" class manages the RSVP block and its variations,
 * primarily transforming block content and preparing it for output.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Blocks\Form_Field;
use GatherPress\Core\Blocks\General_Block;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp as Core_Rsvp;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "RSVP" block and its variations,
 * including dynamic transformations and enhancements for interactive functionality.
 *
 * @since 0.33.0
 */
final class Rsvp {

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
	const BLOCK_NAME = 'gatherpress/rsvp';

	/**
	 * Query arg the RSVP endpoint adds to its redirect when an RSVP sent without JavaScript fails.
	 *
	 * Its value is the event's post ID, so only that event's RSVP blocks show the error.
	 *
	 * @since TBD
	 * @var string
	 */
	const ERROR_QUERY_ARG = 'gatherpress_rsvp_error';

	/**
	 * RSVP blocks rendered so far in this request, counted per event.
	 *
	 * @since TBD
	 * @var array<int, int>
	 */
	private array $render_counts = array();

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

		add_filter( $render_block_hook, array( $this, 'transform_block_content' ), 10, 2 );
		add_filter( $render_block_hook, array( $this, 'apply_rsvp_button_interactivity' ) );
		// Priority 11 ensures this runs after transform_block_content which modifies the block structure.
		add_filter( $render_block_hook, array( $this, 'apply_guest_count_watch' ), 11 );
		// Priority 9 ensures this runs before transform_block_content to properly register form field hooks.
		add_filter( $render_block_hook, array( $this, 'apply_guests_input_interactivity' ), 9 );
		// Priority 12 so the RSVP buttons and fields already carry their interactivity attributes.
		add_filter( $render_block_hook, array( $this, 'submit_rsvp_without_javascript' ), 12, 2 );

		// Add hooks for conditional form field processing.
		$general_block = General_Block::get_instance();
		add_filter( $render_block_hook, array( $general_block, 'process_guests_field' ), 10, 2 );
		add_filter( $render_block_hook, array( $general_block, 'process_anonymous_field' ), 10, 2 );
	}

	/**
	 * Dynamically transforms and renders the content of the RSVP block and its inner blocks.
	 *
	 * This method processes the RSVP block content, applying dynamic attributes and interactivity
	 * settings. It also handles the rendering of inner blocks based on the current RSVP status
	 * and ensures proper serialization of inner block states for persistence. The method
	 * dynamically adjusts the block content based on the event's status (e.g., past or active).
	 *
	 * Key functionalities:
	 * - Adds `data-wp-interactive` attributes for interactivity.
	 * - Dynamically renders and serializes inner blocks for each RSVP status.
	 * - Ensures proper markup updates based on the RSVP status and event state.
	 *
	 * @since 0.33.0
	 *
	 * @param string               $block_content The original HTML content of the block.
	 * @param array<string, mixed> $block         An associative array containing block data, including `blockName`
	 *                                            and `attrs`.
	 *
	 * @return string The updated block content with dynamically rendered inner blocks and attributes.
	 */
	public function transform_block_content( string $block_content, array $block ): string {
		$block_instance = Setup::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Validate that the post type supports RSVP and the viewer could open the
		// event: Event::is_viewable() does not ask about a password.
		if (
			! post_type_supports( (string) get_post_type( $post_id ), Core_Rsvp::SUPPORT ) ||
			! Event::is_viewable( $post_id ) ||
			post_password_required( $post_id )
		) {
			return '';
		}

		$rsvp = new Core_Rsvp( $post_id );

		if ( ! $rsvp->is_enabled() ) {
			return '';
		}

		$event        = new Event( $post_id );
		$inner_blocks = isset( $block['innerBlocks'] ) ? $block['innerBlocks'] : array();
		$tag          = new WP_HTML_Tag_Processor( $block_content );
		$attributes   = isset( $block['attrs'] ) ? $block['attrs'] : array();

		if ( $tag->next_tag() ) {
			/**
			 * Dynamically render inner blocks based on the saved RSVP status.
			 *
			 * This ensures that the block correctly renders the inner blocks
			 * for the currently selected RSVP status and updates the serialized
			 * inner blocks attribute. It addresses the issue of inner blocks
			 * not persisting properly when switching between statuses.
			 *
			 * The method generates dynamic markup for all statuses, wrapping each
			 * rendered inner block set in a container with a `data-rsvp-status`
			 * attribute.
			 */
			$saved_status = $attributes['selectedStatus'] ?? 'no_status';

			// Decode serialized inner blocks from attributes.
			$serialized_inner_blocks = $attributes['serializedInnerBlocks'] ?? '';
			$serialized_inner_blocks = json_decode( $serialized_inner_blocks, true );

			if ( ! is_array( $serialized_inner_blocks ) ) {
				$serialized_inner_blocks = array();
			}

			// Serialize the current inner blocks for the saved status.
			$serialized_inner_blocks[ $saved_status ] = serialize_blocks( $inner_blocks );

			$user_identifier = Rsvp_Setup::get_instance()->get_user_identifier( $post_id );
			$user_data       = $rsvp->get( $user_identifier ) ?? array();

			$filtered_data   = array_intersect_key(
				$user_data,
				array_flip( array( 'status', 'guests', 'anonymous' ) )
			);
			$filtered_status = ! empty( $filtered_data['status'] ) ? $filtered_data['status'] : 'no_status';

			if ( $event->has_event_past() ) {
				$inner_blocks_markup = do_blocks( $serialized_inner_blocks['past'] ?? '' );
			} else {
				unset( $serialized_inner_blocks['past'] );
				// Render inner blocks for all statuses.
				$inner_blocks_markup = '';

				foreach ( $serialized_inner_blocks as $status => $serialized_inner_block ) {
					$class                = $status !== $filtered_status ? 'gatherpress--is-hidden' : '';
					$inner_blocks_markup .= sprintf(
						'<div class="%s" data-rsvp-status="%s">%s</div>',
						esc_attr( $class ),
						esc_attr( $status ),
						do_blocks( $serialized_inner_block )
					);
				}

				// Set dynamic attributes for interactivity.
				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-context', wp_json_encode( array( 'postId' => $post_id ) ) );
				$tag->set_attribute( 'data-user-details', wp_json_encode( $filtered_data ) );
				$tag->set_attribute( 'data-wp-watch', 'callbacks.renderRsvpBlock' );
			}

			// Get the updated block content.
			$block_content = $tag->get_updated_html();

			// @todo: Replace this workaround with a method to properly update inner blocks
			// when https://github.com/WordPress/gutenberg/issues/60397 is resolved.
			preg_match( '/<div\b[^>]*>/i', $block_content, $matches );

			// Use the matched opening <div> tag or fallback to a generic <div>.
			$opening_div = $matches[0] ?? '<div>';

			// Close the block with a standard </div>.
			$closing_div = '</div>';

			// Construct the updated block content with the new inner blocks markup.
			$block_content = $opening_div . $inner_blocks_markup . $closing_div;
		}

		return $block_content;
	}

	/**
	 * Adds interactivity to RSVP buttons within the block content.
	 *
	 * This method scans the block content for elements with the class
	 * `gatherpress-rsvp--trigger-update`. If such an element is found, it checks for a
	 * nested `<a>` or `<button>` tag. The appropriate attributes for interactivity
	 * are added to either the nested tag or the containing element if no nested tag
	 * exists.
	 *
	 * @since 0.33.0
	 *
	 * @param string $block_content The original block content as a string.
	 *
	 * @return string The modified block content with interactivity attributes added.
	 */
	public function apply_rsvp_button_interactivity( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		// Process only tags with the specific class 'gatherpress-rsvp--trigger-update'.
		$rsvp_class = 'gatherpress-rsvp--trigger-update';

		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			// str_contains is used here to match BEM modifiers that extend the base class.
			// For example, 'gatherpress-rsvp--trigger-update__attending' includes the base class as a prefix.
			// A valueless attribute reads back as true.
			if ( is_string( $class_attr ) && str_contains( $class_attr, $rsvp_class ) ) {
				/**
				 * Class names split on whitespace.
				 *
				 * @var list<string> $classes A literal pattern cannot fail, so preg_split() never returns false.
				 */
				$classes = preg_split( '/\s+/', trim( $class_attr ) );

				$statuses       = array( 'attending', 'waiting-list', 'not-attending' );
				$matched_status = null;

				foreach ( $statuses as $status ) {
					$target_class = sprintf( '%s__%s', $rsvp_class, $status );
					if ( in_array( $target_class, $classes, true ) ) {
						$matched_status = $status;
						break;
					}
				}

				// Check if current element is an anchor or button.
				$is_actionable_element = in_array( $tag->get_tag(), array( 'A', 'BUTTON' ), true );

				if ( ! $is_actionable_element ) {
					// If not, check if the next element is an anchor or button.
					// @phpstan-ignore-next-line.
					$is_actionable_element = $tag->next_tag() &&
					in_array( $tag->get_tag(), array( 'A', 'BUTTON' ), true );
				}

				$target_found = $is_actionable_element;

				// Apply RSVP attributes if target was found.
				if ( $target_found ) {
					// Links only get role="button", others get full keyboard handling.
					if ( 'A' === $tag->get_tag() ) {
						$tag->set_attribute( 'role', 'button' ); // For links acting as buttons.
					} else {
						$tag->set_attribute( 'tabindex', '0' );
						$tag->set_attribute( 'role', 'button' );
					}

					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-on--click', 'actions.updateRsvp' );

					if ( ! empty( $matched_status ) ) {
						$tag->set_attribute( 'data-set-status', str_replace( '-', '_', $matched_status ) );
					}
				}
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Lets the RSVP block save an RSVP when JavaScript is unavailable.
	 *
	 * The block gets a hidden POST form for the RSVP REST endpoint, and the
	 * RSVP buttons and fields join it through their `form` attribute, so the
	 * markup around them stays as it is. Each button submits the status it
	 * would send with JavaScript. The endpoint redirects back to this block
	 * (see `Event\Rest_Api::redirect_rsvp_form_submission()`), which then
	 * renders the new status. With JavaScript, the click handlers cancel the
	 * submission.
	 *
	 * Logged-out visitors get the Login modal instead, and a past event
	 * takes no RSVPs, so neither gets the form.
	 *
	 * @since TBD
	 *
	 * @param string               $block_content The RSVP block's HTML.
	 * @param array<string, mixed> $block         The parsed block data.
	 *
	 * @return string The HTML, able to save an RSVP without JavaScript.
	 */
	public function submit_rsvp_without_javascript( string $block_content, array $block ): string {
		$post_id         = Setup::get_instance()->get_post_id( $block );
		$user_identifier = Rsvp_Setup::get_instance()->get_user_identifier( $post_id );
		$tag             = new WP_HTML_Tag_Processor( $block_content );
		$has_wrapper     = $tag->next_tag();

		if (
			! $has_wrapper
			|| ! Utility::is_page_request()
			|| ! $user_identifier
			|| ( new Event( $post_id ) )->has_event_past()
		) {
			return $block_content;
		}

		$this->render_counts[ $post_id ] = ( $this->render_counts[ $post_id ] ?? 0 ) + 1;

		$suffix    = sprintf( '%d-%d', $post_id, $this->render_counts[ $post_id ] );
		$form_id   = sprintf( 'gatherpress-rsvp-form-%s', $suffix );
		$user_data = ( new Core_Rsvp( $post_id ) )->get( $user_identifier ) ?? array();
		$status    = ! empty( $user_data['status'] ) ? (string) $user_data['status'] : 'no_status';
		$anchor    = $tag->get_attribute( 'id' );

		// A valueless attribute reads back as true.
		if ( ! is_string( $anchor ) || '' === trim( $anchor ) ) {
			$anchor = sprintf( 'gatherpress-rsvp-%s', $suffix );

			$tag->set_attribute( 'id', $anchor );
		}

		$in_current_status = false;

		// Every status container has its own copy of the fields, and a form submits
		// hidden fields too, so only the shown container's elements join the form.
		while ( $tag->next_tag() ) {
			$container_status = $tag->get_attribute( 'data-rsvp-status' );

			if ( is_string( $container_status ) ) {
				$in_current_status = $status === $container_status;
			} elseif ( $in_current_status ) {
				$this->join_rsvp_form( $tag, $form_id, $status, $user_data );
			}
		}

		$block_content = $tag->get_updated_html();
		$markup        = $this->get_rsvp_form( $form_id, $post_id, $status, $anchor );

		if ( Utility::get_http_input( INPUT_GET, self::ERROR_QUERY_ARG ) === (string) $post_id ) {
			$markup .= sprintf(
				'<p class="gatherpress-rsvp__notice" role="alert">%s</p>',
				esc_html__( 'Your RSVP could not be saved. Please try again.', 'gatherpress' )
			);
		}

		// The block's opening tag ends at its first `>`; the processor escapes any inside attribute values.
		return substr_replace( $block_content, $markup, (int) strpos( $block_content, '>' ) + 1, 0 );
	}

	/**
	 * Connects one element of the RSVP block to its no-JavaScript form.
	 *
	 * - An RSVP button submits the status its `data-set-status` names, or
	 *   the one it toggles to: Attending from no status or Not Attending,
	 *   otherwise Not Attending, as the view script does.
	 * - A close button submits the current status, so a guest count or
	 *   anonymity change is saved. With JavaScript those save as they are
	 *   made. Before the visitor has an RSVP, closing saves nothing.
	 * - The guest count and anonymity fields join the form, showing the
	 *   saved values.
	 *
	 * @since TBD
	 *
	 * @param WP_HTML_Tag_Processor $tag       Processor positioned on the element.
	 * @param string                $form_id   The id of the RSVP block's form.
	 * @param string                $status    The visitor's current RSVP status.
	 * @param array<string, mixed>  $user_data The visitor's saved RSVP.
	 *
	 * @return void
	 */
	private function join_rsvp_form(
		WP_HTML_Tag_Processor $tag,
		string $form_id,
		string $status,
		array $user_data
	): void {
		$is_button  = 'BUTTON' === $tag->get_tag();
		$field_name = $tag->get_attribute( 'name' );

		if ( $is_button && 'actions.updateRsvp' === $tag->get_attribute( 'data-wp-on--click' ) ) {
			$set_status = $tag->get_attribute( 'data-set-status' );
			$is_out     = in_array( $status, array( 'no_status', 'not_attending' ), true );
			$new_status = $is_out ? 'attending' : 'not_attending';

			if ( is_string( $set_status ) && '' !== $set_status ) {
				$new_status = $set_status;
			}

			$this->submit_rsvp_status( $tag, $form_id, $new_status );
		} elseif ( $is_button && 'no_status' !== $status && null !== $tag->get_attribute( 'data-close-modal' ) ) {
			$this->submit_rsvp_status( $tag, $form_id, $status );
		} elseif ( 'gatherpress_rsvp_guests' === $field_name ) {
			$tag->set_attribute( 'form', $form_id );
			$tag->set_attribute( 'value', (string) intval( $user_data['guests'] ?? 0 ) );
		} elseif ( 'gatherpress_rsvp_anonymous' === $field_name ) {
			$tag->set_attribute( 'form', $form_id );
			$tag->set_attribute( 'checked', ! empty( $user_data['anonymous'] ) );
		}
	}

	/**
	 * Makes a button submit the RSVP block's form with the given status.
	 *
	 * @since TBD
	 *
	 * @param WP_HTML_Tag_Processor $tag     Processor positioned on the button.
	 * @param string                $form_id The id of the RSVP block's form.
	 * @param string                $status  The RSVP status to submit.
	 *
	 * @return void
	 */
	private function submit_rsvp_status( WP_HTML_Tag_Processor $tag, string $form_id, string $status ): void {
		$tag->set_attribute( 'type', 'submit' );
		$tag->set_attribute( 'form', $form_id );
		$tag->set_attribute( 'name', 'status' );
		$tag->set_attribute( 'value', $status );
	}

	/**
	 * Builds the hidden form the RSVP block posts to the RSVP REST endpoint.
	 *
	 * The endpoint redirects back to the block's id, with the modal closed.
	 * A logged-in visitor sends the REST nonce; a magic-link session is
	 * authorized by its cookie, as it is with JavaScript.
	 *
	 * @since TBD
	 *
	 * @param string $form_id The form's id.
	 * @param int    $post_id The event's post ID.
	 * @param string $status  The visitor's current RSVP status.
	 * @param string $anchor  The RSVP block's id.
	 *
	 * @return string The form's HTML.
	 */
	private function get_rsvp_form( string $form_id, int $post_id, string $status, string $anchor ): string {
		$fields = array(
			'post_id'              => (string) $post_id,
			'gatherpress_redirect' => Utility::get_current_url(
				array( Modal_Manager::OPEN_QUERY_ARG, self::ERROR_QUERY_ARG )
			) . '#' . $anchor,
		);

		if ( is_user_logged_in() ) {
			$fields['_wpnonce'] = wp_create_nonce( 'wp_rest' );
		}

		$html = '';

		// Enter in the guest count submits the form's first button, which keeps the current status.
		if ( 'no_status' !== $status ) {
			$html .= sprintf(
				'<button type="submit" name="status" value="%s" tabindex="-1"></button>',
				esc_attr( $status )
			);
		}

		foreach ( $fields as $name => $value ) {
			$html .= sprintf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( $value ) );
		}

		return sprintf(
			'<form id="%s" method="post" action="%s" hidden>%s</form>',
			esc_attr( $form_id ),
			esc_url( rest_url( sprintf( '%s/event/rsvp', GATHERPRESS_REST_NAMESPACE ) ) ),
			$html
		);
	}

	/**
	 * Adds a data-wp-watch attribute to the Guest Count Display Block.
	 *
	 * This method processes the block content of the Guest Count Display Block and
	 * adds the `data-wp-watch` attribute to enable dynamic updates using the
	 * specified callback.
	 *
	 * @since 0.33.0
	 *
	 * @param string $block_content The original block content.
	 *
	 * @return string The modified block content with the data-wp-watch attribute applied.
	 */
	public function apply_guest_count_watch( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		$tag->next_tag();

		$user_details_attr = $tag->get_attribute( 'data-user-details' );

		$user_details = ( is_string( $user_details_attr ) && ! empty( $user_details_attr ) ) ?
			json_decode( $user_details_attr, true ) :
			array();

		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			if ( Utility::has_css_class( $class_attr, 'wp-block-gatherpress-rsvp-guest-count-display' ) ) {
				$tag->set_attribute( 'data-wp-watch', 'callbacks.updateGuestCountDisplay' );

				if ( empty( $user_details['guests'] ) ) {
					$tag->set_attribute( 'class', $class_attr . ' gatherpress--is-hidden' );
				}
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Apply interactivity attributes to guest count form field.
	 *
	 * Processes form-field blocks within RSVP blocks, applying the guest count form field callback.
	 *
	 * @since 0.33.0
	 *
	 * @param string $block_content The block content to modify.
	 *
	 * @return string The modified block content with form field callbacks applied.
	 */
	public function apply_guests_input_interactivity( string $block_content ): string {
		// Apply form field callback for any form-field blocks within this RSVP block.
		$form_field_hook = sprintf( 'render_block_%s', Form_Field::BLOCK_NAME );

		add_filter( $form_field_hook, array( $this, 'handle_rsvp_form_fields' ), 10, 2 );

		return $block_content;
	}

	/**
	 * Handle RSVP-related form field rendering.
	 *
	 * Processes form-field blocks with RSVP-specific field names to either
	 * remove the field (if not permitted) or apply interactivity attributes.
	 *
	 * @since 0.33.0
	 *
	 * @param string               $block_content The form field block content.
	 * @param array<string, mixed> $block         The block data including attributes.
	 *
	 * @return string The modified block content or empty string if field should be hidden.
	 */
	public function handle_rsvp_form_fields( string $block_content, array $block ): string {
		$attributes = $block['attrs'] ?? array();
		$field_name = $attributes['fieldName'] ?? '';

		// Get the correct post ID for remaining logic.
		$block_instance = Setup::get_instance();
		$post_id        = $block_instance->get_post_id( $block );

		// Handle guest count field interactivity.
		if ( 'gatherpress_rsvp_guests' === $field_name ) {
			$guest_limit = get_post_meta( $post_id, 'gatherpress_guest_limit', true );

			// Apply interactivity attributes and max limit for guest count.
			$tag = new WP_HTML_Tag_Processor( $block_content );

			while ( $tag->next_tag( array( 'tag_name' => 'input' ) ) ) {
				$name_attr = $tag->get_attribute( 'name' );

				if ( 'gatherpress_rsvp_guests' === $name_attr ) {
					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-watch', 'callbacks.setGuestCount' );
					$tag->set_attribute( 'data-wp-on--change', 'actions.updateGuestCount' );
					$tag->set_attribute( 'max', (string) $guest_limit );
				}
			}

			return $tag->get_updated_html();
		}

		// Handle anonymous checkbox field interactivity.
		if ( 'gatherpress_rsvp_anonymous' === $field_name ) {
			// Apply interactivity attributes for anonymous checkbox.
			$tag = new WP_HTML_Tag_Processor( $block_content );

			while ( $tag->next_tag( array( 'tag_name' => 'input' ) ) ) {
				$name_attr = $tag->get_attribute( 'name' );

				if ( 'gatherpress_rsvp_anonymous' === $name_attr ) {
					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-on--change', 'actions.updateAnonymous' );
					$tag->set_attribute( 'data-wp-watch', 'callbacks.monitorAnonymousStatus' );
				}
			}

			return $tag->get_updated_html();
		}

		// Return unmodified content for non-RSVP form fields.
		return $block_content;
	}
}
