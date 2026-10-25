<?php
/**
 * The "Modal Manager" class handles the functionality of the Modal Manager block,
 * enabling dynamic management of modals and their associated triggers.
 *
 * This class is responsible for transforming block content, ensuring proper behavior
 * of modal interactions, and preparing the block for output.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Core\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;
use WP_HTML_Tag_Processor;

/**
 * Class responsible for managing the "Modal Manager" block and its associated functionality,
 * including dynamic transformations, modal interactions, and enhancements for interactivity.
 *
 * @since 0.33.0
 */
final class Modal_Manager {

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
	const BLOCK_NAME = 'gatherpress/modal-manager';

	/**
	 * Query arg that opens a modal when the page loads.
	 *
	 * Its value is the key of the modal manager to open, from `get_modal_key()`.
	 *
	 * @since TBD
	 * @var string
	 */
	const OPEN_QUERY_ARG = 'gatherpress_modal';

	/**
	 * Modal managers rendered so far in this request, counted per post.
	 *
	 * @since TBD
	 * @var array<int, int>
	 */
	private array $render_counts = array();

	/**
	 * Attribute marking a modal the server rendered open.
	 *
	 * @since TBD
	 * @var string
	 */
	private const OPENED_ATTRIBUTE = 'data-gatherpress-opened-on-load';

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

		add_filter( $render_block_hook, array( $this, 'attach_modal_open_behavior' ) );
		add_filter( $render_block_hook, array( $this, 'attach_modal_close_behavior' ) );
		// Priority 11 so the triggers already carry their interactivity attributes.
		add_filter( $render_block_hook, array( $this, 'open_modal_without_javascript' ), 11, 2 );
	}

	/**
	 * Attaches modal open behavior to elements with the class 'gatherpress-modal--trigger-open'.
	 *
	 * This method scans the block content for elements containing the 'gatherpress-modal--trigger-open'
	 * class. If such elements are found, it applies the appropriate interactivity attributes
	 * for opening modals. If the target element is not a link (`<a>`) or button (`<button>`),
	 * it modifies the element to behave as a button by adding relevant ARIA and keyboard support.
	 *
	 * - Adds `data-wp-interactive` for interactivity.
	 * - Adds `data-wp-on--click` to handle click events.
	 * - Adds `data-wp-on--keydown` for keyboard accessibility. Links get it too:
	 *   `role="button"` tells assistive technology the element responds to Space,
	 *   which a native link does not.
	 * - Sets `role="button"`, plus `tabindex="0"` on elements that are not focusable on their own
	 *   (anything except a `<button>` or a link with an `href`).
	 *
	 * @since 0.33.0
	 * @since 0.36.0 Links acting as buttons also open the modal on Space. Links without an
	 *            `href` get `tabindex`.
	 *
	 * @param string $block_content The original block HTML content.
	 *
	 * @return string The modified block content with modal open behavior attributes applied.
	 */
	public function attach_modal_open_behavior( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		// Process only tags with the specific class 'gatherpress-modal--trigger-open'.
		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			if ( Utility::has_css_class( $class_attr, 'gatherpress-modal--trigger-open' ) ) {
				// Check if current element is an anchor or button.
				$is_actionable_element = in_array( $tag->get_tag(), array( 'A', 'BUTTON' ), true );

				if ( ! $is_actionable_element ) {
					// If not, check if the next element is an anchor or button.
					// @phpstan-ignore-next-line.
					$is_actionable_element = $tag->next_tag()
					&& in_array( $tag->get_tag(), array( 'A', 'BUTTON' ), true );
				}

				$target_found = $is_actionable_element;

				// Apply modal attributes if target was found.
				if ( $target_found ) {
					// Links with an href are already focusable, so they skip tabindex,
					// but they still need the keydown handler so Space opens the modal.
					// A link without an href (e.g. a Button block with no URL) is not
					// focusable, so it gets tabindex like any other element.
					$tag->set_attribute( 'data-wp-on--keydown', 'actions.openModalOnEnter' );
					$tag->set_attribute( 'role', 'button' );

					if ( ! $this->is_link_with_href( $tag ) ) {
						$tag->set_attribute( 'tabindex', '0' );
					}

					$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
					$tag->set_attribute( 'data-wp-on--click', 'actions.openModal' );
				}
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Attaches modal close behavior to elements with the class 'gatherpress-modal--trigger-close'.
	 *
	 * This method scans the block content for elements containing the 'gatherpress-modal--trigger-close'
	 * class. If such elements are found, it applies the appropriate interactivity attributes
	 * for closing modals. If the target element is not a link (`<a>`) or button (`<button>`),
	 * it modifies the element to behave as a button by adding relevant ARIA and keyboard support.
	 *
	 * - Adds `data-wp-interactive` for interactivity.
	 * - Adds `data-wp-on--click` to handle click events.
	 * - Adds `data-wp-on--keydown` for keyboard accessibility, including on links,
	 *   so Space closes the modal from a link acting as a button.
	 * - Sets `role="button"`, plus `tabindex="0"` on elements that are not focusable on their own
	 *   (anything except a `<button>` or a link with an `href`).
	 *
	 * The modal itself closes on Escape and on a click on its overlay, which
	 * is the dialog around the modal's content. Escape goes through the same
	 * close action as a close button rather than the browser's own close, so
	 * focus returns the same way, which matters once the RSVP block has moved
	 * on to another status's modal.
	 *
	 * @since 0.33.0
	 * @since 0.36.0 Links acting as buttons also close the modal on Space. Links without an
	 *            `href` get `tabindex`.
	 * @since TBD    The modal closes on Escape and on an overlay click.
	 *
	 * @param string $block_content The original block HTML content.
	 *
	 * @return string The modified block content with modal close behavior attributes applied.
	 */
	public function attach_modal_close_behavior( string $block_content ): string {
		$tag = new WP_HTML_Tag_Processor( $block_content );

		// Process the modal and its close triggers.
		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );

			if ( Utility::has_css_class( $class_attr, 'wp-block-gatherpress-modal' ) ) {
				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-on--cancel', 'actions.closeModal' );
				$tag->set_attribute( 'data-wp-on--click', 'actions.closeModalOnOverlay' );
			}

			if ( Utility::has_css_class( $class_attr, 'gatherpress-modal--trigger-close' ) ) {
				// @phpstan-ignore-next-line
				$is_link_with_href = $tag->next_tag() && $this->is_link_with_href( $tag );

				// Links with an href are already focusable, so they skip tabindex,
				// but they still need the keydown handler so Space closes the modal.
				// A link without an href is not focusable, so it gets tabindex.
				$tag->set_attribute( 'data-wp-on--keydown', 'actions.closeModalOnEnter' );
				$tag->set_attribute( 'role', 'button' );

				if ( ! $is_link_with_href ) {
					$tag->set_attribute( 'tabindex', '0' );
				}

				$tag->set_attribute( 'data-wp-interactive', 'gatherpress' );
				$tag->set_attribute( 'data-wp-on--click', 'actions.closeModal' );
				$tag->set_attribute( 'data-close-modal', true );
			}
		}

		return $tag->get_updated_html();
	}

	/**
	 * Lets the modal open and close when JavaScript is unavailable.
	 *
	 * Without JavaScript, a trigger reloads the page with `OPEN_QUERY_ARG`
	 * set to this modal manager's key, and the modal renders already open.
	 * A close trigger reloads the page without it. A link trigger with no
	 * `href` gets one; a button trigger submits a small GET form, since a
	 * button cannot navigate on its own. Both URLs end in this modal
	 * manager's id, so the page lands where the visitor clicked rather than
	 * at the top: the modal itself is fixed in place, so it cannot scroll
	 * the page. A modal rendered open also gets ways out (`add_ways_out()`).
	 * With JavaScript, the click handlers cancel the navigation.
	 *
	 * @since TBD
	 *
	 * @param string               $block_content The modal manager's HTML.
	 * @param array<string, mixed> $block         The parsed block data.
	 *
	 * @return string The HTML, with the triggers able to navigate.
	 */
	public function open_modal_without_javascript( string $block_content, array $block ): string {
		$tag         = new WP_HTML_Tag_Processor( $block_content );
		$has_wrapper = $tag->next_tag();

		if ( ! $has_wrapper || ! Utility::is_page_request() ) {
			return $block_content;
		}

		$key     = $this->get_modal_key( $block );
		$is_open = Utility::get_http_input( INPUT_GET, self::OPEN_QUERY_ARG ) === $key;
		$anchor  = $tag->get_attribute( 'id' );

		// A valueless attribute reads back as true.
		if ( ! is_string( $anchor ) || '' === trim( $anchor ) ) {
			$anchor = sprintf( 'gatherpress-modal-manager-%s', $key );

			$tag->set_attribute( 'id', $anchor );
		}

		$page_url = Utility::get_current_url( array( self::OPEN_QUERY_ARG ) );
		$form_id  = sprintf( 'gatherpress-modal-%s-%s', $is_open ? 'close' : 'open', $key );
		$url      = $is_open ? $page_url : add_query_arg( self::OPEN_QUERY_ARG, $key, $page_url );
		$url     .= '#' . $anchor;
		$result   = $this->apply_to_tags( $tag, $is_open, $form_id, $url );

		$block_content = $tag->get_updated_html();

		if ( $result['opened'] ) {
			$block_content = $this->add_ways_out( $block_content, $form_id, $url, ! $result['has_trigger'] );
		}

		// The overlay of an opened modal submits the close form too.
		if ( $result['opened'] || $result['has_form'] ) {
			$block_content = $this->insert_before_closing_tag(
				$block_content,
				$this->get_navigation_form( $form_id, $url )
			);
		}

		return $block_content;
	}

	/**
	 * Opens the modal manager's modal and wires its triggers, tag by tag.
	 *
	 * When the request asks for this modal manager, its first modal renders
	 * open and the close triggers load the URL that closes it. Otherwise the
	 * open triggers load the URL that opens it.
	 *
	 * @since TBD
	 *
	 * @param WP_HTML_Tag_Processor $tag     Processor positioned on the modal manager's wrapper.
	 * @param bool                  $is_open Whether the request asks for this modal manager's modal.
	 * @param string                $form_id The id of the navigation form.
	 * @param string                $url     The URL the triggers load.
	 *
	 * @return array{opened: bool, has_trigger: bool, has_form: bool} Whether the modal opened,
	 *                                                                 whether a trigger was found,
	 *                                                                 and whether one needs the form.
	 */
	private function apply_to_tags( WP_HTML_Tag_Processor $tag, bool $is_open, string $form_id, string $url ): array {
		$trigger = $is_open ? 'gatherpress-modal--trigger-close' : 'gatherpress-modal--trigger-open';
		$result  = array(
			'opened'      => false,
			'has_trigger' => false,
			'has_form'    => false,
		);

		while ( $tag->next_tag() ) {
			$class_attr = $tag->get_attribute( 'class' );
			$is_modal   = Utility::has_css_class( $class_attr, 'wp-block-gatherpress-modal' );

			if ( $is_open && ! $result['opened'] && $is_modal ) {
				// The view script opens the first modal in its manager, so the server does too.
				// Without a script to call showModal(), the dialog opens as non-modal; with
				// one, the script reopens it as a modal.
				$result['opened'] = true;

				$tag->set_attribute( 'open', true );
				$tag->set_attribute( self::OPENED_ATTRIBUTE, 'true' );
				$tag->set_attribute( 'data-wp-init', 'callbacks.takeOverOpenedModal' );
			} elseif ( Utility::has_css_class( $class_attr, $trigger ) && $this->seek_actionable_element( $tag ) ) {
				$result['has_trigger'] = true;
				$result['has_form']    = $this->navigate_on_click( $tag, $form_id, $url ) || $result['has_form'];
			}
		}

		return $result;
	}

	/**
	 * Gets a key for a modal manager that stays the same across page loads.
	 *
	 * The key is the post being rendered and the modal manager's position
	 * among that post's modal managers in this request. A page renders the
	 * same modal managers in the same order each time, so the key a trigger
	 * puts in the URL finds the same modal manager on the next load. Ids
	 * from `wp_unique_id()` would not: other code on the page shares its
	 * counter.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $block The parsed block data.
	 *
	 * @return string The key, such as `42-1`.
	 */
	private function get_modal_key( array $block ): string {
		$post_id = Setup::get_instance()->get_post_id( $block );

		$this->render_counts[ $post_id ] = ( $this->render_counts[ $post_id ] ?? 0 ) + 1;

		return sprintf( '%d-%d', $post_id, $this->render_counts[ $post_id ] );
	}

	/**
	 * Moves the processor to the link or button a trigger class applies to.
	 *
	 * The class sits on the link or button itself, or on the wrapper right
	 * before it, as the Button block renders it.
	 *
	 * @since TBD
	 *
	 * @param WP_HTML_Tag_Processor $tag Processor positioned on the tag with the trigger class.
	 *
	 * @return bool True when the processor is on a link or button.
	 */
	private function seek_actionable_element( WP_HTML_Tag_Processor $tag ): bool {
		$actionable = array( 'A', 'BUTTON' );

		if ( in_array( $tag->get_tag(), $actionable, true ) ) {
			return true;
		}

		return $tag->next_tag() && in_array( $tag->get_tag(), $actionable, true );
	}

	/**
	 * Makes a trigger load the given URL when clicked without JavaScript.
	 *
	 * A link without an `href` gets the URL; a link with one keeps it. A
	 * button submits the navigation form instead.
	 *
	 * @since TBD
	 *
	 * @param WP_HTML_Tag_Processor $tag     Processor positioned on the link or button.
	 * @param string                $form_id The id of the navigation form.
	 * @param string                $url     The URL to load.
	 *
	 * @return bool True when the trigger needs the navigation form.
	 */
	private function navigate_on_click( WP_HTML_Tag_Processor $tag, string $form_id, string $url ): bool {
		if ( 'BUTTON' === $tag->get_tag() ) {
			$tag->set_attribute( 'type', 'submit' );
			$tag->set_attribute( 'form', $form_id );

			return true;
		}

		if ( null === $tag->get_attribute( 'href' ) ) {
			$tag->set_attribute( 'href', $url );
		}

		return false;
	}

	/**
	 * Builds the hidden GET form a button trigger submits to load a URL.
	 *
	 * A GET form replaces its action's query with its own fields, so the
	 * URL's query args become hidden inputs.
	 *
	 * @since TBD
	 *
	 * @param string $form_id The form's id.
	 * @param string $url     The URL to load.
	 *
	 * @return string The form's HTML.
	 */
	private function get_navigation_form( string $form_id, string $url ): string {
		$query  = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$action = (string) preg_replace( '/\?[^#]*/', '', $url, 1 );
		$inputs = '';

		if ( '' !== $query ) {
			foreach ( explode( '&', $query ) as $pair ) {
				list( $name, $value ) = array_pad( explode( '=', $pair, 2 ), 2, '' );

				$inputs .= sprintf(
					'<input type="hidden" name="%s" value="%s">',
					esc_attr( urldecode( $name ) ),
					esc_attr( urldecode( $value ) )
				);
			}
		}

		return sprintf(
			'<form id="%s" method="get" action="%s" hidden>%s</form>',
			esc_attr( $form_id ),
			esc_url( $action ),
			$inputs
		);
	}

	/**
	 * Adds ways out of a modal the server rendered open.
	 *
	 * Without JavaScript, Escape and a click outside the modal do nothing,
	 * so the overlay behind the modal's content becomes a button that
	 * closes it. It stays out of the tab order and hidden from assistive
	 * technology, like the overlay. Its `data-close-modal` lets the RSVP
	 * block treat it like a close button. A modal with no close trigger
	 * also gets a Close link, so the keyboard has a way out too. With
	 * JavaScript, the view script hides the button in favor of its own
	 * overlay handling, and the link closes the modal without a reload.
	 *
	 * @since TBD
	 *
	 * @param string $block_content    The modal manager's HTML.
	 * @param string $form_id          The id of the close form.
	 * @param string $url              The URL that closes the modal.
	 * @param bool   $needs_close_link Whether the modal has no close trigger of its own.
	 *
	 * @return string The HTML, with the ways out inside the open modal.
	 */
	private function add_ways_out(
		string $block_content,
		string $form_id,
		string $url,
		bool $needs_close_link
	): string {
		$html = sprintf(
			'<button type="submit" form="%s" class="gatherpress-modal__backdrop" %s></button>',
			esc_attr( $form_id ),
			'tabindex="-1" aria-hidden="true" data-close-modal'
		);

		if ( $needs_close_link ) {
			$html .= sprintf(
				'<a class="gatherpress-modal__close" href="%s" %s>%s</a>',
				esc_url( $url ),
				'data-wp-interactive="gatherpress" data-wp-on--click="actions.closeModal"',
				esc_html( _x( 'Close', 'Button label for closing modal dialog', 'gatherpress' ) )
			);
		}

		// The marker sits in the modal's opening tag, which ends at the next `>`.
		$marker = (int) strpos( $block_content, self::OPENED_ATTRIBUTE );

		return substr_replace( $block_content, $html, (int) strpos( $block_content, '>', $marker ) + 1, 0 );
	}

	/**
	 * Inserts HTML just before the block's closing tag.
	 *
	 * @since TBD
	 *
	 * @param string $block_content The block's HTML.
	 * @param string $html          The HTML to insert.
	 *
	 * @return string The block's HTML with the insertion.
	 */
	private function insert_before_closing_tag( string $block_content, string $html ): string {
		$position = strrpos( $block_content, '</' );

		if ( false === $position ) {
			return $block_content . $html;
		}

		return substr_replace( $block_content, $html, $position, 0 );
	}

	/**
	 * Checks whether the processor's current tag is a link with an `href`.
	 *
	 * Only such a link is focusable without `tabindex`. An `<a>` without an
	 * `href` (a Button block with no URL renders one) is not in the tab order.
	 * A valueless `href` still counts, because browsers treat it as a link.
	 *
	 * @since 0.36.0
	 *
	 * @param WP_HTML_Tag_Processor $tag Processor positioned on the tag to check.
	 *
	 * @return bool True when the tag is an `<a>` with an `href` attribute.
	 */
	private function is_link_with_href( WP_HTML_Tag_Processor $tag ): bool {
		return 'A' === $tag->get_tag() && null !== $tag->get_attribute( 'href' );
	}
}
