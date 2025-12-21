<?php
/**
 * Additional coverage tests for GatherPress\Core\Blocks\Rsvp_Form.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Blocks;

use GatherPress\Core\Blocks\Rsvp_Form;
use GatherPress\Core\Event;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Rsvp_Form_Coverage.
 *
 * Additional tests to cover missing lines and private methods.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Rsvp_Form
 */
class Test_Rsvp_Form_Coverage extends Base {
	/**
	 * Tests transform_block_content when event object has no event property.
	 *
	 * Covers line 130: if ( ! $event->event ) { return ''; }
	 *
	 * @covers ::transform_block_content
	 */
	public function test_transform_block_content_event_without_event_property(): void {
		global $wpdb;
		$instance = Rsvp_Form::get_instance();

		// Create event post.
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		// Manipulate cache and database to create inconsistent state.
		// Cache the correct post type, then change it in the database.
		$initial_post = get_post( $post_id );
		wp_cache_set( $post_id, $initial_post, 'posts' );
		wp_cache_set( $post_id, Event::POST_TYPE, 'post_type' );

		// Change post type in database to create inconsistency.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Necessary for testing race condition.
		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => 'page' ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Clear the posts cache but keep the post_type cache.
		wp_cache_delete( $post_id, 'posts' );

		$block_content = '<div class="wp-block-gatherpress-rsvp-form">RSVP Form</div>';
		$block         = array(
			'blockName' => 'gatherpress/rsvp-form',
			'attrs'     => array(
				'postId' => $post_id,
			),
		);

		$result = $instance->transform_block_content( $block_content, $block );

		// Cleanup: restore post type.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Cleanup for test race condition.
		$wpdb->update(
			$wpdb->posts,
			array( 'post_type' => Event::POST_TYPE ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( $post_id );

		$this->assertEmpty( $result, 'Should return empty string when event object has no event property' );
	}

	/**
	 * Tests apply_visibility_attribute returns unchanged content when tag processor fails.
	 *
	 * Covers line 246: return $block_content;
	 *
	 * @covers ::apply_visibility_attribute
	 */
	public function test_apply_visibility_attribute_no_tag_found(): void {
		$instance = Rsvp_Form::get_instance();

		// Malformed HTML that has no valid tag for the processor to find.
		$block_content = 'Plain text with no tags';
		$block         = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'onSuccess' => 'show' ),
				),
			),
		);

		$result = $instance->apply_visibility_attribute( $block_content, $block );

		$this->assertEquals( $block_content, $result, 'Should return unchanged content when no valid tag found' );
	}

	/**
	 * Tests apply_visibility_rule private method.
	 *
	 * @covers ::apply_visibility_rule
	 */
	public function test_apply_visibility_rule(): void {
		$instance = Rsvp_Form::get_instance();

		// Test with show visibility.
		$html = '<div class="test-block">Content</div>';
		$tag  = new \WP_HTML_Tag_Processor( $html );
		$tag->next_tag();

		Utility::invoke_hidden_method(
			$instance,
			'apply_visibility_rule',
			array(
				$tag,
				'{"onSuccess":"show"}',
				true, // is_success.
				false, // is_past.
			)
		);

		$result = $tag->get_updated_html();

		$this->assertStringContainsString( 'aria-hidden="false"', $result );
		$this->assertStringContainsString( 'aria-live="polite"', $result );
		$this->assertStringContainsString( 'role="status"', $result );

		// Test with hide visibility.
		$html = '<div class="test-block">Content</div>';
		$tag  = new \WP_HTML_Tag_Processor( $html );
		$tag->next_tag();

		Utility::invoke_hidden_method(
			$instance,
			'apply_visibility_rule',
			array(
				$tag,
				'{"onSuccess":"hide"}',
				true, // is_success.
				false, // is_past.
			)
		);

		$result = $tag->get_updated_html();

		$this->assertStringContainsString( 'display: none;', $result );
		$this->assertStringContainsString( 'aria-hidden="true"', $result );
	}

	/**
	 * Tests determine_visibility with non-array visibility value.
	 *
	 * Covers line 365: return null;
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_non_array(): void {
		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'invalid-json',
				false,
				false,
			)
		);

		$this->assertNull( $result, 'Should return null for invalid JSON' );
	}

	/**
	 * Tests determine_visibility with past event and whenPast rule.
	 *
	 * Covers line 374: return 'show' === $when_past;
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_when_past_show(): void {
		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"whenPast":"show"}',
				false, // is_success.
				true,  // is_past.
			)
		);

		$this->assertTrue( $result, 'Should return true when past and whenPast is show' );

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"whenPast":"hide"}',
				false, // is_success.
				true,  // is_past.
			)
		);

		$this->assertFalse( $result, 'Should return false when past and whenPast is hide' );
	}

	/**
	 * Tests determine_visibility with not past event and only whenPast rule.
	 *
	 * Covers lines 384-392.
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_not_past_when_past_only(): void {
		$instance = Rsvp_Form::get_instance();

		// When not past and whenPast is 'show', should hide.
		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"whenPast":"show"}',
				false, // is_success.
				false, // is_past.
			)
		);

		$this->assertFalse( $result, 'Should return false when not past and whenPast is show (event not yet passed)' );

		// When not past and whenPast is 'hide', should show.
		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"whenPast":"hide"}',
				false, // is_success.
				false, // is_past.
			)
		);

		$this->assertTrue( $result, 'Should return true when not past and whenPast is hide (inverse)' );
	}

	/**
	 * Tests determine_visibility with onSuccess rule when is_success is true.
	 *
	 * Covers lines 385-386.
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_on_success_when_success(): void {
		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"onSuccess":"show"}',
				true,  // is_success.
				false, // is_past.
			)
		);

		$this->assertTrue( $result, 'Should return true when success and onSuccess is show' );

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"onSuccess":"hide"}',
				true,  // is_success.
				false, // is_past.
			)
		);

		$this->assertFalse( $result, 'Should return false when success and onSuccess is hide' );
	}

	/**
	 * Tests determine_visibility with onSuccess rule when is_success is false.
	 *
	 * Covers lines 389.
	 *
	 * @covers ::determine_visibility
	 */
	public function test_determine_visibility_on_success_when_not_success(): void {
		$instance = Rsvp_Form::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"onSuccess":"show"}',
				false, // is_success.
				false, // is_past.
			)
		);

		$this->assertFalse( $result, 'Should return false when not success and onSuccess is show' );

		$result = Utility::invoke_hidden_method(
			$instance,
			'determine_visibility',
			array(
				'{"onSuccess":"hide"}',
				false, // is_success.
				false, // is_past.
			)
		);

		$this->assertTrue( $result, 'Should return true when not success and onSuccess is hide' );
	}

	/**
	 * Tests extract_form_schemas_from_blocks with nested RSVP forms.
	 *
	 * Covers lines 469-470: nested schema prefixing.
	 *
	 * @covers ::save_form_schema
	 * @covers ::extract_form_schemas_from_blocks
	 */
	public function test_extract_form_schemas_nested_forms(): void {
		$instance = Rsvp_Form::get_instance();
		$post_id  = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '<!-- wp:group -->
					<div class="wp-block-group">
						<!-- wp:gatherpress/rsvp-form -->
						<div class="wp-block-gatherpress-rsvp-form">
							<!-- wp:gatherpress/form-field {"fieldName":"nested_field","fieldType":"text"} -->
							<div class="wp-block-gatherpress-form-field"></div>
							<!-- /wp:gatherpress/form-field -->
						</div>
						<!-- /wp:gatherpress/rsvp-form -->
					</div>
					<!-- /wp:group -->',
			)
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$instance->save_form_schema( $post_id );

		$schemas = get_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', true );

		$this->assertIsArray( $schemas );
		$this->assertNotEmpty( $schemas );

		// Should have a prefixed form ID for nested form.
		$has_prefixed_key = false;
		foreach ( array_keys( $schemas ) as $key ) {
			if ( str_contains( $key, '_form_' ) ) {
				$has_prefixed_key = true;
				break;
			}
		}

		$this->assertTrue( $has_prefixed_key, 'Nested forms should have prefixed form IDs' );
	}

	/**
	 * Tests get_form_schema_id with no post content.
	 *
	 * Covers line 551: return 'form_0'; // Fallback.
	 *
	 * @covers ::get_form_schema_id
	 */
	public function test_get_form_schema_id_no_content(): void {
		$instance = Rsvp_Form::get_instance();

		// Create post with empty content.
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => Event::POST_TYPE,
				'post_content' => '',
			)
		);

		$block = array(
			'blockName' => 'gatherpress/rsvp-form',
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'get_form_schema_id',
			array( $post_id, $block )
		);

		$this->assertEquals( 'form_0', $result, 'Should return form_0 as fallback when no content' );
	}

	/**
	 * Tests find_form_index_in_blocks private method.
	 *
	 * @covers ::find_form_index_in_blocks
	 */
	public function test_find_form_index_in_blocks(): void {
		$instance = Rsvp_Form::get_instance();

		$blocks = array(
			array(
				'blockName' => 'core/paragraph',
				'innerHTML' => '<p>Some content</p>',
			),
			array(
				'blockName'   => 'gatherpress/rsvp-form',
				'innerHTML'   => '<div class="form1"></div>',
				'innerBlocks' => array(),
			),
			array(
				'blockName'   => 'gatherpress/rsvp-form',
				'innerHTML'   => '<div class="form2"></div>',
				'innerBlocks' => array(),
			),
		);

		$target_block = array(
			'blockName'   => 'gatherpress/rsvp-form',
			'innerHTML'   => '<div class="form2"></div>',
			'innerBlocks' => array(),
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'find_form_index_in_blocks',
			array( $blocks, $target_block )
		);

		$this->assertEquals( 2, $result, 'Should find the second form at index 2' );
	}

	/**
	 * Tests find_form_index_in_blocks with nested blocks.
	 *
	 * @covers ::find_form_index_in_blocks
	 */
	public function test_find_form_index_in_blocks_nested(): void {
		$instance = Rsvp_Form::get_instance();

		$target_block = array(
			'blockName'   => 'gatherpress/rsvp-form',
			'innerHTML'   => '<div class="nested-form"></div>',
			'innerBlocks' => array(),
		);

		$blocks = array(
			array(
				'blockName' => 'core/paragraph',
				'innerHTML' => '<p>Some content</p>',
			),
			array(
				'blockName'   => 'core/group',
				'innerHTML'   => '<div></div>',
				'innerBlocks' => array(
					$target_block,
				),
			),
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'find_form_index_in_blocks',
			array( $blocks, $target_block )
		);

		// When found in innerBlocks of block at index 1, result should be: 0 + 1*100 + 0 = 100.
		$this->assertEquals( 100, $result, 'Should find nested form at calculated index 100' );
	}

	/**
	 * Tests find_form_index_in_blocks returns 0 when block not found.
	 *
	 * @covers ::find_form_index_in_blocks
	 */
	public function test_find_form_index_in_blocks_not_found(): void {
		$instance = Rsvp_Form::get_instance();

		$blocks = array(
			array(
				'blockName' => 'core/paragraph',
				'innerHTML' => '<p>Some content</p>',
			),
		);

		$target_block = array(
			'blockName'   => 'gatherpress/rsvp-form',
			'innerHTML'   => '<div class="nonexistent"></div>',
			'innerBlocks' => array(),
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'find_form_index_in_blocks',
			array( $blocks, $target_block )
		);

		$this->assertEquals( 0, $result, 'Should return 0 fallback when block not found' );
	}

	/**
	 * Tests blocks_match private method.
	 *
	 * @covers ::blocks_match
	 */
	public function test_blocks_match(): void {
		$instance = Rsvp_Form::get_instance();

		$block1 = array(
			'innerHTML' => '<div class="matching-content">Test</div>',
		);

		$block2 = array(
			'innerHTML' => '<div class="matching-content">Test</div>',
		);

		$block3 = array(
			'innerHTML' => '<div class="different-content">Test</div>',
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'blocks_match',
			array( $block1, $block2 )
		);

		$this->assertTrue( $result, 'Blocks with same innerHTML should match' );

		$result = Utility::invoke_hidden_method(
			$instance,
			'blocks_match',
			array( $block1, $block3 )
		);

		$this->assertFalse( $result, 'Blocks with different innerHTML should not match' );
	}

	/**
	 * Tests blocks_match with empty innerHTML.
	 *
	 * @covers ::blocks_match
	 */
	public function test_blocks_match_empty(): void {
		$instance = Rsvp_Form::get_instance();

		$block1 = array();
		$block2 = array();

		$result = Utility::invoke_hidden_method(
			$instance,
			'blocks_match',
			array( $block1, $block2 )
		);

		$this->assertTrue( $result, 'Blocks with no innerHTML should match (both empty strings)' );
	}

	/**
	 * Tests process_custom_fields_for_form with no fields in schema.
	 *
	 * Covers line 665: return;
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_no_fields(): void {
		$instance = Rsvp_Form::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => \GatherPress\Core\Rsvp::COMMENT_TYPE,
			)
		);

		// Set up schema with no fields.
		$schemas = array(
			'form_0' => array(
				'fields' => array(), // Empty fields array.
			),
		);
		add_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type && 'gatherpress_form_schema_id' === $var_name ) {
				return 'form_0';
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		// Should not throw errors with empty fields.
		$instance->process_custom_fields_for_form( $comment_id );

		$this->assertTrue( true, 'Should handle empty fields array without errors' );

		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}

	/**
	 * Tests process_custom_fields_for_form skips built-in fields.
	 *
	 * Covers line 672: continue;
	 *
	 * @covers ::process_custom_fields_for_form
	 */
	public function test_process_custom_fields_for_form_skips_built_in_fields(): void {
		$instance = Rsvp_Form::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type' => Event::POST_TYPE,
			)
		);

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => \GatherPress\Core\Rsvp::COMMENT_TYPE,
			)
		);

		// Set up schema with built-in fields.
		$schemas = array(
			'form_0' => array(
				'fields' => array(
					'author'                           => array(
						'name' => 'author',
						'type' => 'text',
					),
					'email'                            => array(
						'name' => 'email',
						'type' => 'email',
					),
					'gatherpress_rsvp_form_guests'     => array(
						'name' => 'gatherpress_rsvp_form_guests',
						'type' => 'number',
					),
					'gatherpress_rsvp_form_anonymous'  => array(
						'name' => 'gatherpress_rsvp_form_anonymous',
						'type' => 'checkbox',
					),
					'gatherpress_event_updates_opt_in' => array(
						'name' => 'gatherpress_event_updates_opt_in',
						'type' => 'checkbox',
					),
				),
			),
		);
		add_post_meta( $post_id, 'gatherpress_rsvp_form_schemas', $schemas );

		$filter_callback = function ( $pre_value, $type, $var_name ) {
			if ( INPUT_POST === $type ) {
				if ( 'gatherpress_form_schema_id' === $var_name ) {
					return 'form_0';
				}
				// Return values for built-in fields.
				if ( 'author' === $var_name ) {
					return 'Test Author';
				}
				if ( 'email' === $var_name ) {
					return 'test@example.com';
				}
			}
			return $pre_value;
		};

		add_filter( 'gatherpress_pre_get_http_input', $filter_callback, 10, 3 );

		$instance->process_custom_fields_for_form( $comment_id );

		// Verify built-in fields were NOT saved as custom fields.
		$this->assertEmpty(
			get_comment_meta( $comment_id, 'gatherpress_custom_author', true ),
			'Built-in field "author" should not be saved as custom field'
		);
		$this->assertEmpty(
			get_comment_meta( $comment_id, 'gatherpress_custom_email', true ),
			'Built-in field "email" should not be saved as custom field'
		);

		remove_filter( 'gatherpress_pre_get_http_input', $filter_callback );
	}
}
