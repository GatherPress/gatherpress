<?php
/**
 * Class handles unit tests for GatherPress\Core\Topic.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Topic;
use GatherPress\Tests\Base;

/**
 * Class Test_Topic.
 *
 * @coversDefaultClass \GatherPress\Core\Topic
 */
class Test_Topic extends Base {
	/**
	 * Coverage for __construct and setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Topic::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'init',
				'priority' => 10,
				'callback' => array( $instance, 'register_taxonomy' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for register_taxonomy method.
	 *
	 * @covers ::register_taxonomy
	 *
	 * @return void
	 */
	public function test_register_taxonomy(): void {
		$instance = Topic::get_instance();

		unregister_taxonomy( Topic::TAXONOMY );

		$this->assertFalse( taxonomy_exists( Topic::TAXONOMY ), 'Failed to assert that taxonomy does not exist.' );

		$instance->register_taxonomy();

		$this->assertTrue( taxonomy_exists( Topic::TAXONOMY ), 'Failed to assert that taxonomy exists.' );
	}

	/**
	 * Coverage for get_localized_taxonomy_slug method.
	 *
	 * @covers ::get_localized_taxonomy_slug
	 *
	 * @return void
	 */
	public function test_get_localized_taxonomy_slug(): void {

		$this->assertSame(
			'topic',
			Topic::get_localized_taxonomy_slug(),
			'Failed to assert English taxonomy slug is "topic".'
		);

		$user_id = $this->factory->user->create();
		update_user_meta( $user_id, 'locale', 'es_ES' );
		switch_to_user_locale( $user_id );

		// @todo This assertion CAN NOT FAIL,
		// until real translations do exist in the wp-env instance.
		// Because WordPress doesn't have any translation files to load,
		// it will return the string in English.
		$this->assertSame(
			'topic',
			Topic::get_localized_taxonomy_slug(),
			'Failed to assert taxonomy slug is "topic", even the locale is not English anymore.'
		);
		// But at least the restoring of the user locale can be tested, without .po files.
		$this->assertSame(
			'es_ES',
			determine_locale(),
			'Failed to assert locale was reset to Spanish, after switching to ~ and restoring from English.'
		);

		// Restore default locale for following tests.
		switch_to_locale( 'en_US' );

		// This also checks that the taxonomy is still registered with the same
		// 'Admin menu and taxonomy singular name' label, which is used by the method under test and the test itself.
		$filter = static function ( string $translation, string $text, string $context ): string {
			if ( 'Topic' !== $text || 'Admin menu and taxonomy singular name' !== $context ) {
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
			Topic::get_localized_taxonomy_slug(),
			'Failed to assert taxonomy slug is "unit-test".'
		);

		remove_filter( 'gettext_with_context_gatherpress', $filter );

		// Test restore_previous_locale() path by switching to a different locale first.
		switch_to_locale( 'es_ES' );
		$this->assertSame(
			'topic',
			Topic::get_localized_taxonomy_slug(),
			'Failed to assert taxonomy slug is "topic" after locale restore.'
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
	 * Coverage for get_localized_taxonomy_slug when locale doesn't switch.
	 *
	 * @covers ::get_localized_taxonomy_slug
	 *
	 * @return void
	 */
	public function test_get_localized_taxonomy_slug_no_locale_switch(): void {
		// When switch_to_locale returns false (already on that locale), restore shouldn't be called.
		// This tests the else branch of the if ( $switched_locale ) condition.
		$current_locale = get_locale();

		// Switch to the current locale (should return false).
		$result = Topic::get_localized_taxonomy_slug();

		$this->assertIsString( $result );
		$this->assertEquals( $current_locale, get_locale(), 'Locale should remain unchanged.' );
	}

	/**
	 * Coverage for get_localized_taxonomy_slug with locale restoration.
	 *
	 * Tests that restore_previous_locale() is called when switch_to_locale() succeeds.
	 * This test creates a scenario where the global locale differs from get_locale().
	 *
	 * @covers ::get_localized_taxonomy_slug
	 *
	 * @return void
	 */
	public function test_get_localized_taxonomy_slug_restores_locale(): void {
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
		$slug = Topic::get_localized_taxonomy_slug();

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
}
