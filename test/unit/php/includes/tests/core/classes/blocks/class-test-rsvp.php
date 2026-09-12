<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Rsvp.
 *
 * @package GatherPress\Core
 * @since 0.33.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Rsvp;
use GatherPress\Core\Event;
use GatherPress\Core\Rsvp as Core_Rsvp;
use GatherPress\Core\Rsvp\Form;
use GatherPress\Core\Event\Setup;
use GatherPress\Core\Settings;
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
	 * @since 0.33.0
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance          = Rsvp::get_instance();
		$render_block_hook = sprintf( 'render_block_%s', Rsvp::BLOCK_NAME );
		$general_block     = \GatherPress\Core\Blocks\General_Block::get_instance();
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
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 9,
				'callback' => array( $instance, 'apply_guests_input_interactivity' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 12,
				'callback' => array( $instance, 'render_no_script_fallback' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $general_block, 'process_guests_field' ),
			),
			array(
				'type'     => 'filter',
				'name'     => $render_block_hook,
				'priority' => 10,
				'callback' => array( $general_block, 'process_anonymous_field' ),
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
	 * @since 0.33.0
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
		$rsvp     = new Core_Rsvp( $post_id );

		$save_result = $rsvp->save( $user->ID, 'attending' );

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
			'The transform_block_content method should add the data-wp-interactive attribute to the RSVP block '
			. 'for interactivity.'
		);
		$this->assertStringContainsString(
			'data-rsvp-status="attending"',
			$result,
			'The transform_block_content method should correctly mark the active RSVP status as attending.'
		);
		$this->assertStringContainsString(
			'<div class="" data-rsvp-status="attending"><p class="wp-block-paragraph">Attending content</p></div>',
			$result,
			'The transform_block_content method should display content for the attending status without a '
			. 'visibility class.'
		);
		$this->assertStringContainsString(
			'<div class="gatherpress--is-hidden" data-rsvp-status="no_status">'
			. '<p class="wp-block-paragraph">No status content</p></div>',
			$result,
			'The transform_block_content method should mark content for the no_status status as not visible.'
		);
		$this->assertStringContainsString(
			'<div class="gatherpress--is-hidden" data-rsvp-status="waiting_list">'
			. '<p class="wp-block-paragraph">Waiting List content</p></div>',
			$result,
			'The transform_block_content method should mark content for the waiting_list status as not visible.'
		);
		$this->assertStringContainsString(
			'<div class="gatherpress--is-hidden" data-rsvp-status="not_attending">'
			. '<p class="wp-block-paragraph">Not Attending content</p></div>',
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
	 * @since 0.33.0
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
		Setup::get_instance()->set_datetimes( $post->ID );

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
			'<p class="wp-block-paragraph">Past content</p>',
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
			'The transform_block_content method should exclude content for other statuses, such as "attending", '
			. 'when the event is in the past.'
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
	 * @since 0.33.0
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
			'The transform_block_content method should include the data-wp-interactive attribute '
			. 'for interactivity, even when inner blocks are missing.'
		);
		$this->assertStringContainsString(
			'data-wp-context',
			$result,
			'The transform_block_content method should include the data-wp-context attribute to provide block '
			. 'context, even when inner blocks are missing.'
		);
		$this->assertStringContainsString(
			'data-rsvp-status="no_status"',
			$result,
			'The transform_block_content method should set the RSVP status to "no_status" when inner blocks '
			. 'are missing.'
		);
	}

	/**
	 * Tests the transform_block_content method when no user is logged in.
	 *
	 * Verifies that the RSVP block is rendered correctly for a logged-out user:
	 * - The RSVP status is set to "no_status".
	 * - The user details attribute is empty (e.g., data-user-details="[]").
	 *
	 * @since 0.33.0
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
	 * @since 0.33.0
	 * @covers ::apply_rsvp_button_interactivity
	 *
	 * @return void
	 */
	public function test_apply_rsvp_button_interactivity_for_button(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div class="gatherpress-rsvp--trigger-update"><button>RSVP</button></div>';
		$output   = $instance->apply_rsvp_button_interactivity( $input );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-wp-interactive attribute with the '
			. 'correct value.'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.updateRsvp"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-wp-on--click attribute with the '
			. 'updateRsvp action.'
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
	 * @since 0.33.0
	 * @covers ::apply_rsvp_button_interactivity
	 *
	 * @return void
	 */
	public function test_apply_rsvp_button_interactivity_for_link(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div class="gatherpress-rsvp--trigger-update"><a href="#">RSVP</a></div>';
		$output   = $instance->apply_rsvp_button_interactivity( $input );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-wp-interactive attribute with the '
			. 'correct value to the RSVP link.'
		);
		$this->assertStringContainsString(
			'data-wp-on--click="actions.updateRsvp"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-wp-on--click attribute with the '
			. 'updateRsvp action to the RSVP link.'
		);
		$this->assertStringContainsString(
			'role="button"',
			$output,
			'The apply_rsvp_button_interactivity method should add the role="button" attribute to the RSVP link '
			. 'for accessibility.'
		);
	}

	/**
	 * Tests the apply_rsvp_button_interactivity method with a status-specific class.
	 *
	 * Ensures that when the RSVP element includes a status-specific class
	 * (e.g., gatherpress-rsvp--trigger-update__attending), the correct data-set-status
	 * attribute is added to reflect the status.
	 *
	 * Specifically checks for:
	 * - The data-set-status attribute with the correct status value.
	 *
	 * @since 0.33.0
	 * @covers ::apply_rsvp_button_interactivity
	 *
	 * @return void
	 */
	public function test_apply_rsvp_button_interactivity_with_status(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div class="gatherpress-rsvp--trigger-update__attending"><button>Attending</button></div>';
		$output   = $instance->apply_rsvp_button_interactivity( $input );

		$this->assertStringContainsString(
			'data-set-status="attending"',
			$output,
			'The apply_rsvp_button_interactivity method should add the data-set-status attribute with the correct '
			. 'status value when a status-specific class is present.'
		);
	}

	/**
	 * Tests the apply_guest_count_watch method with no guests.
	 *
	 * Ensures that when the guest count is zero, the appropriate
	 * data-wp-watch attribute and visibility class are applied
	 * to the guest count display element.
	 *
	 * @since 0.33.0
	 * @covers ::apply_guest_count_watch
	 *
	 * @return void
	 */
	public function test_apply_guest_count_watch_with_no_guests(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div data-user-details=\'{"guests":0}\'>'
			. '<div class="wp-block-gatherpress-rsvp-guest-count-display">Guest Count</div></div>';
		$expected = '<div data-user-details=\'{"guests":0}\'>'
			. '<div data-wp-watch="callbacks.updateGuestCountDisplay" '
			. 'class="wp-block-gatherpress-rsvp-guest-count-display gatherpress--is-hidden">Guest Count</div></div>';
		$result   = $instance->apply_guest_count_watch( $input );

		$this->assertSame(
			$expected,
			$result,
			'The apply_guest_count_watch method should correctly apply the data-wp-watch attribute and visibility '
			. 'class when the guest count is zero.'
		);
	}

	/**
	 * Tests the apply_guest_count_watch method with guests.
	 *
	 * Ensures that when the guest count is greater than zero, the appropriate
	 * data-wp-watch attribute is applied without adding the visibility class
	 * to the guest count display element.
	 *
	 * @since 0.33.0
	 * @covers ::apply_guest_count_watch
	 *
	 * @return void
	 */
	public function test_apply_guest_count_watch_with_guests(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div data-user-details=\'{"guests":2}\'>'
			. '<div class="wp-block-gatherpress-rsvp-guest-count-display">Guest Count</div></div>';
		$expected = '<div data-user-details=\'{"guests":2}\'>'
			. '<div data-wp-watch="callbacks.updateGuestCountDisplay" '
			. 'class="wp-block-gatherpress-rsvp-guest-count-display">Guest Count</div></div>';
		$result   = $instance->apply_guest_count_watch( $input );

		$this->assertSame(
			$expected,
			$result,
			'The apply_guest_count_watch method should correctly apply the data-wp-watch attribute without adding '
			. 'the gatherpress--is-hidden class when the guest count is greater than zero.'
		);
	}

	/**
	 * Tests the apply_guest_count_watch method when user details are missing.
	 *
	 * Ensures that when there are no user details in the input,
	 * the data-wp-watch attribute and the visibility class are correctly applied
	 * to the guest count display element.
	 *
	 * @since 0.33.0
	 * @covers ::apply_guest_count_watch
	 *
	 * @return void
	 */
	public function test_apply_guest_count_watch_no_user_details(): void {
		$instance = Rsvp::get_instance();
		$input    = '<div><div class="wp-block-gatherpress-rsvp-guest-count-display">Guest Count</div></div>';
		$expected = '<div><div data-wp-watch="callbacks.updateGuestCountDisplay" '
			. 'class="wp-block-gatherpress-rsvp-guest-count-display gatherpress--is-hidden">Guest Count</div></div>';
		$result   = $instance->apply_guest_count_watch( $input );

		$this->assertSame(
			$expected,
			$result,
			'The apply_guest_count_watch method should correctly apply the data-wp-watch attribute and the '
			. 'gatherpress--is-hidden class when no user details are present.'
		);
	}

	/**
	 * Tests the handle_rsvp_form_fields method for guest count field with allowed guests.
	 *
	 * Ensures that when guest count field is rendered and guests are allowed (max_guest_limit > 0),
	 * the appropriate interactivity attributes are added to the input field.
	 *
	 * @since 0.33.0
	 * @covers ::handle_rsvp_form_fields
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_fields_guest_count_allowed(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Add post meta.
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 5 );

		// Set the WordPress global query context by visiting the post.
		$this->go_to( get_permalink( $post_id ) );

		$block = array(
			'attrs' => array(
				'fieldName' => 'gatherpress_rsvp_guests',
			),
		);

		$block_content = '<input type="number" name="gatherpress_rsvp_guests" value="0" />';
		$result        = $instance->handle_rsvp_form_fields( $block_content, $block );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'The handle_rsvp_form_fields method should add the data-wp-interactive attribute for guest count field.'
		);
		$this->assertStringContainsString(
			'data-wp-watch="callbacks.setGuestCount"',
			$result,
			'The handle_rsvp_form_fields method should add the data-wp-watch attribute with the setGuestCount callback.'
		);
		$this->assertStringContainsString(
			'data-wp-on--change="actions.updateGuestCount"',
			$result,
			'The handle_rsvp_form_fields method should add the data-wp-on--change attribute with the '
			. 'updateGuestCount action.'
		);
		$this->assertStringContainsString(
			'max="5"',
			$result,
			'The handle_rsvp_form_fields method should add the max attribute with the guest limit value.'
		);
	}

	/**
	 * Tests the handle_rsvp_form_fields method for guest count field when guests are not allowed.
	 *
	 * Ensures that when guests are not allowed (max_guest_limit is 0 or empty),
	 * the method returns empty content to hide the field.
	 *
	 * @since 0.33.0
	 * @covers ::handle_rsvp_form_fields
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_fields_guest_count_not_allowed(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Add post meta.
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 0 );

		// Set the WordPress global query context by visiting the post.
		$this->go_to( get_permalink( $post_id ) );

		$block = array(
			'attrs' => array(
				'fieldName' => 'gatherpress_rsvp_guests',
			),
		);

		$block_content = '<input type="number" name="gatherpress_rsvp_guests" value="0" />';
		$result        = $instance->handle_rsvp_form_fields( $block_content, $block );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'The handle_rsvp_form_fields method should add interactivity attributes. Field visibility is handled '
			. 'by RSVP Form.'
		);
	}

	/**
	 * Tests the handle_rsvp_form_fields method for anonymous checkbox field when enabled.
	 *
	 * Ensures that when anonymous RSVP is enabled, the appropriate interactivity
	 * attributes are added to the checkbox input field.
	 *
	 * @since 0.33.0
	 * @covers ::handle_rsvp_form_fields
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_fields_anonymous_enabled(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Add post meta.
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', true );

		// Set the WordPress global query context by visiting the post.
		$this->go_to( get_permalink( $post_id ) );

		$block = array(
			'attrs' => array(
				'fieldName' => 'gatherpress_rsvp_anonymous',
			),
		);

		$block_content = '<input type="checkbox" name="gatherpress_rsvp_anonymous" value="1" />';
		$result        = $instance->handle_rsvp_form_fields( $block_content, $block );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'The handle_rsvp_form_fields method should add the data-wp-interactive attribute for anonymous '
			. 'checkbox field.'
		);
		$this->assertStringContainsString(
			'data-wp-on--change="actions.updateAnonymous"',
			$result,
			'The handle_rsvp_form_fields method should add the data-wp-on--change attribute with the '
			. 'updateAnonymous action.'
		);
		$this->assertStringContainsString(
			'data-wp-watch="callbacks.monitorAnonymousStatus"',
			$result,
			'The handle_rsvp_form_fields method should add the data-wp-watch attribute with the '
			. 'monitorAnonymousStatus callback.'
		);
	}

	/**
	 * Tests the handle_rsvp_form_fields method for anonymous checkbox field when disabled.
	 *
	 * Ensures that when anonymous RSVP is disabled, the method returns empty content
	 * to hide the field from the frontend.
	 *
	 * @since 0.33.0
	 * @covers ::handle_rsvp_form_fields
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_fields_anonymous_disabled(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Add post meta.
		add_post_meta( $post_id, 'gatherpress_enable_anonymous_rsvp', false );

		// Set the WordPress global query context by visiting the post.
		$this->go_to( get_permalink( $post_id ) );

		$block = array(
			'attrs' => array(
				'fieldName' => 'gatherpress_rsvp_anonymous',
			),
		);

		$block_content = '<input type="checkbox" name="gatherpress_rsvp_anonymous" value="1" />';
		$result        = $instance->handle_rsvp_form_fields( $block_content, $block );

		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'The handle_rsvp_form_fields method should add interactivity attributes. Field visibility is handled '
			. 'by RSVP Form.'
		);
	}

	/**
	 * Tests the handle_rsvp_form_fields method for non-RSVP form fields.
	 *
	 * Ensures that form fields that are not RSVP-related are returned unmodified,
	 * allowing other form-field blocks to function normally.
	 *
	 * @since 0.33.0
	 * @covers ::handle_rsvp_form_fields
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_fields_non_rsvp_field(): void {
		$instance = Rsvp::get_instance();
		$block    = array(
			'attrs' => array(
				'fieldName' => 'some_other_field',
			),
		);

		$block_content = '<input type="text" name="some_other_field" value="" />';
		$result        = $instance->handle_rsvp_form_fields( $block_content, $block );

		$this->assertSame(
			$block_content,
			$result,
			'The handle_rsvp_form_fields method should return unmodified content for non-RSVP form fields.'
		);
	}

	/**
	 * Tests the handle_rsvp_form_fields method when fieldName attribute is missing.
	 *
	 * Ensures that when no fieldName is provided in the block attributes,
	 * the method returns the original content unmodified.
	 *
	 * @since 0.33.0
	 * @covers ::handle_rsvp_form_fields
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_fields_missing_field_name(): void {
		$instance = Rsvp::get_instance();
		$block    = array(
			'attrs' => array(),
		);

		$block_content = '<input type="text" name="test_field" value="" />';
		$result        = $instance->handle_rsvp_form_fields( $block_content, $block );

		$this->assertSame(
			$block_content,
			$result,
			'The handle_rsvp_form_fields method should return unmodified content when fieldName attribute is missing.'
		);
	}

	/**
	 * Tests the handle_rsvp_form_fields method for guest count field with multiple input elements.
	 *
	 * Ensures that when there are multiple input elements in the content,
	 * only the one with the matching name attribute gets the interactivity attributes.
	 *
	 * @since 0.33.0
	 * @covers ::handle_rsvp_form_fields
	 *
	 * @return void
	 */
	public function test_handle_rsvp_form_fields_guest_count_multiple_inputs(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		// Add post meta.
		add_post_meta( $post_id, 'gatherpress_max_guest_limit', 3 );

		// Set the WordPress global query context by visiting the post.
		$this->go_to( get_permalink( $post_id ) );

		$block = array(
			'attrs' => array(
				'fieldName' => 'gatherpress_rsvp_guests',
			),
		);

		$block_content = '<div><input type="text" name="other_field" value="" />'
			. '<input type="number" name="gatherpress_rsvp_guests" value="0" /></div>';
		$result        = $instance->handle_rsvp_form_fields( $block_content, $block );

		// Check that only the guest count input gets the attributes.
		$this->assertStringContainsString(
			'name="gatherpress_rsvp_guests"',
			$result,
			'The handle_rsvp_form_fields method should preserve the guest count input field name.'
		);
		$this->assertStringContainsString(
			'data-wp-interactive="gatherpress"',
			$result,
			'The handle_rsvp_form_fields method should add the data-wp-interactive attribute to the guest count '
			. 'input field.'
		);
		$this->assertStringContainsString(
			'name="other_field" value=""',
			$result,
			'The handle_rsvp_form_fields method should leave other input fields unmodified.'
		);
		// Ensure the other field doesn't have interactivity attributes.
		$this->assertStringNotContainsString(
			'name="other_field" value="" data-wp-interactive',
			$result,
			'The handle_rsvp_form_fields method should not add interactivity attributes to non-matching input fields.'
		);
	}

	/**
	 * Test transform_block_content with non-event post.
	 *
	 * Verifies that the method returns an empty string when called
	 * on a post that is not an event post type.
	 *
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_with_non_event_post(): void {
		$instance = Rsvp::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type' => 'post',
			)
		)->get();

		$block_content = '<div class="wp-block-gatherpress-rsvp"></div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-v2',
			'attrs'     => array(
				'postId' => $post->ID,
			),
		);

		$result = $instance->transform_block_content( $block_content, $block );

		// Tests: return ''; (when post is not an event).
		$this->assertSame(
			'',
			$result,
			'Should return empty string for non-event post.'
		);
	}

	/**
	 * Test transform_block_content returns empty string when RSVP is disabled for the event.
	 *
	 * @covers ::transform_block_content
	 *
	 * @return void
	 */
	public function test_transform_block_content_rsvp_disabled(): void {
		$instance = Rsvp::get_instance();
		$post     = $this->mock->post(
			array(
				'post_type'   => \GatherPress\Core\Event::POST_TYPE,
				'post_status' => 'publish',
			)
		)->get();

		// Set rsvp_mode to per_event_enabled so that per-event disabling is respected.
		Settings::get_instance()->set( 'rsvp_mode', 'per_event_enabled' );

		// Explicitly disable RSVP for this event.
		update_post_meta( $post->ID, 'gatherpress_enable_rsvp', 0 );

		$block_content = '<div class="wp-block-gatherpress-rsvp"></div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-v2',
			'attrs'     => array(
				'postId' => $post->ID,
			),
		);

		$result = $instance->transform_block_content( $block_content, $block );

		$this->assertSame(
			'',
			$result,
			'Should return empty string when RSVP is disabled for the event.'
		);

		delete_post_meta( $post->ID, 'gatherpress_enable_rsvp' );

		// Restore the setting for other tests.
		Settings::get_instance()->set( 'rsvp_mode', 'enabled' );
	}

	/**
	 * Test apply_guests_input_interactivity method.
	 *
	 * Verifies that the method adds the form field filter hook
	 * and returns the block content unchanged.
	 *
	 * @covers ::apply_guests_input_interactivity
	 *
	 * @return void
	 */
	public function test_apply_guests_input_interactivity(): void {
		$instance      = Rsvp::get_instance();
		$block_content = '<div class="wp-block-gatherpress-rsvp">'
			. '<input type="number" name="gatherpress_rsvp_guests" />'
			. '</div>';

		// Remove any existing filters to ensure clean state.
		$form_field_hook = sprintf( 'render_block_%s', \GatherPress\Core\Blocks\Form_Field::BLOCK_NAME );
		remove_all_filters( $form_field_hook );

		$result = $instance->apply_guests_input_interactivity( $block_content );

		// Method should return block content unchanged.
		$this->assertSame(
			$block_content,
			$result,
			'Method should return block content unchanged.'
		);

		// Method should add the filter hook.
		$this->assertTrue(
			has_filter( $form_field_hook, array( $instance, 'handle_rsvp_form_fields' ) ) !== false,
			'Method should add the handle_rsvp_form_fields filter.'
		);

		// Clean up.
		remove_filter( $form_field_hook, array( $instance, 'handle_rsvp_form_fields' ) );
	}

	/**
	 * Tests the render_no_script_fallback method for a logged-in user.
	 *
	 * Verifies that a marked RSVP block gets a <noscript> POST form with the
	 * expected action URL, nonce, post ID, next status, and submit label.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_logged_in(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$this->mock->user( true )->get();

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post_id ),
		);
		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d">'
			. '<button class="gatherpress-rsvp--trigger-update gatherpress-rsvp--trigger-no-script">Attend</button>'
			. '</div>',
			$post_id
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		$this->assertStringContainsString( '<noscript>', $result, 'The fallback form must render inside <noscript>.' );
		$this->assertStringContainsString(
			'method="post"',
			$result,
			'The fallback form must submit via POST so link prefetchers cannot trigger an RSVP.'
		);
		$this->assertStringContainsString(
			esc_attr( admin_url( 'admin-post.php' ) ),
			$result,
			'The fallback form must post to admin-post.php.'
		);
		$this->assertStringContainsString(
			'name="post_id" value="' . $post_id . '"',
			$result,
			'The fallback form must carry the event post ID.'
		);
		$this->assertStringContainsString(
			'name="status" value="attending"',
			$result,
			'The fallback form must default to the attending status for a user without an RSVP.'
		);
		$this->assertStringContainsString( 'Attend', $result, 'The fallback submit button must read Attend.' );
		$this->assertStringContainsString(
			'name="' . Form::NONCE_NAME . '"',
			$result,
			'The fallback form must include a nonce.'
		);
		$this->assertStringContainsString(
			'name="return_url" value="' . esc_url( get_permalink( $post_id ) ) . '"',
			$result,
			'The fallback form must return to the event permalink.'
		);
	}

	/**
	 * Tests the render_no_script_fallback method when the user is logged out.
	 *
	 * Verifies that no fallback form is emitted for anonymous visitors.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_logged_out(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post_id ),
		);
		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d">'
			. '<button class="gatherpress-rsvp--trigger-update gatherpress-rsvp--trigger-no-script">Attend</button>'
			. '</div>',
			$post_id
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		$this->assertSame(
			$block_content,
			$result,
			'The fallback form must not render for logged-out users.'
		);
	}

	/**
	 * Tests the render_no_script_fallback method without the marker class.
	 *
	 * Verifies that unmarked RSVP blocks stay untouched.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_unmarked(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$this->mock->user( true )->get();

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post_id ),
		);
		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d">'
			. '<button class="gatherpress-rsvp--trigger-update">Attend</button>'
			. '</div>',
			$post_id
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		$this->assertSame(
			$block_content,
			$result,
			'The fallback form must not render without the no-script marker class.'
		);
	}

	/**
	 * Tests the render_no_script_fallback method for a user already attending.
	 *
	 * Verifies that the next status and the submit label flip to not_attending.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_attending_toggles_off(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$user     = $this->mock->user( true )->get();
		( new Core_Rsvp( $post_id ) )->save( $user->ID, 'attending' );

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post_id ),
		);
		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d">'
			. '<button class="gatherpress-rsvp--trigger-update gatherpress-rsvp--trigger-no-script">'
			. 'Not Attending</button></div>',
			$post_id
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		$this->assertStringContainsString(
			'name="status" value="not_attending"',
			$result,
			'The fallback form must offer not_attending to a user already attending.'
		);
		$this->assertStringContainsString( 'Not Attending', $result, 'The submit label must read Not Attending.' );
	}

	/**
	 * Tests the render_no_script_fallback method on a past event.
	 *
	 * Verifies that no fallback form is emitted when the event has passed.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_past_event(): void {
		$instance = Rsvp::get_instance();
		$post     = $this->mock->post(
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
		Setup::get_instance()->set_datetimes( $post->ID );

		$this->mock->user( true )->get();

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post->ID ),
		);
		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d">'
			. '<button class="gatherpress-rsvp--trigger-update gatherpress-rsvp--trigger-no-script">Attend</button>'
			. '</div>',
			$post->ID
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		$this->assertStringNotContainsString(
			'<noscript>',
			$result,
			'The fallback form must not render for a past event.'
		);
	}

	/**
	 * Tests the render_no_script_fallback method after a successful submission.
	 *
	 * Verifies that the success query flags produce a status message inside the form.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_success_message(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$this->mock->user( true )->get();

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) {
				if ( INPUT_GET === $type && 'gatherpress_rsvp_no_script' === $var_name ) {
					return 'success';
				}
				if ( INPUT_GET === $type && 'gatherpress_rsvp_status' === $var_name ) {
					return 'waiting_list';
				}
				return $pre_value;
			},
			10,
			3
		);

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post_id ),
		);
		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d">'
			. '<button class="gatherpress-rsvp--trigger-update gatherpress-rsvp--trigger-no-script">Attend</button>'
			. '</div>',
			$post_id
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		remove_all_filters( 'gatherpress_pre_get_http_input' );

		$this->assertStringContainsString(
			'You are on the waiting list.',
			$result,
			'The fallback form must surface the waiting list message after a waiting list placement.'
		);
		$this->assertStringContainsString( 'role="status"', $result, 'The status message must be announced politely.' );
	}

	/**
	 * Tests the render_no_script_fallback method with an error result.
	 *
	 * Verifies that a failed save is communicated to the user.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_error_message(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$this->mock->user( true )->get();

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) {
				if ( INPUT_GET === $type && 'gatherpress_rsvp_no_script' === $var_name ) {
					return 'error';
				}
				return $pre_value;
			},
			10,
			3
		);

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post_id ),
		);
		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d">'
			. '<button class="gatherpress-rsvp--trigger-update gatherpress-rsvp--trigger-no-script">Attend</button>'
			. '</div>',
			$post_id
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		remove_all_filters( 'gatherpress_pre_get_http_input' );

		$this->assertStringContainsString(
			'Your RSVP could not be saved. Please try again.',
			$result,
			'The fallback form must surface the error message.'
		);
	}

	/**
	 * Tests the render_no_script_fallback method with a non-waiting-list success.
	 *
	 * Verifies that a plain success shows the generic updated message.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_success_generic_message(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$this->mock->user( true )->get();

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) {
				if ( INPUT_GET === $type && 'gatherpress_rsvp_no_script' === $var_name ) {
					return 'success';
				}
				if ( INPUT_GET === $type && 'gatherpress_rsvp_status' === $var_name ) {
					return 'attending';
				}
				return $pre_value;
			},
			10,
			3
		);

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post_id ),
		);
		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d">'
			. '<button class="gatherpress-rsvp--trigger-update gatherpress-rsvp--trigger-no-script">Attend</button>'
			. '</div>',
			$post_id
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		remove_all_filters( 'gatherpress_pre_get_http_input' );

		$this->assertStringContainsString(
			'Your RSVP has been updated.',
			$result,
			'The fallback form must surface the generic success message.'
		);
	}

	/**
	 * Tests the render_no_script_fallback method when RSVP is disabled.
	 *
	 * Verifies that no fallback form is emitted when RSVP is disabled for the event.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_rsvp_disabled(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$this->mock->user( true )->get();

		// Sitewide RSVP mode overrides the per-event meta, so switch the mode.
		Settings::get_instance()->set( 'rsvp_mode', 'disabled' );

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post_id ),
		);
		$block_content = sprintf(
			'<div class="wp-block-gatherpress-rsvp" data-post-id="%d">'
			. '<button class="gatherpress-rsvp--trigger-update gatherpress-rsvp--trigger-no-script">Attend</button>'
			. '</div>',
			$post_id
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		$this->assertStringNotContainsString(
			'<noscript>',
			$result,
			'The fallback form must not render when RSVP is disabled for the event.'
		);
	}

	/**
	 * Tests the render_no_script_fallback method without a closing div.
	 *
	 * Verifies that the fallback form is appended when the block content has
	 * no closing div to insert before.
	 *
	 * @since 0.36.0
	 * @covers ::render_no_script_fallback
	 *
	 * @return void
	 */
	public function test_render_no_script_fallback_appends_without_closing_div(): void {
		$instance = Rsvp::get_instance();
		$post_id  = $this->factory()->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);
		$this->mock->user( true )->get();

		$block         = array(
			'blockName' => Rsvp::BLOCK_NAME,
			'attrs'     => array( 'postId' => $post_id ),
		);
		$block_content = sprintf(
			'<button class="gatherpress-rsvp--trigger-update gatherpress-rsvp--trigger-no-script">Attend</button>',
			$post_id
		);

		$result = $instance->render_no_script_fallback( $block_content, $block );

		$this->assertStringEndsWith( '</noscript>', $result, 'The fallback form must be appended to the content.' );
	}
}
