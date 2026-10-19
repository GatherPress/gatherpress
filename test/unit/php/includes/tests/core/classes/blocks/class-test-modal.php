<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Modal.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Modal;
use GatherPress\Tests\Base;
use WP_HTML_Tag_Processor;

/**
 * Class Test_Modal.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Modal
 */
class Test_Modal extends Base {

	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the Modal block.
	 *
	 * @since 0.33.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Modal::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Modal::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'apply_modal_attributes' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'adjust_block_z_index' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'filter_login_modal' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'filter_rsvp_modal' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Test modal attributes are applied with custom name.
	 *
	 * @since  0.33.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_with_custom_name(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div>Modal Content</div>';
		$block       = array(
			'attrs' => array(
				'metadata' => array(
					'name' => 'Custom Modal Name',
				),
			),
		);
		$output_html = $instance->apply_modal_attributes( $input_html, $block );

		$this->assertStringContainsString(
			'role="dialog"',
			$output_html,
			'Output should contain dialog role'
		);
		$this->assertStringContainsString(
			'aria-modal="true"',
			$output_html,
			'Output should contain aria-modal attribute'
		);
		$this->assertStringContainsString(
			'aria-hidden="true"',
			$output_html,
			'Output should contain aria-hidden attribute'
		);
		$this->assertStringContainsString(
			'aria-label="Custom Modal Name"',
			$output_html,
			'Output should contain custom aria-label'
		);
		$this->assertStringContainsString(
			'tabindex="-1"',
			$output_html,
			'Output should contain negative tabindex'
		);
	}

	/**
	 * Test modal attributes are applied with default name.
	 *
	 * @since  0.33.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_with_default_name(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div>Modal Content</div>';
		$block       = array(
			'attrs' => array(),
		);
		$output_html = $instance->apply_modal_attributes( $input_html, $block );

		$this->assertStringContainsString(
			'role="dialog"',
			$output_html,
			'Output should contain dialog role'
		);
		$this->assertStringContainsString(
			'aria-modal="true"',
			$output_html,
			'Output should contain aria-modal attribute'
		);
		$this->assertStringContainsString(
			'aria-hidden="true"',
			$output_html,
			'Output should contain aria-hidden attribute'
		);
		$this->assertStringContainsString(
			'aria-label="Modal"',
			$output_html,
			'Output should contain default aria-label'
		);
		$this->assertStringContainsString(
			'tabindex="-1"',
			$output_html,
			'Output should contain negative tabindex'
		);
	}

	/**
	 * Test an unnamed modal is marked so the view script can name it on open.
	 *
	 * @since  0.36.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_mark_unnamed_modal(): void {
		$instance    = Modal::get_instance();
		$output_html = $instance->apply_modal_attributes( '<div><h2>Details</h2></div>', array( 'attrs' => array() ) );

		$this->assertStringContainsString( 'data-gatherpress-default-label="true"', $output_html );
		$this->assertStringContainsString( 'aria-label="Modal"', $output_html, 'Generic label is the fallback' );
		$this->assertStringContainsString( '<h2>Details</h2>', $output_html, 'Headings are not changed on the server' );
	}

	/**
	 * Test a custom name is not marked, and a blank name is treated as no name.
	 *
	 * @since  0.36.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_custom_and_blank_names(): void {
		$instance = Modal::get_instance();

		$named = $instance->apply_modal_attributes(
			'<div>Content</div>',
			array( 'attrs' => array( 'metadata' => array( 'name' => 'RSVP Modal' ) ) )
		);
		$this->assertStringContainsString( 'aria-label="RSVP Modal"', $named );
		$this->assertStringNotContainsString( 'data-gatherpress-default-label', $named );

		$blank = $instance->apply_modal_attributes(
			'<div>Content</div>',
			array( 'attrs' => array( 'metadata' => array( 'name' => '   ' ) ) )
		);
		$this->assertStringContainsString( 'aria-label="Modal"', $blank );
		$this->assertStringContainsString( 'data-gatherpress-default-label="true"', $blank );
	}

	/**
	 * Returns the text of the element an ARIA attribute on the dialog points at.
	 *
	 * @since TBD
	 *
	 * @param string $html      Rendered modal markup.
	 * @param string $attribute The dialog attribute that holds an element id.
	 *
	 * @return string|null The element's text, or null when the attribute is absent.
	 */
	private function get_referenced_text( string $html, string $attribute ): ?string {
		$tag = new WP_HTML_Tag_Processor( $html );

		$tag->next_tag();

		$id = $tag->get_attribute( $attribute );

		if ( ! is_string( $id ) ) {
			return null;
		}

		$pattern = sprintf( '#<(p|h[1-6])[^>]*\sid="%s"[^>]*>(.*?)</\1>#s', preg_quote( $id, '#' ) );

		$this->assertSame(
			1,
			preg_match( $pattern, $html, $matches ),
			sprintf( 'The %s target should be a paragraph or heading in the modal.', $attribute )
		);

		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $matches[2] ) ) );
	}

	/**
	 * Data provider for the classes of the modals in the RSVP block templates.
	 *
	 * @since TBD
	 *
	 * @return array<string, array<int, string>>
	 */
	public function data_rsvp_modal_classes(): array {
		return array(
			'RSVP modal'                   => array( 'gatherpress-modal--type-rsvp', 'RSVP Modal' ),
			'Login modal (template class)' => array( 'gatherpress-modal--login', 'Login Modal' ),
			'Login modal (filter class)'   => array( 'gatherpress-modal--type-login', 'Login Modal' ),
		);
	}

	/**
	 * Test an RSVP block modal is named by its title and described by its instructions.
	 *
	 * The templates name these modals "RSVP Modal" and "Login Modal" for the
	 * editor. The dialog takes its accessible name from the first paragraph
	 * (the visible title) and its description from the second, and keeps the
	 * template name as the `aria-label` fallback.
	 *
	 * @since TBD
	 * @covers ::apply_modal_attributes
	 * @covers ::is_rsvp_block_modal
	 * @covers ::label_by_paragraphs
	 *
	 * @dataProvider data_rsvp_modal_classes
	 *
	 * @param string $class_name The modal's class.
	 * @param string $name       The modal's template name.
	 *
	 * @return void
	 */
	public function test_rsvp_modal_named_by_title_paragraph( string $class_name, string $name ): void {
		$instance = Modal::get_instance();
		$output   = $instance->apply_modal_attributes(
			sprintf(
				'<div class="wp-block-gatherpress-modal %s"><div class="wp-block-gatherpress-modal-content">'
				. '<p style="margin-top:0"><strong>Dialog title</strong></p>'
				. '<p>Do this, then <a href="#x">that</a>.</p>'
				. '<p class="third">Other text</p></div></div>',
				$class_name
			),
			array(
				'attrs' => array(
					'className' => $class_name,
					'metadata'  => array( 'name' => $name ),
				),
			)
		);

		$this->assertSame( 'Dialog title', $this->get_referenced_text( $output, 'aria-labelledby' ) );
		$this->assertSame( 'Do this, then that.', $this->get_referenced_text( $output, 'aria-describedby' ) );
		$this->assertStringContainsString(
			sprintf( 'aria-label="%s"', $name ),
			$output,
			'The template name stays as the fallback.'
		);
		$this->assertStringContainsString( '<p class="third">', $output, 'Only the first two paragraphs get an id.' );
		$this->assertStringNotContainsString( 'data-gatherpress-default-label', $output );
	}

	/**
	 * Test an RSVP block modal whose title is a heading is named by the heading.
	 *
	 * A site owner can change the title paragraph to a Heading block. The
	 * heading then names the dialog, and the first paragraph after it (the
	 * instructions) describes it, so the instructions do not become the name.
	 *
	 * @since TBD
	 * @covers ::apply_modal_attributes
	 * @covers ::is_rsvp_block_modal
	 * @covers ::label_by_paragraphs
	 *
	 * @return void
	 */
	public function test_rsvp_modal_named_by_title_heading(): void {
		$instance = Modal::get_instance();
		$output   = $instance->apply_modal_attributes(
			'<div><div class="wp-block-gatherpress-modal-content"><h2 class="wp-block-heading">Dialog title</h2>'
			. '<p>Do this.</p><h3>Sub</h3><p>Other text</p></div></div>',
			array(
				'attrs' => array(
					'className' => 'gatherpress-modal--type-rsvp',
					'metadata'  => array( 'name' => 'RSVP Modal' ),
				),
			)
		);

		$this->assertSame( 'Dialog title', $this->get_referenced_text( $output, 'aria-labelledby' ) );
		$this->assertSame( 'Do this.', $this->get_referenced_text( $output, 'aria-describedby' ) );
		$this->assertStringContainsString( '<h3>Sub</h3><p>Other text</p>', $output, 'Later elements get no id.' );
	}

	/**
	 * Test an RSVP block modal reuses ids its paragraphs already have.
	 *
	 * @since TBD
	 * @covers ::apply_modal_attributes
	 * @covers ::is_rsvp_block_modal
	 * @covers ::label_by_paragraphs
	 *
	 * @return void
	 */
	public function test_rsvp_modal_keeps_existing_paragraph_ids(): void {
		$instance = Modal::get_instance();
		$output   = $instance->apply_modal_attributes(
			'<div><p id="my-title">Title</p><p id="my-help">Help</p></div>',
			array( 'attrs' => array( 'className' => 'gatherpress-modal--type-rsvp' ) )
		);
		$tag      = new WP_HTML_Tag_Processor( $output );

		$tag->next_tag();

		$this->assertSame( 'my-title', $tag->get_attribute( 'aria-labelledby' ) );
		$this->assertSame( 'my-help', $tag->get_attribute( 'aria-describedby' ) );
	}

	/**
	 * Test an RSVP block modal with too few paragraphs keeps what it can.
	 *
	 * With one paragraph the dialog gets a name but no description. With none
	 * it keeps the `aria-label` alone.
	 *
	 * @since TBD
	 * @covers ::apply_modal_attributes
	 * @covers ::is_rsvp_block_modal
	 * @covers ::label_by_paragraphs
	 *
	 * @return void
	 */
	public function test_rsvp_modal_with_few_paragraphs(): void {
		$instance = Modal::get_instance();
		$block    = array(
			'attrs' => array(
				'className' => 'gatherpress-modal--type-rsvp',
				'metadata'  => array( 'name' => 'RSVP Modal' ),
			),
		);

		$one = $instance->apply_modal_attributes( '<div><p>Only title</p><button>Attend</button></div>', $block );

		$this->assertSame( 'Only title', $this->get_referenced_text( $one, 'aria-labelledby' ) );
		$this->assertNull( $this->get_referenced_text( $one, 'aria-describedby' ) );

		$none = $instance->apply_modal_attributes( '<div><button>Attend</button></div>', $block );

		$this->assertNull( $this->get_referenced_text( $none, 'aria-labelledby' ) );
		$this->assertNull( $this->get_referenced_text( $none, 'aria-describedby' ) );
		$this->assertStringContainsString( 'aria-label="RSVP Modal"', $none );
	}

	/**
	 * Test two RSVP block modals on one page get different ids.
	 *
	 * The RSVP block renders one modal per status, so a page has several.
	 *
	 * @since TBD
	 * @covers ::apply_modal_attributes
	 * @covers ::is_rsvp_block_modal
	 * @covers ::label_by_paragraphs
	 *
	 * @return void
	 */
	public function test_rsvp_modal_ids_are_unique(): void {
		$instance = Modal::get_instance();
		$html     = '<div><p>Title</p><p>Help</p></div>';
		$block    = array( 'attrs' => array( 'className' => 'gatherpress-modal--type-rsvp' ) );
		$first    = new WP_HTML_Tag_Processor( $instance->apply_modal_attributes( $html, $block ) );
		$second   = new WP_HTML_Tag_Processor( $instance->apply_modal_attributes( $html, $block ) );

		$first->next_tag();
		$second->next_tag();

		$ids = array(
			$first->get_attribute( 'aria-labelledby' ),
			$first->get_attribute( 'aria-describedby' ),
			$second->get_attribute( 'aria-labelledby' ),
			$second->get_attribute( 'aria-describedby' ),
		);

		$this->assertContainsOnly( 'string', $ids );
		$this->assertCount( 4, array_unique( $ids ) );
	}

	/**
	 * Test a modal that is not from the RSVP block is not labeled by its paragraphs.
	 *
	 * @since TBD
	 * @covers ::apply_modal_attributes
	 * @covers ::is_rsvp_block_modal
	 * @covers ::label_by_paragraphs
	 *
	 * @return void
	 */
	public function test_other_modal_not_labeled_by_paragraphs(): void {
		$instance = Modal::get_instance();
		$html     = '<div class="other-class"><p>Title</p><p>Help</p></div>';
		$output   = $instance->apply_modal_attributes(
			$html,
			array(
				'attrs' => array(
					'className' => 'other-class',
					'metadata'  => array( 'name' => 'Venue details' ),
				),
			)
		);

		$this->assertStringNotContainsString( 'aria-labelledby', $output );
		$this->assertStringNotContainsString( 'aria-describedby', $output );
		$this->assertStringContainsString( '<p>Title</p><p>Help</p>', $output, 'Paragraphs are not changed.' );
		$this->assertStringContainsString( 'aria-label="Venue details"', $output );
	}

	/**
	 * Test saved RSVP block modal markup is named by its title when rendered.
	 *
	 * Uses the markup the RSVP block saves for existing events, rendered
	 * through `do_blocks()`, so it covers events made before this change.
	 *
	 * @since TBD
	 * @coversNothing
	 *
	 * @return void
	 */
	public function test_saved_rsvp_modals_named_by_title_when_rendered(): void {
		$rsvp_modal  = implode(
			'',
			array(
				'<!-- wp:gatherpress/modal {"metadata":{"name":"RSVP Modal"},',
				'"className":"gatherpress-modal--type-rsvp"} -->',
				'<div class="wp-block-gatherpress-modal gatherpress-modal--type-rsvp">',
				'<!-- wp:gatherpress/modal-content --><div class="wp-block-gatherpress-modal-content">',
				'<!-- wp:paragraph {"metadata":{"name":"RSVP Heading"}} -->',
				'<p><strong>RSVP to this event</strong></p><!-- /wp:paragraph -->',
				'<!-- wp:paragraph {"metadata":{"name":"RSVP Info"}} -->',
				'<p>To set your attendance status, simply click the <strong>Attend</strong> button below.</p>',
				'<!-- /wp:paragraph -->',
				'</div><!-- /wp:gatherpress/modal-content --></div><!-- /wp:gatherpress/modal -->',
			)
		);
		$login_modal = implode(
			'',
			array(
				'<!-- wp:gatherpress/modal {"metadata":{"name":"Login Modal"},',
				'"className":"gatherpress-modal--login"} -->',
				'<div class="wp-block-gatherpress-modal gatherpress-modal--login">',
				'<!-- wp:gatherpress/modal-content --><div class="wp-block-gatherpress-modal-content">',
				'<!-- wp:paragraph {"metadata":{"name":"Login Heading"}} -->',
				'<p><strong>Login Required</strong></p><!-- /wp:paragraph -->',
				'<!-- wp:paragraph {"metadata":{"name":"Login Info"},"className":"gatherpress--has-login-url"} -->',
				'<p class="gatherpress--has-login-url">This action requires an account. ',
				'Please <a href="#gatherpress-login-url">Login</a> to RSVP to this event.</p>',
				'<!-- /wp:paragraph -->',
				'</div><!-- /wp:gatherpress/modal-content --></div><!-- /wp:gatherpress/modal -->',
			)
		);

		wp_set_current_user( $this->factory->user->create() );

		$rsvp = do_blocks( $rsvp_modal );

		$this->assertSame( 'RSVP to this event', $this->get_referenced_text( $rsvp, 'aria-labelledby' ) );
		$this->assertSame(
			'To set your attendance status, simply click the Attend button below.',
			$this->get_referenced_text( $rsvp, 'aria-describedby' )
		);

		wp_set_current_user( 0 );

		$login = do_blocks( $login_modal );

		$this->assertSame( 'Login Required', $this->get_referenced_text( $login, 'aria-labelledby' ) );
		$this->assertSame(
			'This action requires an account. Please Login to RSVP to this event.',
			$this->get_referenced_text( $login, 'aria-describedby' )
		);
	}

	/**
	 * Test modal attributes preserve existing content.
	 *
	 * @since  0.33.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_preserve_content(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div class="existing-class">Existing Content</div>';
		$block       = array(
			'attrs' => array(),
		);
		$output_html = $instance->apply_modal_attributes( $input_html, $block );

		$this->assertStringContainsString(
			'class="existing-class"',
			$output_html,
			'Output should preserve existing classes'
		);
		$this->assertStringContainsString(
			'Existing Content',
			$output_html,
			'Output should preserve existing content'
		);
	}

	/**
	 * Test modal attributes with empty HTML.
	 *
	 * @since  0.33.0
	 * @covers ::apply_modal_attributes
	 *
	 * @return void
	 */
	public function test_modal_attributes_with_empty_html(): void {
		$instance    = Modal::get_instance();
		$input_html  = '';
		$block       = array(
			'attrs' => array(),
		);
		$output_html = $instance->apply_modal_attributes( $input_html, $block );

		$this->assertEmpty(
			$output_html,
			'Output should remain empty when input is empty'
		);
	}

	/**
	 * Test z-index is applied with default value.
	 *
	 * @since  0.33.0
	 * @covers ::adjust_block_z_index
	 *
	 * @return void
	 */
	public function test_z_index_default_value(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div>Content</div>';
		$block       = array( 'attrs' => array() );
		$output_html = $instance->adjust_block_z_index( $input_html, $block );

		$this->assertStringContainsString(
			'z-index: 1000;',
			$output_html,
			'Output should contain default z-index value'
		);
	}

	/**
	 * Test z-index is applied with custom value.
	 *
	 * @since  0.33.0
	 * @covers ::adjust_block_z_index
	 *
	 * @return void
	 */
	public function test_z_index_custom_value(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div>Content</div>';
		$block       = array(
			'attrs' => array(
				'zIndex' => 2000,
			),
		);
		$output_html = $instance->adjust_block_z_index( $input_html, $block );

		$this->assertStringContainsString(
			'z-index: 2000;',
			$output_html,
			'Output should contain custom z-index value'
		);
	}

	/**
	 * Test z-index merges with existing styles.
	 *
	 * @since  0.33.0
	 * @covers ::adjust_block_z_index
	 *
	 * @return void
	 */
	public function test_z_index_merges_with_existing_styles(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div style="color: red; margin: 10px;">Content</div>';
		$block       = array( 'attrs' => array() );
		$output_html = $instance->adjust_block_z_index( $input_html, $block );

		$this->assertStringContainsString(
			'color: red',
			$output_html,
			'Output should preserve existing styles'
		);
		$this->assertStringContainsString(
			'margin: 10px',
			$output_html,
			'Output should preserve all existing styles'
		);
		$this->assertStringContainsString(
			'z-index: 1000',
			$output_html,
			'Output should append z-index to existing styles'
		);
	}

	/**
	 * Test z-index handles empty style attribute.
	 *
	 * @since  0.33.0
	 * @covers ::adjust_block_z_index
	 *
	 * @return void
	 */
	public function test_z_index_with_empty_style(): void {
		$instance    = Modal::get_instance();
		$input_html  = '<div style="">Content</div>';
		$block       = array( 'attrs' => array() );
		$output_html = $instance->adjust_block_z_index( $input_html, $block );

		$this->assertStringContainsString(
			'style="; z-index: 1000;"',
			$output_html,
			'Output should set z-index when style is empty'
		);
	}

	/**
	 * Data provider for existing styles that contain a percent sign.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<int, string>>
	 */
	public function data_styles_with_percent_signs(): array {
		return array(
			'gradient stops (#2333)'         => array(
				'background:linear-gradient(135deg,rgb(6,147,227) 0%,rgb(155,81,224) 100%)',
			),
			'a percentage width'             => array( 'width:50%' ),
			'a percent-encoded URL'          => array( 'background-image:url(photo%20one.jpg)' ),
			'several percentage values'      => array( 'width:50%;height:25%;margin:5%' ),
			'a sequence that is a specifier' => array( 'background-image:url(img%2d.png)' ),
		);
	}

	/**
	 * Test existing styles containing a percent sign are kept verbatim.
	 *
	 * A `%` in the block's own style attribute must not be read as a
	 * `sprintf()` specifier, which threw a fatal ValueError on gradients.
	 *
	 * @since 0.36.0
	 * @covers ::adjust_block_z_index
	 *
	 * @dataProvider data_styles_with_percent_signs
	 *
	 * @param string $style The block's existing style attribute.
	 *
	 * @return void
	 */
	public function test_z_index_keeps_styles_with_percent_signs( string $style ): void {
		$instance = Modal::get_instance();
		$output   = $instance->adjust_block_z_index(
			sprintf( '<div style="%s">Content</div>', esc_attr( $style ) ),
			array( 'attrs' => array() )
		);
		$tag      = new WP_HTML_Tag_Processor( $output );

		$tag->next_tag();

		$this->assertSame(
			$style . '; z-index: 1000;',
			$tag->get_attribute( 'style' ),
			'Existing styles should survive unchanged, with the z-index appended.'
		);
	}

	/**
	 * Test a custom z-index is still applied alongside a gradient.
	 *
	 * @since 0.36.0
	 * @covers ::adjust_block_z_index
	 *
	 * @return void
	 */
	public function test_z_index_custom_value_with_gradient(): void {
		$instance = Modal::get_instance();
		$gradient = 'background:linear-gradient(135deg,rgb(6,147,227) 0%,rgb(155,81,224) 100%)';
		$output   = $instance->adjust_block_z_index(
			sprintf( '<div style="%s">Content</div>', esc_attr( $gradient ) ),
			array( 'attrs' => array( 'zIndex' => 2000 ) )
		);
		$tag      = new WP_HTML_Tag_Processor( $output );

		$tag->next_tag();

		$this->assertSame(
			$gradient . '; z-index: 2000;',
			$tag->get_attribute( 'style' ),
			'The custom z-index should be applied without disturbing the gradient.'
		);
	}

	/**
	 * Test login modal is removed for logged-in users.
	 *
	 * @since  0.33.0
	 * @covers ::filter_login_modal
	 *
	 * @return void
	 */
	public function test_login_modal_removed_when_logged_in(): void {
		$instance = Modal::get_instance();
		$user_id  = $this->factory->user->create();

		wp_set_current_user( $user_id );

		$input_html  = '<div>Login Form</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'gatherpress-modal--type-login',
			),
		);
		$output_html = $instance->filter_login_modal( $input_html, $block );

		$this->assertEmpty(
			$output_html,
			'Login modal should be empty for logged-in users'
		);

		wp_set_current_user( 0 );
	}


	/**
	 * Test login modal remains for logged-out users.
	 *
	 * @since  0.33.0
	 * @covers ::filter_login_modal
	 *
	 * @return void
	 */
	public function test_login_modal_remains_when_logged_out(): void {
		$instance = Modal::get_instance();

		wp_set_current_user( 0 );

		$input_html  = '<div>Login Form</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'gatherpress-modal--type-login',
			),
		);
		$output_html = $instance->filter_login_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Login modal should remain for logged-out users'
		);
	}


	/**
	 * Test non-login modal remains for logged-in users.
	 *
	 * @since  0.33.0
	 * @covers ::filter_login_modal
	 *
	 * @return void
	 */
	public function test_non_login_modal_remains_when_logged_in(): void {
		$instance = Modal::get_instance();
		$user_id  = $this->factory->user->create();

		wp_set_current_user( $user_id );

		$input_html  = '<div>Regular Modal</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'other-class',
			),
		);
		$output_html = $instance->filter_login_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Non-login modal should remain for logged-in users'
		);

		wp_set_current_user( 0 );
	}


	/**
	 * Test modal without className attribute.
	 *
	 * @since  0.33.0
	 * @covers ::filter_login_modal
	 *
	 * @return void
	 */
	public function test_login_modal_without_classname(): void {
		$instance = Modal::get_instance();
		$user_id  = $this->factory->user->create();

		wp_set_current_user( $user_id );

		$input_html  = '<div>Modal Content</div>';
		$block       = array(
			'attrs' => array(),
		);
		$output_html = $instance->filter_login_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Modal without className should remain unchanged'
		);

		wp_set_current_user( 0 );
	}

	/**
	 * Test RSVP modal is removed for logged-out users.
	 *
	 * @since  0.33.0
	 * @covers ::filter_rsvp_modal
	 *
	 * @return void
	 */
	public function test_rsvp_modal_removed_when_logged_out(): void {
		$instance = Modal::get_instance();

		wp_set_current_user( 0 );

		$input_html  = '<div>RSVP Form</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'gatherpress-modal--type-rsvp',
			),
		);
		$output_html = $instance->filter_rsvp_modal( $input_html, $block );

		$this->assertEmpty(
			$output_html,
			'RSVP modal should be empty for logged-out users'
		);
	}


	/**
	 * Test RSVP modal remains for logged-in users.
	 *
	 * @since  0.33.0
	 * @covers ::filter_rsvp_modal
	 *
	 * @return void
	 */
	public function test_rsvp_modal_remains_when_logged_in(): void {
		$instance = Modal::get_instance();
		$user_id  = $this->factory->user->create();

		wp_set_current_user( $user_id );

		$input_html  = '<div>RSVP Form</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'gatherpress-modal--type-rsvp',
			),
		);
		$output_html = $instance->filter_rsvp_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'RSVP modal should remain for logged-in users'
		);

		wp_set_current_user( 0 );
	}


	/**
	 * Test non-RSVP modal remains for logged-out users.
	 *
	 * @since  0.33.0
	 * @covers ::filter_rsvp_modal
	 *
	 * @return void
	 */
	public function test_non_rsvp_modal_remains_when_logged_out(): void {
		$instance = Modal::get_instance();

		wp_set_current_user( 0 );

		$input_html  = '<div>Regular Modal</div>';
		$block       = array(
			'attrs' => array(
				'className' => 'other-class',
			),
		);
		$output_html = $instance->filter_rsvp_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Non-RSVP modal should remain for logged-out users'
		);
	}


	/**
	 * Test modal without className attribute.
	 *
	 * @since  0.33.0
	 * @covers ::filter_rsvp_modal
	 *
	 * @return void
	 */
	public function test_rsvp_modal_without_classname(): void {
		$instance = Modal::get_instance();

		wp_set_current_user( 0 );

		$input_html  = '<div>Modal Content</div>';
		$block       = array(
			'attrs' => array(),
		);
		$output_html = $instance->filter_rsvp_modal( $input_html, $block );

		$this->assertEquals(
			$input_html,
			$output_html,
			'Modal without className should remain unchanged'
		);
	}
}
