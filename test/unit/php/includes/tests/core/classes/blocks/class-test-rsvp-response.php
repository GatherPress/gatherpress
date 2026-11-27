<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Rsvp_Response.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Rsvp_Response;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PasswordHash;
use WP_Comment;
use WP_HTML_Tag_Processor;

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
	 * @since 0.33.0
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
				'name'     => 'get_avatar_comment_types',
				'priority' => 10,
				'callback' => array( $instance, 'add_rsvp_avatar_comment_type' ),
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
	 * @since 0.33.0
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
	 * Tests transform_block_content keeps a password-protected event's responses behind its password.
	 *
	 * @since 0.36.1
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_password_protected_event(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'     => 'gatherpress_event',
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		$block   = array(
			'blockName' => 'gatherpress/rsvp-response',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'contributor' ) ) );

		$this->assertSame(
			'',
			Rsvp_Response::get_instance()->transform_block_content(
				'<div class="wp-block-gatherpress-rsvp-response">Content</div>',
				$block
			),
			'A password-protected event should keep its responses from a viewer without the password.'
		);
	}

	/**
	 * Tests transform_block_content shows a password-protected event's responses once the viewer has the password.
	 *
	 * @since 0.36.1
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_password_protected_event_with_the_password(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'     => 'gatherpress_event',
				'post_status'   => 'publish',
				'post_password' => 'secret',
			)
		);
		$block   = array(
			'blockName' => 'gatherpress/rsvp-response',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		( new Rsvp( $post_id ) )->save( $this->factory()->user->create(), 'attending', 0, 0 );
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'contributor' ) ) );
		require_once ABSPATH . WPINC . '/class-phpass.php';

		// The cookie WordPress sets once a viewer enters the post password.
		$_COOKIE[ 'wp-postpass_' . COOKIEHASH ] = ( new PasswordHash( 8, true ) )->HashPassword( 'secret' );

		$result = Rsvp_Response::get_instance()->transform_block_content(
			'<div class="wp-block-gatherpress-rsvp-response">Content</div>',
			$block
		);

		unset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );

		$tag = new WP_HTML_Tag_Processor( $result );

		$tag->next_tag();

		$this->assertSame(
			1,
			json_decode( (string) $tag->get_attribute( 'data-counts' ), true )['attending'] ?? null,
			'A password-protected event should show its responses to a viewer with the password.'
		);
	}

	/**
	 * Tests transform_block_content returns empty string for non-event post type.
	 *
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * Tests trigger text with attendee count, when the block has no dropdown menu.
	 *
	 * The trigger updates must not depend on a menu being present.
	 *
	 * @since 0.33.0
	 * @covers ::attach_dropdown_interactivity
	 *
	 * @return void
	 */
	public function test_attach_dropdown_interactivity_trigger(): void {
		$instance      = Rsvp_Response::get_instance();
		$counts        = esc_attr( (string) wp_json_encode( array( 'attending' => 5 ) ) );
		$block_content = '<div data-counts="' . $counts . '">' .
			'<a class="wp-block-gatherpress-dropdown__trigger">%d Attending</a></div>';
		$result        = $instance->attach_dropdown_interactivity( $block_content );

		$this->assertStringContainsString(
			'5 Attending',
			$result,
			'Trigger should show the attending count in place of %d.'
		);
		$this->assertStringContainsString(
			'gatherpress--is-disabled',
			$result,
			'Trigger should be disabled until the block hydrates.'
		);
	}

	/**
	 * Tests RSVP status buttons.
	 *
	 * @since 0.33.0
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
	 * Data provider for trigger labels and the text they render.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function data_trigger_labels(): array {
		return array(
			'the count replaces %d'           => array( 'Attending (%d)', 3, 'Attending (3)' ),
			'a stray percent is left alone'   => array(
				'Attending (%d), 100% full',
				3,
				'Attending (3), 100% full',
			),
			'a label with no %d is unchanged' => array( '50% of seats taken', 3, '50% of seats taken' ),
			'only the first %d is replaced'   => array( '%d of %d', 3, '3 of %d' ),
			'a missing count renders as zero' => array( 'Attending (%d)', null, 'Attending (0)' ),
		);
	}

	/**
	 * Tests the trigger label's `%d` is replaced without reading it as a format string.
	 *
	 * The label is editable, so it can hold a `%` that `sprintf()` would
	 * reject as an unknown specifier and fatal the render (#2333).
	 *
	 * @since 0.36.0
	 * @covers ::attach_dropdown_interactivity
	 *
	 * @dataProvider data_trigger_labels
	 *
	 * @param string   $label    The trigger label as saved in the block.
	 * @param int|null $count    The attending count, or null for no counts attribute.
	 * @param string   $expected The label the trigger should render.
	 *
	 * @return void
	 */
	public function test_attach_dropdown_interactivity_trigger_label(
		string $label,
		?int $count,
		string $expected
	): void {
		$counts = '';

		if ( null !== $count ) {
			$counts = sprintf(
				' data-counts="%s"',
				esc_attr( (string) wp_json_encode( array( 'attending' => $count ) ) )
			);
		}

		// Real RSVP Response markup always includes the dropdown menu.
		$result = Rsvp_Response::get_instance()->attach_dropdown_interactivity(
			sprintf(
				'<div%s><a class="wp-block-gatherpress-dropdown__trigger">%s</a>' .
				'<div class="wp-block-gatherpress-dropdown__menu"></div></div>',
				$counts,
				esc_html( $label )
			)
		);
		$tag    = new WP_HTML_Tag_Processor( $result );

		$tag->next_tag( array( 'tag_name' => 'a' ) );
		$tag->next_token();

		$this->assertSame(
			$expected,
			$tag->get_modifiable_text(),
			'The trigger should render the label with the count swapped in.'
		);
	}

	/**
	 * Tests with missing data counts.
	 *
	 * @since 0.33.0
	 * @covers ::attach_dropdown_interactivity
	 *
	 * @return void
	 */
	public function test_attach_dropdown_interactivity_no_counts(): void {
		$instance      = Rsvp_Response::get_instance();
		$block_content = '<div><a class="wp-block-gatherpress-dropdown__trigger">%d Attending</a></div>';
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
	 * @since 0.33.0
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
	 * Tests that the RSVP comment type is added to the avatar comment types.
	 *
	 * @since TBD
	 * @covers ::add_rsvp_avatar_comment_type
	 *
	 * @return void
	 */
	public function test_add_rsvp_avatar_comment_type(): void {
		$instance = Rsvp_Response::get_instance();

		$this->assertSame(
			array( 'comment', 'gatherpress_rsvp' ),
			$instance->add_rsvp_avatar_comment_type( array( 'comment' ) ),
			'The RSVP comment type should be appended.'
		);
		$this->assertSame(
			array( 'comment', 'gatherpress_rsvp' ),
			$instance->add_rsvp_avatar_comment_type( array( 'comment', 'gatherpress_rsvp' ) ),
			'The RSVP comment type should not be added twice.'
		);
		$this->assertTrue( is_avatar_comment_type( 'gatherpress_rsvp' ), 'Core should allow avatars for RSVPs.' );
	}

	/**
	 * Tests that a regular RSVP gets the responder's own avatar from core.
	 *
	 * @since 0.33.0
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_for_gatherpress_rsvp_regular(): void {
		list( $user_id, $comment_id ) = $this->create_rsvp_comment();

		$this->assertSame(
			get_avatar_url( $user_id ),
			get_avatar_url( get_comment( $comment_id ) ),
			'Avatar URL should match user avatar URL for regular RSVP'
		);
	}

	/**
	 * Tests that an anonymous RSVP gets the default avatar for viewers who cannot manage RSVPs.
	 *
	 * @since 0.33.0
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_for_gatherpress_rsvp_anonymous(): void {
		list( , $comment_id ) = $this->create_rsvp_comment( true );

		$this->assertSame(
			get_avatar_url( '', array( 'force_default' => true ) ),
			get_avatar_url( get_comment( $comment_id ) ),
			'Avatar URL should be the default avatar for anonymous RSVP'
		);
	}

	/**
	 * Tests that a viewer who can manage RSVPs sees an anonymous responder's avatar.
	 *
	 * @since 0.33.0
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_for_gatherpress_rsvp_admin_view(): void {
		list( $user_id, $comment_id ) = $this->create_rsvp_comment( true );

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame(
			get_avatar_url( $user_id ),
			get_avatar_url( get_comment( $comment_id ) ),
			'Admin should see actual user avatar for anonymous RSVP'
		);
	}

	/**
	 * Tests avatar modification for non-RSVP comments.
	 *
	 * @since 0.33.0
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
	 * Tests that the masked avatar falls back to the default size when none is passed.
	 *
	 * @since TBD
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_for_gatherpress_rsvp_anonymous_without_size(): void {
		list( , $comment_id ) = $this->create_rsvp_comment( true );

		$result = Rsvp_Response::get_instance()->modify_avatar_for_gatherpress_rsvp(
			array(
				'url'          => 'https://example.test/real.png',
				'found_avatar' => true,
			),
			get_comment( $comment_id )
		);

		$this->assertSame( get_avatar_url( '', array( 'force_default' => true ) ), $result['url'] );
		$this->assertFalse( $result['found_avatar'] );
	}

	/**
	 * Tests that an avatar from another plugin is kept for a regular RSVP and masked for an anonymous one.
	 *
	 * @since TBD
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_keeps_third_party_avatar(): void {
		$third_party = static function ( array $args, $id_or_email ): array {
			if ( $id_or_email instanceof WP_Comment ) {
				$args['url']          = 'https://example.test/local-avatar.png';
				$args['found_avatar'] = true;
			}

			return $args;
		};

		add_filter( 'pre_get_avatar_data', $third_party, 10, 2 );

		list( , $regular_id )   = $this->create_rsvp_comment();
		list( , $anonymous_id ) = $this->create_rsvp_comment( true );

		$regular   = get_avatar_url( get_comment( $regular_id ) );
		$anonymous = get_avatar_url( get_comment( $anonymous_id ) );

		remove_filter( 'pre_get_avatar_data', $third_party, 10 );

		$this->assertSame(
			'https://example.test/local-avatar.png',
			$regular,
			'An avatar found by another plugin should be kept.'
		);
		$this->assertSame(
			get_avatar_url( '', array( 'force_default' => true ) ),
			$anonymous,
			'An anonymous responder should get the default avatar even when another plugin found one.'
		);
	}

	/**
	 * Tests that an RSVP with no account and no email gets the site's default avatar.
	 *
	 * @since TBD
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_for_accountless_rsvp_uses_site_default(): void {
		update_option( 'avatar_default', 'identicon' );

		$comment_id = $this->factory()->comment->create(
			array(
				'user_id'              => 0,
				'comment_author_email' => '',
				'comment_type'         => 'gatherpress_rsvp',
			)
		);
		$url        = (string) get_avatar_url( get_comment( $comment_id ) );

		delete_option( 'avatar_default' );

		$this->assertStringContainsString( 'd=identicon', $url, 'The site default avatar should be used.' );
		$this->assertStringNotContainsString( 'd=mm', $url, 'The mystery avatar should not be forced.' );
	}

	/**
	 * Tests adding RSVP template to empty ancestor array.
	 *
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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
	 * @since 0.33.0
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

	/**
	 * Tests has-responses section becomes visible when attendees exist.
	 *
	 * @since 0.34.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_show_has_responses_with_attendees(): void {
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
			'<div class="gatherpress-rsvp-response--has-responses gatherpress--is-hidden">Responses</div>' .
			'</div>';
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'gatherpress--is-visible',
			$result,
			'Has-responses section should have visible class when attendees exist.'
		);
		$this->assertStringNotContainsString(
			'gatherpress--is-hidden',
			$result,
			'Has-responses section should not have hidden class when attendees exist.'
		);
	}

	/**
	 * Tests has-responses section becomes hidden when no attendees exist.
	 *
	 * @since 0.34.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_hide_has_responses_without_attendees(): void {
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
		$block_content = '<div class="wp-block-gatherpress-rsvp-response">' .
			'<div class="gatherpress-rsvp-response--has-responses gatherpress--is-visible">Responses</div>' .
			'</div>';
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'gatherpress--is-hidden',
			$result,
			'Has-responses section should have hidden class when no attendees exist.'
		);
		$this->assertStringNotContainsString(
			'gatherpress--is-visible',
			$result,
			'Has-responses section should not have visible class when no attendees exist.'
		);
	}

	/**
	 * Tests extra_attr with a style attribute is preserved and other attributes stripped.
	 *
	 * @since 0.34.0
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_extra_attr_with_style(): void {
		$instance   = Rsvp_Response::get_instance();
		$user_id    = $this->factory()->user->create();
		$comment_id = $this->factory()->comment->create(
			array(
				'user_id'      => $user_id,
				'comment_type' => 'gatherpress_rsvp',
			)
		);
		$comment    = get_comment( $comment_id );
		$args       = array(
			'url'        => '',
			'extra_attr' => 'style="border-radius:50%" data-foo="bar"',
		);
		$result     = $instance->modify_avatar_for_gatherpress_rsvp( $args, $comment );

		$this->assertSame(
			'style="border-radius:50%"',
			$result['extra_attr'],
			'Only the style attribute should be preserved in extra_attr when a style is present.'
		);
	}

	/**
	 * Tests extra_attr without a style attribute is cleared entirely.
	 *
	 * @since 0.34.0
	 * @covers ::modify_avatar_for_gatherpress_rsvp
	 *
	 * @return void
	 */
	public function test_modify_avatar_extra_attr_without_style(): void {
		$instance   = Rsvp_Response::get_instance();
		$user_id    = $this->factory()->user->create();
		$comment_id = $this->factory()->comment->create(
			array(
				'user_id'      => $user_id,
				'comment_type' => 'gatherpress_rsvp',
			)
		);
		$comment    = get_comment( $comment_id );
		$args       = array(
			'url'        => '',
			'extra_attr' => 'data-foo="bar"',
		);
		$result     = $instance->modify_avatar_for_gatherpress_rsvp( $args, $comment );

		$this->assertSame(
			'',
			$result['extra_attr'],
			'extra_attr should be cleared to an empty string when no style attribute is present.'
		);
	}

	/**
	 * Tests that transform_block_content returns empty string when per-event RSVP is disabled.
	 *
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_rsvp_disabled_per_event(): void {
		$instance = Rsvp_Response::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type'   => 'gatherpress_event',
				'post_status' => 'publish',
			)
		);

		Settings::get_instance()->set( 'rsvp_mode', 'per_event_enabled' );
		update_post_meta( $post_id, 'gatherpress_enable_rsvp', 0 );

		$block_content = '<div class="wp-block-gatherpress-rsvp-response">Content</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-response',
			'attrs'     => array( 'postId' => $post_id ),
		);

		$result = $instance->transform_block_content( $block_content, $block );

		$this->assertSame( '', $result, 'Should return empty string when per-event RSVP is disabled.' );

		delete_post_meta( $post_id, 'gatherpress_enable_rsvp' );
		Settings::get_instance()->set( 'rsvp_mode', 'enabled' );
	}

	/**
	 * Creates an RSVP comment for a new user.
	 *
	 * @since TBD
	 *
	 * @param bool $anonymous Whether the responder asked to stay anonymous.
	 *
	 * @return array{0: int, 1: int} The user ID and the comment ID.
	 */
	private function create_rsvp_comment( bool $anonymous = false ): array {
		$user_id    = $this->factory()->user->create();
		$comment_id = $this->factory()->comment->create(
			array(
				'user_id'      => $user_id,
				'comment_type' => 'gatherpress_rsvp',
			)
		);

		if ( $anonymous ) {
			update_comment_meta( $comment_id, 'gatherpress_rsvp_anonymous', '1' );
		}

		return array( $user_id, $comment_id );
	}
}
