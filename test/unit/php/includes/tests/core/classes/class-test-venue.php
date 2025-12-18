<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Venue;
use GatherPress\Tests\Base;

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
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_post_type' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_post_meta' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
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
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for register_post_type method.
	 *
	 * @covers ::register_post_type
	 *
	 * @return void
	 */
	public function test_register_post_type(): void {
		$instance = Venue::get_instance();

		unregister_post_type( Venue::POST_TYPE );

		$this->assertFalse( post_type_exists( Venue::POST_TYPE ), 'Failed to assert that post type does not exist.' );

		$instance->register_post_type();

		$this->assertTrue( post_type_exists( Venue::POST_TYPE ), 'Failed to assert that post type exists.' );
	}

	/**
	 * Coverage for get_localized_post_type_slug method.
	 *
	 * @covers ::get_localized_post_type_slug
	 *
	 * @return void
	 */
	public function test_get_localized_post_type_slug(): void {
		$this->assertSame(
			'venue',
			Venue::get_localized_post_type_slug(),
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
			Venue::get_localized_post_type_slug(),
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

		/**
		 * Instead of loading additional languages into the unit test suite,
		 * we just filter the translated value, to mock different languages.
		 *
		 * Filters text with its translation based on context information for a domain.
		 *
		 * @param string $translation Translated text.
		 * @param string $text        Text to translate.
		 * @param string $context     Context information for the translators.
		 * @return string Translated text.
		 */
		add_filter( 'gettext_with_context_gatherpress', $filter, 10, 3 );

		$this->assertSame(
			'unit-test',
			Venue::get_localized_post_type_slug(),
			'Failed to assert the post type slug is "unit-test".'
		);

		remove_filter( 'gettext_with_context_gatherpress', $filter );

		// Test restore_previous_locale() path by switching to a different locale first.
		switch_to_locale( 'es_ES' );
		$this->assertSame(
			'venue',
			Venue::get_localized_post_type_slug(),
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
	 * Coverage for register_post_meta method.
	 *
	 * @covers ::register_post_meta
	 *
	 * @return void
	 */
	public function test_register_post_meta(): void {
		$instance = Venue::get_instance();

		unregister_post_meta( Venue::POST_TYPE, 'gatherpress_venue_information' );

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		$this->assertArrayNotHasKey(
			'gatherpress_venue_information',
			$meta,
			'Failed to assert that gatherpress_venue_information does not exist.'
		);

		$instance->register_post_meta();

		$meta = get_registered_meta_keys( 'post', Venue::POST_TYPE );

		$this->assertArrayHasKey(
			'gatherpress_venue_information',
			$meta,
			'Failed to assert that gatherpress_venue_information does exist.'
		);
	}

	/**
	 * Coverage for register_taxonomy method.
	 *
	 * @covers ::register_taxonomy
	 *
	 * @return void
	 */
	public function test_register_taxonomy(): void {
		$instance = Venue::get_instance();

		unregister_taxonomy( Venue::TAXONOMY );

		$this->assertFalse( taxonomy_exists( Venue::TAXONOMY ), 'Failed to assert that taxonomy does not exist.' );

		$instance->register_taxonomy();

		$this->assertTrue( taxonomy_exists( Venue::TAXONOMY ), 'Failed to assert that taxonomy exists.' );
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
		$event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'unit-test-event',
			)
		)->get();
		wp_set_post_terms( $event->ID, 'dummy-venue', Venue::TAXONOMY );

		$venue_meta = Venue::get_instance()->get_venue_meta( $event->ID, Event::POST_TYPE );

		// Generic test for an in person event.
		$this->assertFalse( $venue_meta['isOnlineEventTerm'] );
		$this->assertEmpty( $venue_meta['onlineEventLink'] );

		$venue_title = 'Unit Test Venue';

		$venue = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'unit-test-venue',
				'post_title' => $venue_title,
			)
		)->get();

		$venue_meta = Venue::get_instance()->get_venue_meta( $venue->ID, Venue::POST_TYPE );

		// Test for a venue post.
		$this->assertEquals(
			$venue_title,
			$venue_meta['name'],
			'Failed to assert venue title matches the venue meta title.'
		);
	}
}
