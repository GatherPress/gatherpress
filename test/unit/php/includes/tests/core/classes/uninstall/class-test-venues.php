<?php
/**
 * Unit tests for the venue uninstall task.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Event;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Uninstall\Venues;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Venues.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Venues
 */
class Test_Venues extends Base {

	/**
	 * Arms and resets the uninstall opt-ins.
	 */
	use Preferences_Fixture;

	/**
	 * Reset the opt-in map between tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->reset_uninstall_preferences();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->reset_uninstall_preferences();

		parent::tearDown();
	}

	/**
	 * How many rows the venue taxonomy still has.
	 *
	 * @since 0.36.0
	 *
	 * @return int The number of term_taxonomy rows.
	 */
	protected function count_taxonomy_rows(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", Venue::TAXONOMY )
		);
	}

	/**
	 * The task stays off until it is opted in to.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_does_not_apply_by_default(): void {
		$this->assertFalse(
			( new Venues() )->applies(),
			'Removing venues must wait for an explicit opt-in.'
		);
	}

	/**
	 * The task applies once its preference is on.
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies_when_opted_in(): void {
		$this->arm_uninstall( Preferences::TASK_VENUES );

		$this->assertTrue( ( new Venues() )->applies(), 'The opt-in turns the task on.' );
	}

	/**
	 * The venue post type is the one this task removes.
	 *
	 * @covers ::post_type
	 *
	 * @return void
	 */
	public function test_post_type_is_the_venue_post_type(): void {
		$this->assertSame(
			Venue::POST_TYPE,
			Utility::invoke_hidden_method( new Venues(), 'post_type' ),
			'The task removes venues and nothing else.'
		);
	}

	/**
	 * Venues survive when the task was never opted in to.
	 *
	 * @covers ::applies
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_leaves_venues_alone_without_opt_in(): void {
		global $wpdb;

		$venue_id = (int) self::factory()->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		( new Venues() )->run();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the row survived.
		$rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $venue_id )
		);

		$this->assertSame( 1, $rows, 'A task that was never opted in to must remove nothing.' );
		$this->assertGreaterThan(
			0,
			$this->count_taxonomy_rows(),
			'The shadow term for the published venue is still there.'
		);
	}

	/**
	 * Venues go, events stay, and the shadow taxonomy goes with the venues.
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_removes_venues_and_their_shadow_terms(): void {
		global $wpdb;

		$this->arm_uninstall( Preferences::TASK_VENUES );

		$venue_id = (int) self::factory()->post->create(
			array(
				'post_type'   => Venue::POST_TYPE,
				'post_status' => 'publish',
				'post_name'   => 'the-hall',
			)
		);
		$event_id = (int) self::factory()->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$term = get_term_by( 'slug', '_the-hall', Venue::TAXONOMY );

		$this->assertInstanceOf(
			\WP_Term::class,
			$term,
			'Publishing a venue creates the term that shadows it.'
		);

		wp_set_object_terms( $event_id, array( $term->term_id ), Venue::TAXONOMY );

		( new Venues() )->run();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$venue_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $venue_id )
		);

		$this->assertSame( 0, $venue_rows, 'The venue is removed.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying the row survived.
		$event_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID = %d", $event_id )
		);

		$this->assertSame( 1, $event_rows, 'Removing venues must leave events where they are.' );
		$this->assertSame(
			0,
			$this->count_taxonomy_rows(),
			'A term naming a venue that no longer exists is an orphan, so the taxonomy goes with the posts.'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Verifying rows were deleted.
		$relationship_rows = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id = %d", $event_id )
		);

		$this->assertSame(
			0,
			$relationship_rows,
			'The event stops being tagged with a venue that is gone.'
		);
	}

	/**
	 * The task names the preference that gates it.
	 *
	 * Invoked directly as well as through `applies()`, because xdebug does
	 * not trace a protected method called from the parent class.
	 *
	 * @covers ::preference
	 *
	 * @return void
	 */
	public function test_preference_names_its_task(): void {
		$this->assertSame(
			Preferences::TASK_VENUES,
			Utility::invoke_hidden_method( new Venues(), 'preference' ),
			'The task reads the opt-in for venues and nothing else.'
		);
	}
}
