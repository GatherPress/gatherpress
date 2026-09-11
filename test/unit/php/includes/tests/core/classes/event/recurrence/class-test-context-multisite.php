<?php
/**
 * Class handles multisite unit tests for the occurrence resolver's
 * request-scoped memo.
 *
 * WordPress post IDs and recurrence identifiers are blog-local, so any
 * process-wide cache keyed on them alone hands one blog's answer to another
 * once `switch_to_blog()` runs. The resolver feeds routing, authorization,
 * term slugs and cache keys, so a leaked row is a cross-tenant identity, not
 * a stale date.
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

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Setup as Event_Setup;
use GatherPress\Core\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Context_Multisite.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Context
 *
 * @group multisite
 *
 * @since 0.36.0
 */
class Test_Context_Multisite extends Base {

	/**
	 * The reference daily rule: five consecutive daily occurrences.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const DAILY_RULE = array(
		'frequency' => 'daily',
		'interval'  => 1,
		'end_type'  => 'count',
		'count'     => 5,
	);

	/**
	 * A post ID both blogs name, resolved to a different owner on each.
	 *
	 * Deliberately not a real post anywhere: `resolve_in_series()` hands the
	 * named ID to `Series::resolve_post_ids()`, and the blog-sensitive filter
	 * in the test below is what maps it to each blog's own owner, exactly the
	 * way a companion plugin's series registry would.
	 *
	 * @since 0.36.0
	 * @var int
	 */
	const COMMON_NAMED_ID = 424242;

	/**
	 * Leave no occurrence context or resolution memo behind.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Context::get_instance()->clear();
		Context::flush_resolved();

		parent::tearDown();
	}

	/**
	 * Create a recurring series from one shared anchor on the current blog.
	 *
	 * The anchor is taken as a parameter rather than computed here because the
	 * test needs the two blogs' series to project byte-identical recurrence
	 * identifiers: `Ymd\THis` is derived from the start datetime, so two
	 * anchors read from the clock seconds apart would name different
	 * occurrences and the cross-blog collision under test could never happen.
	 *
	 * @since 0.36.0
	 *
	 * @param DateTimeImmutable $anchor The series' first start, in UTC.
	 *
	 * @return int The created series post ID.
	 */
	protected function create_series( DateTimeImmutable $anchor ): int {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $anchor->format( 'Y-m-d H:i:s' ),
					'dateTimeEnd'   => $anchor->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
					'timezone'      => 'UTC',
				)
			)
		);

		Event_Setup::get_instance()->set_datetimes( $post_id );
		add_post_meta( $post_id, Meta::META_KEY, wp_json_encode( self::DAILY_RULE ) );
		Meta::get_instance()->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );
		Query::refresh_has_recurring_events();

		return (int) $post_id;
	}

	/**
	 * The resolver memo is scoped to the blog that resolved.
	 *
	 * The memo in `resolve_in_series()` was keyed `{post_id}:{recurrence_id}`,
	 * and both halves are blog-local. A request that resolved an occurrence on
	 * one blog and then called `switch_to_blog()` received the first blog's
	 * row on the second blog, without the second blog's occurrence table ever
	 * being read, so routing, authorization, term slugs and cache keys there
	 * carried a foreign owner ID.
	 *
	 * Two blogs each project their own series from one shared anchor, so the
	 * recurrence identifier is byte-identical on both. A blog-sensitive
	 * `gatherpress_series_post_ids` filter maps one common named ID to each
	 * blog's own owner, the shape a network-active companion plugin's series
	 * registry produces. Resolution is driven through `set_for_series()`, the
	 * production consumer. The two owners are asserted distinct first, so the
	 * right answer and the leaked answer can never coincide.
	 *
	 * @covers ::resolve_in_series
	 * @covers ::set_for_series
	 *
	 * @return void
	 */
	public function test_the_resolver_memo_is_scoped_to_the_blog_that_resolved(): void {
		$main_blog_id = get_current_blog_id();
		$anchor       = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->modify( '+2 hours' );

		$owner_a = $this->create_series( $anchor );
		$rows_a  = Occurrences::get_instance()->select_for_series( array( $owner_a ) );

		$this->assertNotEmpty( $rows_a, 'Failed to project the main blog series this test resolves.' );

		$recurrence_id = (string) $rows_a[0]['recurrence_id'];
		$blog_id       = $this->factory()->blog->create();

		switch_to_blog( $blog_id );

		Utility::invoke_hidden_method( Setup::get_instance(), 'create_tables' );

		$owner_b = $this->create_series( $anchor );
		$rows_b  = Occurrences::get_instance()->select_for_series( array( $owner_b ) );

		restore_current_blog();

		$this->assertNotSame(
			$owner_a,
			$owner_b,
			'Failed to arrange distinct owner IDs, without which the leak and the right answer coincide.'
		);
		$this->assertNotEmpty( $rows_b, 'Failed to project the second blog series this test resolves.' );
		$this->assertSame(
			$recurrence_id,
			(string) $rows_b[0]['recurrence_id'],
			'Failed to arrange the byte-identical recurrence identifier the collision requires.'
		);

		$filter = static function ( array $post_ids, int $post_id ) use ( $main_blog_id, $owner_a, $owner_b ): array {
			if ( self::COMMON_NAMED_ID !== $post_id ) {
				return $post_ids;
			}

			return array( get_current_blog_id() === $main_blog_id ? $owner_a : $owner_b );
		};

		add_filter( 'gatherpress_series_post_ids', $filter, 10, 2 );
		Context::flush_resolved();

		$entered_a  = Context::get_instance()->set_for_series( self::COMMON_NAMED_ID, $recurrence_id );
		$resolved_a = Context::get_instance()->current();

		Context::get_instance()->clear();

		switch_to_blog( $blog_id );

		$entered_b  = Context::get_instance()->set_for_series( self::COMMON_NAMED_ID, $recurrence_id );
		$resolved_b = Context::get_instance()->current();

		Context::get_instance()->clear();
		restore_current_blog();
		remove_filter( 'gatherpress_series_post_ids', $filter, 10 );
		Context::flush_resolved();

		$this->assertTrue( $entered_a, 'Failed to resolve the main blog occurrence at all.' );
		$this->assertSame(
			$owner_a,
			(int) $resolved_a['series_post_id'],
			'Failed to assert the main blog resolves its own occurrence owner.'
		);
		$this->assertTrue( $entered_b, 'Failed to resolve the second blog occurrence at all.' );
		$this->assertSame(
			$owner_b,
			(int) $resolved_b['series_post_id'],
			'Failed to assert a switched blog resolves its own owner rather than the previous blog\'s.'
		);
	}
}
