<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Status.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Event\Status;
use GatherPress\Tests\Base;

/**
 * Class Test_Status.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Status
 */
class Test_Status extends Base {
	/**
	 * The defaults cover Schema.org's vocabulary and each carries what the
	 * standards need.
	 *
	 * @since TBD
	 *
	 * @covers ::all
	 * @covers ::slugs
	 *
	 * @return void
	 */
	public function test_all_describes_every_default_status(): void {
		$statuses = Status::all();

		$this->assertSame(
			array(
				'scheduled',
				'canceled',
				'postponed',
				'rescheduled',
				'moved-online',
				'tentative',
			),
			Status::slugs(),
			'Failed to assert the default statuses are offered in order.'
		);

		foreach ( $statuses as $slug => $status ) {
			$this->assertNotEmpty( $status['label'], sprintf( '%s should be named.', $slug ) );
			$this->assertNotEmpty( $status['description'], sprintf( '%s should be explained.', $slug ) );
			$this->assertNotEmpty( $status['schema'], sprintf( '%s should name a Schema.org value.', $slug ) );
			$this->assertIsInt( $status['priority'], sprintf( '%s should carry an integer priority.', $slug ) );
			$this->assertContains(
				$status['ical'],
				array( 'CONFIRMED', 'CANCELLED', 'TENTATIVE' ),
				sprintf( '%s should name one of RFC 5545\'s three values.', $slug )
			);
		}
	}

	/**
	 * A status reports the words and standard values it was given.
	 *
	 * @since TBD
	 *
	 * @covers ::label
	 * @covers ::description
	 * @covers ::schema
	 * @covers ::ical
	 * @covers ::exists
	 * @covers ::priority
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_a_known_status_reports_itself(): void {
		$this->assertTrue( Status::exists( 'canceled' ), 'Failed to assert a default status exists.' );
		$this->assertSame( 'Canceled', Status::label( 'canceled' ) );
		$this->assertStringContainsString( 'will not take place', Status::description( 'canceled' ) );
		$this->assertSame( 'EventCancelled', Status::schema( 'canceled' ) );
		$this->assertSame( 'CANCELLED', Status::ical( 'canceled' ) );
		$this->assertSame( 50, Status::priority( 'canceled' ) );

		// An event that has moved online maps to Schema.org's EventMovedOnline.
		$this->assertSame( 'Moved online', Status::label( 'moved-online' ) );
		$this->assertSame( 'EventMovedOnline', Status::schema( 'moved-online' ) );
		$this->assertSame( 'CONFIRMED', Status::ical( 'moved-online' ) );
		$this->assertSame( 20, Status::priority( 'moved-online' ) );

		// A tentative event is provisionally scheduled and awaiting confirmation.
		$this->assertTrue( Status::exists( 'tentative' ), 'Failed to assert tentative status exists.' );
		$this->assertSame( 'Tentative', Status::label( 'tentative' ) );
		$this->assertStringContainsString( 'awaiting confirmation', Status::description( 'tentative' ) );
		$this->assertSame( 'EventScheduled', Status::schema( 'tentative' ) );
		$this->assertSame( 'TENTATIVE', Status::ical( 'tentative' ) );
		$this->assertSame( 10, Status::priority( 'tentative' ) );
	}

	/**
	 * An unknown status is treated as scheduled rather than published as
	 * something a calendar client would misread.
	 *
	 * @since TBD
	 *
	 * @covers ::exists
	 * @covers ::label
	 * @covers ::description
	 * @covers ::schema
	 * @covers ::ical
	 * @covers ::priority
	 *
	 * @return void
	 */
	public function test_an_unknown_status_falls_back_to_scheduled(): void {
		$this->assertFalse( Status::exists( 'not-a-status' ) );
		$this->assertSame( 'Scheduled', Status::label( 'not-a-status' ) );
		$this->assertSame(
			Status::description( 'scheduled' ),
			Status::description( 'not-a-status' ),
			'Failed to assert an unknown status borrows the default explanation.'
		);
		$this->assertSame( 'EventScheduled', Status::schema( 'not-a-status' ) );
		$this->assertSame( 'CONFIRMED', Status::ical( 'not-a-status' ) );
		$this->assertSame( 0, Status::priority( 'not-a-status' ) );
	}

	/**
	 * A site can add a status of its own.
	 *
	 * @since TBD
	 *
	 * @covers ::all
	 * @covers ::slugs
	 * @covers ::exists
	 * @covers ::label
	 *
	 * @return void
	 */
	public function test_a_site_can_register_its_own_status(): void {
		$callback = static function ( array $statuses ): array {
			$statuses['sold-out'] = array(
				'label'       => 'Sold out',
				'description' => 'Every place has been taken.',
				'schema'      => 'EventScheduled',
				'ical'        => 'CONFIRMED',
			);

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		$this->assertTrue( Status::exists( 'sold-out' ), 'Failed to assert a registered status exists.' );
		$this->assertSame( 'Sold out', Status::label( 'sold-out' ) );
		$this->assertContains( 'sold-out', Status::slugs(), 'Failed to assert a registered status is offered.' );

		remove_filter( 'gatherpress_event_statuses', $callback );

		$this->assertFalse( Status::exists( 'sold-out' ), 'Failed to assert the status leaves with its filter.' );
	}

	/**
	 * A registered status that names no standard values still publishes
	 * something true, rather than telling a calendar client nothing.
	 *
	 * @since TBD
	 *
	 * @covers ::schema
	 * @covers ::ical
	 *
	 * @return void
	 */
	public function test_a_registered_status_may_omit_the_standards(): void {
		$callback = static function ( array $statuses ): array {
			$statuses['provisional'] = array( 'label' => 'Provisional' );

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		$this->assertSame( Status::DEFAULT_SCHEMA, Status::schema( 'provisional' ) );
		$this->assertSame( Status::DEFAULT_ICAL, Status::ical( 'provisional' ) );
		$this->assertSame( 0, Status::priority( 'provisional' ) );

		remove_filter( 'gatherpress_event_statuses', $callback );
	}

	/**
	 * A status reports its priority for conflict resolution.
	 *
	 * @since TBD
	 *
	 * @covers ::priority
	 *
	 * @return void
	 */
	public function test_priority_reports_configured_or_default_value(): void {
		$this->assertSame( 50, Status::priority( 'canceled' ) );
		$this->assertSame( 40, Status::priority( 'postponed' ) );
		$this->assertSame( 30, Status::priority( 'rescheduled' ) );
		$this->assertSame( 20, Status::priority( 'moved-online' ) );
		$this->assertSame( 10, Status::priority( 'tentative' ) );
		$this->assertSame( 0, Status::priority( 'scheduled' ) );
		$this->assertSame( 0, Status::priority( 'non-existent' ) );

		$callback = static function ( array $statuses ): array {
			$statuses['urgent'] = array(
				'label'    => 'Urgent',
				'priority' => 99,
			);

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		$this->assertSame( 99, Status::priority( 'urgent' ) );

		remove_filter( 'gatherpress_event_statuses', $callback );
	}

	/**
	 * A site can take a status away as readily as it can add one.
	 *
	 * @since TBD
	 *
	 * @covers ::all
	 * @covers ::slugs
	 * @covers ::default_slug
	 *
	 * @return void
	 */
	public function test_a_site_can_remove_a_status(): void {
		$callback = static function ( array $statuses ): array {
			unset( $statuses['scheduled'] );

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		$this->assertFalse( Status::exists( 'scheduled' ), 'Failed to assert a status can be removed.' );
		$this->assertSame(
			'canceled',
			Status::default_slug(),
			'Failed to assert the first status left becomes the default.'
		);

		remove_filter( 'gatherpress_event_statuses', $callback );
	}

	/**
	 * A status can name the post type support it depends on.
	 *
	 * @since TBD
	 *
	 * @covers ::all
	 * @covers ::exists
	 *
	 * @return void
	 */
	public function test_a_status_can_depend_on_a_post_type_support(): void {
		$this->assertTrue(
			Status::exists( 'moved-online', Event::POST_TYPE ),
			'Failed to assert an event that can say where it is may have moved online.'
		);
		$this->assertFalse(
			Status::exists( 'moved-online', 'post' ),
			'Failed to assert a post type with no venue or online support is not offered moved online.'
		);
		$this->assertTrue(
			Status::exists( 'canceled', 'post' ),
			'Failed to assert a status that names no support is offered everywhere.'
		);
	}

	/**
	 * The filter has the last word, so a site can put back a status its post
	 * type would not otherwise be offered.
	 *
	 * @since TBD
	 *
	 * @covers ::all
	 * @covers ::exists
	 *
	 * @return void
	 */
	public function test_the_filter_overrides_the_support_gate(): void {
		$callback = static function ( array $statuses, string $post_type ): array {
			if ( 'post' === $post_type ) {
				$statuses['moved-online'] = array( 'label' => 'Moved online' );
			}

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback, 10, 2 );

		$this->assertTrue(
			Status::exists( 'moved-online', 'post' ),
			'Failed to assert the filter can put back a gated status.'
		);

		remove_filter( 'gatherpress_event_statuses', $callback, 10 );
	}

	/**
	 * A status gets a term named the way people read it.
	 *
	 * Without this a term is created from the slug, and an event would say
	 * `canceled` where it should say `Canceled`.
	 *
	 * @since TBD
	 *
	 * @covers ::ensure_term
	 *
	 * @return void
	 */
	public function test_ensure_term_names_the_term(): void {
		wp_delete_term(
			(int) ( get_term_by( 'slug', 'canceled', Event::TAXONOMY_STATUS )->term_id ?? 0 ),
			Event::TAXONOMY_STATUS
		);

		Status::ensure_term( 'canceled' );

		$term = get_term_by( 'slug', 'canceled', Event::TAXONOMY_STATUS );

		$this->assertSame( 'Canceled', $term->name, 'Failed to assert the term carries the label.' );
	}

	/**
	 * A term whose name has drifted from the vocabulary is put right.
	 *
	 * @since TBD
	 *
	 * @covers ::ensure_term
	 *
	 * @return void
	 */
	public function test_ensure_term_corrects_a_stale_name(): void {
		Status::ensure_term( 'postponed' );

		$term = get_term_by( 'slug', 'postponed', Event::TAXONOMY_STATUS );

		wp_update_term( $term->term_id, Event::TAXONOMY_STATUS, array( 'name' => 'Put off' ) );

		Status::ensure_term( 'postponed' );

		$this->assertSame(
			'Postponed',
			get_term_by( 'slug', 'postponed', Event::TAXONOMY_STATUS )->name,
			'Failed to assert a drifted name is corrected.'
		);
	}

	/**
	 * A status the vocabulary does not know gets no term.
	 *
	 * @since TBD
	 *
	 * @covers ::ensure_term
	 *
	 * @return void
	 */
	public function test_ensure_term_ignores_an_unknown_status(): void {
		$callback = static function (): array {
			return array();
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		Status::ensure_term( 'nothing-at-all' );

		remove_filter( 'gatherpress_event_statuses', $callback );

		$this->assertFalse(
			get_term_by( 'slug', 'nothing-at-all', Event::TAXONOMY_STATUS ),
			'Failed to assert an unknown status creates no term.'
		);
	}

	/**
	 * Coverage for get.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get(): void {
		$post   = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$status = new Status( $post->ID );

		// Default status is scheduled.
		$this->assertSame( 'scheduled', $status->get() );

		// Set canceled status.
		$status->set( 'canceled' );
		$this->assertSame( 'canceled', $status->get() );

		// Set postponed status.
		$status->set( 'postponed' );
		$this->assertSame( 'postponed', $status->get() );

		// Set tentative status.
		$status->set( 'tentative' );
		$this->assertSame( 'tentative', $status->get() );

		// Invalid status falls back to scheduled.
		wp_set_object_terms( $post->ID, 'invalid-status', Event::TAXONOMY_STATUS );
		$this->assertSame( 'scheduled', $status->get() );

		// An event with no status terms falls back to scheduled.
		wp_delete_object_term_relationships( $post->ID, Event::TAXONOMY_STATUS );
		$this->assertSame( 'scheduled', $status->get() );

		// Non-existent event falls back to scheduled.
		$non_event    = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$empty_status = new Status( $non_event->ID );
		$this->assertSame( 'scheduled', $empty_status->get() );
	}

	/**
	 * Coverage for set method.
	 *
	 * @covers ::set
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_set(): void {
		$post   = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$status = new Status( $post->ID );

		$this->assertTrue(
			$status->set( 'canceled' ),
			'Failed to assert a valid status is stored.'
		);
		$this->assertSame(
			'canceled',
			$status->get(),
			'Failed to assert the stored status reads back.'
		);

		// A second status replaces the first rather than adding to it, which is
		// what keeps the statuses mutually exclusive.
		$status->set( 'postponed' );

		$terms = wp_get_object_terms( $post->ID, Event::TAXONOMY_STATUS, array( 'fields' => 'slugs' ) );

		$this->assertSame(
			array( 'postponed' ),
			$terms,
			'Failed to assert a new status replaces the previous term.'
		);

		$this->assertFalse(
			$status->set( 'not-a-status' ),
			'Failed to assert an unknown status is refused.'
		);
		$this->assertSame(
			'postponed',
			$status->get(),
			'Failed to assert a refused status leaves the stored one alone.'
		);

		// A post that is not an event never gets a post assigned in the
		// constructor, so it has no status to set. Status( 0 ) would not do here:
		// get_post_type( 0 ) falls back to the global post, which this test has.
		$non_event = $this->mock->post( array( 'post_type' => 'post' ) )->get();

		$this->assertFalse(
			( new Status( $non_event->ID ) )->set( 'canceled' ),
			'Failed to assert a post that is not an event refuses a status.'
		);
	}

	/**
	 * A status that cannot be written is reported as not written.
	 *
	 * Unregistering the taxonomy is the reachable way to make the term write
	 * fail, which is what a site would see if something removed it.
	 *
	 * @since TBD
	 *
	 * @covers ::set
	 *
	 * @return void
	 */
	public function test_set_reports_a_failed_write(): void {
		$post   = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$status = new Status( $post->ID );

		unregister_taxonomy( Event::TAXONOMY_STATUS );

		$this->assertFalse(
			$status->set( 'canceled' ),
			'Failed to assert an unwritable status is refused.'
		);

		Event_Setup::get_instance()->register_status_taxonomy();
	}

	/**
	 * Coverage for get method with no backing post.
	 *
	 * @covers ::get
	 *
	 * @return void
	 */
	public function test_get_without_post(): void {
		$non_event = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$status    = new Status( $non_event->ID );

		$this->assertSame(
			'scheduled',
			$status->get(),
			'Failed to assert an event with no post reports the scheduled status.'
		);
	}

	/**
	 * Coverage for get_all method and multi-status priority resolution.
	 *
	 * @since TBD
	 *
	 * @covers ::get_all
	 * @covers ::get
	 * @covers ::set
	 *
	 * @return void
	 */
	public function test_get_all_and_priority_resolution(): void {
		$post   = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$status = new Status( $post->ID );

		// Fresh event reports scheduled.
		$this->assertSame( array( 'scheduled' ), $status->get_all() );

		// Event without backing post reports scheduled.
		$non_event    = $this->mock->post( array( 'post_type' => 'post' ) )->get();
		$empty_status = new Status( $non_event->ID );
		$this->assertSame( array( 'scheduled' ), $empty_status->get_all() );

		// Non-existent status term falls back to default.
		wp_set_object_terms( $post->ID, 'invalid-status', Event::TAXONOMY_STATUS );
		$this->assertSame( array( 'scheduled' ), $status->get_all() );

		// Setting a status sets it as the single status.
		$status->set( 'tentative' );
		$this->assertSame( array( 'tentative' ), $status->get_all() );
		$this->assertSame( 'tentative', $status->get() );

		// Appending a higher priority status reorders by priority descending.
		$status->set( 'moved-online', true );
		$this->assertSame( array( 'moved-online', 'tentative' ), $status->get_all() );
		$this->assertSame( 'moved-online', $status->get() );

		// Appending canceled (priority 50) trumps both moved-online (20) and tentative (10).
		$status->set( 'canceled', true );
		$this->assertSame( array( 'canceled', 'moved-online', 'tentative' ), $status->get_all() );
		$this->assertSame( 'canceled', $status->get() );
		$this->assertSame( 'CANCELLED', $status->get_ical() );
		$this->assertSame( 'EventCancelled', $status->get_schema() );
	}

	/**
	 * Coverage for get_label method.
	 *
	 * @since TBD
	 *
	 * @covers ::get_label
	 *
	 * @return void
	 */
	public function test_get_label(): void {
		$post   = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$status = new Status( $post->ID );

		$this->assertSame( 'Scheduled', $status->get_label() );

		$status->set( 'canceled' );
		$this->assertSame( 'Canceled', $status->get_label() );

		$status->set( 'postponed' );
		$this->assertSame( 'Postponed', $status->get_label() );

		$status->set( 'rescheduled' );
		$this->assertSame( 'Rescheduled', $status->get_label() );

		$status->set( 'moved-online' );
		$this->assertSame( 'Moved online', $status->get_label() );

		$status->set( 'tentative' );
		$this->assertSame( 'Tentative', $status->get_label() );
	}

	/**
	 * Coverage for get_schema method.
	 *
	 * @since TBD
	 *
	 * @covers ::get_schema
	 *
	 * @return void
	 */
	public function test_get_schema(): void {
		$post   = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$status = new Status( $post->ID );

		$this->assertSame( 'EventScheduled', $status->get_schema() );

		$status->set( 'canceled' );
		$this->assertSame( 'EventCancelled', $status->get_schema() );

		$status->set( 'postponed' );
		$this->assertSame( 'EventPostponed', $status->get_schema() );

		$status->set( 'rescheduled' );
		$this->assertSame( 'EventRescheduled', $status->get_schema() );

		$status->set( 'moved-online' );
		$this->assertSame( 'EventMovedOnline', $status->get_schema() );

		$status->set( 'tentative' );
		$this->assertSame( 'EventScheduled', $status->get_schema() );
	}

	/**
	 * Coverage for get_ical method.
	 *
	 * @since TBD
	 *
	 * @covers ::get_ical
	 *
	 * @return void
	 */
	public function test_get_ical(): void {
		$post   = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get();
		$status = new Status( $post->ID );

		$this->assertSame( 'CONFIRMED', $status->get_ical() );

		$status->set( 'canceled' );
		$this->assertSame( 'CANCELLED', $status->get_ical() );

		$status->set( 'postponed' );
		$this->assertSame( 'TENTATIVE', $status->get_ical() );

		$status->set( 'rescheduled' );
		$this->assertSame( 'TENTATIVE', $status->get_ical() );

		$status->set( 'moved-online' );
		$this->assertSame( 'CONFIRMED', $status->get_ical() );

		$status->set( 'tentative' );
		$this->assertSame( 'TENTATIVE', $status->get_ical() );
	}
}
