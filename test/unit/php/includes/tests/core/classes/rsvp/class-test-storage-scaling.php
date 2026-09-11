<?php
/**
 * Scaling tests for GatherPress\Core\Rsvp\Storage.
 *
 * One RSVP write must cost a fixed number of queries however many RSVPs the
 * event already holds. It did not. `Rsvp::save()` reads the whole set twice,
 * once through `attending_limit_reached()` and once through
 * `check_waiting_list()`, and `Storage::hydrate()` asked for each comment's
 * status and provider terms one comment at a time. Two queries per stored RSVP
 * per write makes filling an event quadratic.
 *
 * The measurement has to be taken across the write, not after it. Every write
 * sets terms, which bumps the `terms` last-changed value and invalidates every
 * cached term query, so a read taken afterwards is served entirely from caches
 * the write itself repopulated and shows no growth at all.
 *
 * The assertions are written as "the count does not change with n" rather than
 * against a recorded number, so they stay true if the constant part of a write
 * ever gains or loses a query.
 *
 * @package GatherPress\Core\Rsvp
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Rsvp;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp\Response\Provider\Base as Provider;
use GatherPress\Core\Rsvp\Response\Status;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Core\Rsvp\Storage;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Storage_Scaling.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Storage
 */
class Test_Storage_Scaling extends Base {

	/**
	 * Create an event that accepts RSVPs.
	 *
	 * @since 0.36.0
	 *
	 * @return int The event post ID.
	 */
	protected function create_event(): int {
		return (int) $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Store a number of additional RSVPs on an event, through the real save path.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The event.
	 * @param int $count   How many responders to add.
	 *
	 * @return void
	 */
	protected function add_rsvps( int $post_id, int $count ): void {
		$rsvp = new Rsvp( $post_id );

		for ( $i = 0; $i < $count; $i++ ) {
			$rsvp->save( (int) $this->factory->user->create(), 'attending' );
		}
	}

	/**
	 * Count the queries one further RSVP write issues on an event.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The event.
	 *
	 * @return int Queries issued by the write.
	 */
	protected function count_queries_for_one_save( int $post_id ): int {
		global $wpdb;

		// Created outside the measurement window so the user insert's own
		// queries are not counted as part of the write.
		$user_id = (int) $this->factory->user->create();
		$rsvp    = new Rsvp( $post_id );
		$before  = count( $wpdb->queries );

		$rsvp->save( $user_id, 'attending' );

		return count( $wpdb->queries ) - $before;
	}

	/**
	 * Create one RSVP on a fresh event and return its comment ID.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The event.
	 *
	 * @return int The RSVP comment ID.
	 */
	protected function create_one_rsvp( int $post_id ): int {
		$rsvp    = new Rsvp( $post_id );
		$user_id = (int) $this->factory->user->create();

		$rsvp->save( $user_id, 'attending' );

		return (int) $rsvp->find( $user_id )->comment->comment_ID;
	}

	/**
	 * One RSVP write costs the same at 4 stored responders as at 16.
	 *
	 * @covers ::all
	 * @covers ::hydrate
	 * @covers ::get_value_from_object_terms
	 *
	 * @return void
	 */
	public function test_one_rsvp_write_does_not_scale_with_the_number_of_stored_rsvps(): void {
		global $wpdb;

		$post_id = $this->create_event();

		$this->add_rsvps( $post_id, 4 );

		$small = $this->count_queries_for_one_save( $post_id );

		$this->add_rsvps( $post_id, 12 );

		$large = $this->count_queries_for_one_save( $post_id );

		$this->assertNotEmpty(
			$wpdb->queries,
			'Failed to capture any queries; SAVEQUERIES must be on for this assertion to mean anything.'
		);
		$this->assertSame(
			$small,
			$large,
			sprintf(
				'Failed to assert an RSVP write costs a fixed number of queries: %d with 5 stored, %d with 17.',
				$small,
				$large
			)
		);
	}

	/**
	 * Priming fetches every comment's terms in exactly one query.
	 *
	 * @covers ::prime_term_cache
	 *
	 * @return void
	 */
	public function test_prime_term_cache_costs_one_query_for_any_number_of_comments(): void {
		global $wpdb;

		$post_id = $this->create_event();

		$this->add_rsvps( $post_id, 6 );

		$storage     = new Storage( $post_id );
		$comment_ids = array_map(
			'intval',
			wp_list_pluck(
				get_comments(
					array(
						'post_id' => $post_id,
						'type'    => Rsvp::COMMENT_TYPE,
						'status'  => 'approve',
					)
				),
				'comment_ID'
			)
		);

		clean_object_term_cache( $comment_ids, 'comment' );

		$before = count( $wpdb->queries );

		Utility::invoke_hidden_method( $storage, 'prime_term_cache', array( $comment_ids ) );

		$this->assertSame(
			1,
			count( $wpdb->queries ) - $before,
			'Failed to assert priming six comments cost exactly one query.'
		);
		$this->assertCount(
			1,
			get_object_term_cache( $comment_ids[0], Status::TAXONOMY ),
			'Failed to assert priming populated the status relationship cache.'
		);

		// Second pass: nothing is uncached, so nothing is fetched.
		$before = count( $wpdb->queries );

		Utility::invoke_hidden_method( $storage, 'prime_term_cache', array( $comment_ids ) );

		$this->assertSame(
			0,
			count( $wpdb->queries ) - $before,
			'Failed to assert a fully primed set is not fetched again.'
		);
	}

	/**
	 * Priming an empty set of comments does nothing.
	 *
	 * @covers ::prime_term_cache
	 *
	 * @return void
	 */
	public function test_prime_term_cache_does_nothing_without_comments(): void {
		global $wpdb;

		$storage = new Storage( $this->create_event() );
		$before  = count( $wpdb->queries );

		Utility::invoke_hidden_method( $storage, 'prime_term_cache', array( array() ) );

		$this->assertSame(
			0,
			count( $wpdb->queries ) - $before,
			'Failed to assert priming an empty set issued no query.'
		);
	}

	/**
	 * Priming caches an empty answer for a comment holding no RSVP terms.
	 *
	 * The cached value is asserted, and then the thing the cached value exists
	 * for: `get_value_from_object_terms()` distinguishes "not primed" (`false`)
	 * from "primed empty" (`array()`) precisely so the empty case costs no
	 * query, and only a query-count window can tell the two apart. Asserting
	 * the cache contents alone leaves that branch verified by nothing, since
	 * relaxing the check to `empty( $terms )` passes it.
	 *
	 * @covers ::prime_term_cache
	 * @covers ::get_value_from_object_terms
	 *
	 * @return void
	 */
	public function test_prime_term_cache_caches_the_empty_answer(): void {
		global $wpdb;

		$comment_id = (int) $this->factory->comment->create();
		$storage    = new Storage( $this->create_event() );

		clean_object_term_cache( array( $comment_id ), 'comment' );

		Utility::invoke_hidden_method( $storage, 'prime_term_cache', array( array( $comment_id ) ) );

		$this->assertSame(
			array(),
			get_object_term_cache( $comment_id, Status::TAXONOMY ),
			'Failed to assert a comment with no status term caches an empty answer rather than a miss.'
		);

		$before = count( $wpdb->queries );
		$value  = Utility::invoke_hidden_method(
			$storage,
			'get_value_from_object_terms',
			array( $comment_id, Status::TAXONOMY )
		);

		$this->assertNull(
			$value,
			'Failed to assert a comment with no status term reads back as no status.'
		);
		$this->assertSame(
			0,
			count( $wpdb->queries ) - $before,
			'Failed to assert the cached empty answer is served without a query of its own.'
		);
	}

	/**
	 * Priming gives up when one of the two taxonomies is not registered.
	 *
	 * @covers ::prime_term_cache
	 *
	 * @return void
	 */
	public function test_prime_term_cache_bails_when_a_taxonomy_is_unregistered(): void {
		$post_id    = $this->create_event();
		$comment_id = $this->create_one_rsvp( $post_id );
		$storage    = new Storage( $post_id );

		clean_object_term_cache( array( $comment_id ), 'comment' );
		unregister_taxonomy( Status::TAXONOMY );

		Utility::invoke_hidden_method( $storage, 'prime_term_cache', array( array( $comment_id ) ) );

		$cached = get_object_term_cache( $comment_id, Provider::TAXONOMY );

		// Re-registered before asserting, so a failure does not leave the
		// taxonomy missing for every test that runs after this one.
		Rsvp_Setup::get_instance()->register_taxonomy();

		$this->assertFalse(
			$cached,
			'Failed to assert nothing is cached when the term lookup could not be made.'
		);
	}

	/**
	 * A term lookup is served from the primed object term cache.
	 *
	 * @covers ::get_value_from_object_terms
	 *
	 * @return void
	 */
	public function test_get_value_from_object_terms_reads_the_primed_cache(): void {
		global $wpdb;

		$post_id    = $this->create_event();
		$comment_id = $this->create_one_rsvp( $post_id );
		$storage    = new Storage( $post_id );

		update_object_term_cache( array( $comment_id ), 'comment' );

		$before = count( $wpdb->queries );
		$slug   = Utility::invoke_hidden_method(
			$storage,
			'get_value_from_object_terms',
			array( $comment_id, Status::TAXONOMY )
		);

		$this->assertSame(
			'attending',
			$slug,
			'Failed to assert the primed cache returns the stored status slug.'
		);
		$this->assertSame(
			0,
			count( $wpdb->queries ) - $before,
			'Failed to assert a primed object term cache serves the lookup without a query.'
		);
	}

	/**
	 * A cold object term cache still resolves the term.
	 *
	 * The fallback arm: `get_object_term_cache()` returns false rather than an
	 * empty array when nothing has primed the object, and the lookup has to
	 * reach the database rather than read that false as "no terms".
	 *
	 * @covers ::get_value_from_object_terms
	 *
	 * @return void
	 */
	public function test_get_value_from_object_terms_falls_back_to_an_uncached_read(): void {
		$post_id    = $this->create_event();
		$comment_id = $this->create_one_rsvp( $post_id );
		$storage    = new Storage( $post_id );

		clean_object_term_cache( array( $comment_id ), 'comment' );

		$this->assertSame(
			'attending',
			Utility::invoke_hidden_method(
				$storage,
				'get_value_from_object_terms',
				array( $comment_id, Status::TAXONOMY )
			),
			'Failed to assert an unprimed object resolves its term from the database.'
		);
	}

	/**
	 * An object with no term in a taxonomy resolves to null.
	 *
	 * @covers ::get_value_from_object_terms
	 *
	 * @return void
	 */
	public function test_get_value_from_object_terms_returns_null_without_a_term(): void {
		$post_id    = $this->create_event();
		$comment_id = (int) $this->factory->comment->create();
		$storage    = new Storage( $post_id );

		update_object_term_cache( array( $comment_id ), 'comment' );

		$this->assertNull(
			Utility::invoke_hidden_method(
				$storage,
				'get_value_from_object_terms',
				array( $comment_id, Status::TAXONOMY )
			),
			'Failed to assert an object with no term in the taxonomy resolves to null.'
		);
	}
}
