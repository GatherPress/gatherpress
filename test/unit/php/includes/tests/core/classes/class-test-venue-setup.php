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
				'callback' => array( $instance, 'maybe_register_post_save_hook' ),
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
				'name'     => 'delete_post',
				'priority' => 10,
				'callback' => array( $instance, 'delete_venue_term' ),
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
	 * Coverage for maybe_register_post_save_hook method.
	 *
	 * Verifies that a save_post_{$type} action is registered when the given
	 * post type declares the 'gatherpress-venue-information' support.
	 *
	 * @covers ::maybe_register_post_save_hook
	 *
	 * @return void
	 */
	public function test_maybe_register_post_save_hook(): void {
		$instance = Venue_Setup::get_instance();

		foreach ( get_post_types_by_support( 'gatherpress-venue-information' ) as $post_type ) {
			$instance->maybe_register_post_save_hook( $post_type );
			$this->assertSame(
				10,
				has_action(
					sprintf( 'save_post_%s', $post_type ),
					array( $instance, 'add_venue_term' )
				),
				sprintf( 'Failed to assert that save_post_%s has the add_venue_term action.', $post_type )
			);
		}
	}

	/**
	 * Bails when the post type does not declare venue-information support.
	 *
	 * @covers ::maybe_register_post_save_hook
	 *
	 * @return void
	 */
	public function test_maybe_register_post_save_hook_skips_unsupported_post_type(): void {
		$instance = Venue_Setup::get_instance();

		$instance->maybe_register_post_save_hook( 'post' );

		$this->assertFalse(
			has_action( 'save_post_post', array( $instance, 'add_venue_term' ) ),
			'Failed to assert no save hook is registered for a post type without venue-information support.'
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

		unregister_post_meta( Venue::POST_TYPE, 'gatherpress_venue_information' );
		unregister_post_meta( Venue::POST_TYPE, 'gatherpress_venue_map_show' );
		unregister_post_meta( Venue::POST_TYPE, 'geo_latitude' );
		unregister_post_meta( Venue::POST_TYPE, 'geo_longitude' );
		unregister_post_meta( Venue::POST_TYPE, 'geo_address' );
		unregister_post_meta( Venue::POST_TYPE, 'geo_public' );

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		$this->assertArrayNotHasKey(
			'gatherpress_venue_information',
			$meta,
			'Failed to assert that gatherpress_venue_information does not exist.'
		);

		$this->assertArrayNotHasKey(
			'gatherpress_venue_map_show',
			$meta,
			'Failed to assert that gatherpress_venue_map_show does not exist.'
		);

		$this->assertArrayNotHasKey(
			'geo_latitude',
			$meta,
			'Failed to assert that geo_latitude does not exist.'
		);

		$instance->maybe_register_post_meta( Venue::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		$this->assertArrayHasKey(
			'gatherpress_venue_information',
			$meta,
			'Failed to assert that gatherpress_venue_information exists for gatherpress-venue-information support.'
		);

		$this->assertArrayHasKey(
			'gatherpress_venue_map_show',
			$meta,
			'Failed to assert that gatherpress_venue_map_show exists for gatherpress-venue-map support.'
		);

		foreach ( array( 'geo_latitude', 'geo_longitude', 'geo_address', 'geo_public' ) as $key ) {
			$this->assertArrayHasKey(
				$key,
				$meta,
				sprintf( 'Failed to assert that %s is registered for gatherpress-venue-information support.', $key )
			);
		}
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
			'gatherpress_venue_information',
			'geo_latitude',
			'geo_longitude',
			'geo_address',
			'geo_public',
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
	 * Coverage for set_geodata method.
	 *
	 * Verifies that the WordPress Geodata standard meta keys are derived from
	 * gatherpress_venue_information JSON and written as individual post_meta entries.
	 *
	 * @covers ::set_geodata
	 *
	 * @return void
	 */
	public function test_set_geodata(): void {
		$instance = Venue_Setup::get_instance();

		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$venue_information = wp_json_encode(
			array(
				'fullAddress' => '123 Main St, Paris',
				'latitude'    => '48.856613',
				'longitude'   => '2.352222',
			)
		);

		update_post_meta( $venue_id, 'gatherpress_venue_information', $venue_information );

		$instance->set_geodata( $venue_id );

		$this->assertSame(
			'48.856613',
			get_post_meta( $venue_id, 'geo_latitude', true ),
			'Failed to assert that geo_latitude was set from the JSON meta.'
		);
		$this->assertSame(
			'2.352222',
			get_post_meta( $venue_id, 'geo_longitude', true ),
			'Failed to assert that geo_longitude was set from the JSON meta.'
		);
		$this->assertSame(
			'123 Main St, Paris',
			get_post_meta( $venue_id, 'geo_address', true ),
			'Failed to assert that geo_address was set from the JSON meta.'
		);
		$this->assertSame(
			'1',
			get_post_meta( $venue_id, 'geo_public', true ),
			'Failed to assert that geo_public is 1 for a published venue.'
		);
	}

	/**
	 * Tests that set_geodata returns early for post types without venue information support.
	 *
	 * @covers ::set_geodata
	 *
	 * @return void
	 */
	public function test_set_geodata_unsupported_post_type(): void {
		$instance = Venue_Setup::get_instance();

		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		$instance->set_geodata( $post_id );

		$this->assertSame(
			'',
			get_post_meta( $post_id, 'geo_latitude', true ),
			'Failed to assert that geo_latitude is not written for unsupported post types.'
		);
	}

	/**
	 * Tests that geo_public is 0 for non-published venues.
	 *
	 * @covers ::set_geodata
	 *
	 * @return void
	 */
	public function test_set_geodata_private_post(): void {
		$instance = Venue_Setup::get_instance();

		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'draft',
			)
		);

		$instance->set_geodata( $venue_id );

		$this->assertSame(
			'0',
			get_post_meta( $venue_id, 'geo_public', true ),
			'Failed to assert that geo_public is 0 for a non-published venue.'
		);
	}

	/**
	 * Tests that invalid JSON in gatherpress_venue_information is handled gracefully.
	 *
	 * @covers ::set_geodata
	 *
	 * @return void
	 */
	public function test_set_geodata_invalid_json(): void {
		$instance = Venue_Setup::get_instance();

		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $venue_id, 'gatherpress_venue_information', 'not-valid-json' );

		$instance->set_geodata( $venue_id );

		$this->assertSame(
			'',
			get_post_meta( $venue_id, 'geo_latitude', true ),
			'Failed to assert geo_latitude is empty when JSON is invalid.'
		);
		$this->assertSame(
			'',
			get_post_meta( $venue_id, 'geo_address', true ),
			'Failed to assert geo_address is empty when JSON is invalid.'
		);
	}

	/**
	 * Tests that partial JSON (missing keys or non-numeric lat/lng) is handled gracefully.
	 *
	 * The isset() + is_numeric() fallbacks are the main path for messy real-world data
	 * (legacy venues, partial imports). This exercises each combination: a present but
	 * non-numeric latitude is stored empty, a missing longitude is stored empty, and a
	 * present fullAddress still flows through.
	 *
	 * @covers ::set_geodata
	 *
	 * @return void
	 */
	public function test_set_geodata_partial_json(): void {
		$instance = Venue_Setup::get_instance();

		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		update_post_meta(
			$venue_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'latitude'    => 'not-a-number',
					'fullAddress' => '123 Main St',
				)
			)
		);

		$instance->set_geodata( $venue_id );

		$this->assertSame(
			'',
			get_post_meta( $venue_id, 'geo_latitude', true ),
			'Failed to assert non-numeric latitude is stored empty.'
		);
		$this->assertSame(
			'',
			get_post_meta( $venue_id, 'geo_longitude', true ),
			'Failed to assert missing longitude is stored empty.'
		);
		$this->assertSame(
			'123 Main St',
			get_post_meta( $venue_id, 'geo_address', true ),
			'Failed to assert fullAddress flows through when lat/lng are invalid.'
		);
	}

	/**
	 * Tests that set_geodata skips revision posts.
	 *
	 * The wp_after_insert_post hook fires for revisions; set_geodata must not write
	 * derived meta onto the revision itself since the parent post is the authoritative
	 * source.
	 *
	 * @covers ::set_geodata
	 *
	 * @return void
	 */
	public function test_set_geodata_skips_revisions(): void {
		$instance = Venue_Setup::get_instance();

		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		update_post_meta(
			$venue_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'latitude'  => '48.856613',
					'longitude' => '2.352222',
				)
			)
		);

		$revision_id = wp_save_post_revision( $venue_id );

		$this->assertIsInt( $revision_id, 'wp_save_post_revision should return a revision ID.' );

		$instance->set_geodata( (int) $revision_id );

		$this->assertSame(
			'',
			get_post_meta( (int) $revision_id, 'geo_latitude', true ),
			'Failed to assert revision posts are skipped (no geo_latitude written).'
		);
	}

	/**
	 * Tests that set_geodata skips autosave posts.
	 *
	 * The wp_after_insert_post hook fires for autosaves as well as revisions;
	 * set_geodata must skip both so derived meta isn't written onto draft copies.
	 *
	 * @covers ::set_geodata
	 *
	 * @return void
	 */
	public function test_set_geodata_skips_autosaves(): void {
		$instance = Venue_Setup::get_instance();

		$author_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $author_id );

		$venue_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $author_id,
			)
		);

		update_post_meta(
			$venue_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'latitude'  => '48.856613',
					'longitude' => '2.352222',
				)
			)
		);

		$autosave_id = wp_create_post_autosave(
			array(
				'post_ID'      => $venue_id,
				'post_content' => 'Autosave draft.',
				'post_title'   => 'Autosave Title',
			)
		);

		$this->assertIsInt( $autosave_id, 'wp_create_post_autosave should return an autosave ID.' );

		$instance->set_geodata( (int) $autosave_id );

		$this->assertSame(
			'',
			get_post_meta( (int) $autosave_id, 'geo_latitude', true ),
			'Failed to assert autosave posts are skipped (no geo_latitude written).'
		);
	}

	/**
	 * End-to-end coverage: verifies set_geodata is wired to wp_after_insert_post.
	 *
	 * Inserts a venue via wp_insert_post with venue information meta, then confirms
	 * the derived geo_* keys are populated without an explicit call to set_geodata().
	 *
	 * @covers ::set_geodata
	 *
	 * @return void
	 */
	public function test_set_geodata_runs_on_wp_after_insert_post(): void {
		// Ensure the Venue singleton is instantiated so its hooks are registered.
		Venue_Setup::get_instance();

		$venue_id = wp_insert_post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'End to End Venue',
				'meta_input'  => array(
					'gatherpress_venue_information' => wp_json_encode(
						array(
							'fullAddress' => '1600 Pennsylvania Ave NW',
							'latitude'    => '38.8977',
							'longitude'   => '-77.0365',
						)
					),
				),
			)
		);

		$this->assertIsInt( $venue_id, 'wp_insert_post should return a venue post ID.' );
		$this->assertGreaterThan( 0, $venue_id, 'wp_insert_post should return a positive post ID.' );

		$this->assertSame(
			'38.8977',
			get_post_meta( $venue_id, 'geo_latitude', true ),
			'Failed to assert geo_latitude was set via wp_after_insert_post.'
		);
		$this->assertSame(
			'-77.0365',
			get_post_meta( $venue_id, 'geo_longitude', true ),
			'Failed to assert geo_longitude was set via wp_after_insert_post.'
		);
		$this->assertSame(
			'1600 Pennsylvania Ave NW',
			get_post_meta( $venue_id, 'geo_address', true ),
			'Failed to assert geo_address was set via wp_after_insert_post.'
		);
	}

	/**
	 * Coverage for filter_readonly_meta.
	 *
	 * Verifies that geo_* meta keys are stripped from REST API meta payloads
	 * so the editor cannot write derived values directly.
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
				'geo_latitude'                  => '99.99',
				'geo_longitude'                 => '99.99',
				'geo_address'                   => 'Hack St',
				'geo_public'                    => 0,
				'gatherpress_venue_information' => '{"fullAddress":"Real St"}',
			)
		);

		$prepared = new \stdClass();
		$result   = $instance->filter_readonly_meta( $prepared, $request );

		$this->assertSame( $prepared, $result, 'Filter must return the prepared post object.' );

		$meta = $request->get_param( 'meta' );

		$this->assertArrayNotHasKey( 'geo_latitude', $meta, 'geo_latitude should be stripped.' );
		$this->assertArrayNotHasKey( 'geo_longitude', $meta, 'geo_longitude should be stripped.' );
		$this->assertArrayNotHasKey( 'geo_address', $meta, 'geo_address should be stripped.' );
		$this->assertArrayNotHasKey( 'geo_public', $meta, 'geo_public should be stripped.' );
		$this->assertArrayHasKey(
			'gatherpress_venue_information',
			$meta,
			'Non-geo meta keys should pass through untouched.'
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
		$term     = term_exists( $instance->get_venue_term_slug( $venue->post_name ), Venue::TAXONOMY );

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

		$term = term_exists( $instance->get_venue_term_slug( $venue->post_name ), Venue::TAXONOMY );

		$this->assertNull(
			term_exists( $term['term_id'], Venue::TAXONOMY ),
			'Failed to assert that term does not exist when $update is true.'
		);

		$instance->add_venue_term( $venue->ID, $venue, false );

		$term = term_exists( $instance->get_venue_term_slug( $venue->post_name ), Venue::TAXONOMY );

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
			term_exists( $instance->get_venue_term_slug( $post_before->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term does not exist.'
		);
		$this->assertNull(
			term_exists( $instance->get_venue_term_slug( $post_after->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term does not exist.'
		);

		$venue_before = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();
		$venue_after  = clone $venue_before;

		$venue_after->post_name .= '-first';

		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term = term_exists( $instance->get_venue_term_slug( $venue_after->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Failed to assert that term exists.'
		);

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->get_venue_term_slug( $venue_after->post_name ),
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

		$term = term_exists( $instance->get_venue_term_slug( $venue_after->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Failed to assert that term exists.'
		);

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->get_venue_term_slug( $venue_after->post_name ),
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
			$instance->get_venue_term_slug( $venue_after->post_name ),
			'Failed to assert that slugs do not match.'
		);

		// Setting back to trash should update the term.
		$venue_after->post_status = 'trash';
		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->get_venue_term_slug( $venue_after->post_name ),
			'Failed to assert that slugs match.'
		);

		// Setting back to publish should update the term.
		$venue_after->post_status = 'publish';
		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->get_venue_term_slug( $venue_after->post_name ),
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
			term_exists( $instance->get_venue_term_slug( $venue->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term exists'
		);

		$instance->delete_venue_term( $venue->ID );

		$this->assertNull(
			term_exists( $instance->get_venue_term_slug( $venue->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term was deleted.'
		);
	}

	/**
	 * Coverage for get_venue_term_slug method.
	 *
	 * @covers ::get_venue_term_slug
	 *
	 * @return void
	 */
	public function test_get_venue_term_slug(): void {
		$this->assertSame(
			'_unit-test',
			Venue_Setup::get_instance()->get_venue_term_slug( 'unit-test' ),
			'Failed to assert that term slugs match.'
		);
	}

	/**
	 * Coverage for get_venue_post_from_term_slug method.
	 *
	 * @covers ::get_venue_post_from_term_slug
	 *
	 * @return void
	 */
	public function test_get_venue_post_from_term_slug(): void {
		$venue                = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'unit-test',
			)
		)->get();
		$venue_from_term_slug = Venue_Setup::get_instance()->get_venue_post_from_term_slug( '_unit-test' );

		$this->assertEquals(
			$venue->ID,
			$venue_from_term_slug->ID,
			'Failed to assert that IDs match.'
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

		// Add venue information as JSON.
		$venue_info = array(
			'fullAddress' => '123 Test Street, Test City, TS 12345',
			'phoneNumber' => '555-123-4567',
			'website'     => 'https://example.com',
			'latitude'    => '40.7128',
			'longitude'   => '-74.0060',
		);
		add_post_meta( $venue->ID, 'gatherpress_venue_information', wp_json_encode( $venue_info ) );

		$venue_meta = Venue_Setup::get_instance()->get_venue_meta( $venue->ID, Venue::POST_TYPE );

		// Test that venue information is correctly extracted from JSON.
		$this->assertEquals(
			$venue_title,
			$venue_meta['name'],
			'Failed to assert venue title matches.'
		);
		$this->assertEquals(
			'123 Test Street, Test City, TS 12345',
			$venue_meta['fullAddress'],
			'Failed to assert fullAddress matches.'
		);
		$this->assertEquals(
			'555-123-4567',
			$venue_meta['phoneNumber'],
			'Failed to assert phoneNumber matches.'
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
	 * Coverage for get_venue_post_from_event_post_id method.
	 *
	 * @covers ::get_venue_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_venue_post_from_event_post_id(): void {
		// Create a venue post.
		$venue = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'test-venue-for-event',
				'post_title' => 'Test Venue For Event',
			)
		)->get();

		// Create the venue term with the correct slug format.
		$term_slug = Venue_Setup::get_instance()->get_venue_term_slug( $venue->post_name );
		wp_insert_term(
			'Test Venue For Event',
			Venue::TAXONOMY,
			array( 'slug' => $term_slug )
		);

		// Create an event post.
		$event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'test-event-with-venue',
			)
		)->get();

		// Associate the event with the venue term.
		wp_set_post_terms( $event->ID, $term_slug, Venue::TAXONOMY );

		// Get the venue post from the event.
		$result = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event->ID );

		// The result should be the venue post.
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
	 * Coverage for get_venue_post_from_event_post_id when event has no venue terms.
	 *
	 * @covers ::get_venue_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_venue_post_from_event_post_id_no_terms(): void {
		// Create an event post without any venue terms.
		$event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'test-event-no-venue',
			)
		)->get();

		// Get the venue post from the event.
		$result = Venue_Setup::get_instance()->get_venue_post_from_event_post_id( $event->ID );

		// The result should be null since there are no venue terms.
		$this->assertNull(
			$result,
			'Should return null when event has no venue terms.'
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
				'title'    => 'Invisible Venue Template Block Pattern',
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
			term_exists( $instance->get_venue_term_slug( $post->post_name ), Venue::TAXONOMY ),
			'Failed to assert that no venue term was created for a post type without venue-information support.'
		);
	}
}
