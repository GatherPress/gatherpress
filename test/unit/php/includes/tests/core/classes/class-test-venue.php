<?php
/**
 * Class handles unit tests for GatherPress\Core\Venue.
 *
 * Covers the instance class (constructor, per-post accessors) and the static
 * utilities that live alongside it. WordPress integration (registering the
 * post type, save hooks, etc.) is covered by `Test_Venue_Setup`.
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
 * @group multisite
 * @coversDefaultClass \GatherPress\Core\Venue
 */
class Test_Venue extends Base {
	/**
	 * Construct a Venue instance from a post ID and read it back.
	 *
	 * @covers ::__construct
	 * @covers ::get_post_id
	 *
	 * @return void
	 */
	public function test_construct_and_get_post_id(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);

		$venue = new Venue( $post_id );

		$this->assertSame(
			$post_id,
			$venue->get_post_id(),
			'Failed to assert that the Venue exposes the post ID it was constructed with.'
		);
	}

	/**
	 * Returns parsed venue information when JSON meta is present.
	 *
	 * @covers ::get_information
	 *
	 * @return void
	 */
	public function test_get_information_reads_meta_json(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);
		add_post_meta(
			$post_id,
			'gatherpress_venue_information',
			wp_json_encode(
				array(
					'fullAddress' => '123 Main St',
					'phoneNumber' => '555-0100',
					'website'     => 'https://example.com',
					'latitude'    => '40.7128',
					'longitude'   => '-74.0060',
				)
			)
		);

		$info = ( new Venue( $post_id ) )->get_information();

		$this->assertSame( '123 Main St', $info['fullAddress'] );
		$this->assertSame( '555-0100', $info['phoneNumber'] );
		$this->assertSame( 'https://example.com', $info['website'] );
		$this->assertSame( '40.7128', $info['latitude'] );
		$this->assertSame( '-74.0060', $info['longitude'] );
	}

	/**
	 * Returns a fully-populated empty-string shape when no JSON meta exists.
	 *
	 * The array shape is stable so callers can index into it without guards.
	 *
	 * @covers ::get_information
	 *
	 * @return void
	 */
	public function test_get_information_returns_empty_shape_when_meta_absent(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);

		$info = ( new Venue( $post_id ) )->get_information();

		$this->assertSame(
			array(
				'fullAddress' => '',
				'phoneNumber' => '',
				'website'     => '',
				'latitude'    => '',
				'longitude'   => '',
			),
			$info,
			'Failed to assert the empty-state default shape when no venue meta is stored.'
		);
	}

	/**
	 * Malformed JSON meta falls back to the empty shape rather than throwing.
	 *
	 * @covers ::get_information
	 *
	 * @return void
	 */
	public function test_get_information_handles_invalid_json(): void {
		$post_id = $this->factory->post->create(
			array( 'post_type' => Venue::POST_TYPE )
		);
		add_post_meta( $post_id, 'gatherpress_venue_information', 'not json' );

		$info = ( new Venue( $post_id ) )->get_information();

		$this->assertSame( '', $info['fullAddress'] );
		$this->assertSame( '', $info['latitude'] );
		$this->assertSame( '', $info['longitude'] );
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

		// Now get_locale() will return 'de_DE', but the global locale is still 'en_US'.
		// When the method calls switch_to_locale(get_locale()), it will actually switch.
		// to 'de_DE' and return true, triggering the restore_previous_locale() path.
		$slug = Venue::get_localized_post_type_slug();

		// Verify the slug was generated.
		$this->assertNotEmpty( $slug, 'Failed to assert slug is not empty.' );

		// The method should have called restore_previous_locale() since.
		// switch_to_locale() returned true.
		// We should be back to en_US (with the filter still active).
		remove_filter( 'locale', $locale_filter );

		$this->assertSame(
			'en_US',
			determine_locale(),
			'Failed to assert locale was restored after method execution.'
		);
	}

	/**
	 * Coverage for get_taxonomy method with empty string argument.
	 *
	 * @covers ::get_taxonomy
	 *
	 * @return void
	 */
	public function test_get_taxonomy(): void {
		// No argument falls back to the default venue post type.
		$this->assertSame(
			'_' . Venue::POST_TYPE,
			Venue::get_taxonomy(),
			'Failed to assert that get_taxonomy defaults to the built-in venue taxonomy.'
		);

		// Empty string also falls back to the default venue post type.
		$this->assertSame(
			'_' . Venue::POST_TYPE,
			Venue::get_taxonomy( '' ),
			'Failed to assert that get_taxonomy with empty string defaults to the built-in venue taxonomy.'
		);

		// Custom venue post type returns the correctly prefixed taxonomy.
		$this->assertSame(
			'_custom_venue_type',
			Venue::get_taxonomy( 'custom_venue_type' ),
			'Failed to assert that get_taxonomy prepends an underscore for a custom venue post type.'
		);
	}

	/**
	 * Coverage for get_venue_post_type method.
	 *
	 * @covers ::get_venue_post_type
	 *
	 * @return void
	 */
	public function test_get_venue_post_type(): void {
		// Default returns the built-in venue post type.
		$this->assertSame(
			Venue::POST_TYPE,
			Venue::get_venue_post_type(),
			'Failed to assert that get_venue_post_type returns the default venue post type.'
		);
	}

	/**
	 * Coverage for get_venue_post_type method with filter override.
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
			Venue::get_venue_post_type( 'test_custom_event_type' ),
			'Failed to assert that get_venue_post_type returns the filtered post type.'
		);

		remove_all_filters( 'gatherpress_venue_post_type' );
	}

	/**
	 * Coverage for get_venue_post_type_map method.
	 *
	 * @covers ::get_venue_post_type_map
	 *
	 * @return void
	 */
	public function test_get_venue_post_type_map(): void {
		$map = Venue::get_venue_post_type_map();

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
	 * Coverage for get_term_slug with an explicit post_name argument.
	 *
	 * @covers ::get_term_slug
	 *
	 * @return void
	 */
	public function test_get_term_slug(): void {
		$this->assertSame(
			'_unit-test',
			( new Venue() )->get_term_slug( 'unit-test' ),
			'Failed to assert that term slugs match.'
		);
	}

	/**
	 * Coverage for get_post_from_term_slug method.
	 *
	 * @covers ::get_post_from_term_slug
	 *
	 * @return void
	 */
	public function test_get_post_from_term_slug(): void {
		$venue                = $this->mock->post(
			array(
				'post_type' => Venue::POST_TYPE,
				'post_name' => 'unit-test',
			)
		)->get();
		$venue_from_term_slug = ( new Venue() )->get_post_from_term_slug( '_unit-test' );

		$this->assertEquals(
			$venue->ID,
			$venue_from_term_slug->ID,
			'Failed to assert that IDs match.'
		);
	}

	/**
	 * Coverage for get_post_from_event_post_id method.
	 *
	 * @covers ::get_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_post_from_event_post_id(): void {
		// Create a venue post.
		$venue = $this->mock->post(
			array(
				'post_type'  => Venue::POST_TYPE,
				'post_name'  => 'test-venue-for-event',
				'post_title' => 'Test Venue For Event',
			)
		)->get();

		// Create the venue term with the correct slug format.
		$term_slug = ( new Venue( $venue->ID ) )->get_term_slug();
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
		$result = ( new Venue() )->get_post_from_event_post_id( $event->ID );

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
	 * Coverage for get_post_from_event_post_id when event has no venue terms.
	 *
	 * @covers ::get_post_from_event_post_id
	 *
	 * @return void
	 */
	public function test_get_post_from_event_post_id_no_terms(): void {
		// Create an event post without any venue terms.
		$event = $this->mock->post(
			array(
				'post_type' => Event::POST_TYPE,
				'post_name' => 'test-event-no-venue',
			)
		)->get();

		// Get the venue post from the event.
		$result = ( new Venue() )->get_post_from_event_post_id( $event->ID );

		// The result should be null since there are no venue terms.
		$this->assertNull(
			$result,
			'Should return null when event has no venue terms.'
		);
	}
}
