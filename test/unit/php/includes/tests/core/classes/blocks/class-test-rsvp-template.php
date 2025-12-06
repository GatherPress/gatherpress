<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Rsvp_Template.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Rsvp_Template;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Tests\Base;
use WP_Block;

/**
 * Class Test_Rsvp_Template.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Rsvp_Template
 */
class Test_Rsvp_Template extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the RSVP Template block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Rsvp_Template::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Rsvp_Template::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'ensure_block_styles_loaded' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'generate_rsvp_template_block' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Tests ensure_block_styles_loaded with no data-blocks attribute.
	 *
	 * @covers ::ensure_block_styles_loaded
	 * @return void
	 */
	public function test_ensure_block_styles_loaded_no_data_blocks(): void {
		$instance      = Rsvp_Template::get_instance();
		$block_content = '<div>Test content</div>';
		$result        = $instance->ensure_block_styles_loaded( $block_content );

		$this->assertSame(
			$block_content,
			$result,
			'Failed to assert block content unchanged when no data-blocks attribute.'
		);
	}

	/**
	 * Tests ensure_block_styles_loaded with empty data-blocks.
	 *
	 * @covers ::ensure_block_styles_loaded
	 * @return void
	 */
	public function test_ensure_block_styles_loaded_empty_data_blocks(): void {
		$instance      = Rsvp_Template::get_instance();
		$block_content = '<div data-blocks="">Test content</div>';
		$result        = $instance->ensure_block_styles_loaded( $block_content );

		$this->assertSame(
			$block_content,
			$result,
			'Failed to assert block content unchanged when data-blocks is empty.'
		);
	}

	/**
	 * Tests ensure_block_styles_loaded with valid data-blocks.
	 *
	 * @covers ::ensure_block_styles_loaded
	 * @return void
	 */
	public function test_ensure_block_styles_loaded_with_blocks(): void {
		$instance      = Rsvp_Template::get_instance();
		$blocks_data   = wp_json_encode(
			array(
				array( 'blockName' => 'core/paragraph' ),
			)
		);
		$block_content = sprintf( '<div data-blocks=\'%s\'>Test content</div>', $blocks_data );
		$result        = $instance->ensure_block_styles_loaded( $block_content );

		$this->assertSame(
			$block_content,
			$result,
			'Failed to assert block content returned correctly.'
		);
	}

	/**
	 * Tests generate_rsvp_template_block with non-event post.
	 *
	 * @covers ::generate_rsvp_template_block
	 * @return void
	 */
	public function test_generate_rsvp_template_block_non_event(): void {
		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create( array( 'post_type' => 'post' ) );
		$wp_block   = new WP_Block(
			array(),
			array( 'postId' => $post_id )
		);
		$block      = array();
		$input_html = '<div>Original content</div>';
		$result     = $instance->generate_rsvp_template_block( $input_html, $block, $wp_block );

		$this->assertSame(
			$input_html,
			$result,
			'Failed to assert non-event post returns original content.'
		);
	}

	/**
	 * Tests generate_rsvp_template_block with unpublished event.
	 *
	 * @covers ::generate_rsvp_template_block
	 * @return void
	 */
	public function test_generate_rsvp_template_block_unpublished(): void {
		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		$wp_block   = new WP_Block(
			array(),
			array( 'postId' => $post_id )
		);
		$block      = array();
		$input_html = '<div>Original content</div>';
		$result     = $instance->generate_rsvp_template_block( $input_html, $block, $wp_block );

		$this->assertSame(
			$input_html,
			$result,
			'Failed to assert unpublished event returns original content.'
		);
	}

	/**
	 * Tests generate_rsvp_template_block with published event.
	 *
	 * @covers ::generate_rsvp_template_block
	 * @return void
	 */
	public function test_generate_rsvp_template_block_published_event(): void {
		$instance = Rsvp_Template::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
			)
		)->get();
		$post_id  = $post->ID;

		$wp_block = new WP_Block(
			array(),
			array( 'postId' => $post_id )
		);
		$block    = array( 'innerBlocks' => array() );
		$result   = $instance->generate_rsvp_template_block( '', $block, $wp_block );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'Failed to assert published event generates interactive markup.'
		);
		$this->assertStringContainsString(
			'data-wp-watch="callbacks.renderBlocks"',
			$result,
			'Failed to assert published event includes watch callback.'
		);
	}

	/**
	 * Tests get_block_content method.
	 *
	 * @covers ::get_block_content
	 * @return void
	 */
	public function test_get_block_content(): void {
		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		wp_set_object_terms( $comment_id, 'attending', Rsvp::TAXONOMY );

		$parsed_block = array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '<p>Test</p>',
			'innerContent' => array( '<p>Test</p>' ),
		);

		$result = $instance->get_block_content( $parsed_block, $comment_id );

		$this->assertStringContainsString(
			'data-id="rsvp-' . $comment_id . '"',
			$result,
			'Failed to assert block content includes data-id attribute.'
		);
	}

	/**
	 * Tests get_block_content with limit enabled.
	 *
	 * @covers ::get_block_content
	 * @return void
	 */
	public function test_get_block_content_with_limit(): void {
		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$parsed_block = array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '<p>Test</p>',
			'innerContent' => array( '<p>Test</p>' ),
		);

		$args = array(
			'limit_enabled' => true,
			'limit'         => 2,
			'index'         => 3,
		);

		$result = $instance->get_block_content( $parsed_block, $comment_id, $args );

		$this->assertStringContainsString(
			'gatherpress--is-hidden',
			$result,
			'Failed to assert block is hidden when limit exceeded.'
		);
	}

	/**
	 * Tests get_block_content with limit not exceeded.
	 *
	 * @covers ::get_block_content
	 * @return void
	 */
	public function test_get_block_content_within_limit(): void {
		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$parsed_block = array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '<p>Test</p>',
			'innerContent' => array( '<p>Test</p>' ),
		);

		$args = array(
			'limit_enabled' => true,
			'limit'         => 5,
			'index'         => 3,
		);

		$result = $instance->get_block_content( $parsed_block, $comment_id, $args );

		$this->assertStringNotContainsString(
			'gatherpress--is-hidden',
			$result,
			'Failed to assert block is not hidden when within limit.'
		);
	}

	/**
	 * Tests anonymize_rsvp_blocks with core/avatar block.
	 *
	 * @covers ::anonymize_rsvp_blocks
	 * @return void
	 */
	public function test_anonymize_rsvp_blocks_avatar(): void {
		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$blocks = array(
			array(
				'blockName'    => 'core/avatar',
				'attrs'        => array( 'isLink' => 1 ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
		);

		$instance->anonymize_rsvp_blocks( $blocks, $comment_id );

		$this->assertSame(
			0,
			$blocks[0]['attrs']['isLink'],
			'Failed to assert avatar isLink is set to 0.'
		);
	}

	/**
	 * Tests anonymize_rsvp_blocks with core/comment-author-name block.
	 *
	 * @covers ::anonymize_rsvp_blocks
	 * @return void
	 */
	public function test_anonymize_rsvp_blocks_author_name(): void {
		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$blocks = array(
			array(
				'blockName'    => 'core/comment-author-name',
				'attrs'        => array( 'isLink' => 1 ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
		);

		$instance->anonymize_rsvp_blocks( $blocks, $comment_id );

		$this->assertSame(
			'core/paragraph',
			$blocks[0]['blockName'],
			'Failed to assert author name block converted to paragraph.'
		);
		$this->assertSame(
			0,
			$blocks[0]['attrs']['isLink'],
			'Failed to assert author name isLink is set to 0.'
		);
		$this->assertStringContainsString(
			'Anonymous',
			$blocks[0]['innerContent'][0],
			'Failed to assert author name replaced with Anonymous.'
		);
	}

	/**
	 * Tests anonymize_rsvp_blocks with nested blocks.
	 *
	 * @covers ::anonymize_rsvp_blocks
	 * @return void
	 */
	public function test_anonymize_rsvp_blocks_nested(): void {
		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$blocks = array(
			array(
				'blockName'    => 'core/group',
				'attrs'        => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/avatar',
						'attrs'        => array( 'isLink' => 1 ),
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					),
				),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
		);

		$instance->anonymize_rsvp_blocks( $blocks, $comment_id );

		$this->assertSame(
			0,
			$blocks[0]['innerBlocks'][0]['attrs']['isLink'],
			'Failed to assert nested avatar isLink is set to 0.'
		);
	}

	/**
	 * Tests get_block_content with anonymous RSVP.
	 *
	 * @covers ::get_block_content
	 * @return void
	 */
	public function test_get_block_content_anonymous(): void {
		// Set user without edit_posts capability.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Mark as anonymous.
		add_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', 1 );

		$parsed_block = array(
			'blockName'    => 'core/group',
			'attrs'        => array(),
			'innerBlocks'  => array(
				array(
					'blockName'    => 'core/avatar',
					'attrs'        => array( 'isLink' => 1 ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
			),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$result = $instance->get_block_content( $parsed_block, $comment_id );

		$this->assertStringContainsString(
			'data-id="rsvp-' . $comment_id . '"',
			$result,
			'Failed to assert anonymous RSVP includes data-id.'
		);
	}

	/**
	 * Tests get_block_content with anonymous RSVP for admin.
	 *
	 * @covers ::get_block_content
	 * @return void
	 */
	public function test_get_block_content_anonymous_admin(): void {
		// Set user with edit_posts capability.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance   = Rsvp_Template::get_instance();
		$post_id    = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		// Mark as anonymous.
		add_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', 1 );

		$parsed_block = array(
			'blockName'    => 'core/group',
			'attrs'        => array(),
			'innerBlocks'  => array(
				array(
					'blockName'    => 'core/avatar',
					'attrs'        => array( 'isLink' => 1 ),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				),
			),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		$result = $instance->get_block_content( $parsed_block, $comment_id );

		$this->assertStringContainsString(
			'data-id="rsvp-' . $comment_id . '"',
			$result,
			'Failed to assert admin can see anonymous RSVP.'
		);
	}
}
