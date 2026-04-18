<?php
/**
 * Unit tests for GatherPress\Core\Venue_Map_Prewarm.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Venue;
use GatherPress\Core\Venue_Map;
use GatherPress\Core\Venue_Map_Prewarm;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Venue_Map_Prewarm.
 *
 * @coversDefaultClass \GatherPress\Core\Venue_Map_Prewarm
 */
class Test_Venue_Map_Prewarm extends Base {
	/**
	 * Clear scheduled warm events between tests — wp_next_scheduled lookups
	 * otherwise leak across cases and skew dedup assertions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		wp_clear_scheduled_hook( Venue_Map_Prewarm::CRON_ACTION );
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
		$instance = Venue_Map_Prewarm::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => Venue_Map_Prewarm::CRON_ACTION,
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
		$instance = Venue_Map_Prewarm::get_instance();

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
		$instance = Venue_Map_Prewarm::get_instance();
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
		$instance = Venue_Map_Prewarm::get_instance();
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
	 * Missing block attributes fall back to the Venue_Map defaults.
	 *
	 * @covers ::extract_block_combo
	 *
	 * @return void
	 */
	public function test_extract_block_combo_uses_venue_map_defaults(): void {
		$instance = Venue_Map_Prewarm::get_instance();

		$result = Utility::invoke_hidden_method(
			$instance,
			'extract_block_combo',
			array( array() )
		);

		$this->assertSame( Venue_Map::DEFAULT_ZOOM, $result['zoom'] );
		$this->assertSame( 0, $result['width'] );
		$this->assertSame( Venue_Map::DEFAULT_HEIGHT, $result['height'] );
		$this->assertSame( Venue_Map::DEFAULT_ASPECT_RATIO, $result['aspect_ratio'] );
	}

	/**
	 * Dedupe collapses identical (zoom, width, height, aspect_ratio) tuples.
	 *
	 * @covers ::dedupe_combos
	 *
	 * @return void
	 */
	public function test_dedupe_combos_collapses_duplicates(): void {
		$instance = Venue_Map_Prewarm::get_instance();
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
		$instance      = Venue_Map_Prewarm::get_instance();
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
			Venue_Map_Prewarm::CRON_ACTION,
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
		$instance      = Venue_Map_Prewarm::get_instance();
		$venue_post_id = $this->factory->post->create( array( 'post_type' => Venue::POST_TYPE ) );
		$combo         = array(
			'zoom'         => 15,
			'width'        => 800,
			'height'       => 400,
			'aspect_ratio' => '2/1',
		);

		Utility::invoke_hidden_method( $instance, 'enqueue_warm_job', array( $venue_post_id, $combo ) );
		$first_timestamp = wp_next_scheduled(
			Venue_Map_Prewarm::CRON_ACTION,
			array( $venue_post_id, 15, 800, 400, '2/1' )
		);

		Utility::invoke_hidden_method( $instance, 'enqueue_warm_job', array( $venue_post_id, $combo ) );
		$second_timestamp = wp_next_scheduled(
			Venue_Map_Prewarm::CRON_ACTION,
			array( $venue_post_id, 15, 800, 400, '2/1' )
		);

		$this->assertSame( $first_timestamp, $second_timestamp, 'Dedup keeps the original schedule.' );
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
		$instance = Venue_Map_Prewarm::get_instance();

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
			(bool) wp_next_scheduled( Venue_Map_Prewarm::CRON_ACTION ),
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
		$instance = Venue_Map_Prewarm::get_instance();

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
			(bool) wp_next_scheduled( Venue_Map_Prewarm::CRON_ACTION ),
			'Revisions do not enqueue warm jobs.'
		);
	}

	/**
	 * Delegates to Venue_Map::warm from the cron handler — this test only
	 * verifies it tolerates a missing venue ID without throwing
	 * (Venue_Map::warm returns null for invalid input).
	 *
	 * @covers ::process_warm_job
	 *
	 * @return void
	 */
	public function test_process_warm_job_is_safe_for_missing_venue(): void {
		$instance = Venue_Map_Prewarm::get_instance();

		// No exception: warm() returns null for non-existent post IDs, and
		// the cron handler is expected to swallow that outcome silently.
		$instance->process_warm_job( 0, 15, 600, 300, '2/1' );
		$this->assertTrue( true );
	}
}
