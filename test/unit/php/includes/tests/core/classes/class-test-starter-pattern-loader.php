<?php
/**
 * Class handles unit tests for GatherPress\Core\Starter_Pattern_Loader.
 *
 * @package GatherPress\Core
 * @since 0.34.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Starter_Pattern_Loader;
use GatherPress\Tests\Base;
use WP_Block_Patterns_Registry;

/**
 * Class Test_Starter_Pattern_Loader.
 *
 * @coversDefaultClass \GatherPress\Core\Starter_Pattern_Loader
 */
class Test_Starter_Pattern_Loader extends Base {

	/**
	 * Loads each `*.php` file in the fixtures directory and returns the
	 * union of their pattern definitions, skipping anything that does
	 * not return an array with a `name` key (the fixtures cover both
	 * the missing-name and non-array branches).
	 *
	 * @covers ::load
	 *
	 * @return void
	 */
	public function test_load_collects_pattern_definitions(): void {
		$dir = dirname( __DIR__, 4 ) . '/fixtures/starter-pattern-loader/valid';

		$patterns = Starter_Pattern_Loader::load( $dir );

		$this->assertCount(
			1,
			$patterns,
			'Loader should return only the entries that supplied a name.'
		);
		$this->assertSame( 'unit-test/valid', $patterns[0]['name'] );
	}

	/**
	 * Returns an empty array when the directory has no `*.php` files —
	 * lets callers safely apply their filter and `foreach` without an
	 * explicit empty-check.
	 *
	 * @covers ::load
	 *
	 * @return void
	 */
	public function test_load_returns_empty_array_for_empty_directory(): void {
		$dir = dirname( __DIR__, 4 ) . '/fixtures/starter-pattern-loader/empty';

		$patterns = Starter_Pattern_Loader::load( $dir );

		$this->assertSame( array(), $patterns );
	}

	/**
	 * Unregister a pattern if a previous test left it behind.
	 *
	 * @param string $name Pattern name.
	 *
	 * @return void
	 */
	protected function forget_pattern( string $name ): void {
		$registry = WP_Block_Patterns_Registry::get_instance();

		if ( $registry->is_registered( $name ) ) {
			$registry->unregister( $name );
		}
	}

	/**
	 * A definition without its own `postTypes` key registers against the
	 * default post types, scoped to `core/post-content`, with the
	 * definition fields mapped through.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_uses_default_post_types(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$this->forget_pattern( 'unit-test/support-scoped' );

		Starter_Pattern_Loader::register(
			array(
				array(
					'name'        => 'unit-test/support-scoped',
					'title'       => 'Support Scoped',
					'description' => 'Registered against the defaults.',
					'content'     => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
				),
			),
			array( 'gatherpress_event', 'my_custom_event' )
		);

		$pattern = $registry->get_registered( 'unit-test/support-scoped' );

		$this->assertSame(
			array( 'gatherpress_event', 'my_custom_event' ),
			$pattern['postTypes'],
			'Without a per-pattern postTypes key, the default list applies.'
		);
		$this->assertSame( array( 'core/post-content' ), $pattern['blockTypes'] );
		$this->assertSame( 'Support Scoped', $pattern['title'] );
		$this->assertSame( 'Registered against the defaults.', $pattern['description'] );

		$registry->unregister( 'unit-test/support-scoped' );
	}

	/**
	 * A definition carrying its own `postTypes` key registers against
	 * exactly those slugs — core's per-pattern granularity — instead of
	 * the default list.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_honors_per_pattern_post_types(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$this->forget_pattern( 'unit-test/slug-scoped' );

		Starter_Pattern_Loader::register(
			array(
				array(
					'name'      => 'unit-test/slug-scoped',
					'title'     => 'Slug Scoped',
					'content'   => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
					'postTypes' => array( 'my_custom_event' ),
				),
			),
			array( 'gatherpress_event', 'my_custom_event' )
		);

		$pattern = $registry->get_registered( 'unit-test/slug-scoped' );

		$this->assertSame(
			array( 'my_custom_event' ),
			$pattern['postTypes'],
			'A per-pattern postTypes key narrows registration to those slugs only.'
		);

		$registry->unregister( 'unit-test/slug-scoped' );
	}

	/**
	 * Malformed `postTypes` values — a non-array, an array with no string
	 * entries, or an empty array — fall back to the default post types
	 * instead of registering against garbage.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_falls_back_when_post_types_malformed(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$defaults = array( 'gatherpress_event' );

		$cases = array(
			'unit-test/non-array-post-types' => 'my_custom_event',
			'unit-test/no-string-post-types' => array( 123, true, array( 'nested' ) ),
			'unit-test/empty-post-types'     => array(),
		);

		foreach ( $cases as $name => $post_types_value ) {
			$this->forget_pattern( $name );

			Starter_Pattern_Loader::register(
				array(
					array(
						'name'      => $name,
						'title'     => 'Malformed',
						'content'   => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
						'postTypes' => $post_types_value,
					),
				),
				$defaults
			);

			$pattern = $registry->get_registered( $name );

			$this->assertSame(
				$defaults,
				$pattern['postTypes'],
				sprintf( '%s should fall back to the default post types.', $name )
			);

			$registry->unregister( $name );
		}

		// String entries survive a partially malformed list; the junk is dropped.
		$this->forget_pattern( 'unit-test/mixed-post-types' );

		Starter_Pattern_Loader::register(
			array(
				array(
					'name'      => 'unit-test/mixed-post-types',
					'title'     => 'Mixed',
					'content'   => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
					'postTypes' => array( 'my_custom_event', 42, false ),
				),
			),
			$defaults
		);

		$this->assertSame(
			array( 'my_custom_event' ),
			$registry->get_registered( 'unit-test/mixed-post-types' )['postTypes'],
			'Non-string entries are dropped; the remaining slugs still narrow the pattern.'
		);

		$registry->unregister( 'unit-test/mixed-post-types' );
	}

	/**
	 * Entries that are not arrays or lack a `name` are skipped, and
	 * missing title/description/content default to empty strings.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_skips_invalid_entries(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();
		$this->forget_pattern( 'unit-test/bare-minimum' );

		Starter_Pattern_Loader::register(
			array(
				'not-an-array',
				array( 'title' => 'Nameless' ),
				array( 'name' => 'unit-test/bare-minimum' ),
			),
			array( 'gatherpress_event' )
		);

		$this->assertFalse(
			$registry->is_registered( 'Nameless' ),
			'A definition without a name is never registered.'
		);

		$pattern = $registry->get_registered( 'unit-test/bare-minimum' );

		$this->assertSame( '', $pattern['title'], 'Missing title defaults to an empty string.' );
		$this->assertSame( '', $pattern['description'], 'Missing description defaults to an empty string.' );
		$this->assertSame( '', $pattern['content'], 'Missing content defaults to an empty string.' );

		$registry->unregister( 'unit-test/bare-minimum' );
	}
}
