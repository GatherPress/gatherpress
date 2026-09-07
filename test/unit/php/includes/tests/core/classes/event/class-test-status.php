<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Status.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event;
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
	 * @since 0.36.0
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
				'moved',
			),
			Status::slugs(),
			'Failed to assert the default statuses are offered in order.'
		);

		foreach ( $statuses as $slug => $status ) {
			$this->assertNotEmpty( $status['label'], sprintf( '%s should be named.', $slug ) );
			$this->assertNotEmpty( $status['description'], sprintf( '%s should be explained.', $slug ) );
			$this->assertNotEmpty( $status['schema'], sprintf( '%s should name a Schema.org value.', $slug ) );
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
	 * @since 0.36.0
	 *
	 * @covers ::label
	 * @covers ::description
	 * @covers ::schema
	 * @covers ::ical
	 * @covers ::exists
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

		// An event that has moved is still going ahead, so it stays confirmed
		// and the new location speaks for itself.
		$this->assertSame( 'Moved', Status::label( 'moved' ) );
		$this->assertSame( 'EventScheduled', Status::schema( 'moved' ) );
		$this->assertSame( 'CONFIRMED', Status::ical( 'moved' ) );
	}

	/**
	 * An unknown status is treated as scheduled rather than published as
	 * something a calendar client would misread.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::exists
	 * @covers ::label
	 * @covers ::description
	 * @covers ::schema
	 * @covers ::ical
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
	}

	/**
	 * A site can add a status of its own.
	 *
	 * @since 0.36.0
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
	 * @since 0.36.0
	 *
	 * @covers ::schema
	 * @covers ::ical
	 *
	 * @return void
	 */
	public function test_a_registered_status_may_omit_the_standards(): void {
		$callback = static function ( array $statuses ): array {
			$statuses['tentative'] = array( 'label' => 'Tentative' );

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		$this->assertSame( Status::DEFAULT_SCHEMA, Status::schema( 'tentative' ) );
		$this->assertSame( Status::DEFAULT_ICAL, Status::ical( 'tentative' ) );

		remove_filter( 'gatherpress_event_statuses', $callback );
	}

	/**
	 * A site can take a status away as readily as it can add one.
	 *
	 * @since 0.36.0
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
	 * @since 0.36.0
	 *
	 * @covers ::all
	 * @covers ::exists
	 *
	 * @return void
	 */
	public function test_a_status_can_depend_on_a_post_type_support(): void {
		$this->assertTrue(
			Status::exists( 'moved', Event::POST_TYPE ),
			'Failed to assert an event that can say where it is may have moved.'
		);
		$this->assertFalse(
			Status::exists( 'moved', 'post' ),
			'Failed to assert a post type with no venue or online support is not offered moved.'
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
	 * @since 0.36.0
	 *
	 * @covers ::all
	 * @covers ::exists
	 *
	 * @return void
	 */
	public function test_the_filter_overrides_the_support_gate(): void {
		$callback = static function ( array $statuses, string $post_type ): array {
			if ( 'post' === $post_type ) {
				$statuses['moved'] = array( 'label' => 'Moved' );
			}

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback, 10, 2 );

		$this->assertTrue(
			Status::exists( 'moved', 'post' ),
			'Failed to assert the filter can put back a gated status.'
		);

		remove_filter( 'gatherpress_event_statuses', $callback, 10 );
	}

	/**
	 * A status is shown in its own color, and anything that could break out
	 * of a style attribute is refused.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::color
	 *
	 * @return void
	 */
	public function test_color_accepts_only_what_is_safe_to_render(): void {
		$this->assertSame( '#c5221f', Status::color( 'canceled' ) );

		$callback = static function ( array $statuses ): array {
			$statuses['token']  = array( 'color' => 'var(--wp--preset--color--accent-1, #000)' );
			$statuses['broken'] = array( 'color' => 'red;} body { display: none' );
			$statuses['named']  = array( 'color' => 'rebeccapurple' );

			return $statuses;
		};

		add_filter( 'gatherpress_event_statuses', $callback );

		$this->assertSame(
			'var(--wp--preset--color--accent-1, #000)',
			Status::color( 'token' ),
			'Failed to assert a custom property reference is allowed.'
		);
		$this->assertSame( '', Status::color( 'broken' ), 'Failed to assert an escape attempt is refused.' );
		$this->assertSame( '', Status::color( 'named' ), 'Failed to assert an unrecognized form is refused.' );

		remove_filter( 'gatherpress_event_statuses', $callback );
	}

	/**
	 * A status gets a term named the way people read it.
	 *
	 * Without this a term is created from the slug, and an event would say
	 * `canceled` where it should say `Canceled`.
	 *
	 * @since 0.36.0
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
	 * @since 0.36.0
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
	 * @since 0.36.0
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
}
