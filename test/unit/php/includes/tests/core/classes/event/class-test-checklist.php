<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Checklist.
 *
 * @package GatherPress\Core\Event
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Checklist;
use GatherPress\Tests\Base;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Test_Checklist.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Checklist
 */
class Test_Checklist extends Base {

	/**
	 * Read override installed by `force_prunable_read()`.
	 *
	 * @var callable|null
	 */
	private $prune_read_filter = null;

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
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'prune_empty_items' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'prune_empty_items' ),
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
		$this->assertNotFalse(
			has_filter(
				sprintf( 'rest_prepare_%s', $test_pt ),
				array( $instance, 'strip_from_readers' )
			),
			'Failed to assert the public-read backstop is hooked on a supporting post type.'
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
	 * A JSON object at the top level is rejected rather than reindexed.
	 *
	 * `json_decode( $value, true )` turns a JSON object into a PHP array, so
	 * an associative payload such as `{"row":{...}}` would otherwise satisfy
	 * the array check and be silently rewritten as a list. It has to take the
	 * malformed-payload fallback instead.
	 *
	 * @covers ::sanitize
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_associative_payload(): void {
		$instance = Checklist::get_instance();

		$this->assertSame(
			Checklist::EMPTY_CHECKLIST,
			$instance->sanitize( '{"row":{"id":"a","text":"Ask"}}' ),
			'Failed to assert an associative payload is not reindexed into a list.'
		);
	}

	/**
	 * An item id over the cap is dropped.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_drops_over_long_ids(): void {
		$instance = Checklist::get_instance();

		$result = $instance->sanitize(
			sprintf(
				'[{"id":"%s","text":"Too long","completed":false},'
					. '{"id":"kept","text":"Kept","completed":false}]',
				str_repeat( 'i', Checklist::MAX_ID_LENGTH + 1 )
			)
		);

		$this->assertSame(
			'[{"id":"kept","text":"Kept","completed":false}]',
			$result,
			'Failed to assert an over-long id is dropped instead of stored.'
		);
	}

	/**
	 * An item id exactly at the cap is kept.
	 *
	 * The bound has to admit the editor's UUIDs and hand-written ids alike, so
	 * the check is `>` and not `>=`.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_keeps_ids_at_the_cap(): void {
		$instance = Checklist::get_instance();
		$id       = str_repeat( 'i', Checklist::MAX_ID_LENGTH );

		$result = $instance->sanitize(
			sprintf( '[{"id":"%s","text":"Kept","completed":false}]', $id )
		);

		$this->assertSame(
			sprintf( '[{"id":"%s","text":"Kept","completed":false}]', $id ),
			$result,
			'Failed to assert an id at the cap is kept.'
		);
	}

	/**
	 * Non-scalar `completed` values coerce to false.
	 *
	 * `sanitize_item()` maps a non-scalar to an empty string before handing it
	 * to `rest_sanitize_boolean()`, which reads that as false. An array or
	 * object from a malformed write must not mark an item complete.
	 *
	 * @covers ::sanitize
	 * @covers ::sanitize_item
	 *
	 * @return void
	 */
	public function test_sanitize_coerces_non_scalar_completed_to_false(): void {
		$instance = Checklist::get_instance();

		$result = $instance->sanitize(
			'[{"id":"array","text":"Array","completed":{"nested":true}},'
				. '{"id":"object","text":"Object","completed":[1,2]}]'
		);

		$this->assertSame(
			'[{"id":"array","text":"Array","completed":false},'
				. '{"id":"object","text":"Object","completed":false}]',
			$result,
			'Failed to assert a non-scalar completed value coerces to false.'
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

	/**
	 * A public read omits the checklist.
	 *
	 * The meta is registered with `show_in_rest` limited to the `edit` context,
	 * so `rest_filter_response_by_context()` drops the key from a `view`
	 * response even though the rest of the published event is readable. This is
	 * the privacy property the class header promises, so it gets a real
	 * dispatched request rather than a schema assertion.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_rest_view_context_omits_the_checklist(): void {
		Checklist::get_instance()->register( Event::POST_TYPE );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, Checklist::META_KEY, '[{"id":"a","text":"Invoice","completed":true}]' );

		wp_set_current_user( 0 );

		$rest_base = get_post_type_object( Event::POST_TYPE )->rest_base;
		$request   = new WP_REST_Request( 'GET', sprintf( '/wp/v2/%s/%d', $rest_base, $post_id ) );
		$request->set_param( 'context', 'view' );

		$response = rest_do_request( $request );
		$meta     = $response->get_data()['meta'] ?? array();

		$this->assertSame(
			200,
			$response->get_status(),
			'Failed to assert the published event is readable anonymously.'
		);
		// Assert the meta field itself is present, so the absence check below
		// cannot pass merely because the whole meta object is missing.
		$this->assertArrayHasKey(
			'meta',
			$response->get_data(),
			'Failed to assert the response carries a meta field to check within.'
		);
		$this->assertArrayNotHasKey(
			Checklist::META_KEY,
			$meta,
			'Failed to assert a public read carries no checklist.'
		);
	}

	/**
	 * `strip_from_readers()` drops the checklist for a reader without edit access.
	 *
	 * The schema alone is not enough to keep the list out of a public read: the
	 * posts controller memoizes its item schema, so a controller built before
	 * this meta was registered carries no property for the key, and core keeps
	 * keys it finds no property for. This is the backstop that answers from the
	 * capability instead, so it is asserted directly as well as through the
	 * dispatched request above.
	 *
	 * @covers ::strip_from_readers
	 *
	 * @return void
	 */
	public function test_strip_from_readers_removes_the_checklist_without_edit_access(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( 0 );

		$response = new WP_REST_Response(
			array(
				'meta' => array(
					Checklist::META_KEY => '[{"id":"a","text":"Invoice","completed":true}]',
					'kept'              => 'value',
				),
			)
		);

		$stripped = Checklist::get_instance()->strip_from_readers( $response, get_post( $post_id ) );

		$this->assertArrayNotHasKey(
			Checklist::META_KEY,
			$stripped->get_data()['meta'],
			'Failed to assert a reader without edit access gets no checklist.'
		);
		$this->assertSame(
			'value',
			$stripped->get_data()['meta']['kept'],
			'Failed to assert the backstop leaves the rest of the meta alone.'
		);
	}

	/**
	 * `strip_from_readers()` leaves the checklist alone for an editor.
	 *
	 * @covers ::strip_from_readers
	 *
	 * @return void
	 */
	public function test_strip_from_readers_keeps_the_checklist_for_an_editor(): void {
		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$stored  = '[{"id":"a","text":"Invoice","completed":true}]';

		wp_set_current_user( $user_id );

		$response = new WP_REST_Response(
			array(
				'meta' => array( Checklist::META_KEY => $stored ),
			)
		);

		$kept = Checklist::get_instance()->strip_from_readers( $response, get_post( $post_id ) );

		$this->assertSame(
			$stored,
			$kept->get_data()['meta'][ Checklist::META_KEY ],
			'Failed to assert an editor still gets the stored checklist.'
		);
	}

	/**
	 * An editor read in the edit context returns the stored checklist.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_rest_edit_context_returns_the_checklist(): void {
		Checklist::get_instance()->register( Event::POST_TYPE );

		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$stored  = '[{"id":"a","text":"Invoice","completed":true}]';

		update_post_meta( $post_id, Checklist::META_KEY, $stored );
		wp_set_current_user( $user_id );

		$rest_base = get_post_type_object( Event::POST_TYPE )->rest_base;
		$request   = new WP_REST_Request( 'GET', sprintf( '/wp/v2/%s/%d', $rest_base, $post_id ) );
		$request->set_param( 'context', 'edit' );

		$response = rest_do_request( $request );

		$this->assertSame(
			200,
			$response->get_status(),
			'Failed to assert an editor can read the event in the edit context.'
		);
		$this->assertSame(
			$stored,
			$response->get_data()['meta'][ Checklist::META_KEY ] ?? null,
			'Failed to assert the edit context returns the stored checklist.'
		);
	}

	/**
	 * An editor can write the checklist over REST.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_rest_editor_can_write_the_checklist(): void {
		Checklist::get_instance()->register( Event::POST_TYPE );

		$user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$payload = '[{"id":"a","text":"Invoice","completed":false}]';

		wp_set_current_user( $user_id );

		$rest_base = get_post_type_object( Event::POST_TYPE )->rest_base;
		$request   = new WP_REST_Request( 'POST', sprintf( '/wp/v2/%s/%d', $rest_base, $post_id ) );
		$request->set_body_params(
			array(
				'meta' => array( Checklist::META_KEY => $payload ),
			)
		);

		$response = rest_do_request( $request );

		$this->assertSame(
			200,
			$response->get_status(),
			'Failed to assert an editor can write the checklist over REST.'
		);
		$this->assertSame(
			$payload,
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert the written checklist persists.'
		);
	}

	/**
	 * A subscriber cannot write the checklist over REST.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_rest_subscriber_cannot_write_the_checklist(): void {
		Checklist::get_instance()->register( Event::POST_TYPE );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$stored  = '[{"id":"a","text":"Kept","completed":false}]';
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		update_post_meta( $post_id, Checklist::META_KEY, $stored );
		wp_set_current_user( $user_id );

		$rest_base = get_post_type_object( Event::POST_TYPE )->rest_base;
		$request   = new WP_REST_Request( 'POST', sprintf( '/wp/v2/%s/%d', $rest_base, $post_id ) );
		$request->set_body_params(
			array(
				'meta' => array( Checklist::META_KEY => '[{"id":"b","text":"Injected","completed":true}]' ),
			)
		);

		$response = rest_do_request( $request );

		$this->assertGreaterThanOrEqual(
			400,
			$response->get_status(),
			'Failed to assert a subscriber cannot write the checklist over REST.'
		);
		$this->assertLessThan(
			500,
			$response->get_status(),
			'Failed to assert the subscriber write is denied, not a server error.'
		);
		$this->assertSame(
			$stored,
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert a denied write leaves the checklist unchanged.'
		);
	}

	/**
	 * An empty row is dropped the moment the checklist is stored.
	 *
	 * This is the reviewer's scenario: a row is added and never typed into, then
	 * the event is saved. The sanitizer keeps the row so the editor can round
	 * trip it while it is being filled in, so it is the prune that has to drop
	 * it once the list is written.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_drops_empty_rows_on_write(): void {
		$post_id = $this->seed_prune_post();

		update_post_meta(
			$post_id,
			Checklist::META_KEY,
			'[{"id":"a","text":"","completed":false},{"id":"b","text":"Pay","completed":false}]'
		);

		$this->assertSame(
			'[{"id":"b","text":"Pay","completed":false}]',
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert an untitled row is pruned from the stored checklist.'
		);
	}

	/**
	 * A whitespace-only row is dropped on a second write.
	 *
	 * Exercises the `updated_post_meta` arm rather than `added_post_meta`, and
	 * pins that blank-but-not-empty text counts as empty.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_drops_blank_rows_on_update(): void {
		$post_id = $this->seed_prune_post();

		update_post_meta(
			$post_id,
			Checklist::META_KEY,
			'[{"id":"b","text":"Pay","completed":false}]'
		);
		update_post_meta(
			$post_id,
			Checklist::META_KEY,
			'[{"id":"b","text":"Pay","completed":false},{"id":"c","text":"   ","completed":false}]'
		);

		$this->assertSame(
			'[{"id":"b","text":"Pay","completed":false}]',
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert a whitespace-only row is pruned.'
		);
	}

	/**
	 * A checklist with only filled-in rows is left exactly as written.
	 *
	 * This is the `$encoded === $stored` guard: there is nothing to drop, so the
	 * method must not rewrite the meta. It also terminates the re-entry that the
	 * method's own `update_post_meta()` triggers.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_keeps_a_clean_list(): void {
		$post_id = $this->seed_prune_post();
		$stored  = '[{"id":"b","text":"Pay","completed":true}]';

		update_post_meta( $post_id, Checklist::META_KEY, $stored );

		$this->assertSame(
			$stored,
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert a checklist with no empty rows is left alone.'
		);
	}

	/**
	 * A write to any other meta key is ignored.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_ignores_other_meta_keys(): void {
		$post_id  = $this->seed_prune_post();
		$baseline = $this->seed_stored_checklist( $post_id );

		Checklist::get_instance()->prune_empty_items( 1, $post_id, 'some_other_meta' );

		$this->assertSame(
			$baseline,
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert a write to another meta key does not prune the checklist.'
		);
	}

	/**
	 * A revision is ignored, which is also what keeps the row alive through an
	 * autosave (an autosave is stored as a revision).
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_ignores_revisions(): void {
		$post_id     = $this->seed_prune_post();
		$revision_id = (int) $this->factory->post->create(
			array(
				'post_type'   => 'revision',
				'post_parent' => $post_id,
			)
		);

		Checklist::get_instance()->prune_empty_items( 1, $revision_id, Checklist::META_KEY );

		$this->assertSame(
			'',
			get_post_meta( $revision_id, Checklist::META_KEY, true ),
			'Failed to assert a revision is left without a pruned checklist.'
		);
	}

	/**
	 * A post type without checklist support is ignored.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_ignores_unsupported_post_type(): void {
		$post_id = (int) $this->factory->post->create( array( 'post_type' => 'post' ) );

		update_post_meta( $post_id, Checklist::META_KEY, '[{"id":"a","text":"","completed":false}]' );

		Checklist::get_instance()->prune_empty_items( 1, $post_id, Checklist::META_KEY );

		$this->assertSame(
			'[{"id":"a","text":"","completed":false}]',
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert the prune leaves an unsupported post type alone.'
		);
	}

	/**
	 * A stored value that is not a string is ignored.
	 *
	 * The registered sanitizer rewrites anything written through
	 * `update_post_meta()`, so a stored value of the wrong type can only be
	 * reached by short-circuiting the read.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_ignores_non_string_stored_value(): void {
		$post_id  = $this->seed_prune_post();
		$baseline = $this->seed_stored_checklist( $post_id );

		$this->force_prunable_read( $post_id, 12345 );

		Checklist::get_instance()->prune_empty_items( 1, $post_id, Checklist::META_KEY );

		$this->release_prunable_read();

		$this->assertSame(
			$baseline,
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert a non-string stored value is left alone.'
		);
	}

	/**
	 * A stored value that does not decode to a JSON array is ignored.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_ignores_malformed_json(): void {
		$post_id  = $this->seed_prune_post();
		$baseline = $this->seed_stored_checklist( $post_id );

		$this->force_prunable_read( $post_id, 'not json' );

		Checklist::get_instance()->prune_empty_items( 1, $post_id, Checklist::META_KEY );

		$this->release_prunable_read();

		$this->assertSame(
			$baseline,
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert malformed JSON is not pruned.'
		);
	}

	/**
	 * A JSON object at the top level is ignored rather than reindexed.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_ignores_associative_json(): void {
		$post_id  = $this->seed_prune_post();
		$baseline = $this->seed_stored_checklist( $post_id );

		$this->force_prunable_read( $post_id, '{"row":{"id":"a","text":""}}' );

		Checklist::get_instance()->prune_empty_items( 1, $post_id, Checklist::META_KEY );

		$this->release_prunable_read();

		$this->assertSame(
			$baseline,
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert an associative payload is not reindexed by the prune.'
		);
	}

	/**
	 * An entry that is not shaped like an item is dropped by the prune.
	 *
	 * The injected list pairs a non-item entry with an empty-text row, so the
	 * expected `[]` can only come from the prune. The sanitizer alone keeps the
	 * empty row, so a passing assertion here is prune-specific rather than the
	 * sanitizer's doing.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_drops_non_array_entries(): void {
		$post_id = $this->seed_prune_post();

		$this->force_prunable_read(
			$post_id,
			'["a string",{"id":"a","text":"","completed":false}]'
		);

		Checklist::get_instance()->prune_empty_items( 1, $post_id, Checklist::META_KEY );

		$this->release_prunable_read();

		$this->assertSame(
			'[]',
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert a non-item entry is pruned.'
		);
	}

	/**
	 * An item whose text is not a scalar is dropped by the prune.
	 *
	 * `sanitize_item()` coerces a non-scalar text to `''` while keeping the row,
	 * so a passing assertion is prune-specific: the sanitizer alone would leave
	 * the coerced empty row in place.
	 *
	 * @covers ::prune_empty_items
	 *
	 * @return void
	 */
	public function test_prune_empty_items_drops_non_scalar_text(): void {
		$post_id = $this->seed_prune_post();

		$this->force_prunable_read(
			$post_id,
			'[{"id":"a","text":{"nested":true},"completed":false},{"id":"b","text":"Keep","completed":false}]'
		);

		Checklist::get_instance()->prune_empty_items( 1, $post_id, Checklist::META_KEY );

		$this->release_prunable_read();

		$this->assertSame(
			'[{"id":"b","text":"Keep","completed":false}]',
			get_post_meta( $post_id, Checklist::META_KEY, true ),
			'Failed to assert a non-scalar text is pruned.'
		);
	}

	/**
	 * Create an event post with the checklist meta registered on its type.
	 *
	 * Registration is forced because the `registered_post_type` listener only
	 * fires when this class is instantiated before the post type registers.
	 *
	 * @return int The event post id.
	 */
	private function seed_prune_post(): int {
		Checklist::get_instance()->register( Event::POST_TYPE );

		return (int) $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Store a filled-in checklist and return what ended up stored.
	 *
	 * Reading the value back rather than returning the input keeps the tests
	 * honest: `update_post_meta()` runs the registered sanitizer, so the stored
	 * value is what the sanitizer left, not necessarily what was passed in.
	 *
	 * @param int $post_id Event post id.
	 *
	 * @return string The stored checklist value.
	 */
	private function seed_stored_checklist( int $post_id ): string {
		update_post_meta(
			$post_id,
			Checklist::META_KEY,
			'[{"id":"seed","text":"Seed","completed":false}]'
		);

		return (string) get_post_meta( $post_id, Checklist::META_KEY, true );
	}

	/**
	 * Force the next checklist read to return a fixed value.
	 *
	 * Only the first read is intercepted. The prune's own write reads the meta
	 * again (once for the old-value comparison and once on re-entry), and those
	 * reads have to see the real stored value or the second pass would keep
	 * finding the same unpruned list and recurse.
	 *
	 * @param int   $post_id Post id to intercept.
	 * @param mixed $value   Value to hand back.
	 *
	 * @return void
	 */
	private function force_prunable_read( int $post_id, $value ): void {
		$reads = 0;

		$this->prune_read_filter = static function ( $check, $object_id, $meta_key ) use ( $post_id, $value, &$reads ) {
			if ( (int) $object_id === $post_id && Checklist::META_KEY === $meta_key && 0 === $reads++ ) {
				return $value;
			}

			return $check;
		};

		add_filter( 'get_post_metadata', $this->prune_read_filter, 10, 3 );
	}

	/**
	 * Remove the read override installed by `force_prunable_read()`.
	 *
	 * @return void
	 */
	private function release_prunable_read(): void {
		if ( null !== $this->prune_read_filter ) {
			remove_filter( 'get_post_metadata', $this->prune_read_filter, 10 );
			$this->prune_read_filter = null;
		}
	}
}
