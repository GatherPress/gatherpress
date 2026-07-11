<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue\Setup.
 *
 * @package GatherPress\Core\Venue
 * @since 0.27.0
 */

namespace GatherPress\Tests\Core\Venue;

use GatherPress\Core\Event;
use GatherPress\Core\Venue\Map\Setup as Map_Setup;
use GatherPress\Core\Venue\Meta;
use GatherPress\Core\Venue\Setup;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Block_Patterns_Registry;

/**
 * Class Test_Setup.
 *
 * @group multisite
 * @coversDefaultClass \GatherPress\Core\Venue\Setup
 */
class Test_Setup extends Base {

	/**
	 * Venue\Setup hands the map subsystem off to `Map\Setup` and the
	 * meta surface off to `Venue\Meta`. Per-sibling internals are
	 * proved by their own test classes.
	 *
	 * @covers ::__construct
	 * @covers ::instantiate_classes
	 *
	 * @return void
	 */
	public function test_instantiate_classes_hands_off_to_siblings(): void {
		// Force the method to run inside the test's coverage window —
		// Setup is a singleton cached during plugin bootstrap, so
		// `get_instance()` here returns the cached instance and doesn't
		// re-fire the constructor.
		Utility::invoke_hidden_method( Setup::get_instance(), 'instantiate_classes' );

		$this->assertInstanceOf(
			Map_Setup::class,
			Map_Setup::get_instance(),
			'Map\Setup must be instantiated so the map subsystem is wired.'
		);
		$this->assertInstanceOf(
			Meta::class,
			Meta::get_instance(),
			'Venue\Meta must be instantiated so meta registration is wired.'
		);
	}

	/**
	 * Coverage for __construct and setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Setup::get_instance();
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
				'priority' => 9,
				'callback' => array( $instance, 'maybe_link_shadow_source_support' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'register_taxonomy' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'register_starter_pattern' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'block_editor_settings_all',
				'priority' => 10,
				'callback' => array( $instance, 'add_editor_settings' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'gatherpress_shadow_taxonomy_object_types',
				'priority' => 10,
				'callback' => array( $instance, 'attach_venue_taxonomy_to_event_types' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for maybe_link_shadow_source_support — venue support implicitly
	 * declares gatherpress-shadow-source so the shadow-taxonomy primitive
	 * wires up automatically for venue post types.
	 *
	 * @covers ::maybe_link_shadow_source_support
	 *
	 * @return void
	 */
	public function test_maybe_link_shadow_source_support(): void {
		$instance = Setup::get_instance();

		// A venue post type carries gatherpress-venue-information and should be auto-linked.
		$instance->maybe_link_shadow_source_support( Venue::POST_TYPE );

		$this->assertTrue(
			post_type_supports( Venue::POST_TYPE, 'gatherpress-shadow-source' ),
			'Failed to assert venue post type implicitly declares gatherpress-shadow-source.'
		);
	}

	/**
	 * Coverage for maybe_link_shadow_source_support — bails when the post type
	 * does not declare gatherpress-venue-information.
	 *
	 * @covers ::maybe_link_shadow_source_support
	 *
	 * @return void
	 */
	public function test_maybe_link_shadow_source_support_skips_unsupported_post_type(): void {
		$instance = Setup::get_instance();

		$instance->maybe_link_shadow_source_support( 'post' );

		$this->assertFalse(
			post_type_supports( 'post', 'gatherpress-shadow-source' ),
			'Failed to assert non-venue post type is not auto-linked.'
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
		$instance = Setup::get_instance();

		unregister_post_type( Venue::POST_TYPE );

		$this->assertFalse( post_type_exists( Venue::POST_TYPE ), 'Failed to assert that post type does not exist.' );

		$instance->register_post_type();

		$this->assertTrue( post_type_exists( Venue::POST_TYPE ), 'Failed to assert that post type exists.' );
	}


	/**
	 * Coverage for register_taxonomy method.
	 *
	 * @covers ::register_taxonomy
	 *
	 * @return void
	 */
	public function test_register_taxonomy(): void {
		$instance = Setup::get_instance();

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
	 * Filter callbacks may return entries that aren't valid pattern
	 * definitions (missing `name`, non-array values). The registration
	 * loop must skip those gracefully so one bad entry from a
	 * third-party filter doesn't bring down the rest of the chooser.
	 *
	 * @covers ::register_starter_pattern
	 *
	 * @return void
	 */
	public function test_register_starter_pattern_skips_malformed_filter_entries(): void {
		$instance = Setup::get_instance();
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( $registry->is_registered( 'gatherpress/venue-with-map' ) ) {
			$registry->unregister( 'gatherpress/venue-with-map' );
		}

		$inject_garbage = static function ( array $patterns ): array {
			$patterns[] = array( 'title' => 'No name key — must be skipped.' );
			$patterns[] = 'not-an-array — must be skipped.';
			return $patterns;
		};

		add_filter( 'gatherpress_venue_starter_patterns', $inject_garbage );

		$instance->register_starter_pattern();

		remove_filter( 'gatherpress_venue_starter_patterns', $inject_garbage );

		$this->assertTrue(
			$registry->is_registered( 'gatherpress/venue-with-map' ),
			'Bundled pattern should still register when filter entries before/after it are malformed.'
		);

		$registry->unregister( 'gatherpress/venue-with-map' );
	}

	/**
	 * Bails before registering when no post type declares
	 * `gatherpress-venue-information` support. Without the guard,
	 * `register_block_pattern` would be called with an empty
	 * `postTypes` array and the chooser modal would have no
	 * post-type scope to match against.
	 *
	 * @covers ::register_starter_pattern
	 *
	 * @return void
	 */
	public function test_register_starter_pattern_bails_without_supported_post_types(): void {
		$instance = Setup::get_instance();
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( $registry->is_registered( 'gatherpress/venue-with-map' ) ) {
			$registry->unregister( 'gatherpress/venue-with-map' );
		}

		// Strip the support from every post type that currently declares
		// it so `get_post_types_by_support()` returns an empty array.
		$supported = get_post_types_by_support( 'gatherpress-venue-information' );
		foreach ( $supported as $post_type ) {
			remove_post_type_support( $post_type, 'gatherpress-venue-information' );
		}

		$instance->register_starter_pattern();

		$this->assertFalse(
			$registry->is_registered( 'gatherpress/venue-with-map' ),
			'Starter pattern must not be registered when no post type declares the venue-information support.'
		);

		// Restore support.
		foreach ( $supported as $post_type ) {
			add_post_type_support( $post_type, 'gatherpress-venue-information' );
		}
	}

	/**
	 * Registers the user-facing starter pattern scoped to core/post-content
	 * and every post type declaring `gatherpress-venue-information` so the
	 * starter pattern modal surfaces it on new venues.
	 *
	 * @covers ::register_starter_pattern
	 *
	 * @return void
	 */
	public function test_register_starter_pattern(): void {
		$instance = Setup::get_instance();
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( $registry->is_registered( 'gatherpress/venue-with-map' ) ) {
			$registry->unregister( 'gatherpress/venue-with-map' );
		}

		$instance->register_starter_pattern();

		$this->assertTrue(
			$registry->is_registered( 'gatherpress/venue-with-map' ),
			'Starter pattern should be registered.'
		);

		$pattern = $registry->get_registered( 'gatherpress/venue-with-map' );

		$this->assertContains(
			'core/post-content',
			$pattern['blockTypes'],
			'Starter pattern must scope to core/post-content so the chooser modal surfaces it.'
		);
		$this->assertContains(
			Venue::POST_TYPE,
			$pattern['postTypes'],
			'Starter pattern must scope to gatherpress_venue post type.'
		);

		$registry->unregister( 'gatherpress/venue-with-map' );
	}

	/**
	 * Third parties can append their own pattern definitions via the
	 * `gatherpress_venue_starter_patterns` filter without having to
	 * call `register_block_pattern()` themselves.
	 *
	 * @covers ::register_starter_pattern
	 *
	 * @return void
	 */
	public function test_register_starter_pattern_filter_extends(): void {
		$instance = Setup::get_instance();
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( $registry->is_registered( 'unit-test/extra-venue-pattern' ) ) {
			$registry->unregister( 'unit-test/extra-venue-pattern' );
		}

		$append_pattern = static function ( array $patterns ): array {
			$patterns[] = array(
				'name'        => 'unit-test/extra-venue-pattern',
				'title'       => 'Extra Venue Pattern',
				'description' => 'Added through the filter.',
				'content'     => '<!-- wp:paragraph --><p>Extra</p><!-- /wp:paragraph -->',
			);
			return $patterns;
		};

		add_filter( 'gatherpress_venue_starter_patterns', $append_pattern );

		$instance->register_starter_pattern();

		remove_filter( 'gatherpress_venue_starter_patterns', $append_pattern );

		$this->assertTrue(
			$registry->is_registered( 'unit-test/extra-venue-pattern' ),
			'Patterns appended via the filter must be registered alongside the bundled defaults.'
		);

		$registry->unregister( 'unit-test/extra-venue-pattern' );
	}

	/**
	 * The `gatherpress_venue_starter_patterns` filter passes the array of
	 * post types about to receive the registered patterns as its second
	 * argument, so consumers can vary the returned patterns based on which
	 * venue-acting post types are in scope.
	 *
	 * @covers ::register_starter_pattern
	 *
	 * @return void
	 */
	public function test_register_starter_pattern_filter_receives_post_types(): void {
		$instance        = Setup::get_instance();
		$captured_pt_arg = null;

		$capture_post_types = static function ( array $patterns, array $post_types ) use ( &$captured_pt_arg ): array {
			$captured_pt_arg = $post_types;
			return $patterns;
		};

		add_filter( 'gatherpress_venue_starter_patterns', $capture_post_types, 10, 2 );

		$instance->register_starter_pattern();

		remove_filter( 'gatherpress_venue_starter_patterns', $capture_post_types, 10 );

		$this->assertIsArray(
			$captured_pt_arg,
			'Filter must receive the post-type array as its second argument.'
		);
		$this->assertContains(
			Venue::POST_TYPE,
			$captured_pt_arg,
			'Post-type array must include every post type declaring gatherpress-venue-information support.'
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

		$venue_meta = Setup::get_instance()->get_venue_meta( $event->ID, Event::POST_TYPE );

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

		$venue_meta = Setup::get_instance()->get_venue_meta( $venue->ID, Venue::POST_TYPE );

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

		add_post_meta( $venue->ID, 'gatherpress_address', '123 Test Street, Test City, TS 12345' );
		add_post_meta( $venue->ID, 'gatherpress_phone', '555-123-4567' );
		add_post_meta( $venue->ID, 'gatherpress_website', 'https://example.com' );
		add_post_meta( $venue->ID, 'gatherpress_latitude', '40.7128' );
		add_post_meta( $venue->ID, 'gatherpress_longitude', '-74.006' );

		$venue_meta = Setup::get_instance()->get_venue_meta( $venue->ID, Venue::POST_TYPE );

		$this->assertEquals(
			$venue_title,
			$venue_meta['name'],
			'Failed to assert venue title matches.'
		);
		$this->assertEquals(
			'123 Test Street, Test City, TS 12345',
			$venue_meta['address'],
			'Failed to assert full_address matches.'
		);
		$this->assertEquals(
			'555-123-4567',
			$venue_meta['phone'],
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
			'-74.006',
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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();

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
		$instance         = Setup::get_instance();
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
		$instance = Setup::get_instance();

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
		$instance = Setup::get_instance();
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
		$instance = Setup::get_instance();

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
		$result = Setup::get_instance()->get_venue_post_from_term_slug( '_unit-test' );

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
		$instance = Setup::get_instance();
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

		$result = Setup::get_instance()->get_venue_post_from_event_post_id( $event->ID );

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

		$result = Setup::get_instance()->get_venue_post_from_event_post_id( $event->ID );

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
		$instance = Setup::get_instance();
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

		$result = Setup::get_instance()->get_venue_post_from_event_post_id( $event->ID );

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
			Setup::get_instance()->term_slug_from_post_name( 'unit-test' ),
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
		$instance = Setup::get_instance();

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
			Setup::get_instance()->get_venue_post_type(),
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
			Setup::get_instance()->get_venue_post_type( 'test_custom_event_type' ),
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
		$map = Setup::get_instance()->get_venue_post_type_map();

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
		$instance = Setup::get_instance();

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

		$slug = Setup::get_instance()->get_localized_post_type_slug();

		$this->assertNotEmpty( $slug, 'Failed to assert slug is not empty.' );

		remove_filter( 'locale', $locale_filter );

		$this->assertSame(
			'en_US',
			determine_locale(),
			'Failed to assert locale was restored after method execution.'
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
		$instance    = Setup::get_instance();
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

		add_post_meta( $venue->ID, 'gatherpress_address', '1 Library Lane' );
		add_post_meta( $venue->ID, 'gatherpress_phone', '555-1234' );
		add_post_meta( $venue->ID, 'gatherpress_website', 'https://example.test' );
		add_post_meta( $venue->ID, 'gatherpress_latitude', '40.0' );
		add_post_meta( $venue->ID, 'gatherpress_longitude', '-70.0' );

		$venue_meta = $instance->get_venue_meta( $event->ID, Event::POST_TYPE );

		$this->assertSame(
			$venue_title,
			$venue_meta['name'],
			'Expected venue name to come from the linked venue post.'
		);
		$this->assertSame( '1 Library Lane', $venue_meta['address'] );
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
		$instance = Setup::get_instance();

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
			Setup::get_instance()->taxonomy_for_event_post_type( Event::POST_TYPE ),
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
		$instance = Setup::get_instance();

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

	/**
	 * Coverage for attach_venue_taxonomy_to_event_types — returns the
	 * gatherpress-venue-supporting event CPTs when the filter is called for
	 * the venue post type, leaves the list unchanged for other shadow
	 * sources.
	 *
	 * @covers ::attach_venue_taxonomy_to_event_types
	 *
	 * @return void
	 */
	public function test_attach_venue_taxonomy_to_event_types(): void {
		$instance = Setup::get_instance();

		// Source is venue — returns gatherpress_event (which has gatherpress-venue support).
		$result = $instance->attach_venue_taxonomy_to_event_types( array(), Venue::POST_TYPE );

		$this->assertContains(
			Event::POST_TYPE,
			$result,
			'Filter callback should return gatherpress_event when source is the venue post type.'
		);

		// Source is something else — passes through unchanged.
		$passthrough = $instance->attach_venue_taxonomy_to_event_types(
			array( 'gatherpress_event' ),
			'unrelated_source'
		);
		$this->assertSame(
			array( 'gatherpress_event' ),
			$passthrough,
			'Filter callback should pass through unchanged for non-venue sources.'
		);
	}
}
