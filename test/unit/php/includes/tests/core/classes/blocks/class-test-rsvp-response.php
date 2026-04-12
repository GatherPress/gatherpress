<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Rsvp_Response.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Rsvp_Response;
use GatherPress\Core\Rsvp;
use GatherPress\Tests\Base;

/**
 * Class Test_Rsvp_Response.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Rsvp_Response
 */
class Test_Rsvp_Response extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the RSVP Response block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Rsvp_Response::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Rsvp_Response::BLOCK_NAME );
		$hooks             = array(
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $instance, 'transform_block_content' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 11,
				'callback' => array( $instance, 'attach_dropdown_interactivity' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'get_avatar_data',
				'priority' => 10,
				'callback' => array( $instance, 'modify_avatar_for_gatherpress_rsvp' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'block_type_metadata',
				'priority' => 10,
				'callback' => array( $instance, 'add_rsvp_to_comment_ancestor' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Tests basic block content transformation.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_basic(): void {
		$instance      = Rsvp_Response::get_instance();
		$post_id       = $this->factory()->post->create(
			array(
				'post_type' => 'gatherpress_event',
			)
		);
		$block         = array(
			'blockName' => 'gatherpress/rsvp-response',
			'attrs'     => array(
				'postId'           => $post_id,
				'rsvpLimitEnabled' => '1',
				'rsvpLimit'        => '10',
			),
		);
		$block_content = '<div class="wp-block-gatherpress-rsvp-response">Content</div>';
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'data-limit-enabled="1"',
			$result,
			'RSVP limit enabled attribute should be present'
		);
		$this->assertStringContainsString(
			'data-limit="10"',
			$result,
			'RSVP limit value should be present'
		);
		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'Interactive attribute should be present'
		);
	}

	/**
	 * Tests transform_block_content returns empty string for non-event post type.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_non_event_post(): void {
		$instance      = Rsvp_Response::get_instance();
		$post_id       = $this->factory()->post->create(
			array(
				'post_type' => 'post',
			)
		);
		$block         = array(
			'blockName' => 'gatherpress/rsvp-response',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);
		$block_content = '<div class="wp-block-gatherpress-rsvp-response">Content</div>';
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertSame(
			'',
			$result,
			'Non-event post type should return empty string.'
		);
	}

	/**
	 * Tests empty RSVP visibility handling.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_empty_rsvp(): void {
		$instance      = Rsvp_Response::get_instance();
		$post_id       = $this->factory()->post->create(
			array(
				'post_type' => 'gatherpress_event',
			)
		);
		$block         = array(
			'blockName' => 'gatherpress/rsvp-response',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);
		$block_content = '<div><div class="gatherpress-rsvp-response--no-responses">No RSVPs</div></div>';
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'gatherpress--is-visible',
			$result,
			'Empty RSVP notice should be visible when no attendees'
		);
	}

	/**
	 * Tests RSVP count data attributes.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_with_responses(): void {
		$instance = Rsvp_Response::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => 'gatherpress_event',
			)
		);
		$user_id  = $this->factory()->user->create();
		$rsvp     = new Rsvp( $post_id );

		$rsvp->save( $user_id, 'attending' );

		$block         = array(
			'blockName' => 'gatherpress/rsvp-response',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);
		$block_content = '<div class="wp-block-gatherpress-rsvp-response">Content</div>';
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'data-counts',
			$result,
			'RSVP count data attribute should be present'
		);
	}

	/**
	 * Tests no-responses visibility handling with attending responses.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_hide_no_responses_with_attendees(): void {
		$instance = Rsvp_Response::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => 'gatherpress_event',
			)
		);
		$user_id  = $this->factory()->user->create();
		$rsvp     = new Rsvp( $post_id );

		$rsvp->save( $user_id, 'attending' );

		$block         = array(
			'blockName' => 'gatherpress/rsvp-response',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);
		$block_content = '<div class="wp-block-gatherpress-rsvp-response">' .
			'<div class="gatherpress-rsvp-response--no-responses gatherpress--is-visible">No responses</div>' .
			'</div>';
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'gatherpress--is-hidden',
			$result,
			'No-responses section should have hidden class when attendees exist.'
		);
		$this->assertStringNotContainsString(
			'gatherpress--is-visible',
			$result,
			'No-responses section should not have visible class when attendees exist.'
		);
	}

	/**
	 * Tests default values for RSVP limits.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_default_limits(): void {
		$instance      = Rsvp_Response::get_instance();
		$post_id       = $this->factory()->post->create(
			array(
				'post_type' => 'gatherpress_event',
			)
		);
		$block         = array(
			'blockName' => 'gatherpress/rsvp-response',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);
		$block_content = '<div class="wp-block-gatherpress-rsvp-response">Content</div>';
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'data-limit-enabled="0"',
			$result,
			'RSVP limit should be disabled by default'
		);
		$this->assertStringContainsString(
			'data-limit="8"',
			$result,
			'Default RSVP limit should be 8'
		);
	}

	/**
	 * Tests trigger text with attendee count.
	 *
	 * @since 1.0.0
	 * @covers ::attach_dropdown_interactivity
	 *
	 * @return void
	 */
	public function test_attach_dropdown_interactivity_trigger(): void {
		$instance      = Rsvp_Response::get_instance();
		$block_content = sprintf(
			'<div data-counts="%s"><a class="wp-block-gatherpress-dropdown__trigger">%d Attending</a></div>',
			wp_json_encode( array( 'attending' => 5 ) ),
			5
		);
		$result        = $instance->attach_dropdown_interactivity( $block_content );

		$this->assertStringContainsString(
			'{"attending":5}',
			$result,
			'Trigger should show correct attendee data'
		);
		$this->assertStringContainsString(
			'5 Attending',
			$result,
			'Trigger should show correct attendee count'
		);
	}

	/**
	 * Tests RSVP status buttons.
	 *
	 * @since 1.0.0
	 * @covers ::attach_dropdown_interactivity
	 *
	 * @return void
	 */
	public function test_attach_dropdown_interactivity_status_buttons(): void {
		$instance      = Rsvp_Response::get_instance();
		$block_content = '<div>
			<a class="wp-block-gatherpress-dropdown__trigger">Attending</a>
			<div class="wp-block-gatherpress-dropdown__menu">
				<div class="wp-block-gatherpress-dropdown-item gatherpress--is-attending">
					<a href="#">Attending</a></div>
				<div class="wp-block-gatherpress-dropdown-item gatherpress--is-waiting-list">
					<a href="#">Waiting List</a></div>
				<div class="wp-block-gatherpress-dropdown-item gatherpress--is-not-attending">
					<a href="#">Not Attending</a></div>
			</div>
		</div>';
		$result        = $instance->attach_dropdown_interactivity( $block_content );
		$this->assertStringContainsString(
			'data-status="attending"',
			$result,
			'Attending button should have correct status'
		);
		$this->assertStringContainsString(
			'data-status="waiting_list"',
			$result,
			'Waiting list button should have correct status'
		);
		$this->assertStringContainsString(
			'data-status="not_attending"',
			$result,
			'Not attending button should have correct status'
		);
		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'Buttons should have interactive attribute'
		);
		$this->assertStringContainsString(
			'data-wp-watch="callbacks.processRsvpDropdown"',
			$result,
			'Buttons should have watch callback'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.processRsvpSelection"',
			$result,
			'Buttons should have click handler'
		);
	}

	/**
	 * Tests with missing data counts.
	 *
	 * @since 1.0.0
	 * @covers ::attach_dropdown_interactivity
	 *
	 * @return void
	 */
	public function test_attach_dropdown_interactivity_no_counts(): void {
		$instance      = Rsvp_Response::get_instance();
		$block_content = '<div><a class="wp-block-gatherpress-dropdown__trigger">0 Attending</a></div>';
		$result        = $instance->attach_dropdown_interactivity( $block_content );

		$this->assertStringContainsString(
			'0 Attending',
			$result,
			'Should default to 0 when no counts provided'
		);
	}

	/**
	 * Tests with non-RSVP menu items.
	 *
	 * @since 1.0.0
	 * @covers ::attach_dropdown_interactivity
	 *
	 * @return void
	 */
	public function test_attach_dropdown_interactivity_non_rsvp_items(): void {
		$instance      = Rsvp_Response::get_instance();
		$block_content = '<div>
			<div class="wp-block-gatherpress-dropdown__menu">
				<div class="wp-block-gatherpress-dropdown-item"><a href="#">Regular Item</a></div>
			</div>
		</div>';
		$result        = $instance->attach_dropdown_interactivity( $block_content );

		$this->assertStringNotContainsString(
			'data-wp-interactive',
			$result,
			'Non-RSVP items should not get interactive attributes'
		);
	}

	/**
	 * Tests avatar modification for regular RSVP comments.
	 *
	 * @since 1.0.0
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_for_gatherpress_rsvp_regular(): void {
		$instance   = Rsvp_Response::get_instance();
		$user_id    = $this->factory()->user->create();
		$comment_id = $this->factory()->comment->create(
			array(
				'user_id'      => $user_id,
				'comment_type' => 'gatherpress_rsvp',
			)
		);
		$comment    = get_comment( $comment_id );
		$args       = array( 'url' => '' );
		$result     = $instance->modify_avatar_for_gatherpress_rsvp( $args, $comment );

		$this->assertEquals(
			get_avatar_url( $user_id, array( 'default' => 'mystery' ) ),
			$result['url'],
			'Avatar URL should match user avatar URL for regular RSVP'
		);
	}

	/**
	 * Tests avatar modification for anonymous RSVP comments.
	 *
	 * @since 1.0.0
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_for_gatherpress_rsvp_anonymous(): void {
		$instance   = Rsvp_Response::get_instance();
		$user_id    = $this->factory()->user->create();
		$comment_id = $this->factory()->comment->create(
			array(
				'user_id'      => $user_id,
				'comment_type' => 'gatherpress_rsvp',
			)
		);

		update_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', '1' );

		$comment = get_comment( $comment_id );
		$args    = array( 'url' => '' );

		$result = $instance->modify_avatar_for_gatherpress_rsvp( $args, $comment );

		$this->assertEquals(
			get_avatar_url( 0, array( 'default' => 'mystery' ) ),
			$result['url'],
			'Avatar URL should use mystery avatar for anonymous RSVP'
		);
	}

	/**
	 * Tests avatar modification for admin viewing anonymous RSVP.
	 *
	 * @since 1.0.0
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_for_gatherpress_rsvp_admin_view(): void {
		$instance   = Rsvp_Response::get_instance();
		$admin_id   = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id    = $this->factory()->user->create();
		$user       = get_userdata( $user_id );
		$comment_id = $this->factory()->comment->create(
			array(
				'user_id'      => $user_id,
				'comment_type' => 'gatherpress_rsvp',
			)
		);

		update_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', '1' );
		wp_set_current_user( $admin_id );

		$comment = get_comment( $comment_id );
		$args    = array( 'url' => '' );
		$result  = $instance->modify_avatar_for_gatherpress_rsvp( $args, $comment );

		$this->assertEquals(
			get_avatar_url( $user->user_email, array( 'default' => 'mystery' ) ),
			$result['url'],
			'Admin should see actual user avatar for anonymous RSVP'
		);
	}

	/**
	 * Tests avatar modification for non-RSVP comments.
	 *
	 * @since 1.0.0
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_for_non_rsvp_comment(): void {
		$instance   = Rsvp_Response::get_instance();
		$comment_id = $this->factory()->comment->create(
			array(
				'comment_type' => 'comment',
			)
		);
		$comment    = get_comment( $comment_id );
		$args       = array( 'url' => 'original-url' );
		$result     = $instance->modify_avatar_for_gatherpress_rsvp( $args, $comment );

		$this->assertEquals(
			'original-url',
			$result['url'],
			'Avatar URL should remain unchanged for non-RSVP comments'
		);
	}

	/**
	 * Tests adding RSVP template to empty ancestor array.
	 *
	 * @since 1.0.0
	 * @covers ::add_rsvp_to_comment_ancestor
	 *
	 * @return void
	 */
	public function test_add_rsvp_to_comment_ancestor_empty(): void {
		$instance = Rsvp_Response::get_instance();
		$metadata = array(
			'name'     => 'core/comment-author-name',
			'ancestor' => array(),
		);
		$result   = $instance->add_rsvp_to_comment_ancestor( $metadata );

		$this->assertContains(
			'gatherpress/rsvp-template',
			$result['ancestor'],
			'RSVP template should be added to empty ancestor array'
		);
	}

	/**
	 * Tests adding RSVP template to existing ancestors.
	 *
	 * @since 1.0.0
	 * @covers ::add_rsvp_to_comment_ancestor
	 *
	 * @return void
	 */
	public function test_add_rsvp_to_comment_ancestor_existing(): void {
		$instance = Rsvp_Response::get_instance();
		$metadata = array(
			'name'     => 'core/comment-author-name',
			'ancestor' => array( 'core/comment-template' ),
		);
		$result   = $instance->add_rsvp_to_comment_ancestor( $metadata );

		$this->assertContains(
			'gatherpress/rsvp-template',
			$result['ancestor'],
			'RSVP template should be added to existing ancestors'
		);
		$this->assertContains(
			'core/comment-template',
			$result['ancestor'],
			'Existing ancestors should be preserved'
		);
	}

	/**
	 * Tests missing ancestor property.
	 *
	 * @since 1.0.0
	 * @covers ::add_rsvp_to_comment_ancestor
	 *
	 * @return void
	 */
	public function test_add_rsvp_to_comment_ancestor_missing_property(): void {
		$instance = Rsvp_Response::get_instance();
		$metadata = array(
			'name' => 'core/comment-author-name',
		);
		$result   = $instance->add_rsvp_to_comment_ancestor( $metadata );

		$this->assertArrayHasKey(
			'ancestor',
			$result,
			'Ancestor property should be added if missing'
		);
		$this->assertEquals(
			array( 'gatherpress/rsvp-template' ),
			$result['ancestor'],
			'RSVP template should be the only ancestor when property was missing'
		);
	}

	/**
	 * Tests non-comment author block metadata.
	 *
	 * @since 1.0.0
	 * @covers ::add_rsvp_to_comment_ancestor
	 *
	 * @return void
	 */
	public function test_add_rsvp_to_comment_ancestor_different_block(): void {
		$instance = Rsvp_Response::get_instance();
		$metadata = array(
			'name'     => 'core/paragraph',
			'ancestor' => array(),
		);
		$result   = $instance->add_rsvp_to_comment_ancestor( $metadata );

		$this->assertEmpty(
			$result['ancestor'],
			'Ancestor array should remain empty for non-comment author blocks'
		);
	}
}
