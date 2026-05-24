<?php
/**
 * Class handles unit tests for GatherPress\Core\Shadow_Source.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Shadow_Source;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;

/**
 * Class Test_Shadow_Source.
 *
 * Exercises the per-post-type shadow taxonomy primitive against the
 * `gatherpress_venue` post type, which declares `gatherpress-shadow-source`
 * support out of the box.
 *
 * @coversDefaultClass \GatherPress\Core\Shadow_Source
 */
class Test_Shadow_Source extends Base {

	/**
	 * Coverage for __construct and setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Shadow_Source::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_register_post_type_hooks' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 11,
				'callback' => array( $instance, 'register_taxonomies' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 12,
				'callback' => array( $instance, 'attach_taxonomies_to_object_types' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'post_updated',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_update_term_slug' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for maybe_register_post_type_hooks — wires per-post-type
	 * save and delete actions when the given post type declares
	 * `gatherpress-shadow-source` support.
	 *
	 * @covers ::maybe_register_post_type_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_post_type_hooks(): void {
		$instance = Shadow_Source::get_instance();

		foreach ( get_post_types_by_support( 'gatherpress-shadow-source' ) as $post_type ) {
			$instance->maybe_register_post_type_hooks( $post_type );
			$this->assertSame(
				10,
				has_action(
					sprintf( 'save_post_%s', $post_type ),
					array( $instance, 'add_term' )
				),
				sprintf( 'Failed to assert that save_post_%s has the add_term action.', $post_type )
			);
			$this->assertSame(
				10,
				has_action(
					sprintf( 'delete_post_%s', $post_type ),
					array( $instance, 'delete_term' )
				),
				sprintf( 'Failed to assert that delete_post_%s has the delete_term action.', $post_type )
			);
		}
	}

	/**
	 * Bails when the post type does not declare `gatherpress-shadow-source`.
	 *
	 * @covers ::maybe_register_post_type_hooks
	 *
	 * @return void
	 */
	public function test_maybe_register_post_type_hooks_skips_unsupported_post_type(): void {
		$instance = Shadow_Source::get_instance();

		$instance->maybe_register_post_type_hooks( 'post' );

		$this->assertFalse(
			has_action( 'save_post_post', array( $instance, 'add_term' ) ),
			'Failed to assert no save hook is registered for a post type without gatherpress-shadow-source support.'
		);
		$this->assertFalse(
			has_action( 'delete_post_post', array( $instance, 'delete_term' ) ),
			'Failed to assert no delete hook is registered for a post type without gatherpress-shadow-source support.'
		);
	}

	/**
	 * Coverage for register_taxonomies method — registers a hidden taxonomy
	 * per shadow-source post type.
	 *
	 * @covers ::register_taxonomies
	 * @covers ::get_taxonomy_args
	 *
	 * @return void
	 */
	public function test_register_taxonomies(): void {
		$instance = Shadow_Source::get_instance();

		unregister_taxonomy( Venue::TAXONOMY );

		$this->assertFalse( taxonomy_exists( Venue::TAXONOMY ), 'Failed to assert that taxonomy does not exist.' );

		$instance->register_taxonomies();

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
	 * Coverage for the gatherpress_shadow_taxonomy_args filter.
	 *
	 * @covers ::register_taxonomies
	 * @covers ::get_taxonomy_args
	 *
	 * @return void
	 */
	public function test_register_taxonomies_applies_filter(): void {
		$instance = Shadow_Source::get_instance();

		unregister_taxonomy( Venue::TAXONOMY );

		$filter = static function ( array $args ): array {
			$args['labels']['name'] = 'Filtered Label';
			return $args;
		};

		add_filter( 'gatherpress_shadow_taxonomy_args', $filter );

		$instance->register_taxonomies();

		remove_filter( 'gatherpress_shadow_taxonomy_args', $filter );

		$taxonomy = get_taxonomy( Venue::TAXONOMY );

		$this->assertSame(
			'Filtered Label',
			$taxonomy->labels->name,
			'Filter should be able to override the taxonomy labels.'
		);
	}

	/**
	 * Coverage for attach_taxonomies_to_object_types — walks shadow-source
	 * CPTs and registers each shadow taxonomy on the event CPTs returned by
	 * the `gatherpress_shadow_taxonomy_object_types` filter.
	 *
	 * @covers ::attach_taxonomies_to_object_types
	 *
	 * @return void
	 */
	public function test_attach_taxonomies_to_object_types_uses_filter(): void {
		$instance = Shadow_Source::get_instance();

		// Detach gatherpress_event from the venue taxonomy so we can assert
		// the filter callback re-attaches it.
		unregister_taxonomy_for_object_type( Venue::TAXONOMY, 'gatherpress_event' );
		$this->assertFalse(
			is_object_in_taxonomy( 'gatherpress_event', Venue::TAXONOMY ),
			'Precondition: venue taxonomy should be detached from gatherpress_event before running.'
		);

		$filter = static function ( array $object_types, string $source_post_type ): array {
			if ( Venue::POST_TYPE === $source_post_type ) {
				$object_types[] = 'gatherpress_event';
			}
			return $object_types;
		};
		add_filter( 'gatherpress_shadow_taxonomy_object_types', $filter, 10, 2 );

		$instance->attach_taxonomies_to_object_types();

		remove_filter( 'gatherpress_shadow_taxonomy_object_types', $filter, 10 );

		$this->assertTrue(
			is_object_in_taxonomy( 'gatherpress_event', Venue::TAXONOMY ),
			'Filter callback should have wired the venue taxonomy onto gatherpress_event.'
		);
	}

	/**
	 * Coverage for attach_taxonomies_to_object_types — empty filter
	 * default attaches the taxonomy to nothing.
	 *
	 * @covers ::attach_taxonomies_to_object_types
	 *
	 * @return void
	 */
	public function test_attach_taxonomies_to_object_types_default_is_empty(): void {
		$instance = Shadow_Source::get_instance();

		// Stub the filter to short-circuit to an empty list regardless of
		// other registered callbacks (the venue subsystem registers one in
		// its own setup_hooks). High-priority callback wins on the chain.
		$short_circuit = static function (): array {
			return array();
		};
		add_filter( 'gatherpress_shadow_taxonomy_object_types', $short_circuit, PHP_INT_MAX );

		unregister_taxonomy_for_object_type( Venue::TAXONOMY, 'gatherpress_event' );

		$instance->attach_taxonomies_to_object_types();

		$this->assertFalse(
			is_object_in_taxonomy( 'gatherpress_event', Venue::TAXONOMY ),
			'When the filter resolves to an empty list, no event CPTs should be wired.'
		);

		remove_filter( 'gatherpress_shadow_taxonomy_object_types', $short_circuit, PHP_INT_MAX );
	}

	/**
	 * Coverage for add_term — inserts a term on initial publish, idempotent
	 * on $update=true, no-op on unsupported post types.
	 *
	 * @covers ::add_term
	 *
	 * @return void
	 */
	public function test_add_term(): void {
		$instance = Shadow_Source::get_instance();
		$venue    = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();
		$term     = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Failed to assert that term exists.'
		);

		// Delete term to ensure add_term re-creates it.
		wp_delete_term( $term['term_id'], Venue::TAXONOMY );

		$this->assertNull(
			term_exists( $term['term_id'], Venue::TAXONOMY ),
			'Failed to assert that term does not exist after being deleted.'
		);

		$instance->add_term( $venue->ID, $venue, true );

		$term = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertNull(
			$term,
			'Failed to assert that term does not exist when $update is true.'
		);

		$instance->add_term( $venue->ID, $venue, false );

		$term = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Failed to assert that term exists after add_term on first publish.'
		);
	}

	/**
	 * Coverage for add_term when post type does not support gatherpress-shadow-source.
	 *
	 * @covers ::add_term
	 *
	 * @return void
	 */
	public function test_add_term_unsupported_post_type(): void {
		$instance = Shadow_Source::get_instance();
		$post     = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		// Calling add_term on a standard 'post' should return early and create no term.
		$instance->add_term( $post->ID, $post, false );

		$this->assertNull(
			term_exists( $instance->term_slug_from_post_name( $post->post_name ), Venue::TAXONOMY ),
			'Failed to assert that no shadow term was created without shadow-source support.'
		);
	}

	/**
	 * Coverage for maybe_update_term_slug — renames the term when post_name
	 * or post_title change, recreates the term if missing, ignores the call
	 * for unsupported post types and non-publish/trash transitions.
	 *
	 * @covers ::maybe_update_term_slug
	 *
	 * @return void
	 */
	public function test_maybe_update_term_slug(): void {
		$instance    = Shadow_Source::get_instance();
		$post_before = $this->mock->post()->get();
		$post_after  = clone $post_before;

		$post_after->post_name .= '-after';

		// Unsupported post type: nothing should happen.
		$instance->maybe_update_term_slug( $post_before->ID, $post_after, $post_before );
		$this->assertNull(
			term_exists( $instance->term_slug_from_post_name( $post_before->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term does not exist for an unsupported post type.'
		);
		$this->assertNull(
			term_exists( $instance->term_slug_from_post_name( $post_after->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term does not exist for an unsupported post type.'
		);

		$venue_before = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();
		$venue_after  = clone $venue_before;

		$venue_after->post_name .= '-first';

		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term = term_exists( $instance->term_slug_from_post_name( $venue_after->post_name ), Venue::TAXONOMY );

		$this->assertIsArray(
			$term,
			'Failed to assert that term exists after rename.'
		);

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->term_slug_from_post_name( $venue_after->post_name ),
			'Failed to assert that slugs match after rename.'
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
			'Failed to assert that term was recreated when missing.'
		);

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->term_slug_from_post_name( $venue_after->post_name ),
			'Failed to assert that recreated term has the new slug.'
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
			'Failed to assert that draft transitions are ignored.'
		);

		// Setting to trash should update the term.
		$venue_after->post_status = 'trash';
		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->term_slug_from_post_name( $venue_after->post_name ),
			'Failed to assert that trash transitions update the term.'
		);

		// Setting to publish should update the term.
		$venue_after->post_status = 'publish';
		$instance->maybe_update_term_slug( $venue_before->ID, $venue_after, $venue_before );

		$term_object = get_term( $term['term_id'] );

		$this->assertSame(
			$term_object->slug,
			$instance->term_slug_from_post_name( $venue_after->post_name ),
			'Failed to assert that publish transitions update the term.'
		);
	}

	/**
	 * Coverage for add_term's early return when the term already exists.
	 *
	 * @covers ::add_term
	 *
	 * @return void
	 */
	public function test_add_term_skips_when_term_already_exists(): void {
		$instance = Shadow_Source::get_instance();
		$venue    = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();

		// First save (via factory) creates the term.
		$term = term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY );

		$this->assertIsArray( $term, 'Expected term to be created on first save.' );

		$term_id = $term['term_id'];

		// Second call with $update=false should detect the existing term and bail.
		$instance->add_term( $venue->ID, $venue, false );

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
		$instance = Shadow_Source::get_instance();
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
	 * Coverage for delete_term — removes the term when its source post is deleted,
	 * no-op for unsupported post types or posts with empty post_name.
	 *
	 * @covers ::delete_term
	 *
	 * @return void
	 */
	public function test_delete_term(): void {
		$instance = Shadow_Source::get_instance();
		$venue    = $this->mock->post( array( 'post_type' => Venue::POST_TYPE ) )->get();

		$this->assertIsArray(
			term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term exists before deletion.'
		);

		$instance->delete_term( $venue->ID );

		$this->assertNull(
			term_exists( $instance->term_slug_from_post_name( $venue->post_name ), Venue::TAXONOMY ),
			'Failed to assert that term was deleted.'
		);

		// Unsupported post type: should bail, not throw.
		$post = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$instance->delete_term( $post->ID );

		$this->assertNull(
			term_exists( $instance->term_slug_from_post_name( $post->post_name ), Venue::TAXONOMY ),
			'Failed to assert that delete_term is a no-op for unsupported post types.'
		);
	}

	/**
	 * Coverage for delete_term's empty-post_name early return.
	 *
	 * Auto-drafts and similar transient states can leave `post_name` blank.
	 * The delete hook still fires for those, so the method must bail before
	 * dereferencing an empty slug to avoid attempting to resolve a `_` term.
	 *
	 * @covers ::delete_term
	 *
	 * @return void
	 */
	public function test_delete_term_skips_when_post_has_empty_post_name(): void {
		$instance = Shadow_Source::get_instance();

		// Auto-draft + empty title leaves post_name blank — wp_insert_post
		// only generates a slug when there's a title to derive one from.
		$post_id = wp_insert_post(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'auto-draft',
				'post_title'  => '',
			)
		);

		$post = get_post( $post_id );

		$this->assertNotEmpty( $post_id, 'Failed to create the auto-draft test post.' );
		$this->assertEmpty( $post->post_name, 'Expected the auto-draft to have a blank post_name.' );

		// Seed a sentinel term so we can prove the early return didn't touch it.
		if ( ! term_exists( 'online-event', Venue::TAXONOMY ) ) {
			wp_insert_term( 'Online event', Venue::TAXONOMY, array( 'slug' => 'online-event' ) );
		}

		$instance->delete_term( $post_id );

		$this->assertNotNull(
			term_exists( 'online-event', Venue::TAXONOMY ),
			'Failed to assert that delete_term bails on empty post_name without touching unrelated terms.'
		);
	}

	/**
	 * Coverage for get_taxonomy — pure formatter prepends an underscore.
	 *
	 * @covers ::get_taxonomy
	 *
	 * @return void
	 */
	public function test_get_taxonomy(): void {
		$this->assertSame(
			'_my-post-type',
			Shadow_Source::get_instance()->get_taxonomy( 'my-post-type' ),
			'Failed to assert that get_taxonomy prepends an underscore to the post type slug.'
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
			Shadow_Source::get_instance()->term_slug_from_post_name( 'unit-test' ),
			'Failed to assert that term_slug_from_post_name prepends an underscore.'
		);
	}

	/**
	 * Coverage for is_shadow_term_slug — sentinel slugs (no underscore prefix)
	 * are filtered out so resolution logic doesn't treat them as real source posts.
	 *
	 * @covers ::is_shadow_term_slug
	 *
	 * @return void
	 */
	public function test_is_shadow_term_slug(): void {
		$instance = Shadow_Source::get_instance();

		$this->assertTrue(
			$instance->is_shadow_term_slug( '_my-venue' ),
			'Failed to assert that an underscore-prefixed slug is a shadow term slug.'
		);
		$this->assertFalse(
			$instance->is_shadow_term_slug( 'online-event' ),
			'Failed to assert that a non-underscore slug is treated as a sentinel.'
		);
	}

	/**
	 * Coverage for get_post_from_term_slug — strips the underscore and
	 * resolves the post via get_page_by_path against the given post type.
	 *
	 * @covers ::get_post_from_term_slug
	 *
	 * @return void
	 */
	public function test_get_post_from_term_slug(): void {
		$instance = Shadow_Source::get_instance();
		$venue    = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'unit-test',
			)
		)->get();

		$result = $instance->get_post_from_term_slug( '_unit-test', Venue::POST_TYPE );

		$this->assertEquals(
			$venue->ID,
			$result->ID,
			'Failed to assert that get_post_from_term_slug returns the matching post.'
		);

		$this->assertNull(
			$instance->get_post_from_term_slug( '_does-not-exist', Venue::POST_TYPE ),
			'Failed to assert that get_post_from_term_slug returns null for missing slugs.'
		);
	}

	/**
	 * Coverage for get_source_post_from_event_post_id — walks the event's
	 * shadow taxonomy terms for the given source post type and resolves the
	 * first underscore-prefixed slug to its source post.
	 *
	 * @covers ::get_source_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_source_post_from_event_post_id_resolves_linked_source(): void {
		$instance = Shadow_Source::get_instance();
		$venue    = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'unit-test-source-resolve',
			)
		)->get();
		$event    = $this->mock->post(
			array( 'post_type' => 'gatherpress_event' )
		)->get();

		wp_set_post_terms( $event->ID, '_unit-test-source-resolve', Venue::TAXONOMY );

		$resolved = $instance->get_source_post_from_event_post_id( $event->ID, Venue::POST_TYPE );

		$this->assertInstanceOf( \WP_Post::class, $resolved, 'Expected a WP_Post for the linked source.' );
		$this->assertSame( $venue->ID, $resolved->ID, 'Expected the venue post ID to match the linked source.' );
	}

	/**
	 * Coverage for get_source_post_from_event_post_id — sentinel terms
	 * (slugs without a leading underscore, e.g. `online-event`) are skipped.
	 *
	 * @covers ::get_source_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_source_post_from_event_post_id_skips_sentinel_terms(): void {
		$instance = Shadow_Source::get_instance();
		$event    = $this->mock->post(
			array( 'post_type' => 'gatherpress_event' )
		)->get();

		// Insert a sentinel term (no underscore prefix) and attach it to the event.
		$term = wp_insert_term( 'Online Event', Venue::TAXONOMY, array( 'slug' => 'online-event-sentinel' ) );
		wp_set_post_terms( $event->ID, array( (int) $term['term_id'] ), Venue::TAXONOMY );

		$this->assertNull(
			$instance->get_source_post_from_event_post_id( $event->ID, Venue::POST_TYPE ),
			'Sentinel terms should be skipped during source resolution.'
		);
	}

	/**
	 * Coverage for get_source_post_from_event_post_id — returns null when
	 * the event has no terms in the source's shadow taxonomy.
	 *
	 * @covers ::get_source_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_source_post_from_event_post_id_returns_null_without_terms(): void {
		$instance = Shadow_Source::get_instance();
		$event    = $this->mock->post(
			array( 'post_type' => 'gatherpress_event' )
		)->get();

		$this->assertNull(
			$instance->get_source_post_from_event_post_id( $event->ID, Venue::POST_TYPE ),
			'Should return null when no terms link the event to any source post.'
		);
	}
}
