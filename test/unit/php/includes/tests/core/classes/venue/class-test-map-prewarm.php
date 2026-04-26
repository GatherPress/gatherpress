<?php
/**
 * Unit tests for GatherPress\Core\Venue\Map_Prewarm.
 *
 * @package GatherPress\Core\Venue
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Venue;

use GatherPress\Core\Venue\Map;
use GatherPress\Core\Venue\Map_Prewarm;
use GatherPress\Core\Venue\Setup;
use GatherPress\Core\Venue\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Map_Prewarm.
 *
 * @coversDefaultClass \GatherPress\Core\Venue\Map_Prewarm
 */
class Test_Map_Prewarm extends Base {
	/**
	 * Clear scheduled warm events between tests — wp_next_scheduled lookups
	 * otherwise leak across cases and skew dedup assertions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		wp_clear_scheduled_hook( Map_Prewarm::CRON_ACTION );
		parent::tear_down();
	}

	/**
	 * Coverage for setup_hooks — verifies cron handler + save/theme-switch
	 * callbacks are registered at the expected priorities.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Map_Prewarm::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => Map_Prewarm::CRON_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'process_warm_job' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 12,
				'callback' => array( $instance, 'on_post_saved' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'switch_theme',
				'priority' => 10,
				'callback' => array( $instance, 'on_theme_switched' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Content with no venue-map block returns an empty combo list.
	 *
	 * @covers ::collect_combos_from_content
	 *
	 * @return void
	 */
	public function test_collect_combos_from_content_ignores_unrelated_markup(): void {
		$instance = Map_Prewarm::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'collect_combos_from_content',
			array( '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' )
		);

		$this->assertSame( array(), $result, 'Content without venue-map produces no combos.' );

		$empty = Utility::invoke_hidden_method(
			$instance,
			'collect_combos_from_content',
			array( '' )
		);

		$this->assertSame( array(), $empty, 'Empty content produces no combos.' );
	}

	/**
	 * A single venue-map block in content produces its attributes as a combo.
	 *
	 * @covers ::collect_combos_from_content
	 * @covers ::walk_blocks_for_combos
	 * @covers ::extract_block_combo
	 *
	 * @return void
	 */
	public function test_collect_combos_from_content_extracts_single_block(): void {
		$instance = Map_Prewarm::get_instance();
		$content  = '<!-- wp:gatherpress/venue-map {"zoom":15,"width":0,"height":400,"aspectRatio":"16/9"} /-->';

		$result = Utility::invoke_hidden_method(
			$instance,
			'collect_combos_from_content',
			array( $content )
		);

		$this->assertCount( 1, $result );
		$this->assertSame(
			array(
				'zoom'         => 15,
				'width'        => 0,
				'height'       => 400,
				'aspect_ratio' => '16/9',
			),
			$result[0]
		);
	}

	/**
	 * Nested venue-map blocks (e.g. inside a venue parent) are still found.
	 *
	 * @covers ::walk_blocks_for_combos
	 *
	 * @return void
	 */
	public function test_walk_blocks_for_combos_recurses_inner_blocks(): void {
		$instance = Map_Prewarm::get_instance();
		$content  = '<!-- wp:gatherpress/venue -->'
			. '<div class="wp-block-gatherpress-venue">'
			. '<!-- wp:gatherpress/venue-map {"zoom":10,"width":800,"height":400,"aspectRatio":"2/1"} /-->'
			. '</div>'
			. '<!-- /wp:gatherpress/venue -->';

		$result = Utility::invoke_hidden_method(
			$instance,
			'collect_combos_from_content',
			array( $content )
		);

		$this->assertCount( 1, $result );
		$this->assertSame( 10, $result[0]['zoom'] );
		$this->assertSame( 800, $result[0]['width'] );
	}

	/**
	 * Missing block attributes fall back to the Map defaults.
	 *
	 * @covers ::extract_block_combo
	 *
	 * @return void
	 */
	public function test_extract_block_combo_uses_venue_map_defaults(): void {
		$instance = Map_Prewarm::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'extract_block_combo',
			array( array() )
		);

		$this->assertSame( Map::DEFAULT_ZOOM, $result['zoom'] );
		$this->assertSame( 0, $result['width'] );
		$this->assertSame( Map::DEFAULT_HEIGHT, $result['height'] );
		$this->assertSame( Map::DEFAULT_ASPECT_RATIO, $result['aspect_ratio'] );
	}

	/**
	 * Dedupe collapses identical (zoom, width, height, aspect_ratio) tuples.
	 *
	 * @covers ::dedupe_combos
	 *
	 * @return void
	 */
	public function test_dedupe_combos_collapses_duplicates(): void {
		$instance = Map_Prewarm::get_instance();
		$combos   = array(
			array(
				'zoom'         => 15,
				'width'        => 800,
				'height'       => 400,
				'aspect_ratio' => '2/1',
			),
			array(
				'zoom'         => 15,
				'width'        => 800,
				'height'       => 400,
				'aspect_ratio' => '2/1',
			),
			array(
				'zoom'         => 18,
				'width'        => 600,
				'height'       => 300,
				'aspect_ratio' => '2/1',
			),
		);

		$result = Utility::invoke_hidden_method(
			$instance,
			'dedupe_combos',
			array( $combos )
		);

		$this->assertCount( 2, $result );
	}

	/**
	 * Schedules a cron event for the given (venue, combo) when enqueue is called.
	 *
	 * @covers ::enqueue_warm_job
	 *
	 * @return void
	 */
	public function test_enqueue_warm_job_schedules_cron_event(): void {
		$instance      = Map_Prewarm::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$combo         = array(
			'zoom'         => 15,
			'width'        => 800,
			'height'       => 400,
			'aspect_ratio' => '2/1',
		);

		Utility::invoke_hidden_method(
			$instance,
			'enqueue_warm_job',
			array( $venue_post_id, $combo )
		);

		$scheduled = wp_next_scheduled(
			Map_Prewarm::CRON_ACTION,
			array( $venue_post_id, 15, 800, 400, '2/1' )
		);
		$this->assertNotFalse( $scheduled, 'Warm job is scheduled.' );
	}

	/**
	 * Re-enqueuing the same (venue, combo) is a no-op — the first scheduled
	 * event sticks instead of being duplicated.
	 *
	 * @covers ::enqueue_warm_job
	 *
	 * @return void
	 */
	public function test_enqueue_warm_job_deduplicates_identical_args(): void {
		$instance      = Map_Prewarm::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$combo         = array(
			'zoom'         => 15,
			'width'        => 800,
			'height'       => 400,
			'aspect_ratio' => '2/1',
		);

		Utility::invoke_hidden_method( $instance, 'enqueue_warm_job', array( $venue_post_id, $combo ) );
		$first_timestamp = wp_next_scheduled(
			Map_Prewarm::CRON_ACTION,
			array( $venue_post_id, 15, 800, 400, '2/1' )
		);

		Utility::invoke_hidden_method( $instance, 'enqueue_warm_job', array( $venue_post_id, $combo ) );
		$second_timestamp = wp_next_scheduled(
			Map_Prewarm::CRON_ACTION,
			array( $venue_post_id, 15, 800, 400, '2/1' )
		);

		$this->assertSame( $first_timestamp, $second_timestamp, 'Dedup keeps the original schedule.' );
	}

	/**
	 * A non-null return from the `gatherpress_venue_map_prewarm_pre_enqueue_job`
	 * filter must suppress the default WP-Cron enqueue so a companion
	 * plugin can route the fanout through its own queue (e.g. Action
	 * Scheduler). The filter receives the hook name and args for routing
	 * decisions.
	 *
	 * @covers ::enqueue_warm_job
	 *
	 * @return void
	 */
	public function test_enqueue_warm_job_filter_short_circuits_wp_cron_path(): void {
		$instance      = Map_Prewarm::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$combo         = array(
			'zoom'         => 15,
			'width'        => 800,
			'height'       => 400,
			'aspect_ratio' => '2/1',
		);

		$seen = array();
		$spy  = static function ( $short_circuit, $hook, $args ) use ( &$seen ) {
			$seen[] = array(
				'hook' => $hook,
				'args' => $args,
			);
			// Non-null return value. Companion plugins would return an AS
			// action ID or similar; core only cares that it's not null.
			return 'handled-by-companion';
		};
		add_filter( 'gatherpress_venue_map_prewarm_pre_enqueue_job', $spy, 10, 3 );

		Utility::invoke_hidden_method( $instance, 'enqueue_warm_job', array( $venue_post_id, $combo ) );

		remove_filter( 'gatherpress_venue_map_prewarm_pre_enqueue_job', $spy, 10 );

		$this->assertFalse(
			wp_next_scheduled(
				Map_Prewarm::CRON_ACTION,
				array( $venue_post_id, 15, 800, 400, '2/1' )
			),
			'Default WP-Cron path must be suppressed when the filter returns non-null.'
		);
		$this->assertCount( 1, $seen, 'Filter is invoked exactly once per enqueue call.' );
		$this->assertSame( Map_Prewarm::CRON_ACTION, $seen[0]['hook'] );
		$this->assertSame(
			array( $venue_post_id, 15, 800, 400, '2/1' ),
			$seen[0]['args'],
			'Filter receives the same args that the hook would have fired with.'
		);
	}

	/**
	 * Every non-null return value — including falsy ones like `false`,
	 * `0`, and `''` — must suppress the default enqueue. Mirrors the
	 * WordPress `pre_*` filter contract (only `null` means "pass
	 * through") and locks the contract in so a future accident where
	 * the check becomes e.g. `if ( $short_circuit )` instead of
	 * `if ( null !== $short_circuit )` fails the suite.
	 *
	 * @covers ::enqueue_warm_job
	 *
	 * @return void
	 */
	public function test_enqueue_warm_job_filter_falsy_non_null_values_still_short_circuit(): void {
		$instance = Map_Prewarm::get_instance();

		foreach ( array( false, 0, '' ) as $index => $falsy_return ) {
			$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
			$combo         = array(
				'zoom'         => 15,
				// Unique per iteration so dedup can't mask a missing short-circuit.
				'width'        => 800 + $index,
				'height'       => 400,
				'aspect_ratio' => '2/1',
			);

			$callback = static function () use ( $falsy_return ) {
				return $falsy_return;
			};
			add_filter( 'gatherpress_venue_map_prewarm_pre_enqueue_job', $callback );

			Utility::invoke_hidden_method( $instance, 'enqueue_warm_job', array( $venue_post_id, $combo ) );

			remove_filter( 'gatherpress_venue_map_prewarm_pre_enqueue_job', $callback );

			$this->assertFalse(
				wp_next_scheduled(
					Map_Prewarm::CRON_ACTION,
					array( $venue_post_id, 15, 800 + $index, 400, '2/1' )
				),
				sprintf(
					'Filter returning %s (non-null) must suppress the default WP-Cron path.',
					wp_json_encode( $falsy_return )
				)
			);
		}
	}

	/**
	 * Returning `null` (the default) from the filter must leave the
	 * default WP-Cron behavior untouched — the whole point of the filter
	 * is to be a no-op when nothing hooks it.
	 *
	 * @covers ::enqueue_warm_job
	 *
	 * @return void
	 */
	public function test_enqueue_warm_job_filter_returning_null_preserves_default_path(): void {
		$instance      = Map_Prewarm::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$combo         = array(
			'zoom'         => 15,
			'width'        => 800,
			'height'       => 400,
			'aspect_ratio' => '2/1',
		);

		$passthrough = static function ( $short_circuit ) {
			return $short_circuit;
		};
		add_filter( 'gatherpress_venue_map_prewarm_pre_enqueue_job', $passthrough );

		Utility::invoke_hidden_method( $instance, 'enqueue_warm_job', array( $venue_post_id, $combo ) );

		remove_filter( 'gatherpress_venue_map_prewarm_pre_enqueue_job', $passthrough );

		$this->assertNotFalse(
			wp_next_scheduled(
				Map_Prewarm::CRON_ACTION,
				array( $venue_post_id, 15, 800, 400, '2/1' )
			),
			'Null return from the filter must fall through to wp_schedule_single_event().'
		);
	}

	/**
	 * Saving a venue post enqueues a warm job for that venue when any
	 * template in the site references a venue-map combo. We simulate the
	 * template existence by saving a wp_template first.
	 *
	 * @covers ::on_post_saved
	 * @covers ::enqueue_for_venue
	 *
	 * @return void
	 */
	public function test_on_post_saved_enqueues_for_venue(): void {
		$instance = Map_Prewarm::get_instance();

		// Create a venue so get_venue_post_ids has at least one result when
		// the template save would fan out; the test itself doesn't care
		// about the template path, only the venue save.
		$venue_post_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		// No templates have combos, so enqueue_for_venue iterates an empty
		// combo set. Expect no scheduled event, and no errors.
		$post = get_post( $venue_post_id );
		$instance->on_post_saved( $venue_post_id, $post );

		$this->assertFalse(
			(bool) wp_next_scheduled( Map_Prewarm::CRON_ACTION ),
			'No combos means no scheduled warm jobs.'
		);
	}

	/**
	 * Saving a revision or autosave does not enqueue anything — guard rail
	 * so rapid editor saves don't churn the cron queue.
	 *
	 * @covers ::on_post_saved
	 *
	 * @return void
	 */
	public function test_on_post_saved_ignores_revisions(): void {
		$instance = Map_Prewarm::get_instance();

		$venue_post_id = $this->factory->post->create(
			array(
				'post_type' => Venue::POST_TYPE,
			)
		);

		$revision_id = wp_save_post_revision( $venue_post_id );

		if ( false === $revision_id || is_wp_error( $revision_id ) ) {
			$this->markTestSkipped( 'Could not create a revision in this environment.' );
		}

		$revision = get_post( $revision_id );
		$instance->on_post_saved( $revision_id, $revision );

		$this->assertFalse(
			(bool) wp_next_scheduled( Map_Prewarm::CRON_ACTION ),
			'Revisions do not enqueue warm jobs.'
		);
	}

	/**
	 * Draft / trashed venue saves skip the enqueue path — only published
	 * posts are reachable from the front-end and contribute to the cache
	 * set, so unpublished saves shouldn't spend cron cycles on them.
	 *
	 * @covers ::on_post_saved
	 *
	 * @return void
	 */
	public function test_on_post_saved_skips_non_published_posts(): void {
		$instance = Map_Prewarm::get_instance();

		foreach ( array( 'draft', 'auto-draft', 'trash' ) as $status ) {
			$venue_post_id = $this->factory->post->create(
				array(
					'post_type'   => Venue::POST_TYPE,
					'post_status' => $status,
				)
			);

			$instance->on_post_saved( $venue_post_id, get_post( $venue_post_id ) );
		}

		$this->assertFalse(
			(bool) wp_next_scheduled( Map_Prewarm::CRON_ACTION ),
			'Non-published saves enqueue nothing.'
		);
	}

	/**
	 * Delegates to Map::warm from the cron handler — this test only
	 * verifies it tolerates a missing venue ID without throwing
	 * (Map::warm returns null for invalid input).
	 *
	 * @covers ::process_warm_job
	 *
	 * @return void
	 */
	public function test_process_warm_job_is_safe_for_missing_venue(): void {
		$instance = Map_Prewarm::get_instance();

		// No exception: warm() returns null for non-existent post IDs, and
		// the cron handler is expected to swallow that outcome silently.
		$instance->process_warm_job( 0, 15, 600, 300, '2/1' );
		$this->assertTrue( true );
	}

	/**
	 * Saving an FSE template (wp_template) fans the template's combos
	 * out across every known venue — one cron job per (venue, combo).
	 *
	 * @covers ::on_post_saved
	 * @covers ::enqueue_for_all_venues
	 *
	 * @return void
	 */
	public function test_on_post_saved_fan_outs_template_save_to_all_venues(): void {
		$instance = Map_Prewarm::get_instance();

		$venue_a = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$venue_b = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$template_id = $this->factory->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:gatherpress/venue-map '
					. '{"zoom":15,"width":800,"height":400,"aspectRatio":"2/1"} /-->',
			)
		);

		$instance->on_post_saved( $template_id, get_post( $template_id ) );

		$this->assertNotFalse(
			wp_next_scheduled(
				Map_Prewarm::CRON_ACTION,
				array( $venue_a, 15, 800, 400, '2/1' )
			),
			'Venue A received a warm job for the template combo.'
		);
		$this->assertNotFalse(
			wp_next_scheduled(
				Map_Prewarm::CRON_ACTION,
				array( $venue_b, 15, 800, 400, '2/1' )
			),
			'Venue B received a warm job for the template combo.'
		);
	}

	/**
	 * Saving a template without a venue-map block enqueues nothing — the
	 * early-return path on an empty combo list.
	 *
	 * @covers ::on_post_saved
	 * @covers ::enqueue_for_all_venues
	 *
	 * @return void
	 */
	public function test_on_post_saved_skips_template_without_venue_map(): void {
		$instance = Map_Prewarm::get_instance();

		$this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$template_id = $this->factory->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>No map here.</p><!-- /wp:paragraph -->',
			)
		);

		$instance->on_post_saved( $template_id, get_post( $template_id ) );

		$this->assertFalse(
			(bool) wp_next_scheduled( Map_Prewarm::CRON_ACTION ),
			'Templates without venue-map blocks enqueue nothing.'
		);
	}

	/**
	 * Triggers a full rescan against every venue on theme switch — even
	 * with no templates, the handler runs without error.
	 *
	 * @covers ::on_theme_switched
	 * @covers ::collect_all_template_combos
	 * @covers ::get_venue_post_ids
	 *
	 * @return void
	 */
	public function test_on_theme_switched_runs_without_error(): void {
		$instance = Map_Prewarm::get_instance();

		$this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$instance->on_theme_switched();

		// No assertion beyond "doesn't throw" — without a template that
		// references a combo, there's nothing to enqueue, but the full code
		// path is now exercised for coverage (collect + get_venue_post_ids).
		$this->assertTrue( true );
	}

	/**
	 * Returns published venue post IDs only.
	 *
	 * @covers ::get_venue_post_ids
	 *
	 * @return void
	 */
	public function test_get_venue_post_ids_returns_published_venues(): void {
		$instance = Map_Prewarm::get_instance();

		$published = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		// A draft should NOT appear in the warm-eligible set.
		$this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'draft',
			)
		);

		$ids = Utility::invoke_hidden_method( $instance, 'get_venue_post_ids' );

		$this->assertContains( $published, $ids );
	}

	/**
	 * Saving an event post with an embedded venue-map enqueues warm jobs
	 * against the associated venue (via the `_gatherpress_venue` taxonomy),
	 * not the event itself.
	 *
	 * @covers ::on_post_saved
	 *
	 * @return void
	 */
	public function test_on_post_saved_event_enqueues_via_linked_venue(): void {
		$instance    = Map_Prewarm::get_instance();
		$venue_setup = Setup::get_instance();

		$venue_post_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_name'   => 'some-venue',
				'post_status' => 'publish',
			)
		);

		$term_slug = $venue_setup->term_slug_from_post_name( 'some-venue' );
		wp_insert_term( 'Some Venue', Venue::TAXONOMY, array( 'slug' => $term_slug ) );

		$event_post_id = $this->factory->post->create(
			array(
				'post_type'    => 'gatherpress_event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:gatherpress/venue-map '
					. '{"zoom":12,"width":600,"height":300,"aspectRatio":"2/1"} /-->',
			)
		);
		wp_set_post_terms( $event_post_id, $term_slug, Venue::TAXONOMY );

		$instance->on_post_saved( $event_post_id, get_post( $event_post_id ) );

		$this->assertNotFalse(
			wp_next_scheduled(
				Map_Prewarm::CRON_ACTION,
				array( $venue_post_id, 12, 600, 300, '2/1' )
			),
			'Event save enqueues a warm job against the linked venue.'
		);
	}

	/**
	 * An event post with no linked venue — the term lookup short-circuits
	 * and no cron jobs are scheduled.
	 *
	 * @covers ::on_post_saved
	 *
	 * @return void
	 */
	public function test_on_post_saved_event_skips_when_no_venue_term(): void {
		$instance = Map_Prewarm::get_instance();

		$event_post_id = $this->factory->post->create(
			array(
				'post_type'    => 'gatherpress_event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:gatherpress/venue-map '
					. '{"zoom":12,"width":600,"height":300,"aspectRatio":"2/1"} /-->',
			)
		);

		$instance->on_post_saved( $event_post_id, get_post( $event_post_id ) );

		$this->assertFalse(
			(bool) wp_next_scheduled( Map_Prewarm::CRON_ACTION ),
			'Event with no venue term enqueues nothing.'
		);
	}

	/**
	 * Content scan paginates through event-post batches — with a batch
	 * size of 1 and two events contributing combos, the loop must tick
	 * past its full-batch branch (the ++$page path) to reach the second
	 * event.
	 *
	 * @covers ::collect_all_template_combos
	 *
	 * @return void
	 */
	public function test_collect_all_template_combos_paginates_event_content(): void {
		$instance = Map_Prewarm::get_instance();

		$this->factory->post->create(
			array(
				'post_type'    => 'gatherpress_event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:gatherpress/venue-map '
					. '{"zoom":5,"width":150,"height":75,"aspectRatio":"2/1"} /-->',
			)
		);
		$this->factory->post->create(
			array(
				'post_type'    => 'gatherpress_event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:gatherpress/venue-map '
					. '{"zoom":4,"width":120,"height":60,"aspectRatio":"2/1"} /-->',
			)
		);

		$one_per_page = static function () {
			return 1;
		};
		add_filter( 'gatherpress_venue_map_prewarm_content_batch_size', $one_per_page );

		$combos = Utility::invoke_hidden_method( $instance, 'collect_all_template_combos' );

		remove_filter( 'gatherpress_venue_map_prewarm_content_batch_size', $one_per_page );

		$keys = array_map(
			static function ( $combo ) {
				return sprintf(
					'%d-%d-%d-%s',
					(int) $combo['zoom'],
					(int) $combo['width'],
					(int) $combo['height'],
					(string) $combo['aspect_ratio']
				);
			},
			$combos
		);

		$this->assertContains( '5-150-75-2/1', $keys, 'First event combo surfaced.' );
		$this->assertContains( '4-120-60-2/1', $keys, 'Second event combo surfaced through the ++$page tail.' );
	}

	/**
	 * Content-scan batch size is separately filterable so an extender can
	 * shrink the post_content-loading loop without touching the ID-only
	 * venue scan. Clamps to at least 1.
	 *
	 * @covers ::get_content_scan_batch_size
	 *
	 * @return void
	 */
	public function test_get_content_scan_batch_size_applies_filter_and_clamps(): void {
		$instance = Map_Prewarm::get_instance();

		$this->assertSame(
			Map_Prewarm::CONTENT_SCAN_BATCH_SIZE,
			Utility::invoke_hidden_method( $instance, 'get_content_scan_batch_size' ),
			'Default matches the CONTENT_SCAN_BATCH_SIZE constant.'
		);

		$override = static function () {
			return 7;
		};
		add_filter( 'gatherpress_venue_map_prewarm_content_batch_size', $override );

		$this->assertSame(
			7,
			Utility::invoke_hidden_method( $instance, 'get_content_scan_batch_size' ),
			'Filter-supplied value replaces the default.'
		);

		remove_filter( 'gatherpress_venue_map_prewarm_content_batch_size', $override );

		$clamp = static function () {
			return -5;
		};
		add_filter( 'gatherpress_venue_map_prewarm_content_batch_size', $clamp );

		$this->assertSame(
			1,
			Utility::invoke_hidden_method( $instance, 'get_content_scan_batch_size' ),
			'Values below 1 clamp up to 1.'
		);

		remove_filter( 'gatherpress_venue_map_prewarm_content_batch_size', $clamp );

		$ceiling = static function () {
			return PHP_INT_MAX;
		};
		add_filter( 'gatherpress_venue_map_prewarm_content_batch_size', $ceiling );

		$this->assertSame(
			1000,
			Utility::invoke_hidden_method( $instance, 'get_content_scan_batch_size' ),
			'Values above 1000 clamp down so a misbehaving filter can\'t load every event at once.'
		);

		remove_filter( 'gatherpress_venue_map_prewarm_content_batch_size', $ceiling );
	}

	/**
	 * Batch-size filter lets callers override SCAN_BATCH_SIZE; values below
	 * 1 are clamped up to 1 to avoid an infinite empty-batch loop.
	 *
	 * @covers ::get_scan_batch_size
	 *
	 * @return void
	 */
	public function test_get_scan_batch_size_applies_filter_and_clamps(): void {
		$instance = Map_Prewarm::get_instance();

		$this->assertSame(
			Map_Prewarm::SCAN_BATCH_SIZE,
			Utility::invoke_hidden_method( $instance, 'get_scan_batch_size' ),
			'Default matches the SCAN_BATCH_SIZE constant.'
		);

		$override = static function () {
			return 25;
		};
		add_filter( 'gatherpress_venue_map_prewarm_batch_size', $override );

		$this->assertSame(
			25,
			Utility::invoke_hidden_method( $instance, 'get_scan_batch_size' ),
			'Filter-supplied value replaces the default.'
		);

		remove_filter( 'gatherpress_venue_map_prewarm_batch_size', $override );

		$clamp = static function () {
			return 0;
		};
		add_filter( 'gatherpress_venue_map_prewarm_batch_size', $clamp );

		$this->assertSame(
			1,
			Utility::invoke_hidden_method( $instance, 'get_scan_batch_size' ),
			'Values below 1 clamp up to 1.'
		);

		remove_filter( 'gatherpress_venue_map_prewarm_batch_size', $clamp );
	}

	/**
	 * Pagination actually iterates — with a batch size of 1 and three
	 * published venues, a template save enqueues warm jobs for all three,
	 * exercising the multi-page loop tail.
	 *
	 * @covers ::enqueue_for_all_venues
	 *
	 * @return void
	 */
	public function test_enqueue_for_all_venues_paginates(): void {
		$instance = Map_Prewarm::get_instance();

		$venue_ids = array(
			$this->factory->post->create(
				array(
					'post_type'   => Venue::POST_TYPE,
					'post_status' => 'publish',
				)
			),
			$this->factory->post->create(
				array(
					'post_type'   => Venue::POST_TYPE,
					'post_status' => 'publish',
				)
			),
			$this->factory->post->create(
				array(
					'post_type'   => Venue::POST_TYPE,
					'post_status' => 'publish',
				)
			),
		);

		$one_per_page = static function () {
			return 1;
		};
		add_filter( 'gatherpress_venue_map_prewarm_batch_size', $one_per_page );

		$template_id = $this->factory->post->create(
			array(
				'post_type'    => 'wp_template',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:gatherpress/venue-map '
					. '{"zoom":11,"width":500,"height":250,"aspectRatio":"2/1"} /-->',
			)
		);

		$instance->on_post_saved( $template_id, get_post( $template_id ) );

		remove_filter( 'gatherpress_venue_map_prewarm_batch_size', $one_per_page );

		foreach ( $venue_ids as $venue_post_id ) {
			$this->assertNotFalse(
				wp_next_scheduled(
					Map_Prewarm::CRON_ACTION,
					array( $venue_post_id, 11, 500, 250, '2/1' )
				),
				sprintf( 'Venue %d received a warm job through the paginated loop.', $venue_post_id )
			);
		}
	}

	/**
	 * Pagination through collect_all_template_combos + get_venue_post_ids —
	 * shrinks the batch so the loop tails run with only a couple of fixtures.
	 *
	 * @covers ::collect_all_template_combos
	 * @covers ::get_venue_post_ids
	 *
	 * @return void
	 */
	public function test_collect_and_get_ids_paginate(): void {
		$instance = Map_Prewarm::get_instance();

		$this->factory->post->create(
			array(
				'post_type'    => 'gatherpress_event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:gatherpress/venue-map '
					. '{"zoom":8,"width":200,"height":100,"aspectRatio":"2/1"} /-->',
			)
		);
		$this->factory->post->create(
			array(
				'post_type'    => 'gatherpress_event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:gatherpress/venue-map '
					. '{"zoom":9,"width":300,"height":150,"aspectRatio":"2/1"} /-->',
			)
		);
		$venue_ids = array(
			$this->factory->post->create(
				array(
					'post_type'   => Venue::POST_TYPE,
					'post_status' => 'publish',
				)
			),
			$this->factory->post->create(
				array(
					'post_type'   => Venue::POST_TYPE,
					'post_status' => 'publish',
				)
			),
		);

		$one_per_page = static function () {
			return 1;
		};
		add_filter( 'gatherpress_venue_map_prewarm_batch_size', $one_per_page );

		$combos = Utility::invoke_hidden_method( $instance, 'collect_all_template_combos' );
		$ids    = Utility::invoke_hidden_method( $instance, 'get_venue_post_ids' );

		remove_filter( 'gatherpress_venue_map_prewarm_batch_size', $one_per_page );

		$this->assertCount( 2, $combos, 'Both event combos collected through the paginated event loop.' );
		foreach ( $venue_ids as $venue_post_id ) {
			$this->assertContains( $venue_post_id, $ids, 'Venue collected through the paginated ID loop.' );
		}
	}

	/**
	 * Iterates the combo list collected from templates and events from
	 * enqueue_for_venue, scheduling a job for each — covers the inner loop
	 * body on the non-empty combo path.
	 *
	 * @covers ::enqueue_for_venue
	 *
	 * @return void
	 */
	public function test_enqueue_for_venue_schedules_jobs_for_each_combo(): void {
		$instance = Map_Prewarm::get_instance();

		$venue_post_id = $this->factory->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		// Seed an event whose content contributes a combo to the
		// collect_all_template_combos pool.
		$this->factory->post->create(
			array(
				'post_type'    => 'gatherpress_event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:gatherpress/venue-map '
					. '{"zoom":13,"width":700,"height":350,"aspectRatio":"2/1"} /-->',
			)
		);

		Utility::invoke_hidden_method(
			$instance,
			'enqueue_for_venue',
			array( $venue_post_id )
		);

		$this->assertNotFalse(
			wp_next_scheduled(
				Map_Prewarm::CRON_ACTION,
				array( $venue_post_id, 13, 700, 350, '2/1' )
			),
			'Venue received a warm job for the combo surfaced via enqueue_for_venue.'
		);
	}

	/**
	 * When no post type supports gatherpress-venue-information,
	 * enqueue_for_all_venues and get_venue_post_ids short-circuit without
	 * scheduling anything. Filters the supports list to simulate a site
	 * where nothing registers as a venue source.
	 *
	 * @covers ::enqueue_for_all_venues
	 * @covers ::get_venue_post_ids
	 *
	 * @return void
	 */
	public function test_scans_short_circuit_without_venue_types(): void {
		$instance = Map_Prewarm::get_instance();

		// Temporarily unregister the venue-information support so the
		// gatherpress-venue-information feature has no matching post types.
		$supported = get_post_types_by_support( 'gatherpress-venue-information' );
		foreach ( $supported as $post_type ) {
			remove_post_type_support( $post_type, 'gatherpress-venue-information' );
		}

		try {
			$combos = array(
				array(
					'zoom'         => 15,
					'width'        => 800,
					'height'       => 400,
					'aspect_ratio' => '2/1',
				),
			);

			Utility::invoke_hidden_method(
				$instance,
				'enqueue_for_all_venues',
				array( $combos )
			);

			$ids = Utility::invoke_hidden_method( $instance, 'get_venue_post_ids' );
		} finally {
			foreach ( $supported as $post_type ) {
				add_post_type_support( $post_type, 'gatherpress-venue-information' );
			}
		}

		$this->assertFalse(
			(bool) wp_next_scheduled( Map_Prewarm::CRON_ACTION ),
			'No venue types means no scheduled warm jobs.'
		);
		$this->assertSame( array(), $ids, 'get_venue_post_ids returns [] when no venue types are registered.' );
	}

	/**
	 * Feeds combos into collect_all_template_combos when
	 * get_block_templates() returns venue-map-bearing templates — covers
	 * the DB-template + template-part scan branches. Factory posts don't
	 * register as block templates the way theme resolution does, so we
	 * inject the results through the `get_block_templates` filter.
	 *
	 * @covers ::collect_all_template_combos
	 *
	 * @return void
	 */
	public function test_collect_all_template_combos_scans_db_templates(): void {
		if ( ! class_exists( 'WP_Block_Template' ) ) {
			$this->markTestSkipped( 'WP_Block_Template not available in this environment.' );
		}

		$instance = Map_Prewarm::get_instance();

		$template          = new \WP_Block_Template();
		$template->content = '<!-- wp:gatherpress/venue-map '
			. '{"zoom":7,"width":400,"height":200,"aspectRatio":"2/1"} /-->';

		$part          = new \WP_Block_Template();
		$part->content = '<!-- wp:gatherpress/venue-map '
			. '{"zoom":6,"width":350,"height":175,"aspectRatio":"2/1"} /-->';

		$inject = static function ( $query_result, $query, $template_type ) use ( $template, $part ) {
			unset( $query );
			if ( 'wp_template' === $template_type ) {
				return array( $template );
			}
			if ( 'wp_template_part' === $template_type ) {
				return array( $part );
			}
			return $query_result;
		};
		add_filter( 'get_block_templates', $inject, 10, 3 );

		$combos = Utility::invoke_hidden_method( $instance, 'collect_all_template_combos' );

		remove_filter( 'get_block_templates', $inject, 10 );

		$keys = array_map(
			static function ( $combo ) {
				return sprintf(
					'%d-%d-%d-%s',
					(int) $combo['zoom'],
					(int) $combo['width'],
					(int) $combo['height'],
					(string) $combo['aspect_ratio']
				);
			},
			$combos
		);

		$this->assertContains( '7-400-200-2/1', $keys, 'Template combo surfaced through the DB-template branch.' );
		$this->assertContains( '6-350-175-2/1', $keys, 'Template-part combo surfaced through the DB branch.' );
	}

	/**
	 * Saving an event post without any venue-map block early-returns before
	 * the term lookup runs.
	 *
	 * @covers ::on_post_saved
	 *
	 * @return void
	 */
	public function test_on_post_saved_event_skips_when_no_combos(): void {
		$instance = Map_Prewarm::get_instance();

		$event_post_id = $this->factory->post->create(
			array(
				'post_type'    => 'gatherpress_event',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>no map</p><!-- /wp:paragraph -->',
			)
		);

		$instance->on_post_saved( $event_post_id, get_post( $event_post_id ) );

		$this->assertFalse(
			(bool) wp_next_scheduled( Map_Prewarm::CRON_ACTION ),
			'No combos in event content means no cron jobs.'
		);
	}
}
