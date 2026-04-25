<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue_Setup.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Core\Venue_Setup;
use GatherPress\Tests\Base;
use WP_Block_Patterns_Registry;

/**
 * Class Test_Venue_Setup.
 *
 * @group multisite
 * @coversDefaultClass \GatherPress\Core\Venue_Setup
 */
class Test_Venue_Setup extends Base {
	/**
	 * Coverage for __construct and setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Venue_Setup::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => sprintf( 'save_post_%s', Venue::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'maybe_apply_venue_template' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_post_type' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_register_post_type_hooks' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_register_post_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'register_taxonomy' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'post_updated',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_update_term_slug' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 10,
				'callback' => array( $instance, 'set_geodata' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'block_editor_settings_all',
				'priority' => 10,
				'callback' => array( $instance, 'add_editor_settings' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for maybe_register_post_type_hooks method.
	 *
	 * Verifies that per-post-type save and delete actions are registered when
	 * the given post type declares the 'gatherpress-venue-information' support.
	 *
	 * @covers ::maybe_register_post_type_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_post_type_hooks(): void {
		$instance = Venue_Setup::get_instance();

		foreach ( get_post_types_by_support( 'gatherpress-venue-information' ) as $post_type ) {
			$instance->maybe_register_post_type_hooks( $post_type );
			$this->assertSame(
				10,
				has_action(
					sprintf( 'save_post_%s', $post_type ),
					array( $instance, 'add_venue_term' )
				),
				sprintf( 'Failed to assert that save_post_%s has the add_venue_term action.', $post_type )
			);
			$this->assertSame(
				10,
				has_action(
					sprintf( 'delete_post_%s', $post_type ),
					array( $instance, 'delete_venue_term' )
				),
				sprintf( 'Failed to assert that delete_post_%s has the delete_venue_term action.', $post_type )
			);
		}
	}

	/**
	 * Bails when the post type does not declare venue-information support.
	 *
	 * @covers ::maybe_register_post_type_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_post_type_hooks_skips_unsupported_post_type(): void {
		$instance = Venue_Setup::get_instance();

		$instance->maybe_register_post_type_hooks( 'post' );

		$this->assertFalse(
			has_action( 'save_post_post', array( $instance, 'add_venue_term' ) ),
			'Failed to assert no save hook is registered for a post type without venue-information support.'
		);
		$this->assertFalse(
			has_action( 'delete_post_post', array( $instance, 'delete_venue_term' ) ),
			'Failed to assert no delete hook is registered for a post type without venue-information support.'
		);
	}

	/**
	 * Coverage for register_post_type method.
	 *
	 * @covers ::register_post_type
	 *
	 * @return void
	 */
	public function test_register_post_type(): void {
		$instance = Venue_Setup::get_instance();

		unregister_post_type( Venue::POST_TYPE );

		$this->assertFalse( post_type_exists( Venue::POST_TYPE ), 'Failed to assert that post type does not exist.' );

		$instance->register_post_type();

		$this->assertTrue( post_type_exists( Venue::POST_TYPE ), 'Failed to assert that post type exists.' );
	}


	/**
	 * Coverage for maybe_register_post_meta method.
	 *
	 * @covers ::maybe_register_post_meta
	 *
	 * @return void
	 */
	public function test_maybe_register_post_meta(): void {
		$instance = Venue_Setup::get_instance();

		$venue_information_keys = array(
			'gatherpress_full_address',
			'gatherpress_phone_number',
			'gatherpress_website',
			'gatherpress_latitude',
			'gatherpress_longitude',
			'gatherpress_venue_static_map',
		);

		foreach ( $venue_information_keys as $key ) {
			unregister_post_meta( Venue::POST_TYPE, $key );
		}

		unregister_post_meta( Venue::POST_TYPE, 'gatherpress_venue_map_show' );

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		foreach ( $venue_information_keys as $key ) {
			$this->assertArrayNotHasKey(
				$key,
				$meta,
				sprintf( 'Failed to assert that %s is unregistered before re-registration.', $key )
			);
		}

		$this->assertArrayNotHasKey(
			'gatherpress_venue_map_show',
			$meta,
			'Failed to assert that gatherpress_venue_map_show does not exist.'
		);

		$instance->maybe_register_post_meta( Venue::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		foreach ( $venue_information_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$meta,
				sprintf( 'Failed to assert that %s is registered for gatherpress-venue-information support.', $key )
			);
		}

		$this->assertArrayHasKey(
			'gatherpress_venue_map_show',
			$meta,
			'Failed to assert that gatherpress_venue_map_show exists for gatherpress-venue-map support.'
		);
	}

	/**
	 * Coverage for maybe_register_post_meta when the venue post type does not support revisions.
	 *
	 * Registers a throwaway venue post type that declares gatherpress-venue-information
	 * support but omits WordPress revisions support. Verifies that maybe_register_post_meta
	 * silently drops revisions_enabled for that post type and still registers the meta
	 * without triggering a WordPress _doing_it_wrong notice.
	 *
	 * @covers ::maybe_register_post_meta
	 *
	 * @return void
	 */
	public function test_maybe_register_post_meta_without_revisions_support(): void {
		$instance = Venue_Setup::get_instance();
		$test_pt  = 'test_venue_no_rev';

		register_post_type(
			$test_pt,
			array(
				'label'    => 'Test Venues (no revisions)',
				'public'   => false,
				'supports' => array( 'title', 'gatherpress-venue-information' ),
			)
		);

		$instance->maybe_register_post_meta( $test_pt );

		$meta = get_registered_meta_keys( 'post', $test_pt );

		$expected_keys = array(
			'gatherpress_full_address',
			'gatherpress_phone_number',
			'gatherpress_website',
			'gatherpress_latitude',
			'gatherpress_longitude',
		);

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey(
				$key,
				$meta,
				sprintf( 'Failed to assert %s is registered for a venue post type without revisions support.', $key )
			);
		}

		unregister_post_type( $test_pt );
	}

	/**
	 * Coverage for filter_readonly_meta.
	 *
	 * Verifies that server-managed meta keys (the static map descriptor blob)
	 * are stripped from REST API meta payloads so the editor cannot write them
	 * directly, while editor-writable keys pass through.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta(): void {
		$instance = Venue_Setup::get_instance();
		$request  = new \WP_REST_Request();

		$request->set_param(
			'meta',
			array(
				'gatherpress_venue_static_map' => array(
					'15' => array(
						'url'  => 'evil.png',
						'hash' => 'x',
					),
				),
				'gatherpress_full_address'     => 'Real St',
				'gatherpress_latitude'         => '12.345',
			)
		);

		$prepared = new \stdClass();
		$result   = $instance->filter_readonly_meta( $prepared, $request );

		$this->assertSame( $prepared, $result, 'Filter must return the prepared post object.' );

		$meta = $request->get_param( 'meta' );

		$this->assertArrayNotHasKey(
			'gatherpress_venue_static_map',
			$meta,
			'gatherpress_venue_static_map is server-generated and must not be writable via REST.'
		);
		$this->assertArrayHasKey(
			'gatherpress_full_address',
			$meta,
			'Editor-writable venue meta should pass through untouched.'
		);
		$this->assertArrayHasKey(
			'gatherpress_latitude',
			$meta,
			'Editor-writable venue meta should pass through untouched.'
		);
	}

	/**
	 * Tests that filter_readonly_meta handles a REST request with no meta param.
	 *
	 * Exercises the is_array() guard: when the request has no meta (or a non-array
	 * value), the filter must return the prepared post object unchanged without
	 * mutating the request.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta_no_meta_param(): void {
		$instance = Venue_Setup::get_instance();
		$request  = new \WP_REST_Request();
		$prepared = new \stdClass();

		$result = $instance->filter_readonly_meta( $prepared, $request );

		$this->assertSame( $prepared, $result, 'Filter must return the prepared post object unchanged.' );
		$this->assertNull( $request->get_param( 'meta' ), 'Missing meta param should remain null.' );
	}

	/**
	 * Tests can_edit_posts_meta authorization callback.
	 *
	 * @covers ::can_edit_posts_meta
	 *
	 * @return void
	 */
	public function test_can_edit_posts_meta(): void {
		$instance = Venue_Setup::get_instance();

		// Test with user who can edit posts.
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertTrue( $instance->can_edit_posts_meta(), 'Editor should be able to edit post meta.' );

		// Test with user who cannot edit posts.
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( $instance->can_edit_posts_meta(), 'Subscriber should not be able to edit post meta.' );

		// Test with logged-out user.
		wp_set_current_user( 0 );

		$this->assertFalse( $instance->can_edit_posts_meta(), 'Logged-out user should not be able to edit post meta.' );
	}

	/**
	 * Coverage for register_taxonomy method.
	 *
	 * @covers ::register_taxonomy
	 *
	 * @return void
	 */
	public function test_register_taxonomy(): void {
		$instance = Venue_Setup::get_instance();

		unregister_taxonomy( Venue::TAXONOMY );

		$this->assertFalse( taxonomy_exists( Venue::TAXONOMY ), 'Failed to assert that taxonomy does not exist.' );

		$instance->register_taxonomy();

		$this->assertTrue( taxonomy_exists( Venue::TAXONOMY ), 'Failed to assert that taxonomy exists.' );

		// Verify taxonomy properties needed for Query Loop block integration.
		$taxonomy = get_taxonomy( Venue::TAXONOMY );

		$this->assertTrue(
			$taxonomy->publicly_queryable,
			'Taxonomy should be publicly queryable for Query Loop block taxonomy filters.'
		);
		$this->assertFalse(
			$taxonomy->rewrite,
			'Taxonomy rewrite should be disabled to prevent public archive URLs.'
		);
		$this->assertTrue(
			$taxonomy->show_in_rest,
			'Taxonomy should be available in REST API.'
		);
		$this->assertFalse(
			$taxonomy->show_ui,
			'Taxonomy should remain hidden from admin UI as a shadow taxonomy.'
		);
	}

	/**
	 * Coverage for add_venue_term.
	 *
	 * @covers ::add_venue_term
	 *
	 * @return void
	 */
	public function test_add_venue_term(): void {
		$instance = Venue_Setup::get_instance();
		$venue    = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();
		$term     = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Failed to assert that term exists.'
		);

		// Delete term to ensure add_venue_term re-creates it.
		wp_delete_term( $term['term_id'], Venue::TAXONOMY );

		$this->assertNull(
			term_exists( $term['term_id'], Venue::TAXONOMY ),
			'Failed to assert that term does not exist after being deleted.'
		);

		$instance->add_venue_term( $venue->ID, $venue, true );

		$term = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertNull(
			term_exists( $term['term_id'], Venue::TAXONOMY ),
			'Failed to assert that term does not exist when $update is true.'
		);

		$instance->add_venue_term( $venue->ID, $venue, false );

		$term = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Failed to assert that term exists.'
		);
	}

	/**
	 * Coverage for maybe_update_term_slug.
	 *
	 * @covers ::maybe_update_term_slug
	 *
	 * @return void
	 */
	public function test_maybe_update_term_slug(): void {
		$instance    = Venue_Setup::get_instance();
		$post_before = $this->mock->post()->get();
		$post_after  = clone $post_before;

		$post_after->post_name .= '-after';

		$instance->maybe_update_term_slug( $post_before->ID, $post_after, $post_before );
		$this->assertNull(
			term_exists( $instance->term_slug_from_post_name( $post_before->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term does not exist.'
		);
		$this->assertNull(
			term_exists( $instance->term_slug_from_post_name( $post_after->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term does not exist.'
		);

		$venue_before = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();
		$venue_after  = clone $venue_before;

		$venue_after->post_name .= '-first';

		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term = term_exists( $instance->term_slug_from_post_name( $venue_after->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Failed to assert that term exists.'
		);

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->term_slug_from_post_name( $venue_after->post_name ),
			'Failed to assert that slugs match.'
		);

		$venue_before = clone $venue_after;
		$venue_after  = clone $venue_before;

		// Delete term to ensure maybe_update_term_slug re-creates it.
		wp_delete_term( $term['term_id'], Venue::TAXONOMY );

		$this->assertNull(
			term_exists( $term['term_id'], Venue::TAXONOMY ),
			'Failed to assert that term does not exist after being deleted.'
		);

		$venue_after->post_name .= '-second';

		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term = term_exists( $instance->term_slug_from_post_name( $venue_after->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Failed to assert that term exists.'
		);

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->term_slug_from_post_name( $venue_after->post_name ),
			'Failed to assert that slugs match.'
		);

		$venue_before = clone $venue_after;

		$venue_after->post_name .= '-third';

		// Setting to draft should not update term.
		$venue_after->post_status = 'draft';
		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term_object = get_term( $term['term_id'] );

		$this->assertNotSame(
			$term_object->slug,
			$instance->term_slug_from_post_name( $venue_after->post_name ),
			'Failed to assert that slugs do not match.'
		);

		// Setting back to trash should update the term.
		$venue_after->post_status = 'trash';
		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->term_slug_from_post_name( $venue_after->post_name ),
			'Failed to assert that slugs match.'
		);

		// Setting back to publish should update the term.
		$venue_after->post_status = 'publish';
		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->term_slug_from_post_name( $venue_after->post_name ),
			'Failed to assert that slugs match.'
		);
	}

	/**
	 * Coverage for delete_venue_term.
	 *
	 * @covers ::delete_venue_term
	 *
	 * @return void
	 */
	public function test_delete_venue_term(): void {
		$instance = Venue_Setup::get_instance();
		$venue    = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();

		$this->assertIsArray(
			term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term exists'
		);

		$instance->delete_venue_term( $venue->ID );

		$this->assertNull(
			term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term was deleted.'
		);
	}

	/**
	 * Coverage for get_venue_meta method.
	 *
	 * @covers ::get_venue_meta
	 *
	 * @return void
	 */
	public function test_get_venue_meta(): void {
		$event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'unit-test-event',
			)
		)->get();
		wp_set_post_terms( $event->ID, 'dummy-venue', Venue::TAXONOMY );

		$venue_meta = Venue_Setup::get_instance()->get_venue_meta( $event->ID, Event::POST_TYPE );

		// Generic test for an in person event.
		$this->assertFalse( $venue_meta['isOnlineEventTerm'] );

		$venue_title = 'Unit Test Venue';

		$venue = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'unit-test-venue',
				'post_title' => $venue_title,
			)
		)->get();

		$venue_meta = Venue_Setup::get_instance()->get_venue_meta( $venue->ID, Venue::POST_TYPE );

		// Test for a venue post.
		$this->assertEquals(
			$venue_title,
			$venue_meta['name'],
			'Failed to assert venue title matches the venue meta title.'
		);
	}

	/**
	 * Coverage for get_venue_meta method with valid JSON venue information.
	 *
	 * @covers ::get_venue_meta
	 *
	 * @return void
	 */
	public function test_get_venue_meta_with_venue_info_json(): void {
		$venue_title = 'Unit Test Venue With Info';

		$venue = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'unit-test-venue-with-info',
				'post_title' => $venue_title,
			)
		)->get();

		add_post_meta( $venue->ID, 'gatherpress_full_address', '123 Test Street, Test City, TS 12345' );
		add_post_meta( $venue->ID, 'gatherpress_phone_number', '555-123-4567' );
		add_post_meta( $venue->ID, 'gatherpress_website', 'https://example.com' );
		add_post_meta( $venue->ID, 'gatherpress_latitude', '40.7128' );
		add_post_meta( $venue->ID, 'gatherpress_longitude', '-74.0060' );

		$venue_meta = Venue_Setup::get_instance()->get_venue_meta( $venue->ID, Venue::POST_TYPE );

		$this->assertEquals(
			$venue_title,
			$venue_meta['name'],
			'Failed to assert venue title matches.'
		);
		$this->assertEquals(
			'123 Test Street, Test City, TS 12345',
			$venue_meta['full_address'],
			'Failed to assert full_address matches.'
		);
		$this->assertEquals(
			'555-123-4567',
			$venue_meta['phone_number'],
			'Failed to assert phone_number matches.'
		);
		$this->assertEquals(
			'https://example.com',
			$venue_meta['website'],
			'Failed to assert website matches.'
		);
		$this->assertEquals(
			'40.7128',
			$venue_meta['latitude'],
			'Failed to assert latitude matches.'
		);
		$this->assertEquals(
			'-74.0060',
			$venue_meta['longitude'],
			'Failed to assert longitude matches.'
		);
	}

	/**
	 * Coverage for maybe_apply_venue_template method.
	 *
	 * Verifies that the venue template content is applied when a venue
	 * is saved with empty content (e.g., created via REST API).
	 *
	 * @covers ::maybe_apply_venue_template
	 *
	 * @return void
	 */
	public function test_maybe_apply_venue_template(): void {
		$instance = Venue_Setup::get_instance();

		// Create a venue post with empty content (simulating REST API creation).
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => Venue::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		$post = get_post( $post_id );

		// Call the method directly (simulating initial REST insert).
		$instance->maybe_apply_venue_template( $post_id, $post, false );

		// Refresh the post data.
		$updated_post = get_post( $post_id );

		$this->assertNotEmpty(
			$updated_post->post_content,
			'Venue with empty content should have template applied.'
		);

		$this->assertStringContainsString(
			'wp:gatherpress/venue',
			$updated_post->post_content,
			'Template content should contain venue block.'
		);
	}

	/**
	 * Coverage for maybe_apply_venue_template skipping updates to existing venues.
	 *
	 * When a user intentionally clears the content of an existing venue and saves,
	 * the template must NOT silently re-seed the content.
	 *
	 * @covers ::maybe_apply_venue_template
	 *
	 * @return void
	 */
	public function test_maybe_apply_venue_template_skips_updates(): void {
		$instance = Venue_Setup::get_instance();

		// Factory creation triggers the save_post hook with $update=false which
		// seeds the template. Reset content directly to simulate the user having
		// cleared an existing venue and re-saved it.
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => Venue::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '',
			)
		);

		$post = get_post( $post_id );
		$this->assertEmpty(
			$post->post_content,
			'Test setup failure: content should be empty before the update call.'
		);

		// Simulate an update (user saved an existing venue after clearing its content).
		$instance->maybe_apply_venue_template( $post_id, $post, true );

		$updated_post = get_post( $post_id );

		$this->assertEmpty(
			$updated_post->post_content,
			'Updates to an existing venue must not re-seed the template.'
		);
	}

	/**
	 * Coverage for maybe_apply_venue_template skipping non-empty content.
	 *
	 * Verifies that the template is NOT applied when venue already has content.
	 *
	 * @covers ::maybe_apply_venue_template
	 *
	 * @return void
	 */
	public function test_maybe_apply_venue_template_skips_non_empty(): void {
		$instance         = Venue_Setup::get_instance();
		$existing_content = '<!-- wp:paragraph --><p>Existing content.</p><!-- /wp:paragraph -->';

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => Venue::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => $existing_content,
			)
		);

		$post = get_post( $post_id );

		$instance->maybe_apply_venue_template( $post_id, $post, false );

		// Content should remain unchanged.
		$updated_post = get_post( $post_id );

		$this->assertSame(
			$existing_content,
			$updated_post->post_content,
			'Venue with existing content should not be modified.'
		);
	}

	/**
	 * Coverage for maybe_apply_venue_template skipping draft posts.
	 *
	 * Verifies that the template is NOT applied to draft venues.
	 *
	 * @covers ::maybe_apply_venue_template
	 *
	 * @return void
	 */
	public function test_maybe_apply_venue_template_skips_drafts(): void {
		$instance = Venue_Setup::get_instance();

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => Venue::POST_TYPE,
				'post_status'  => 'draft',
				'post_content' => '',
			)
		);

		$post = get_post( $post_id );

		$instance->maybe_apply_venue_template( $post_id, $post, false );

		// Content should remain empty for drafts.
		$updated_post = get_post( $post_id );

		$this->assertEmpty(
			$updated_post->post_content,
			'Draft venue should not have template applied.'
		);
	}

	/**
	 * Coverage for maybe_apply_venue_template when pattern is not registered.
	 *
	 * Verifies that the method returns early when the venue-template
	 * pattern is not found in the registry.
	 *
	 * @covers ::maybe_apply_venue_template
	 *
	 * @return void
	 */
	public function test_maybe_apply_venue_template_skips_unregistered_pattern(): void {
		$instance = Venue_Setup::get_instance();
		$registry = WP_Block_Patterns_Registry::get_instance();

		// Unregister the venue-template pattern.
		$registry->unregister( 'gatherpress/venue-template' );

		$post_id = $this->factory->post->create(
			array(
				'post_type'    => Venue::POST_TYPE,
				'post_status'  => 'publish',
				'post_content' => '',
			)
		);

		$post = get_post( $post_id );

		$instance->maybe_apply_venue_template( $post_id, $post, false );

		// Content should remain empty when pattern is not registered.
		$updated_post = get_post( $post_id );

		$this->assertEmpty(
			$updated_post->post_content,
			'Venue should not have template applied when pattern is unregistered.'
		);

		// Re-register the pattern for other tests.
		$registry->register(
			'gatherpress/venue-template',
			array(
				'title'    => 'Venue Post Default Content',
				'content'  => '<!-- wp:gatherpress/venue /-->',
				'inserter' => false,
				'source'   => 'plugin',
			)
		);
	}

	/**
	 * Coverage for add_editor_settings method.
	 *
	 * @covers ::add_editor_settings
	 *
	 * @return void
	 */
	public function test_add_editor_settings(): void {
		$instance = Venue_Setup::get_instance();

		// Test with an empty settings array.
		$result = $instance->add_editor_settings( array() );

		$this->assertArrayHasKey(
			'gatherpress',
			$result,
			'Failed to assert that the gatherpress key is added to an empty settings array.'
		);
		$this->assertArrayHasKey(
			'config',
			$result['gatherpress'],
			'Failed to assert that config key is present in gatherpress settings.'
		);
		$this->assertArrayHasKey(
			'venuePostTypes',
			$result['gatherpress']['config'],
			'Failed to assert that venuePostTypes is present in gatherpress config.'
		);
		$this->assertIsArray(
			$result['gatherpress']['config']['venuePostTypes'],
			'Failed to assert that venuePostTypes is an array.'
		);

		// Test that existing gatherpress settings are preserved and venuePostTypes is appended.
		$settings = array(
			'gatherpress' => array( 'existingKey' => 'existingValue' ),
		);
		$result   = $instance->add_editor_settings( $settings );

		$this->assertSame(
			'existingValue',
			$result['gatherpress']['existingKey'],
			'Failed to assert that existing gatherpress settings are preserved.'
		);
		$this->assertArrayHasKey(
			'venuePostTypes',
			$result['gatherpress']['config'],
			'Failed to assert that venuePostTypes is added alongside existing gatherpress settings.'
		);
	}

	/**
	 * Coverage for add_venue_term when post type does not support gatherpress-venue-information.
	 *
	 * @covers ::add_venue_term
	 *
	 * @return void
	 */
	public function test_add_venue_term_unsupported_post_type(): void {
		$instance = Venue_Setup::get_instance();
		$post     = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		// Calling add_venue_term on a standard 'post' should return early and create no term.
		$instance->add_venue_term( $post->ID, $post, false );

		$this->assertNull(
			term_exists( $instance->term_slug_from_post_name( $post->post_name ), Venue::TAXONOMY ),
			'Failed to assert that no venue term was created for a post type without venue-information support.'
		);
	}

	/**
	 * Coverage for get_venue_post_from_term_slug.
	 *
	 * @covers ::get_venue_post_from_term_slug
	 *
	 * @return void
	 */
	public function test_get_venue_post_from_term_slug(): void {
		$venue  = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'unit-test',
			)
		)->get();
		$result = Venue_Setup::get_instance()->get_venue_post_from_term_slug( '_unit-test' );

		$this->assertEquals(
			$venue->ID,
			$result->ID,
			'Failed to assert that IDs match.'
		);
	}

	/**
	 * Coverage for get_venue_post_from_event_post_id.
	 *
	 * @covers ::get_venue_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_venue_post_from_event_post_id(): void {
		$instance = Venue_Setup::get_instance();
		$venue    = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'test-venue-for-event',
				'post_title' => 'Test Venue For Event',
			)
		)->get();

		$term_slug = $instance->term_slug_from_post_name( $venue->post_name );
		wp_insert_term(
			'Test Venue For Event',
			Venue::TAXONOMY,
			array( 'slug' => $term_slug )
		);

		$event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'test-event-with-venue',
			)
		)->get();

		wp_set_post_terms( $event->ID, $term_slug, Venue::TAXONOMY );

		$result = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event->ID );

		$this->assertInstanceOf(
			'WP_Post',
			$result,
			'Should return a WP_Post instance.'
		);
		$this->assertEquals(
			$venue->ID,
			$result->ID,
			'Should return the correct venue post.'
		);
	}

	/**
	 * Coverage for get_venue_post_from_event_post_id when the event has no venue terms.
	 *
	 * @covers ::get_venue_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_venue_post_from_event_post_id_no_terms(): void {
		$event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'test-event-no-venue',
			)
		)->get();

		$result = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event->ID );

		$this->assertNull(
			$result,
			'Should return null when event has no venue terms.'
		);
	}

	/**
	 * Coverage for get_venue_post_from_event_post_id when an event carries both
	 * `online-event` and a real venue term: the physical venue should win.
	 *
	 * @covers ::get_venue_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_venue_post_from_event_post_id_skips_online_event_sentinel(): void {
		$instance = Venue_Setup::get_instance();
		$venue    = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'library-venue',
				'post_title' => 'Library Venue',
			)
		)->get();

		$venue_term_slug = $instance->term_slug_from_post_name( $venue->post_name );

		// Ensure the online-event sentinel exists in the taxonomy.
		if ( ! term_exists( 'online-event', Venue::TAXONOMY ) ) {
			wp_insert_term( 'Online event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}

		$event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'hybrid-event',
			)
		)->get();

		// Attach both terms in a deliberate order that would have tripped the
		// old `$venue_terms[0]` logic when the sentinel came first.
		wp_set_post_terms( $event->ID, array( 'online-event', $venue_term_slug ), Venue::TAXONOMY );

		$result = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event->ID );

		$this->assertInstanceOf(
			'WP_Post',
			$result,
			'Should skip the online-event sentinel and return the real venue post.'
		);
		$this->assertEquals(
			$venue->ID,
			$result->ID,
			'Should return the library venue, not the online-event sentinel.'
		);
	}

	/**
	 * Coverage for term_slug_from_post_name — pure formatter prepends an underscore.
	 *
	 * @covers ::term_slug_from_post_name
	 *
	 * @return void
	 */
	public function test_term_slug_from_post_name(): void {
		$this->assertSame(
			'_unit-test',
			Venue_Setup::get_instance()->term_slug_from_post_name( 'unit-test' ),
			'Failed to assert that term_slug_from_post_name prepends an underscore.'
		);
	}

	/**
	 * Coverage for get_taxonomy.
	 *
	 * @covers ::get_taxonomy
	 *
	 * @return void
	 */
	public function test_get_taxonomy(): void {
		$instance = Venue_Setup::get_instance();

		// No argument falls back to the default venue post type.
		$this->assertSame(
			'_' . Venue::POST_TYPE,
			$instance->get_taxonomy(),
			'Failed to assert that get_taxonomy defaults to the built-in venue taxonomy.'
		);

		// Empty string also falls back to the default venue post type.
		$this->assertSame(
			'_' . Venue::POST_TYPE,
			$instance->get_taxonomy( '' ),
			'Failed to assert that get_taxonomy with empty string defaults to the built-in venue taxonomy.'
		);

		// Custom venue post type returns the correctly prefixed taxonomy.
		$this->assertSame(
			'_custom_venue_type',
			$instance->get_taxonomy( 'custom_venue_type' ),
			'Failed to assert that get_taxonomy prepends an underscore for a custom venue post type.'
		);
	}

	/**
	 * Coverage for get_venue_post_type.
	 *
	 * @covers ::get_venue_post_type
	 *
	 * @return void
	 */
	public function test_get_venue_post_type(): void {
		$this->assertSame(
			Venue::POST_TYPE,
			Venue_Setup::get_instance()->get_venue_post_type(),
			'Failed to assert that get_venue_post_type returns the default venue post type.'
		);
	}

	/**
	 * Coverage for get_venue_post_type with filter override.
	 *
	 * @covers ::get_venue_post_type
	 *
	 * @return void
	 */
	public function test_get_venue_post_type_with_filter(): void {
		add_filter( 'gatherpress_venue_post_type', fn() => 'custom_venue_type' );

		// Pass a unique event post type to avoid returning a cached result from a prior
		// test run that used the default (empty-string) key.
		$this->assertSame(
			'custom_venue_type',
			Venue_Setup::get_instance()->get_venue_post_type( 'test_custom_event_type' ),
			'Failed to assert that get_venue_post_type returns the filtered post type.'
		);

		remove_all_filters( 'gatherpress_venue_post_type' );
	}

	/**
	 * Coverage for get_venue_post_type_map.
	 *
	 * @covers ::get_venue_post_type_map
	 *
	 * @return void
	 */
	public function test_get_venue_post_type_map(): void {
		$map = Venue_Setup::get_instance()->get_venue_post_type_map();

		$this->assertIsArray(
			$map,
			'Failed to assert that the venue post type map is an array.'
		);
		$this->assertArrayHasKey(
			Event::POST_TYPE,
			$map,
			'Failed to assert that the map contains the default event post type.'
		);
		$this->assertSame(
			Venue::POST_TYPE,
			$map[ Event::POST_TYPE ],
			'Failed to assert that the default event post type maps to the default venue post type.'
		);
	}

	/**
	 * Coverage for get_localized_post_type_slug.
	 *
	 * @covers ::get_localized_post_type_slug
	 *
	 * @return void
	 */
	public function test_get_localized_post_type_slug(): void {
		$instance = Venue_Setup::get_instance();

		$this->assertSame(
			'venue',
			$instance->get_localized_post_type_slug(),
			'Failed to assert English post type slug is "venue".'
		);

		$user_id = $this->factory->user->create();
		update_user_meta( $user_id, 'locale', 'es_ES' );
		switch_to_user_locale( $user_id );

		// @todo This assertion CAN NOT FAIL,
		// until real translations do exist in the wp-env instance.
		// Because WordPress doesn't have any translation files to load,
		// it will return the string in English.
		$this->assertSame(
			'venue',
			$instance->get_localized_post_type_slug(),
			'Failed to assert post type slug is "venue", even the locale is not English anymore.'
		);
		// But at least the restoring of the user locale can be tested, without .po files.
		$this->assertSame(
			'es_ES',
			determine_locale(),
			'Failed to assert locale was reset to Spanish, after switching to ~ and restoring from English.'
		);

		// Restore default locale for following tests.
		switch_to_locale( 'en_US' );

		// This checks that the post type is still registered with the same
		// 'Admin menu and post type singular name' label, used by the method under test.
		$filter = static function ( string $translation, string $text, string $context ): string {
			if ( 'Venue' !== $text || 'Admin menu and post type singular name' !== $context ) {
				return $translation;
			}
			return 'Ünit Tést';
		};

		add_filter( 'gettext_with_context_gatherpress', $filter, 10, 3 );

		$this->assertSame(
			'unit-test',
			$instance->get_localized_post_type_slug(),
			'Failed to assert the post type slug is "unit-test".'
		);

		remove_filter( 'gettext_with_context_gatherpress', $filter );

		// Test restore_previous_locale() path by switching to a different locale first.
		switch_to_locale( 'es_ES' );
		$this->assertSame(
			'venue',
			$instance->get_localized_post_type_slug(),
			'Failed to assert post type slug is "venue" after locale restore.'
		);
		// Verify we're back to Spanish after the method restored the previous locale.
		$this->assertSame(
			'es_ES',
			determine_locale(),
			'Failed to assert locale was restored to Spanish.'
		);

		// Clean up: restore to en_US for other tests.
		restore_previous_locale();
	}

	/**
	 * Coverage for get_localized_post_type_slug with locale restoration.
	 *
	 * Tests that restore_previous_locale() is called when switch_to_locale() succeeds.
	 * This test creates a scenario where the global locale differs from get_locale().
	 *
	 * @covers ::get_localized_post_type_slug
	 *
	 * @return void
	 */
	public function test_get_localized_post_type_slug_restores_locale(): void {
		// Create a scenario where get_locale() returns a different value.
		// than the current global locale by using the locale filter.
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Intentionally overriding locale.
		$locale_filter = static function ( $locale ) {
			return 'de_DE';
		};

		add_filter( 'locale', $locale_filter );

		$slug = Venue_Setup::get_instance()->get_localized_post_type_slug();

		$this->assertNotEmpty( $slug, 'Failed to assert slug is not empty.' );

		remove_filter( 'locale', $locale_filter );

		$this->assertSame(
			'en_US',
			determine_locale(),
			'Failed to assert locale was restored after method execution.'
		);
	}

	/**
	 * Coverage for add_venue_term's early return when the term already exists.
	 *
	 * @covers ::add_venue_term
	 *
	 * @return void
	 */
	public function test_add_venue_term_skips_when_term_already_exists(): void {
		$instance = Venue_Setup::get_instance();
		$venue    = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();

		// First save creates the term.
		$term = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertIsArray( $term, 'Expected term to be created on first save.' );

		$term_id = $term['term_id'];

		// Second call with $update=false should detect the existing term and bail.
		$instance->add_venue_term( $venue->ID, $venue, false );

		$term_after = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertSame(
			$term_id,
			$term_after['term_id'],
			'Expected the existing term to be untouched rather than replaced.'
		);
	}

	/**
	 * Coverage for maybe_update_term_slug's no-change early return.
	 *
	 * @covers ::maybe_update_term_slug
	 *
	 * @return void
	 */
	public function test_maybe_update_term_slug_no_changes(): void {
		$instance = Venue_Setup::get_instance();
		$venue    = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();

		// Call with identical before/after — neither slug nor title changed.
		$instance->maybe_update_term_slug( $venue->ID, $venue, $venue );

		$term = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Expected the original term to still exist — no-change path should leave terms untouched.'
		);
	}

	/**
	 * Coverage for delete_venue_term's early return when the post type does not
	 * declare gatherpress-venue-information support.
	 *
	 * @covers ::delete_venue_term
	 *
	 * @return void
	 */
	public function test_delete_venue_term_unsupported_post_type(): void {
		$instance = Venue_Setup::get_instance();

		// Seed a sentinel term so we can prove the method didn't touch it.
		if ( ! term_exists( 'online-event', Venue::TAXONOMY ) ) {
			wp_insert_term( 'Online event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}

		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		$instance->delete_venue_term( $post->ID );

		$this->assertNotNull(
			term_exists( 'online-event', Venue::TAXONOMY ),
			'The sentinel term must not be affected when delete_venue_term is called on a non-venue post.'
		);
	}

	/**
	 * Coverage for get_venue_meta when the event post has a linked venue.
	 *
	 * @covers ::get_venue_meta
	 *
	 * @return void
	 */
	public function test_get_venue_meta_event_with_linked_venue(): void {
		$instance    = Venue_Setup::get_instance();
		$venue_title = 'Linked Venue';
		$venue       = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'linked-venue-for-meta',
				'post_title' => $venue_title,
			)
		)->get();

		// Attach the venue to the event via the taxonomy.
		$term_slug = $instance->term_slug_from_post_name( $venue->post_name );
		$event     = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		wp_set_post_terms( $event->ID, $term_slug, Venue::TAXONOMY );

		add_post_meta( $venue->ID, 'gatherpress_full_address', '1 Library Lane' );
		add_post_meta( $venue->ID, 'gatherpress_phone_number', '555-1234' );
		add_post_meta( $venue->ID, 'gatherpress_website', 'https://example.test' );
		add_post_meta( $venue->ID, 'gatherpress_latitude', '40.0' );
		add_post_meta( $venue->ID, 'gatherpress_longitude', '-70.0' );

		$venue_meta = $instance->get_venue_meta( $event->ID, Event::POST_TYPE );

		$this->assertSame(
			$venue_title,
			$venue_meta['name'],
			'Expected venue name to come from the linked venue post.'
		);
		$this->assertSame( '1 Library Lane', $venue_meta['full_address'] );
		$this->assertFalse( $venue_meta['isOnlineEventTerm'] );
	}

	/**
	 * Coverage for get_venue_post_from_event_post_id when all attached terms are
	 * non-`_`-prefixed sentinels (e.g. only `online-event`).
	 *
	 * Exercises the `continue` and the post-loop `return null` fallthrough.
	 *
	 * @covers ::get_venue_post_from_event_post_id
	 *
	 * @return void
	 */
	/**
	 * Coverage for is_venue_term_slug.
	 *
	 * @covers ::is_venue_term_slug
	 *
	 * @return void
	 */
	public function test_is_venue_term_slug(): void {
		$instance = Venue_Setup::get_instance();

		$this->assertTrue( $instance->is_venue_term_slug( '_my-venue' ) );
		$this->assertFalse( $instance->is_venue_term_slug( 'online-event' ) );
		$this->assertFalse( $instance->is_venue_term_slug( '' ) );
	}

	/**
	 * Coverage for taxonomy_for_event_post_type.
	 *
	 * @covers ::taxonomy_for_event_post_type
	 *
	 * @return void
	 */
	public function test_taxonomy_for_event_post_type(): void {
		$this->assertSame(
			Venue::TAXONOMY,
			Venue_Setup::get_instance()->taxonomy_for_event_post_type( Event::POST_TYPE ),
			'Expected the default event post type to resolve to the default venue taxonomy.'
		);
	}

	/**
	 * Coverage for get_venue_post_from_event_post_id when all attached terms are
	 * non-`_`-prefixed sentinels (e.g. only `online-event`).
	 *
	 * Exercises the `continue` and the post-loop `return null` fallthrough.
	 *
	 * @covers ::get_venue_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_venue_post_from_event_post_id_only_sentinel_terms(): void {
		$instance = Venue_Setup::get_instance();

		if ( ! term_exists( 'online-event', Venue::TAXONOMY ) ) {
			wp_insert_term( 'Online event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}

		$event = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();

		// Attach only the online-event sentinel — no `_`-prefixed venue term.
		wp_set_post_terms( $event->ID, array( 'online-event' ), Venue::TAXONOMY );

		$this->assertNull(
			$instance->get_venue_post_from_event_post_id( $event->ID ),
			'Expected null when the only attached term is the non-venue sentinel.'
		);
	}
}
