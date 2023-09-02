<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use PMC\Unit_Test\Base;

/**
 * Class Test_Venue.
 *
 * @coversDefaultClass \GatherPress\Core\Venue
 */
class Test_Venue extends Base {

	/**
	 * Coverage for __construct and setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Venue::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => sprintf( 'save_post_%s', Venue::POST_TYPE ),
				'priority' => 10,
				'callback' => array( $instance, 'add_venue_term' ),
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
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for add_venue_term.
	 *
	 * @covers ::add_venue_term
	 *
	 * @return void
	 */
	public function test_add_venue_term(): void {
		$instance = Venue::get_instance();
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
		$instance    = Venue::get_instance();
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
	}

	/**
	 * Coverage for delete_venue_term.
	 *
	 * @covers ::delete_venue_term
	 *
	 * @return void
	 */
	public function test_delete_venue_term(): void {
		$instance = Venue::get_instance();
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
			Venue::get_instance()->get_venue_term_slug( 'unit-test' ),
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
		$venue_from_term_slug = Venue::get_instance()->get_venue_post_from_term_slug( '_unit-test' );

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
		$event  = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'unit-test-event',
			)
		)->get();
		
		$venue_meta = Venue::get_instance()->get_venue_meta( $event->ID, Event::POST_TYPE );
        
		//Generic test for an in person event
		$this->assertFalse($venue_meta['isOnlineEventTerm']);
		$this->assertEmpty($venue_meta['onlineEventLink']);
		
	}

}
