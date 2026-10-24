<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Modal_Manager.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Modal_Manager;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_HTML_Tag_Processor;

/**
 * Class Test_Modal_Manager.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Modal_Manager
 */
class Test_Modal_Manager extends Base {

	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Modal_Manager block.
	 *
	 * @since 0.33.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Modal_Manager::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Modal_Manager::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'attach_modal_open_behavior' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'attach_modal_close_behavior' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 11,
				'callback' => array( $instance, 'open_modal_without_javascript' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test modal open behavior is applied to non-link elements with open modal class.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_button(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="gatherpress-modal--trigger-open"><button>Button</button></div>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.openModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'data-wp-on--keydown="actions.openModalOnEnter"',
			$output_html,
			'Output should contain keydown handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
		$this->assertStringContainsString(
			'tabindex="0"',
			$output_html,
			'Output should contain tabindex'
		);
	}

	/**
	 * Test modal open behavior is applied to link elements with open modal class.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_link(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="gatherpress-modal--trigger-open"><a href="#">Open Modal</a></div>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.openModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
		$this->assertStringContainsString(
			'data-wp-on--keydown="actions.openModalOnEnter"',
			$output_html,
			'Links acting as buttons need the keydown handler so Space works'
		);
		$this->assertStringNotContainsString(
			'tabindex',
			$output_html,
			'Output should not contain tabindex for links'
		);
	}

	/**
	 * Test modal open behavior with button element having modal class directly.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_direct_button_class(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<button class="gatherpress-modal--trigger-open">Open Modal</button>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.openModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'data-wp-on--keydown="actions.openModalOnEnter"',
			$output_html,
			'Output should contain keydown handler for buttons'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
	}

	/**
	 * Test modal open behavior with anchor element having modal class directly.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_direct_anchor_class(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<a href="#" class="gatherpress-modal--trigger-open">Open Modal</a>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.openModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role for links'
		);
		$this->assertStringContainsString(
			'data-wp-on--keydown="actions.openModalOnEnter"',
			$output_html,
			'Links acting as buttons need the keydown handler so Space works'
		);
	}

	/**
	 * Test modal open behavior is not applied to elements without open modal class.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_not_applied_without_class(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="other-class">Regular Content</div>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Content should remain unchanged when modal class is not present'
		);
	}

	/**
	 * Test modal open behavior with multiple elements in content.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_multiple_elements(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '
			<div>
				<div class="gatherpress-modal--trigger-open"><button>Button</button></div>
				<p>Regular content</p>
				<div class="gatherpress-modal--trigger-open"><button>Button</button></div>
			</div>
		';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertEquals(
			2,
			substr_count( $output_html, 'data-wp-interactive="gatherpress"' ),
			'Should apply interactive attribute to both modal trigger elements'
		);
		$this->assertEquals(
			2,
			substr_count( $output_html, 'role="button"' ),
			'Should apply button role to both modal trigger elements'
		);
		$this->assertStringContainsString(
			'Regular content',
			$output_html,
			'Should preserve non-modal content'
		);
	}

	/**
	 * Test modal open behavior with mixed class names.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_open_behavior
	 *
	 * @return void
	 */
	public function test_open_behavior_with_mixed_classes(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="custom-class gatherpress-modal--trigger-open another-class">'
			. '<button>Button</button></div>';
		$output_html = $instance->attach_modal_open_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Should apply interactive attribute when modal class is mixed with others'
		);
		$this->assertStringContainsString(
			'custom-class',
			$output_html,
			'Should preserve existing classes'
		);
		$this->assertStringContainsString(
			'another-class',
			$output_html,
			'Should preserve all existing classes'
		);
	}

	/**
	 * Test modal close behavior is applied to div with nested button element.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_with_button(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="gatherpress-modal--trigger-close" data-close-modal="true">'
			. '<button type="button">Close Modal</button></div>';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.closeModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'data-wp-on--keydown="actions.closeModalOnEnter"',
			$output_html,
			'Output should contain keydown handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
		$this->assertStringContainsString(
			'tabindex="0"',
			$output_html,
			'Output should contain tabindex'
		);
	}

	/**
	 * Test modal close behavior is applied to div with nested anchor element.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_with_link(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="gatherpress-modal--trigger-close" data-close-modal="true">'
			. '<a href="#">Close Modal</a></div>';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Output should contain interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.closeModal"',
			$output_html,
			'Output should contain click handler'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output_html,
			'Output should contain button role'
		);
		$this->assertStringContainsString(
			'data-wp-on--keydown="actions.closeModalOnEnter"',
			$output_html,
			'Links acting as buttons need the keydown handler so Space works'
		);
		$this->assertStringNotContainsString(
			'tabindex',
			$output_html,
			'Output should not contain tabindex for links'
		);
	}

	/**
	 * Test close behavior is not applied to elements without close modal class.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_not_applied_without_class(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="other-class"><button type="button">Regular Button</button></div>';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Content should remain unchanged when modal class is not present'
		);
	}

	/**
	 * Test close behavior with multiple elements in content.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_with_multiple_elements(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '
			<div>
				<div class="gatherpress-modal--trigger-close" data-close-modal="true">'
				. '<button type="button">Close First Modal</button></div>
				<p>Regular content</p>
				<div class="gatherpress-modal--trigger-close" data-close-modal="true">'
				. '<a href="#">Close Second Modal</a></div>
			</div>
		';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertEquals(
			2,
			substr_count( $output_html, 'data-wp-interactive="gatherpress"' ),
			'Should apply interactive attribute to both modal trigger elements'
		);
		$this->assertEquals(
			2,
			substr_count( $output_html, 'role="button"' ),
			'Should apply button role to both modal trigger elements'
		);
		$this->assertStringContainsString(
			'Regular content',
			$output_html,
			'Should preserve non-modal content'
		);
	}

	/**
	 * Test close behavior with mixed class names.
	 *
	 * @since  0.33.0
	 * @covers ::attach_modal_close_behavior
	 *
	 * @return void
	 */
	public function test_close_behavior_with_mixed_classes(): void {
		$instance = Modal_Manager::get_instance();

		$input_html  = '<div class="custom-class gatherpress-modal--trigger-close another-class" '
			. 'data-close-modal="true"><button type="button">Close Modal</button></div>';
		$output_html = $instance->attach_modal_close_behavior( $input_html );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output_html,
			'Should apply interactive attribute when modal class is mixed with others'
		);
		$this->assertStringContainsString(
			'custom-class',
			$output_html,
			'Should preserve existing classes'
		);
		$this->assertStringContainsString(
			'another-class',
			$output_html,
			'Should preserve all existing classes'
		);
	}

	/**
	 * Test a link trigger without an href gets tabindex so it can take focus.
	 *
	 * A Button block with no URL renders `<a>` without `href`, which is not in
	 * the tab order. A link with an `href`, even a valueless one, needs none.
	 *
	 * @since  0.36.0
	 * @covers ::attach_modal_open_behavior
	 * @covers ::attach_modal_close_behavior
	 * @covers ::is_link_with_href
	 *
	 * @return void
	 */
	public function test_link_without_href_gets_tabindex(): void {
		$instance = Modal_Manager::get_instance();

		$open = $instance->attach_modal_open_behavior(
			'<div class="wp-block-button gatherpress-modal--trigger-open">'
			. '<a class="wp-block-button__link">Open</a></div>'
		);
		$this->assertStringContainsString( 'tabindex="0"', $open, 'Open link without href should get tabindex' );
		$this->assertStringContainsString( 'data-wp-on--keydown="actions.openModalOnEnter"', $open );

		$close = $instance->attach_modal_close_behavior(
			'<div class="wp-block-button gatherpress-modal--trigger-close">'
			. '<a class="wp-block-button__link">Close</a></div>'
		);
		$this->assertStringContainsString( 'tabindex="0"', $close, 'Close link without href should get tabindex' );
		$this->assertStringContainsString( 'data-wp-on--keydown="actions.closeModalOnEnter"', $close );

		$direct = $instance->attach_modal_open_behavior( '<a class="gatherpress-modal--trigger-open">Open</a>' );
		$this->assertStringContainsString( 'tabindex="0"', $direct, 'Direct link without href needs tabindex' );

		$valueless = $instance->attach_modal_open_behavior(
			'<div class="gatherpress-modal--trigger-open"><a href>Open</a></div>'
		);
		$this->assertStringNotContainsString( 'tabindex', $valueless, 'A valueless href is focusable' );
	}

	/**
	 * Builds modal manager markup with a button trigger.
	 *
	 * @since TBD
	 *
	 * @param string $modals The markup after the trigger. Defaults to a modal with a close button.
	 *
	 * @return string The modal manager markup.
	 */
	private function get_modal_manager_markup( string $modals = '' ): string {
		if ( '' === $modals ) {
			$modals = '<div class="wp-block-gatherpress-modal"><div class="wp-block-gatherpress-modal-content">'
				. '<div class="wp-block-button gatherpress-modal--trigger-close">'
				. '<button type="button">Close</button></div></div></div>';
		}

		return '<div class="wp-block-gatherpress-modal-manager">'
			. '<div class="wp-block-button gatherpress-modal--trigger-open">'
			. '<button type="button" class="wp-block-button__link">RSVP</button></div>'
			. $modals
			. '</div>';
	}

	/**
	 * Builds the hidden inputs a GET form needs to reload a post's test page.
	 *
	 * The test site uses plain permalinks, so the post's own `p` arg comes first.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string The hidden inputs.
	 */
	private function get_page_inputs( int $post_id ): string {
		return sprintf(
			'<input type="hidden" name="p" value="%d"><input type="hidden" name="ref" value="x">',
			$post_id
		);
	}

	/**
	 * Loads a post's page with a `ref` query arg and resets the modal keys.
	 *
	 * @since TBD
	 *
	 * @param bool $open Whether the request asks for the post's first modal manager to open.
	 *
	 * @return int The post ID.
	 */
	private function set_up_page( bool $open = false ): int {
		$post_id = $this->factory->post->create();
		$key     = sprintf( '%d-1', $post_id );

		$this->go_to( add_query_arg( 'ref', 'x', get_permalink( $post_id ) ) );

		Utility::set_and_get_hidden_property( Modal_Manager::get_instance(), 'render_counts', array() );

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $open, $key ) {
				if ( $open && INPUT_GET === $type && Modal_Manager::OPEN_QUERY_ARG === $var_name ) {
					return $key;
				}

				return $pre_value;
			},
			10,
			3
		);

		return $post_id;
	}

	/**
	 * Clears the faked query args.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'gatherpress_pre_get_http_input' );

		parent::tear_down();
	}

	/**
	 * A closed modal's button trigger submits a GET form that reloads the
	 * page with the modal open, at the modal manager.
	 *
	 * @since TBD
	 * @covers ::open_modal_without_javascript
	 * @covers ::apply_to_tags
	 * @covers ::get_modal_key
	 * @covers ::get_navigation_form
	 * @covers ::insert_before_closing_tag
	 *
	 * @return void
	 */
	public function test_open_modal_without_javascript_reloads_from_a_button(): void {
		$post_id = $this->set_up_page();
		$key     = sprintf( '%d-1', $post_id );
		$anchor  = 'gatherpress-modal-manager-' . $key;
		$output  = Modal_Manager::get_instance()->open_modal_without_javascript(
			$this->get_modal_manager_markup(),
			array()
		);

		$this->assertStringContainsString( sprintf( 'id="%s"', $anchor ), $output, 'The modal manager gets an id.' );
		$this->assertMatchesRegularExpression(
			sprintf( '#<button (?=[^>]*type="submit")(?=[^>]*form="gatherpress-modal-open-%s")[^>]*>RSVP#', $key ),
			$output,
			'The trigger submits the open form.'
		);
		$this->assertStringContainsString(
			sprintf(
				'<form id="gatherpress-modal-open-%1$s" method="get" action="%2$s#%3$s" hidden>%4$s'
				. '<input type="hidden" name="gatherpress_modal" value="%1$s"></form></div>',
				$key,
				esc_url( home_url( '/' ) ),
				$anchor,
				$this->get_page_inputs( $post_id )
			),
			$output,
			'The open form loads the page at the modal manager, keeps the query args, and ends the modal manager.'
		);
		$this->assertStringNotContainsString( 'gatherpress--is-visible', $output, 'The modal stays closed.' );
		$this->assertStringNotContainsString(
			'gatherpress-modal-close-',
			$output,
			'A closed modal has no close form.'
		);
	}

	/**
	 * A link trigger without an `href` gets the URL that opens the modal; one with an `href` keeps it.
	 *
	 * @since TBD
	 * @covers ::open_modal_without_javascript
	 * @covers ::navigate_on_click
	 *
	 * @return void
	 */
	public function test_open_modal_without_javascript_links(): void {
		$post_id = $this->set_up_page();
		$url     = add_query_arg(
			Modal_Manager::OPEN_QUERY_ARG,
			$post_id . '-1',
			add_query_arg( 'ref', 'x', get_permalink( $post_id ) )
		);
		$output  = Modal_Manager::get_instance()->open_modal_without_javascript(
			'<div class="wp-block-gatherpress-modal-manager" id="custom">'
			. '<div class="wp-block-button gatherpress-modal--trigger-open"><a class="link">RSVP</a></div>'
			. '<a class="gatherpress-modal--trigger-open" href="https://example.org/">Elsewhere</a>'
			. '</div>',
			array()
		);

		$this->assertStringContainsString( 'id="custom"', $output, 'An existing id is kept.' );
		$this->assertStringContainsString(
			sprintf( 'href="%s#custom"', esc_url( $url ) ),
			$output,
			'A link without an href gets the URL that opens the modal.'
		);
		$this->assertStringContainsString( 'href="https://example.org/"', $output, 'A link with an href keeps it.' );
		$this->assertStringNotContainsString( '<form', $output, 'Links need no form.' );
	}

	/**
	 * The requested modal renders open, its close trigger submits the close
	 * form, and the overlay closes it too.
	 *
	 * @since TBD
	 * @covers ::open_modal_without_javascript
	 * @covers ::apply_to_tags
	 * @covers ::add_ways_out
	 *
	 * @return void
	 */
	public function test_open_modal_without_javascript_opens_the_requested_modal(): void {
		$post_id = $this->set_up_page( true );
		$key     = sprintf( '%d-1', $post_id );
		$second  = '<div class="wp-block-gatherpress-modal second"></div>';
		$markup  = str_replace(
			'</div></div></div></div>',
			'</div></div></div>' . $second . '</div>',
			$this->get_modal_manager_markup()
		);
		$output  = Modal_Manager::get_instance()->open_modal_without_javascript( $markup, array() );

		$this->assertMatchesRegularExpression(
			'#<div (?=[^>]*aria-hidden="false")(?=[^>]*data-gatherpress-opened-on-load="true")'
			. '(?=[^>]*class="wp-block-gatherpress-modal gatherpress--is-visible")[^>]*>'
			. '<button type="submit" form="gatherpress-modal-close-' . $key . '" class="gatherpress-modal__backdrop" '
			. 'tabindex="-1" aria-hidden="true" data-close-modal></button>'
			. '<div class="wp-block-gatherpress-modal-content">#',
			$output,
			'The modal renders open, with the overlay button first inside it.'
		);
		$this->assertStringContainsString( $second, $output, 'Only the first modal opens.' );
		$this->assertMatchesRegularExpression(
			sprintf( '#<button (?=[^>]*type="submit")(?=[^>]*form="gatherpress-modal-close-%s")[^>]*>Close#', $key ),
			$output,
			'The close trigger submits the close form.'
		);
		$this->assertStringContainsString(
			'<button type="button" class="wp-block-button__link">RSVP',
			$output,
			'The open trigger is left alone while the modal is open.'
		);
		$this->assertStringNotContainsString(
			'gatherpress-modal__close',
			$output,
			'A modal with a close trigger needs no Close link.'
		);
		$this->assertStringContainsString(
			sprintf(
				'<form id="gatherpress-modal-close-%1$s" method="get" '
				. 'action="%2$s#gatherpress-modal-manager-%1$s" hidden>%3$s</form>',
				$key,
				esc_url( home_url( '/' ) ),
				$this->get_page_inputs( $post_id )
			),
			$output,
			'The close form keeps the other query args and drops the open one.'
		);
	}

	/**
	 * A modal with no close trigger also gets a Close link.
	 *
	 * @since TBD
	 * @covers ::open_modal_without_javascript
	 * @covers ::apply_to_tags
	 * @covers ::add_ways_out
	 *
	 * @return void
	 */
	public function test_open_modal_without_javascript_adds_a_close_link(): void {
		$post_id = $this->set_up_page( true );
		$output  = Modal_Manager::get_instance()->open_modal_without_javascript(
			$this->get_modal_manager_markup( '<div class="wp-block-gatherpress-modal"><div>Content</div></div>' ),
			array()
		);

		$this->assertStringContainsString(
			sprintf(
				'data-close-modal></button>'
				. '<a class="gatherpress-modal__close" href="%s#gatherpress-modal-manager-%d-1">Close</a>'
				. '<div>Content</div>',
				esc_url( add_query_arg( 'ref', 'x', get_permalink( $post_id ) ) ),
				$post_id
			),
			$output,
			'The Close link comes after the overlay, before the content.'
		);
	}

	/**
	 * A request for a modal manager without a modal adds no ways out.
	 *
	 * @since TBD
	 * @covers ::open_modal_without_javascript
	 *
	 * @return void
	 */
	public function test_open_modal_without_javascript_without_a_modal(): void {
		$this->set_up_page( true );

		$output = Modal_Manager::get_instance()->open_modal_without_javascript(
			'<div class="wp-block-gatherpress-modal-manager"><p>No modal</p></div>',
			array()
		);

		$this->assertStringNotContainsString(
			'gatherpress-modal__backdrop',
			$output,
			'There is no modal to add a way out to.'
		);
		$this->assertStringNotContainsString( '<form', $output, 'Nothing needs a form.' );
	}

	/**
	 * Keys count modal managers per post, so they stay the same across page loads.
	 *
	 * @since TBD
	 * @covers ::get_modal_key
	 *
	 * @return void
	 */
	public function test_open_modal_without_javascript_keys_count_per_post(): void {
		$post_id  = $this->set_up_page();
		$other_id = $this->factory->post->create();
		$instance = Modal_Manager::get_instance();
		$markup   = '<div class="wp-block-gatherpress-modal-manager"></div>';

		$this->assertStringContainsString(
			sprintf( 'id="gatherpress-modal-manager-%d-1"', $post_id ),
			$instance->open_modal_without_javascript( $markup, array() )
		);
		$this->assertStringContainsString(
			sprintf( 'id="gatherpress-modal-manager-%d-2"', $post_id ),
			$instance->open_modal_without_javascript( $markup, array() )
		);
		$this->assertStringContainsString(
			sprintf( 'id="gatherpress-modal-manager-%d-1"', $other_id ),
			$instance->open_modal_without_javascript( $markup, array( 'attrs' => array( 'postId' => $other_id ) ) ),
			'Another post counts from 1.'
		);
	}

	/**
	 * Empty markup and requests other than a front-end page are left alone.
	 *
	 * @since TBD
	 * @covers ::open_modal_without_javascript
	 *
	 * @return void
	 */
	public function test_open_modal_without_javascript_skips_other_requests(): void {
		$this->set_up_page();

		$instance = Modal_Manager::get_instance();
		$markup   = $this->get_modal_manager_markup();

		$this->assertSame( '', $instance->open_modal_without_javascript( '', array() ), 'Empty markup is left alone.' );

		add_filter( 'wp_doing_ajax', '__return_true' );
		$ajax = $instance->open_modal_without_javascript( $markup, array() );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		$this->assertSame( $markup, $ajax, 'An Ajax request is left alone.' );
	}

	/**
	 * Only a link or button, with the trigger class or right after it, is a trigger.
	 *
	 * @since TBD
	 * @covers ::seek_actionable_element
	 * @covers ::navigate_on_click
	 *
	 * @return void
	 */
	public function test_trigger_helpers(): void {
		$instance = Modal_Manager::get_instance();

		$cases = array(
			'<button>'    => true,
			'<div><a>'    => true,
			'<div><span>' => false,
		);

		foreach ( $cases as $markup => $expected ) {
			$tag = new WP_HTML_Tag_Processor( $markup );
			$tag->next_tag();

			$this->assertSame(
				$expected,
				Utility::invoke_hidden_method( $instance, 'seek_actionable_element', array( $tag ) ),
				sprintf( 'Failed to assert whether %s holds a trigger.', $markup )
			);
		}

		$button = new WP_HTML_Tag_Processor( '<button type="button">' );
		$button->next_tag();

		$this->assertTrue(
			Utility::invoke_hidden_method( $instance, 'navigate_on_click', array( $button, 'f', '/url/' ) ),
			'A button needs the form.'
		);
		$this->assertSame( '<button form="f" type="submit">', $button->get_updated_html() );

		$link = new WP_HTML_Tag_Processor( '<a>' );
		$link->next_tag();

		$this->assertFalse(
			Utility::invoke_hidden_method( $instance, 'navigate_on_click', array( $link, 'f', '/url/' ) ),
			'A link does not.'
		);
		$this->assertSame( '<a href="/url/">', $link->get_updated_html() );
	}

	/**
	 * Builds the navigation form with and without query args, and inserts HTML before the closing tag.
	 *
	 * @since TBD
	 * @covers ::get_navigation_form
	 * @covers ::insert_before_closing_tag
	 *
	 * @return void
	 */
	public function test_form_helpers(): void {
		$instance = Modal_Manager::get_instance();

		$this->assertSame(
			'<form id="f" method="get" action="https://example.org/page/#a" hidden></form>',
			Utility::invoke_hidden_method(
				$instance,
				'get_navigation_form',
				array( 'f', 'https://example.org/page/#a' )
			),
			'A URL without a query needs no inputs.'
		);
		$this->assertSame(
			'<form id="f" method="get" action="https://example.org/page/#a" hidden>'
			. '<input type="hidden" name="x[]" value="1 &amp; 2"><input type="hidden" name="y" value=""></form>',
			Utility::invoke_hidden_method(
				$instance,
				'get_navigation_form',
				array( 'f', 'https://example.org/page/?x%5B%5D=1+%26+2&y#a' )
			),
			'Each query arg becomes a decoded hidden input.'
		);
		$this->assertSame(
			'<div>a<b></b></div>',
			Utility::invoke_hidden_method( $instance, 'insert_before_closing_tag', array( '<div>a</div>', '<b></b>' ) )
		);
		$this->assertSame(
			'<div>a<b></b>',
			Utility::invoke_hidden_method( $instance, 'insert_before_closing_tag', array( '<div>a', '<b></b>' ) ),
			'Without a closing tag, the HTML goes at the end.'
		);
	}

	/**
	 * Calls apply_to_tags() directly for each outcome, so its coverage is traced.
	 *
	 * @since TBD
	 * @covers ::apply_to_tags
	 *
	 * @return void
	 */
	public function test_apply_to_tags(): void {
		$instance = Modal_Manager::get_instance();
		$open     = '<div class="gatherpress-modal--trigger-open"><button>RSVP</button></div>';
		$close    = '<div class="gatherpress-modal--trigger-close"><button>Close</button></div>';
		$modal    = '<div class="wp-block-gatherpress-modal">' . $close . '</div>';
		$link     = '<a class="gatherpress-modal--trigger-open">RSVP</a>';
		$cases    = array(
			'button trigger'      => array( $open . $modal, false, array( false, true, true ) ),
			'link trigger'        => array( $link, false, array( false, true, false ) ),
			'opened modal'        => array( $open . $modal, true, array( true, true, true ) ),
			'requested, no modal' => array( $open, true, array( false, false, false ) ),
			'no trigger'          => array( '<p>Text</p>', false, array( false, false, false ) ),
		);

		foreach ( $cases as $label => list( $inner, $is_open, $expected ) ) {
			$tag = new WP_HTML_Tag_Processor( '<div class="wp-block-gatherpress-modal-manager">' . $inner . '</div>' );
			$tag->next_tag();

			$this->assertSame(
				array_combine( array( 'opened', 'has_trigger', 'has_form' ), $expected ),
				Utility::invoke_hidden_method( $instance, 'apply_to_tags', array( $tag, $is_open, 'f', '/url/' ) ),
				sprintf( 'Failed for the %s case.', $label )
			);
		}
	}
}
