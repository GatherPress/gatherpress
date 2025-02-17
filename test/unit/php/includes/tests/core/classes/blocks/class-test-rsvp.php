<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Rsvp.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Rsvp;
use GatherPress\Core\Event;
use GatherPress\Core\Event_Setup;
use GatherPress\Tests\Base;

/**
 * Class Test_Rsvp.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Rsvp
 */
class Test_Rsvp extends Base {
	/**
	 * Tests the setup_hooks method.
	 *
	 * Verifies that the appropriate filters are registered during setup,
	 * ensuring the hooks are properly configured for the RSVP block.
	 *
	 * @since 1.0.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Rsvp::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Rsvp::BLOCK_NAME );
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
				'priority' => 10,
				'callback' => array( $instance, 'apply_rsvp_button_interactivity' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 11,
				'callback' => array( $instance, 'apply_guest_count_watch' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Tests the transform_block_content method for an active event.
	 *
	 * Verifies that the RSVP block content is correctly transformed
	 * based on the current user's RSVP status. Specifically, ensures:
	 * - The active RSVP status is marked and displayed correctly.
	 * - The appropriate interactive attributes are added to the block.
	 * - Content for other statuses is marked as not visible.
	 * - Past status content is excluded.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_active_event(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$user     = $this->mock->user( true )->get();
		$event    = new Event( $post_id );

		$event->rsvp->save( $user->ID, 'attending' );

		$block = array(
			'blockName'   => 'gatherpress/rsvp-v2',
			'attrs'       => array(
				'postId'                => $post_id,
				'selectedStatus'        => 'no_status',
				'serializedInnerBlocks' => wp_json_encode(
					array(
						'no_status'     => '<!-- wp:paragraph --><p>No status content</p><!-- /wp:paragraph -->',
						'attending'     => '<!-- wp:paragraph --><p>Attending content</p><!-- /wp:paragraph -->',
						'waiting_list'  => '<!-- wp:paragraph --><p>Waiting List content</p><!-- /wp:paragraph -->',
						'not_attending' => '<!-- wp:paragraph --><p>Not Attending content</p><!-- /wp:paragraph -->',
						'past'          => '<!-- wp:paragraph --><p>Past content</p><!-- /wp:paragraph -->',
					)
				),
			),
			'innerBlocks' => array(
				array(
					'blockName'    => 'core/paragraph',
					'attrs'        => array(),
					'innerBlocks'  => array(),
					'innerHTML'    => '<p>No status content</p>',
					'innerContent' => array( '<p>No status content</p>' ),
				),
			),
		);

		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d"></div>',
			$post_id
		);

		$result = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'The transform_block_content method should add the data-wp-interactive attribute to the RSVP block for interactivity.'
		);
		$this->assertStringContainsString(
			'data-rsvp-status="attending"',
			$result,
			'The transform_block_content method should correctly mark the active RSVP status as attending.'
		);
		$this->assertStringContainsString(
			'<div class="" data-rsvp-status="attending"><p>Attending content</p></div>',
			$result,
			'The transform_block_content method should display content for the attending status without a visibility class.'
		);
		$this->assertStringContainsString(
			'<div class="gatherpress--is-not-visible" data-rsvp-status="no_status"><p>No status content</p></div>',
			$result,
			'The transform_block_content method should mark content for the no_status status as not visible.'
		);
		$this->assertStringContainsString(
			'<div class="gatherpress--is-not-visible" data-rsvp-status="waiting_list"><p>Waiting List content</p></div>',
			$result,
			'The transform_block_content method should mark content for the waiting_list status as not visible.'
		);
		$this->assertStringContainsString(
			'<div class="gatherpress--is-not-visible" data-rsvp-status="not_attending"><p>Not Attending content</p></div>',
			$result,
			'The transform_block_content method should mark content for the not_attending status as not visible.'
		);
		$this->assertStringNotContainsString(
			'Past content',
			$result,
			'The transform_block_content method should exclude past status content from the output.'
		);
	}

	/**
	 * Tests the transform_block_content method for a past event.
	 *
	 * Verifies that the RSVP block is rendered correctly for a past event:
	 * - The content for the "past" status is included in the output.
	 * - The block does not include the data-wp-interactive attribute since the event is no longer active.
	 * - Content for other statuses, such as "attending", is excluded from the output.
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_past_event(): void {
		$instance       = Rsvp::get_instance();
		$datetime_start = wp_date( 'Y-m-d H:i:s', strtotime( '-2 day' ) );
		$datetime_end   = wp_date( 'Y-m-d H:i:s', strtotime( '-1 day' ) );
		$post           = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_meta' => array(
					'gatherpress_datetime' => wp_json_encode(
						array(
							'dateTimeStart' => '2024-01-22 18:00:00',
							'dateTimeEnd'   => '2024-01-22 20:00:00',
							'timezone'      => 'America/New_York',
						)
					),
				),
			)
		)->get();

		// Trigger to set datetimes.
		Event_Setup::get_instance()->set_datetimes( $post->ID );

		$block = array(
			'blockName' => 'gatherpress/rsvp-v2',
			'attrs'     => array(
				'postId'                => $post->ID,
				'serializedInnerBlocks' => wp_json_encode(
					array(
						'past'      => '<!-- wp:paragraph --><p>Past content</p><!-- /wp:paragraph -->',
						'attending' => '<!-- wp:paragraph --><p>Attending content</p><!-- /wp:paragraph -->',
					)
				),
			),
		);

		$block_content = sprintf( '<div class="wp-block-gatherpress-rsvp" data-post-id="%d"></div>', $post->ID );
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'<p>Past content</p>',
			$result,
			'The transform_block_content method should include the "past" status content for a past event.'
		);
		$this->assertStringNotContainsString(
			'data-wp-interactive',
			$result,
			'The transform_block_content method should not include the data-wp-interactive attribute for a past event.'
		);
		$this->assertStringNotContainsString(
			'Attending content',
			$result,
			'The transform_block_content method should exclude content for other statuses, such as "attending", when the event is in the past.'
		);
	}

	/**
	 * Tests the transform_block_content method when inner blocks are missing.
	 *
	 * Verifies that the RSVP block is rendered correctly even if the
	 * inner blocks are not provided. Specifically checks that:
	 * - The block includes the data-wp-interactive attribute.
	 * - The block includes the data-wp-context attribute.
	 * - The RSVP status is set to "no_status".
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_missing_inner_blocks(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		$block = array(
			'blockName' => 'gatherpress/rsvp-v2',
			'attrs'     => array( 'postId' => $post_id ),
		);

		$block_content = sprintf( '<div class="wp-block-gatherpress-rsvp" data-post-id="%d"></div>', $post_id );
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'The transform_block_content method should include the data-wp-interactive attribute for interactivity, even when inner blocks are missing.'
		);
		$this->assertStringContainsString(
			'data-wp-context',
			$result,
			'The transform_block_content method should include the data-wp-context attribute to provide block context, even when inner blocks are missing.'
		);
		$this->assertStringContainsString(
			'data-rsvp-status="no_status"',
			$result,
			'The transform_block_content method should set the RSVP status to "no_status" when inner blocks are missing.'
		);
	}

	/**
	 * Tests the transform_block_content method when no user is logged in.
	 *
	 * Verifies that the RSVP block is rendered correctly for a logged-out user:
	 * - The RSVP status is set to "no_status".
	 * - The user details attribute is empty (e.g., data-user-details="[]").
	 *
	 * @since 1.0.0
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_no_user(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		wp_set_current_user( 0 );

		$block = array(
			'blockName' => 'gatherpress/rsvp-v2',
			'attrs'     => array(
				'postId'                => $post_id,
				'serializedInnerBlocks' => wp_json_encode(
					array(
						'no_status' => '<!-- wp:paragraph --><p>No status content</p><!-- /wp:paragraph -->',
					),
				),
			),
		);

		$block_content = sprintf( '<div class="wp-block-gatherpress-rsvp" data-post-id="%d"></div>', $post_id );
		$result        = $instance->transform_block_content( $block_content, $block );

		$this->assertStringContainsString(
			'data-rsvp-status="no_status"',
			$result,
			'The transform_block_content method should set the RSVP status to "no_status" when no user is logged in.'
		);
		$this->assertStringContainsString(
			'data-user-details="[]"',
			$result,
			'The transform_block_content method should set an empty data-user-details attribute for logged-out users.'
		);
	}

	/**
	 * Tests the apply_rsvp_button_interactivity method for a button element.
	 *
	 * Ensures that the interactivity attributes are correctly added to
	 * the RSVP button, making it accessible and interactive.
	 *
	 * Specifically checks for:
	 * - The data-wp-interactive attribute with the correct value.
	 * - The data-wp-on--click attribute for the appropriate action.
	 * - The role="button" attribute for accessibility.
	 * - The tabindex="0" attribute for keyboard navigation.
	 *
	 * @since 1.0.0
	 * @covers ::apply_rsvp_button_interactivity
	 *
	 * @return void
	 */
	public function test_apply_rsvp_button_interactivity_for_button(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div class="gatherpress--update-rsvp"><button>RSVP</button></div>';
		$output   = $instance->apply_rsvp_button_interactivity( $input );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-wp-interactive attribute with the correct value.'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.updateRsvp"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-wp-on--click attribute with the updateRsvp action.'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output,
			'The apply_rsvp_button_interactivity method should add the role="button" attribute for accessibility.'
		);
		$this->assertStringContainsString(
			'tabindex="0"',
			$output,
			'The apply_rsvp_button_interactivity method should add the tabindex="0" attribute for keyboard navigation.'
		);
	}

	/**
	 * Tests the apply_rsvp_button_interactivity method for a link element.
	 *
	 * Ensures that the interactivity attributes are correctly added to
	 * the RSVP link, making it behave like a button while maintaining
	 * accessibility and interactivity.
	 *
	 * Specifically checks for:
	 * - The data-wp-interactive attribute with the correct value.
	 * - The data-wp-on--click attribute for the appropriate action.
	 * - The role="button" attribute for accessibility.
	 *
	 * @since 1.0.0
	 * @covers ::apply_rsvp_button_interactivity
	 *
	 * @return void
	 */
	public function test_apply_rsvp_button_interactivity_for_link(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div class="gatherpress--update-rsvp"><a href="#">RSVP</a></div>';
		$output   = $instance->apply_rsvp_button_interactivity( $input );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-wp-interactive attribute with the correct value to the RSVP link.'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.updateRsvp"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-wp-on--click attribute with the updateRsvp action to the RSVP link.'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output,
			'The apply_rsvp_button_interactivity method should add the role="button" attribute to the RSVP link for accessibility.'
		);
	}

	/**
	 * Tests the apply_rsvp_button_interactivity method with a status-specific class.
	 *
	 * Ensures that when the RSVP element includes a status-specific class
	 * (e.g., gatherpress--update-rsvp__attending), the correct data-set-status
	 * attribute is added to reflect the status.
	 *
	 * Specifically checks for:
	 * - The data-set-status attribute with the correct status value.
	 *
	 * @since 1.0.0
	 * @covers ::apply_rsvp_button_interactivity
	 *
	 * @return void
	 */
	public function test_apply_rsvp_button_interactivity_with_status(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div class="gatherpress--update-rsvp__attending"><button>Attending</button></div>';
		$output   = $instance->apply_rsvp_button_interactivity( $input );

		$this->assertStringContainsString(
			'data-set-status="attending"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-set-status attribute with the correct status value when a status-specific class is present.'
		);
	}

	/**
	 * Tests the apply_guest_count_watch method with no guests.
	 *
	 * Ensures that when the guest count is zero, the appropriate
	 * data-wp-watch attribute and visibility class are applied
	 * to the guest count display element.
	 *
	 * @since 1.0.0
	 * @covers ::apply_guest_count_watch
	 *
	 * @return void
	 */
	public function test_apply_guest_count_watch_with_no_guests(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div data-user-details=\'{"guests":0}\'><div class="wp-block-gatherpress-rsvp-guest-count-display">Guest Count</div></div>';
		$expected = '<div data-user-details=\'{"guests":0}\'><div data-wp-watch="callbacks.updateGuestCountDisplay" class="wp-block-gatherpress-rsvp-guest-count-display gatherpress--is-not-visible">Guest Count</div></div>';
		$result   = $instance->apply_guest_count_watch( $input );

		$this->assertSame(
			$expected,
			$result,
			'The apply_guest_count_watch method should correctly apply the data-wp-watch attribute and visibility class when the guest count is zero.'
		);
	}

	/**
	 * Tests the apply_guest_count_watch method with guests.
	 *
	 * Ensures that when the guest count is greater than zero, the appropriate
	 * data-wp-watch attribute is applied without adding the visibility class
	 * to the guest count display element.
	 *
	 * @since 1.0.0
	 * @covers ::apply_guest_count_watch
	 *
	 * @return void
	 */
	public function test_apply_guest_count_watch_with_guests(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div data-user-details=\'{"guests":2}\'><div class="wp-block-gatherpress-rsvp-guest-count-display">Guest Count</div></div>';
		$expected = '<div data-user-details=\'{"guests":2}\'><div data-wp-watch="callbacks.updateGuestCountDisplay" class="wp-block-gatherpress-rsvp-guest-count-display">Guest Count</div></div>';
		$result   = $instance->apply_guest_count_watch( $input );

		$this->assertSame(
			$expected,
			$result,
			'The apply_guest_count_watch method should correctly apply the data-wp-watch attribute without adding the gatherpress--is-not-visible class when the guest count is greater than zero.'
		);
	}

	/**
	 * Tests the apply_guest_count_watch method when user details are missing.
	 *
	 * Ensures that when there are no user details in the input,
	 * the data-wp-watch attribute and the visibility class are correctly applied
	 * to the guest count display element.
	 *
	 * @since 1.0.0
	 * @covers ::apply_guest_count_watch
	 *
	 * @return void
	 */
	public function test_apply_guest_count_watch_no_user_details(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div><div class="wp-block-gatherpress-rsvp-guest-count-display">Guest Count</div></div>';
		$expected = '<div><div data-wp-watch="callbacks.updateGuestCountDisplay" class="wp-block-gatherpress-rsvp-guest-count-display gatherpress--is-not-visible">Guest Count</div></div>';
		$result   = $instance->apply_guest_count_watch( $input );

		$this->assertSame(
			$expected,
			$result,
			'The apply_guest_count_watch method should correctly apply the data-wp-watch attribute and the gatherpress--is-not-visible class when no user details are present.'
		);
	}
}
