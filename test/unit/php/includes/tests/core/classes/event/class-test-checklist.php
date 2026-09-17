<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Checklist.
 *
 * @package GatherPress\Core\Event
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Checklist;
use GatherPress\Tests\Base;

/**
 * Class Test_Checklist.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Checklist
 */
class Test_Checklist extends Base {

	/**
	 * Coverage for `__construct` and `setup_hooks`.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Checklist::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'register' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * The support is declared on the built-in event post type.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_event_post_type_declares_the_support(): void {
		$this->assertTrue(
			post_type_supports( Event::POST_TYPE, Checklist::SUPPORT ),
			'Failed to assert the event post type declares checklist support.'
		);
	}

	/**
	 * `register()` writes the meta and forces `custom-fields` on a post type
	 * that declares the checklist support.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_on_supporting_post_type(): void {
		$instance = Checklist::get_instance();
		$test_pt  = 'test_event_checklist';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Test Event Checklist',
				'public'   => false,
				'supports' => array( 'title', Checklist::SUPPORT ),
			)
		);

		// The post type was registered before the hook could fire for it in
		// some orderings, so call through directly.
		$instance->register( $test_pt );

		$meta = get_registered_meta_keys( 'post', $test_pt );

		$this->assertArrayHasKey(
			Checklist::META_KEY,
			$meta,
			'Failed to assert the checklist meta registers on a supporting post type.'
		);
		$this->assertSame(
			'string',
			$meta[ Checklist::META_KEY ]['type'],
			'Failed to assert the checklist stores as a string.'
		);
		$this->assertSame(
			Checklist::EMPTY_CHECKLIST,
			$meta[ Checklist::META_KEY ]['default'],
			'Failed to assert an event starts with an empty checklist.'
		);
		$this->assertTrue(
			$meta[ Checklist::META_KEY ]['single'],
			'Failed to assert the checklist is a single meta row.'
		);
		$this->assertSame(
			array( 'edit' ),
			$meta[ Checklist::META_KEY ]['show_in_rest']['schema']['context'],
			'Failed to assert the checklist is kept out of public REST reads.'
		);
		$this->assertTrue(
			post_type_supports( $test_pt, 'custom-fields' ),
			'Failed to assert custom-fields support is auto-added so REST exposes meta.'
		);

		unregister_post_type( $test_pt );
	}

	/**
	 * `register()` leaves a post type without the support alone.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_skips_unsupported_post_type(): void {
		$instance = Checklist::get_instance();

		$instance->register( 'post' );

		$this->assertArrayNotHasKey(
			Checklist::META_KEY,
			get_registered_meta_keys( 'post', 'post' ),
			'Failed to assert the checklist stays off unsupported post types.'
		);
	}

	/**
	 * The registered auth callback lets an editor-capable user edit the meta.
	 *
	 * `register_post_meta()` wires `auth_callback` as the
	 * `auth_post_meta_{$meta_key}_for_{$post_type}` filter, which only
	 * `map_meta_cap()` consults. `update_post_meta()` does not check
	 * capabilities at all, so the assertion goes through the capability the
	 * REST meta controller will check.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_auth_callback(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		$this->assertTrue(
			current_user_can( 'edit_post_meta', $post_id, Checklist::META_KEY ),
			'Failed to assert an editor can edit the checklist meta.'
		);
	}

	/**
	 * The registered auth callback refuses a user who cannot edit the event.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_auth_callback_denies_subscriber(): void {
		$post_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse(
			current_user_can( 'edit_post_meta', $post_id, Checklist::META_KEY ),
			'Failed to assert a subscriber cannot edit the checklist meta.'
		);
	}

	/**
	 * A valid payload survives the sanitizer with its shape intact.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_keeps_a_valid_payload(): void {
		$instance = Checklist::get_instance();
		$payload  = array(
			array(
				'id'        => 'inquiry',
				'text'      => 'Send the inquiry',
				'completed' => true,
			),
			array(
				'id'        => 'invoice',
				'text'      => 'Issue the invoice',
				'completed' => false,
			),
		);

		$result = $instance->sanitize( (string) wp_json_encode( $payload ) );

		$this->assertSame(
			'[{"id":"inquiry","text":"Send the inquiry","completed":true},'
				. '{"id":"invoice","text":"Issue the invoice","completed":false}]',
			$result,
			'Failed to assert a valid checklist round-trips unchanged.'
		);
	}

	/**
	 * Item text is sanitized and `completed` is coerced to a boolean.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_cleans_item_fields(): void {
		$instance = Checklist::get_instance();

		$result = $instance->sanitize(
			'[{"id":"a","text":"<script>alert(1)<\/script>Pay <b>invoice</b>","completed":"1"}]'
		);

		$this->assertSame(
			'[{"id":"a","text":"Pay invoice","completed":true}]',
			$result,
			'Failed to assert tags are stripped from the text and completed is coerced.'
		);
	}

	/**
	 * An item without text keeps an empty string rather than being dropped,
	 * because the editor writes on every keystroke.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_keeps_items_without_text(): void {
		$instance = Checklist::get_instance();

		$result = $instance->sanitize( '[{"id":"a","completed":false}]' );

		$this->assertSame(
			'[{"id":"a","text":"","completed":false}]',
			$result,
			'Failed to assert an untitled item is kept with empty text.'
		);
	}

	/**
	 * Item text over the cap is trimmed.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_trims_long_text(): void {
		$instance = Checklist::get_instance();

		$result = $instance->sanitize(
			sprintf(
				'[{"id":"a","text":"%s","completed":false}]',
				str_repeat( 'a', Checklist::MAX_TEXT_LENGTH + 20 )
			)
		);

		$decoded = json_decode( $result, true );

		$this->assertSame(
			Checklist::MAX_TEXT_LENGTH,
			mb_strlen( $decoded[0]['text'] ),
			'Failed to assert item text is capped.'
		);
	}

	/**
	 * Entries that are not shaped like items are dropped.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_drops_unusable_entries(): void {
		$instance = Checklist::get_instance();

		$result = $instance->sanitize(
			'["a string",{"text":"no id"},{"id":"","text":"empty id"},'
				. '{"id":{"nested":true},"text":"array id"},{"id":"kept","text":"Kept"}]'
		);

		$this->assertSame(
			'[{"id":"kept","text":"Kept","completed":false}]',
			$result,
			'Failed to assert only the usable entry survives.'
		);
	}

	/**
	 * Non-scalar text falls back to an empty string.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_falls_back_when_text_is_not_scalar(): void {
		$instance = Checklist::get_instance();

		$result = $instance->sanitize( '[{"id":"a","text":{"nested":true},"completed":false}]' );

		$this->assertSame(
			'[{"id":"a","text":"","completed":false}]',
			$result,
			'Failed to assert a non-scalar text is discarded.'
		);
	}

	/**
	 * Values that are not strings collapse to an empty checklist.
	 *
	 * @covers ::sanitize
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_non_strings(): void {
		$instance = Checklist::get_instance();

		$this->assertSame(
			Checklist::EMPTY_CHECKLIST,
			$instance->sanitize( array( 'id' => 'a' ) ),
			'Failed to assert an array payload is rejected.'
		);
	}

	/**
	 * Malformed JSON collapses to an empty checklist.
	 *
	 * @covers ::sanitize
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_malformed_json(): void {
		$instance = Checklist::get_instance();

		$this->assertSame(
			Checklist::EMPTY_CHECKLIST,
			$instance->sanitize( '{"id":"a"' ),
			'Failed to assert malformed JSON is rejected.'
		);
	}

	/**
	 * A JSON value that is not an array collapses to an empty checklist.
	 *
	 * @covers ::sanitize
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_non_array_json(): void {
		$instance = Checklist::get_instance();

		$this->assertSame(
			Checklist::EMPTY_CHECKLIST,
			$instance->sanitize( '"a string"' ),
			'Failed to assert a scalar JSON payload is rejected.'
		);
	}

	/**
	 * A checklist longer than the cap is trimmed to the cap.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_caps_the_item_count(): void {
		$instance = Checklist::get_instance();
		$items    = array();

		for ( $i = 0; $i <= Checklist::MAX_ITEMS; $i++ ) {
			$items[] = array(
				'id'        => 'item-' . $i,
				'text'      => 'Item ' . $i,
				'completed' => false,
			);
		}

		$decoded = json_decode( $instance->sanitize( (string) wp_json_encode( $items ) ), true );

		$this->assertCount(
			Checklist::MAX_ITEMS,
			$decoded,
			'Failed to assert the checklist is capped.'
		);
		$this->assertSame(
			'item-' . ( Checklist::MAX_ITEMS - 1 ),
			$decoded[ Checklist::MAX_ITEMS - 1 ]['id'],
			'Failed to assert the cap keeps the first items.'
		);
	}
}
