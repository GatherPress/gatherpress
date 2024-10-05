<?php
/**
 * Class handles unit tests for GatherPress\Core\Topic.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Topic;
use PMC\Unit_Test\Base;

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
			'Failed to assert english taxonomy slug is "topic".'
		);
		
		// This also checks that the taxonomy is still registered with the same 'Taxonomy Singular Name' label,
		// which is used by the method under test and the test itself.
		$filter = static function ( string $translation, string $text, string $context ): string {
			if ( 'Topic' !== $text || 'Taxonomy Singular Name' !== $context ) {
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
	}
}
