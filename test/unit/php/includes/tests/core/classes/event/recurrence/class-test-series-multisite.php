<?php
/**
 * Class handles multisite unit tests for the series resolver's
 * request-scoped membership memo.
 *
 * WordPress post IDs are blog-local, so any process-wide cache keyed on them
 * alone hands one blog's answer to another once `switch_to_blog()` runs. The
 * membership set feeds every occurrence query, calendar exports, redirects
 * and one write path, so a leaked set is a cross-tenant identity, not a
 * stale list.
 *
 * Per AGENTS.md: never remove `@group multisite` from this class, and never
 * add `@codeCoverageIgnore` to a multisite-only branch. `phpunit.xml.dist`
 * excludes the `multisite` group, so this file is silent in
 * `npm run test:unit:php`; run it with `npm run test:unit:php:multisite`.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Recurrence\Series;
use GatherPress\Tests\Base;

/**
 * Class Test_Series_Multisite.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Series
 *
 * @group multisite
 *
 * @since 0.36.0
 */
class Test_Series_Multisite extends Base {

	/**
	 * Start every test with no membership memo left over from another test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		Series::get_instance()->flush_memo();
		$this->forget_series_taxonomy();
	}

	/**
	 * Leave no membership memo behind for the next test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Series::get_instance()->flush_memo();
		$this->forget_series_taxonomy();

		parent::tearDown();
	}

	/**
	 * Unregister the series taxonomy so no test inherits another's registration.
	 *
	 * Taxonomy registration is process-global and WordPress's test case does
	 * not roll it back, so a later test would otherwise find the taxonomy
	 * already registered and pass or fail on execution order.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function forget_series_taxonomy(): void {
		if ( taxonomy_exists( Series::TAXONOMY ) ) {
			unregister_taxonomy( Series::TAXONOMY );
		}
	}

	/**
	 * The membership memo is scoped to the blog that resolved.
	 *
	 * `Series` is a singleton that survives `switch_to_blog()`, and post IDs
	 * are blog-local: the resolving post's ID names a different post, or no
	 * post at all, on every other blog of the network. A memo keyed by the
	 * bare post ID therefore hands blog one's membership set to a switched
	 * blog whose term storage holds no series at all, and that foreign set
	 * feeds `Occurrences::select_for_series()`, the calendar export, the
	 * bare-URL redirect, and `Revision::advance()`, which writes meta onto
	 * every ID the resolver returns.
	 *
	 * The join happens on blog one, and the same resolution is then repeated
	 * on a freshly created blog whose flag admits the read but whose storage
	 * has no term: the fresh blog must answer with a series of one. The two
	 * answers can never coincide, because blog one's set has two members.
	 *
	 * @covers ::resolve_post_ids
	 * @covers ::resolve_from_taxonomy
	 *
	 * @return void
	 */
	public function test_the_membership_memo_is_scoped_to_the_blog_that_resolved(): void {
		$origin  = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$forward = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		$term_id = Series::get_instance()->join( $origin, $forward );

		$this->assertGreaterThan( 0, $term_id, 'Fixture setup: the join must create the series term.' );

		$expected = array( min( $origin, $forward ), max( $origin, $forward ) );

		// This read primes the memo with blog one's two-member set.
		$this->assertSame(
			$expected,
			Series::get_instance()->resolve_post_ids( $origin ),
			'Fixture setup: blog one must resolve both members of the joined series.'
		);

		$blog_id = self::factory()->blog->create();

		switch_to_blog( $blog_id );

		// The recurring flag is per-blog site state and is what admits the
		// taxonomy read on the switched blog; without it the resolver would
		// bail before the memo and the leak could never be observed.
		update_option( Query::HAS_RECURRING_OPTION, '1', true );

		$on_fresh_blog = Series::get_instance()->resolve_post_ids( $origin );

		restore_current_blog();

		$this->assertSame(
			array( $origin ),
			$on_fresh_blog,
			'A switched blog with no series term must resolve a series of one, not the previous blog\'s set.'
		);

		// Switching back must still serve blog one its own correct answer.
		$this->assertSame(
			$expected,
			Series::get_instance()->resolve_post_ids( $origin ),
			'Blog one must keep resolving its own membership after the switch back.'
		);
	}
}
