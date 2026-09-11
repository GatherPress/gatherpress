<?php
/**
 * Class handles unit tests for what a forward split has to keep intact.
 *
 * The split is the one operation in the subsystem that changes an occurrence's
 * owner, and every consumer that had already recorded that owner has to move
 * with it: the occurrence row, the RSVP occurrence term, the RSVP comment, the
 * caches keyed on the pair, and the calendar revision that tells subscribers
 * the published rule changed.
 *
 * Two rules govern every assertion here.
 *
 * **Read through the production entry point, never the layer beneath.** The
 * suite that existed before this file asserted a per-occurrence RSVP vector by
 * reading each comment's own taxonomy term, and passed against precisely the
 * state that made those RSVPs invisible to `Rsvp::responses()`. Every RSVP
 * assertion below goes through `Rsvp::responses()` or through a dispatched REST
 * request.
 *
 * **A rollback is only proven against the whole observable surface.** A fixture
 * where the rolled-back state and the never-attempted state look the same
 * proves nothing, so the failure-injection tests compare posts, postmeta,
 * occurrence rows, RSVP comments, both taxonomies and the site flag, and first
 * establish that a *successful* split moves that same comparison.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Calendar\Revision;
use GatherPress\Core\Calendar\Setup as Calendar_Setup;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Occurrence_Identity;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Event\Recurrence\Series;
use GatherPress\Core\Event\Recurrence\Splitter;
use GatherPress\Core\Event\Rest_Api as Event_Rest_Api;
use GatherPress\Core\Event\Recurrence\Rest_Api as Recurrence_Rest_Api;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Core\Rsvp\Token;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Error;
use WP_REST_Request;

/**
 * Class Test_Split_Continuity.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Splitter
 */
class Test_Split_Continuity extends Base {

	use Occurrence_Fixtures;

	/**
	 * A five-occurrence weekly rule, projected from a relative anchor.
	 *
	 * Five so a split at the third occurrence leaves an asymmetric two behind
	 * and moves three, and so a split at the second and at the fifth reach the
	 * two demotion paths without a second fixture.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const WEEKLY_RULE = array(
		'frequency' => 'weekly',
		'interval'  => 1,
		'weekdays'  => array( 2 ),
		'end_type'  => 'count',
		'count'     => 5,
	);

	/**
	 * Identifiers of the projected occurrences, ascending.
	 *
	 * Read back out of the rows projection actually wrote rather than restated,
	 * so the fixture and the assertions cannot drift apart as the anchor moves.
	 *
	 * @since 0.36.0
	 * @var string[]
	 */
	protected array $identifiers = array();

	/**
	 * RSVP comment IDs `arrange_rich_series()` created, keyed by recurrence ID.
	 *
	 * @since 0.36.0
	 * @var array<string, int>
	 */
	protected array $rsvps = array();

	/**
	 * Start every test from an empty occurrence table with the routes registered.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();

		Rsvp_Setup::get_instance()->register_taxonomy();
		Event_Rest_Api::get_instance()->register_endpoints();
		Recurrence_Rest_Api::get_instance()->register_endpoints();
		Settings::get_instance()->set( 'enable_open_rsvp', true );

		Context::get_instance()->clear();
		Context::flush_resolved();
		Series::get_instance()->flush_memo();
	}

	/**
	 * Leave no occurrence context, resolution memo or series membership behind.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		Context::get_instance()->clear();
		Context::flush_resolved();
		Series::get_instance()->flush_memo();

		parent::tearDown();
	}

	/**
	 * Build the anchor every fixture here is dated from.
	 *
	 * Thirty days out, so nothing is ever in the past. `Rest_Api::update_rsvp()`
	 * gates its write on `! $event->has_event_past()`, so a pinned anchor would
	 * turn this whole file into a date bomb that stops writing anything the day
	 * real time crosses it.
	 *
	 * @since 0.36.0
	 *
	 * @return DateTimeImmutable The anchor start, in the fixture timezone.
	 */
	protected function relative_anchor(): DateTimeImmutable {
		return ( new DateTimeImmutable( 'now', new DateTimeZone( 'America/New_York' ) ) )
			->modify( '+30 days' )
			->setTime( 18, 0 );
	}

	/**
	 * Create the reference recurring series, project it, and flag the site.
	 *
	 * @since 0.36.0
	 *
	 * @return int The projected series post ID.
	 */
	protected function create_and_project(): int {
		$anchor  = $this->relative_anchor();
		$post_id = $this->create_relative_recurring_event(
			self::WEEKLY_RULE,
			$anchor,
			$anchor->modify( '+2 hours' )
		);

		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();

		$this->identifiers = $this->identifiers_for( $post_id );

		$this->assertCount(
			5,
			$this->identifiers,
			'Failed to project the five occurrences every test in this class is written against.'
		);

		return $post_id;
	}

	/**
	 * Read one post's occurrence identifiers, ordered ascending by start.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post to read.
	 *
	 * @return string[] The identifiers.
	 */
	protected function identifiers_for( int $post_id ): array {
		return array_map(
			'strval',
			wp_list_pluck(
				Occurrences::get_instance()->select_for_series( array( $post_id ) ),
				'recurrence_id'
			)
		);
	}

	/**
	 * Dispatch one REST request through the real server.
	 *
	 * @since 0.36.0
	 *
	 * @param string $method Request method.
	 * @param string $route  Route below the `event` namespace.
	 * @param array  $params Request parameters.
	 *
	 * @return \WP_REST_Response The dispatched response.
	 */
	protected function dispatch( string $method, string $route, array $params ) {
		$request = new WP_REST_Request(
			$method,
			sprintf( '/%s/event/%s', GATHERPRESS_REST_NAMESPACE, $route )
		);

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * Issue a real guest token against one occurrence, through the public form route.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id       Event post ID.
	 * @param string $email         Responder email.
	 * @param string $recurrence_id Occurrence to book.
	 *
	 * @return array{comment_id: int, token: string} The stored comment and its magic-link token.
	 */
	protected function issue_token( int $post_id, string $email, string $recurrence_id ): array {
		$response = $this->dispatch(
			'POST',
			'rsvp-form',
			array(
				'comment_post_ID' => $post_id,
				'author'          => 'Ada Lovelace',
				'email'           => $email,
				'recurrence_id'   => $recurrence_id,
			)
		);

		$this->assertSame( 200, $response->get_status(), 'Failed to arrange an accepted form submission.' );

		$comment_id = (int) $response->get_data()['comment_id'];
		$token      = new Token( $comment_id );
		$token_str  = sprintf( '%d_%s', $comment_id, $token->get_token() );

		$token->approve_comment();

		return array(
			'comment_id' => $comment_id,
			'token'      => $token_str,
		);
	}

	/**
	 * Read the roster of one occurrence through the production RSVP API.
	 *
	 * `Rsvp::responses()` is what the rsvp-response block, the REST roster and
	 * the editor all read. A term-level or `Storage`-level count would pass on
	 * exactly the contradictory-owner state this file exists to forbid.
	 *
	 * @since 0.36.0
	 *
	 * @param int         $post_id       Event post ID.
	 * @param string|null $recurrence_id Occurrence to scope to, or null for the series.
	 *
	 * @return int[] Comment IDs on the roster, ascending, across every status.
	 */
	protected function roster( int $post_id, ?string $recurrence_id ): array {
		Context::get_instance()->clear();
		Context::flush_resolved();

		if ( null !== $recurrence_id ) {
			Context::get_instance()->set_for_series( $post_id, $recurrence_id );
		}

		$responses = ( new Rsvp( $post_id ) )->responses();

		Context::get_instance()->clear();

		$ids = array();

		foreach ( array( 'attending', 'not_attending', 'waiting_list' ) as $status ) {
			$ids = array_merge( $ids, array_column( $responses[ $status ]['records'], 'comment_id' ) );
		}

		$ids = array_map( 'intval', $ids );

		sort( $ids );

		return $ids;
	}

	/**
	 * Capture every durable thing a split can touch.
	 *
	 * Rule 3a #8: a rollback assertion against one column cannot tell a restored
	 * state from a state the operation never reached. This captures the whole
	 * surface the split writes to: posts and their fields, all postmeta,
	 * occurrence rows, RSVP comments and their owners, both taxonomies keyed by
	 * slug rather than by term ID, and the has-recurring-events option.
	 *
	 * Terms are keyed by slug deliberately: a rollback that recreates a deleted
	 * term restores every observable property except `term_id`, and nothing in
	 * production resolves a term any way but by slug.
	 *
	 * @since 0.36.0
	 *
	 * @return array The observable state.
	 */
	protected function whole_surface(): array {
		global $wpdb;

		$state = array(
			'posts'       => array(),
			'meta'        => array(),
			'occurrences' => array(),
			'comments'    => array(),
			'terms'       => array(),
			'options'     => array(
				Recurrence_Query::HAS_RECURRING_OPTION => get_option( Recurrence_Query::HAS_RECURRING_OPTION ),
			),
		);

		$posts = get_posts(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'orderby'     => 'ID',
				'order'       => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$state['posts'][ (int) $post->ID ] = array(
				'author'         => (int) $post->post_author,
				'comment_status' => $post->comment_status,
				'content'        => $post->post_content,
				'menu_order'     => (int) $post->menu_order,
				'parent'         => (int) $post->post_parent,
				'password'       => $post->post_password,
				'ping_status'    => $post->ping_status,
				'status'         => $post->post_status,
				'title'          => $post->post_title,
			);

			$meta = get_post_meta( (int) $post->ID );

			ksort( $meta );

			$state['meta'][ (int) $post->ID ] = $meta;
		}

		$table = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$state['occurrences'] = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT series_post_id, recurrence_id, datetime_start, datetime_end, timezone, status'
					. ' FROM %i ORDER BY series_post_id ASC, recurrence_id ASC',
				$table
			),
			ARRAY_A
		);

		foreach ( get_comments(
			array(
				'type'   => Rsvp::COMMENT_TYPE,
				'status' => 'all',
			)
		) as $comment ) {
			$state['comments'][ (int) $comment->comment_ID ] = array(
				'approved' => (string) $comment->comment_approved,
				'email'    => (string) $comment->comment_author_email,
				'post'     => (int) $comment->comment_post_ID,
			);
		}

		ksort( $state['comments'] );

		foreach ( array( Rsvp_Occurrence::TAXONOMY, Series::TAXONOMY ) as $taxonomy ) {
			$state['terms'][ $taxonomy ] = array();

			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			$terms = is_wp_error( $terms ) ? array() : $terms;

			foreach ( $terms as $term ) {
				$objects = get_objects_in_term( array( (int) $term->term_id ), $taxonomy );
				$objects = is_wp_error( $objects ) ? array() : array_map( 'intval', $objects );

				sort( $objects );

				$state['terms'][ $taxonomy ][ $term->slug ] = $objects;
			}

			ksort( $state['terms'][ $taxonomy ] );
		}

		return $state;
	}

	/**
	 * Acceptance 5: a pre-split RSVP is readable exactly once, on the canonical post.
	 *
	 * The measured cross-PR failure. `Rsvp\Storage` narrows every read by
	 * `post_id` **and** by the occurrence term, conjoined, so a split that
	 * renamed the term and left `comment_post_ID` behind made the response
	 * unreadable from both sides: the forward post's read matched the term but
	 * not the post, and the origin's matched the post but not the term. The
	 * responder then read as `no_status` and could book the same date twice.
	 *
	 * Read through `Rsvp::responses()`, which is what the roster block, the REST
	 * roster and the editor all call. The suite's earlier split test resolved
	 * each comment's own taxonomy term instead, which is the layer beneath the
	 * defect, and it passed throughout.
	 *
	 * @covers ::split_forward
	 * @covers ::split_owned_series
	 * @covers ::migrate_rsvps
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::migrate_owner
	 *
	 * @return void
	 */
	public function test_pre_split_rsvps_are_readable_exactly_once_through_the_production_roster(): void {
		$origin_id = $this->create_and_project();

		wp_set_current_user( 0 );

		$stays = $this->issue_token( $origin_id, 'grace@example.test', $this->identifiers[0] );
		$moves = $this->issue_token( $origin_id, 'ada@example.test', $this->identifiers[3] );

		$this->assertSame(
			array( $moves['comment_id'] ),
			$this->roster( $origin_id, $this->identifiers[3] ),
			'Failed to arrange a response readable on the occurrence that is about to move.'
		);

		$result     = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward_id = (int) $result['forward_post_id'];

		$this->assertTrue( $result['split'], 'Failed to arrange an actual split.' );

		// The whole per-occurrence vector, read through the owner of each date.
		// One row would pass on a split that collapsed every response onto the
		// first forward occurrence, or that duplicated one across both posts.
		$this->assertSame(
			array(
				$this->identifiers[0] => array( $stays['comment_id'] ),
				$this->identifiers[1] => array(),
				$this->identifiers[2] => array(),
				$this->identifiers[3] => array( $moves['comment_id'] ),
				$this->identifiers[4] => array(),
			),
			array(
				$this->identifiers[0] => $this->roster( $origin_id, $this->identifiers[0] ),
				$this->identifiers[1] => $this->roster( $origin_id, $this->identifiers[1] ),
				$this->identifiers[2] => $this->roster( $forward_id, $this->identifiers[2] ),
				$this->identifiers[3] => $this->roster( $forward_id, $this->identifiers[3] ),
				$this->identifiers[4] => $this->roster( $forward_id, $this->identifiers[4] ),
			),
			'Failed to assert every pre-split response is readable exactly once, on the post that owns its date.'
		);
		// The series-wide read carries no occurrence term at all, so it answers
		// purely on `comment_post_ID`. Asserting it on both posts is what proves
		// the comment owner moved rather than merely that the term did, and that
		// exactly one post claims each response.
		$this->assertSame(
			array( $stays['comment_id'] ),
			$this->roster( $origin_id, null ),
			'Failed to assert the origin owns only the comment for the date it kept.'
		);
		$this->assertSame(
			array( $moves['comment_id'] ),
			$this->roster( $forward_id, null ),
			'Failed to assert the forward post owns the comment for the date that moved to it.'
		);
		$this->assertSame(
			$forward_id,
			(int) get_comment( $moves['comment_id'] )->comment_post_ID,
			'Failed to assert the RSVP comment moved to the post that owns its occurrence row.'
		);
		$this->assertSame(
			array(
				'series_post_id' => $forward_id,
				'recurrence_id'  => $this->identifiers[3],
			),
			Rsvp_Occurrence::occurrence_for_comment( $moves['comment_id'] ),
			'Failed to assert the occurrence term names the same post as the comment.'
		);
	}

	/**
	 * Acceptance 6: a pre-split token routes, authorizes and updates on the canonical owner.
	 *
	 * A magic link issued before the split names the comment; after the split
	 * the comment, its occurrence row and its term all name the forward post, so
	 * the generated URL, the permission callback and the write must agree on
	 * that one post. They did not before: routing followed the renamed term to
	 * the forward post while the permission callback read the token's post off
	 * `comment_post_ID`, which was still the origin, so the correct request was
	 * denied.
	 *
	 * @covers ::split_owned_series
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::migrate_owner
	 *
	 * @return void
	 */
	public function test_a_pre_split_token_authorizes_and_updates_the_moved_occurrence(): void {
		$origin_id = $this->create_and_project();

		wp_set_current_user( 0 );

		$issued = $this->issue_token( $origin_id, 'ada@example.test', $this->identifiers[3] );

		$result     = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward_id = (int) $result['forward_post_id'];

		$this->assertStringContainsString(
			(string) get_post_field( 'post_name', $forward_id ),
			( new Token( $issued['comment_id'] ) )->generate_url(),
			'Failed to assert the pre-split link routes to the post that now owns the occurrence.'
		);

		$response = $this->dispatch(
			'POST',
			'rsvp',
			array(
				'post_id'       => $forward_id,
				'status'        => 'not_attending',
				'rsvp_token'    => $issued['token'],
				'recurrence_id' => $this->identifiers[3],
			)
		);

		$this->assertSame(
			200,
			$response->get_status(),
			'Failed to assert a pre-split token authorizes a write to the occurrence it was issued for.'
		);
		$this->assertTrue(
			$response->get_data()['success'],
			'Failed to assert the canonical post accepted the token holder\'s update.'
		);
		$this->assertSame(
			array( $issued['comment_id'] ),
			$this->roster( $forward_id, $this->identifiers[3] ),
			'Failed to assert the update landed on the one existing response rather than creating a second.'
		);
		$this->assertSame(
			'not_attending',
			$this->status_of( $issued['comment_id'] ),
			'Failed to assert the update actually changed the stored response.'
		);
	}

	/**
	 * Acceptance 6: the same token is refused on the sibling that no longer owns the date.
	 *
	 * Naming the origin post for a date the forward post now owns must be
	 * refused with nothing changed and nothing revealed, whether the caller is
	 * guessing or holding a stale bookmark.
	 *
	 * The refusal is **401, not 403**, and the difference is not this layer's to
	 * change. `Event\Rest_Api::can_update_rsvp()` enters its token branch only
	 * when the token's own post equals the post the request named; the token now
	 * belongs to the forward post, so a request naming the origin falls through
	 * to the logged-in branch and an unauthenticated guest is refused there. The
	 * security property is identical either way, because the write does not
	 * happen and the response does not vary with whether the occurrence exists.
	 * The status is asserted exactly rather than loosely, so a change to it has
	 * to be a deliberate one.
	 *
	 * @covers ::split_owned_series
	 * @covers \GatherPress\Core\Event\Rest_Api::can_update_rsvp
	 *
	 * @return void
	 */
	public function test_a_pre_split_token_is_refused_on_the_sibling_that_lost_the_occurrence(): void {
		$origin_id = $this->create_and_project();

		wp_set_current_user( 0 );

		$issued = $this->issue_token( $origin_id, 'ada@example.test', $this->identifiers[3] );

		$result     = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward_id = (int) $result['forward_post_id'];
		$before     = $this->roster( $forward_id, $this->identifiers[3] );

		$response = $this->dispatch(
			'POST',
			'rsvp',
			array(
				'post_id'       => $origin_id,
				'status'        => 'not_attending',
				'rsvp_token'    => $issued['token'],
				'recurrence_id' => $this->identifiers[3],
			)
		);

		$this->assertSame(
			401,
			$response->get_status(),
			'Failed to assert a request naming the sibling that no longer owns the occurrence is refused.'
		);
		$this->assertSame(
			$before,
			$this->roster( $forward_id, $this->identifiers[3] ),
			'Failed to assert the refused request left the canonical roster unchanged.'
		);
		$this->assertSame(
			'attending',
			$this->status_of( $issued['comment_id'] ),
			'Failed to assert the refused request did not change the stored response.'
		);
	}

	/**
	 * Read one RSVP comment's stored status through its own taxonomy term.
	 *
	 * Deliberately not through the roster: the roster is what the tests above
	 * assert about, and using it here to establish that a write did or did not
	 * happen would make one observation carry two jobs.
	 *
	 * @since 0.36.0
	 *
	 * @param int $comment_id RSVP comment ID.
	 *
	 * @return string The status slug, or an empty string when none is stored.
	 */
	protected function status_of( int $comment_id ): string {
		$slugs = wp_get_object_terms( $comment_id, '_gatherpress_rsvp_status', array( 'fields' => 'slugs' ) );

		return is_wp_error( $slugs ) || empty( $slugs ) ? '' : (string) $slugs[0];
	}

	/**
	 * Acceptance 1: capability on one fragment never authorizes a mutation of a sibling.
	 *
	 * The measured object-level authorization bypass. The permission callback
	 * checked `edit_post` on the post the request named; the splitter then
	 * resolved the occurrence across the whole series, and capped, moved rows
	 * off and rewrote whichever sibling actually owned it. An author who could
	 * edit A and not B could therefore restructure B by naming A.
	 *
	 * The sibling is left in a state worth defending, with five occurrences, a
	 * rule and its own membership, and the whole of it is compared before and
	 * after, so a refusal that changed something quietly would fail.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rest_Api::split_series
	 * @covers \GatherPress\Core\Event\Recurrence\Rest_Api::refuse_unauthorized_split
	 *
	 * @return void
	 */
	public function test_capability_on_one_fragment_does_not_authorize_a_sibling_split(): void {
		// Authors, not editors: an editor holds `edit_others_posts`, so no
		// sibling would ever be one they cannot edit and the test could not
		// fail.
		$authorized   = $this->factory->user->create( array( 'role' => 'author' ) );
		$unauthorized = $this->factory->user->create( array( 'role' => 'author' ) );

		$origin_id = $this->create_and_project();

		wp_update_post(
			array(
				'ID'          => $origin_id,
				'post_author' => $authorized,
			)
		);

		// A real two-post series: the split is performed as an administrator so
		// the fixture itself is never the thing under test.
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$result     = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward_id = (int) $result['forward_post_id'];

		wp_update_post(
			array(
				'ID'          => $forward_id,
				'post_author' => $unauthorized,
			)
		);

		wp_set_current_user( $authorized );

		$this->assertTrue(
			current_user_can( 'edit_post', $origin_id ),
			'Failed to arrange a caller who can edit the post it names.'
		);
		$this->assertFalse(
			current_user_can( 'edit_post', $forward_id ),
			'Failed to arrange a sibling the caller cannot edit.'
		);

		$before = $this->whole_surface();

		$response = $this->dispatch(
			'POST',
			'split-series',
			array(
				'post_id'       => $origin_id,
				'recurrence_id' => $this->identifiers[3],
			)
		);

		$this->assertSame(
			403,
			$response->get_status(),
			'Failed to assert a caller authorized for one fragment cannot split a sibling it may not edit.'
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert the refused split left every post, row, comment, term, meta value and option alone.'
		);
	}

	/**
	 * Acceptance 1: the same caller may still split the fragment it does own.
	 *
	 * The other half of the verdict. A refusal that also refused the legitimate
	 * case would satisfy the test above and break the feature.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rest_Api::split_series
	 * @covers \GatherPress\Core\Event\Recurrence\Rest_Api::refuse_unauthorized_split
	 *
	 * @return void
	 */
	public function test_the_owner_of_a_fragment_may_still_split_it(): void {
		$author    = $this->factory->user->create( array( 'role' => 'author' ) );
		$origin_id = $this->create_and_project();

		wp_update_post(
			array(
				'ID'          => $origin_id,
				'post_author' => $author,
			)
		);
		wp_set_current_user( $author );

		$response = $this->dispatch(
			'POST',
			'split-series',
			array(
				'post_id'       => $origin_id,
				'recurrence_id' => $this->identifiers[2],
			)
		);

		$this->assertSame(
			200,
			$response->get_status(),
			'Failed to assert the author of the owning fragment may split it.'
		);
		$this->assertTrue(
			$response->get_data()['split'],
			'Failed to assert the authorized split actually happened.'
		);
	}

	/**
	 * Acceptance 1: a caller who cannot publish cannot create a published duplicate.
	 *
	 * A split creates a second post at the origin's own status. A contributor
	 * who may edit a published post but may not publish must not be able to
	 * bring a second published event into existence by splitting.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rest_Api::refuse_unauthorized_split
	 *
	 * @return void
	 */
	public function test_a_caller_without_publish_capability_cannot_split_a_published_series(): void {
		$contributor = $this->factory->user->create( array( 'role' => 'contributor' ) );
		$origin_id   = $this->create_and_project();

		wp_update_post(
			array(
				'ID'          => $origin_id,
				'post_author' => $contributor,
			)
		);

		// A contributor can edit their own post only while it is not published,
		// so the capability is granted directly for exactly the one post: the
		// test is about the publish check, not about the edit check.
		$grant = static function ( array $caps, string $cap, int $user_id, array $args ) use ( $origin_id ): array {
			return 'edit_post' === $cap && isset( $args[0] ) && (int) $args[0] === $origin_id
				? array( 'exist' )
				: $caps;
		};

		add_filter( 'map_meta_cap', $grant, 10, 4 );
		wp_set_current_user( $contributor );

		$this->assertTrue(
			current_user_can( 'edit_post', $origin_id ),
			'Failed to arrange a caller who may edit the published series.'
		);

		$before   = $this->whole_surface();
		$response = $this->dispatch(
			'POST',
			'split-series',
			array(
				'post_id'       => $origin_id,
				'recurrence_id' => $this->identifiers[2],
			)
		);

		remove_filter( 'map_meta_cap', $grant, 10 );

		$this->assertSame(
			403,
			$response->get_status(),
			'Failed to assert a caller who cannot publish is refused a split that would publish a second event.'
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert the refused split changed nothing.'
		);
	}

	/**
	 * Acceptance 2: a rejected destination insert leaves everything untouched.
	 *
	 * The measured failure. `create_forward_post()` cast the insert result to an
	 * integer, so a veto became post ID 0: the split moved the forward rows onto
	 * a post that does not exist, capped the origin's rule, and answered
	 * `split: true` with `forward_post_id: 0`.
	 *
	 * The veto is `wp_insert_post_empty_content`, which is the production filter
	 * a plugin would use, rather than a mocked `$wpdb`.
	 *
	 * @covers ::create_forward_post
	 * @covers ::roll_back
	 * @covers ::run_phases
	 * @covers ::split_owned_series
	 *
	 * @return void
	 */
	public function test_a_rejected_destination_insert_leaves_everything_unchanged(): void {
		$origin_id = $this->create_and_project();

		wp_set_current_user( 0 );
		$this->issue_token( $origin_id, 'ada@example.test', $this->identifiers[3] );

		$before = $this->whole_surface();

		add_filter( 'wp_insert_post_empty_content', '__return_true' );

		$result = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		remove_filter( 'wp_insert_post_empty_content', '__return_true' );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a rejected destination insert is reported as an error rather than as a split.'
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert a rejected destination insert left every post, row, comment, term, meta value'
				. ' and option exactly as it found them.'
		);
	}

	/**
	 * Acceptance 4: the forward post inherits the password and every supported field.
	 *
	 * `post_password` is the one that mattered: splitting a password-protected
	 * published event produced a second published event carrying the same
	 * content with no password at all, so the protected content was public.
	 *
	 * @covers ::create_forward_post
	 *
	 * @return void
	 */
	public function test_the_forward_post_inherits_the_password_and_every_supported_field(): void {
		$parent_id = $this->factory->post->create( array( 'post_type' => 'page' ) );
		$origin_id = $this->create_and_project();

		wp_update_post(
			array(
				'ID'             => $origin_id,
				'comment_status' => 'closed',
				'menu_order'     => 7,
				'ping_status'    => 'closed',
				'post_parent'    => $parent_id,
				'post_password'  => 'correct-horse',
			)
		);

		$origin  = get_post( $origin_id );
		$result  = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward = get_post( (int) $result['forward_post_id'] );

		// The field list is written out rather than read from
		// `COPIED_POST_FIELDS`, because deriving it from the constant would make
		// removing a field from the constant remove it from the assertion too,
		// leaving a test that can never fail for the one defect it exists to
		// catch.
		$fields   = array(
			'comment_status',
			'menu_order',
			'ping_status',
			'post_author',
			'post_content',
			'post_excerpt',
			'post_parent',
			'post_password',
			'post_status',
			'post_title',
			'post_type',
		);
		$expected = array();
		$observed = array();

		foreach ( $fields as $field ) {
			$expected[ $field ] = $origin->{$field};
			$observed[ $field ] = $forward->{$field};
		}

		$this->assertSame(
			'correct-horse',
			$origin->post_password,
			'Failed to arrange a password-protected origin, without which this test cannot fail.'
		);
		$this->assertSame(
			$expected,
			$observed,
			'Failed to assert every copied post field arrived on the forward post byte for byte.'
		);
		$this->assertSame(
			$fields,
			Splitter::COPIED_POST_FIELDS,
			'Failed to assert the copied-field allowlist still names exactly the fields asserted above.'
		);
		$this->assertNotSame(
			$origin->post_name,
			$forward->post_name,
			'Failed to assert the forward post got its own slug rather than colliding with the origin.'
		);
	}

	/**
	 * The origin's old-slug history stays with the origin.
	 *
	 * `post_name` is deliberately not copied, so the forward post gets a slug
	 * of its own and never answered to any of the origin's earlier ones.
	 * Copying `_wp_old_slug` handed it those permalinks anyway, and WordPress's
	 * old-slug redirect resolves one to whichever post claims it, sending a
	 * visitor who follows a published link away from the post that still owns
	 * the dates it was published for. Reachable on any event renamed after
	 * publication, which is when WordPress writes the key.
	 *
	 * @covers ::copy_meta
	 *
	 * @return void
	 */
	public function test_the_old_slug_history_stays_with_the_origin(): void {
		$origin_id = $this->create_and_project();

		// The shape WordPress writes itself when a published post's slug
		// changes: one row per slug the post has answered to before.
		add_post_meta( $origin_id, '_wp_old_slug', 'downtown-meetup' );
		add_post_meta( $origin_id, '_wp_old_slug', 'downtown-wordpress-meetup' );

		$result  = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward = (int) $result['forward_post_id'];

		$this->assertTrue( $result['split'], 'Fixture setup: the split must succeed.' );
		$this->assertSame(
			array(),
			get_post_meta( $forward, '_wp_old_slug', false ),
			'Failed to assert the forward post claims none of the origin\'s old permalinks.'
		);
		$this->assertSame(
			array( 'downtown-meetup', 'downtown-wordpress-meetup' ),
			get_post_meta( $origin_id, '_wp_old_slug', false ),
			'Failed to assert the origin kept the whole of its own rename history.'
		);
	}

	/**
	 * Acceptance 4: repeated meta arrives with its cardinality intact.
	 *
	 * `meta_input` takes one value per key, so an extension that had stored two
	 * values under one key saw the second silently disappear on the forward post
	 * with no error anywhere.
	 *
	 * @covers ::copy_meta
	 * @covers ::create_forward_post
	 *
	 * @return void
	 */
	public function test_multi_valued_meta_survives_the_split(): void {
		$origin_id = $this->create_and_project();

		add_post_meta( $origin_id, 'extension_schedule', 'first' );
		add_post_meta( $origin_id, 'extension_schedule', 'second' );
		add_post_meta( $origin_id, 'extension_config', array( 'nested' => array( 1, 2 ) ) );

		$result  = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward = (int) $result['forward_post_id'];

		$this->assertSame(
			array( 'first', 'second' ),
			get_post_meta( $forward, 'extension_schedule', false ),
			'Failed to assert both values of a repeated meta key came across in order.'
		);
		$this->assertSame(
			array( array( 'nested' => array( 1, 2 ) ) ),
			get_post_meta( $forward, 'extension_config', false ),
			'Failed to assert a serialized meta value came across in the same shape.'
		);
	}

	/**
	 * B4: capping the origin's rule advances the logical series' calendar revision.
	 *
	 * A rule cap is a bare `postmeta` write. It changes published calendar
	 * content, because the origin's already-issued `UID` acquires a shorter
	 * `RRULE`, while touching no occurrence row, so nothing else on the stack
	 * notices.
	 * Without this a subscriber keeps expanding the dates the split moved away
	 * and then accepts them again under the sibling's identifier.
	 *
	 * @covers ::advance_revision
	 * @covers \GatherPress\Core\Calendar\Revision::advance
	 *
	 * @return void
	 */
	public function test_the_split_advances_the_calendar_revision_for_the_whole_series(): void {
		$origin_id = $this->create_and_project();
		$before    = Revision::get_instance()->current( $origin_id );
		$at_cap    = 0;

		// Moving the occurrence rows already advances the revision through
		// `gatherpress_occurrences_changed`, so a test that only compared before
		// and after would pass with no advance for the rule cap at all. The
		// reading taken the moment the cap completes is what separates the two.
		$observe = static function ( $outcome, string $phase ) use ( $origin_id, &$at_cap ) {
			if ( 'origin_rule' === $phase ) {
				$at_cap = Revision::get_instance()->stored( $origin_id );
			}

			return $outcome;
		};

		add_filter( 'gatherpress_split_phase_complete', $observe, 10, 2 );

		$result  = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );
		$forward = (int) $result['forward_post_id'];

		remove_filter( 'gatherpress_split_phase_complete', $observe, 10 );

		$this->assertGreaterThan(
			0,
			$at_cap,
			'Failed to observe the revision at the moment the origin rule was capped.'
		);
		$this->assertGreaterThan(
			$at_cap,
			Revision::get_instance()->stored( $origin_id ),
			'Failed to assert the revision advances for the capped rule, not only for the moved rows.'
		);
		$this->assertGreaterThan(
			$before,
			Revision::get_instance()->stored( $origin_id ),
			'Failed to assert the split advanced the revision the origin fragment reports.'
		);
		$this->assertSame(
			Revision::get_instance()->stored( $origin_id ),
			Revision::get_instance()->stored( $forward ),
			'Failed to assert both fragments of the logical series report one revision.'
		);
	}

	/**
	 * Every durable phase of a split, in the order they complete.
	 *
	 * @since 0.36.0
	 *
	 * @return array<string, string[]> Phase name, as a PHPUnit data set.
	 */
	public function durable_phases(): array {
		return array(
			'after the destination post exists'  => array( 'create_forward_post' ),
			'after the occurrence rows moved'    => array( 'move_occurrences' ),
			'after the RSVP owners moved'        => array( 'migrate_rsvps' ),
			'after the series term joined'       => array( 'join_series' ),
			'after the forward rule was written' => array( 'forward_rule' ),
			'after the origin rule was capped'   => array( 'origin_rule' ),
			'after the partition was verified'   => array( 'verify_partition' ),
			'after the revision advanced'        => array( 'advance_revision' ),
		);
	}

	/**
	 * Acceptance 3: a failure after any durable phase rolls the whole split back.
	 *
	 * A split writes to five WordPress stores that share no transaction. The
	 * seam a failure is injected through is the production
	 * `gatherpress_split_phase_complete` filter rather than a broken table,
	 * because DDL inside a test body implicitly commits the surrounding
	 * transaction and leaks its fixtures into every later test in the run.
	 *
	 * The fixture is deliberately one a partial rollback cannot satisfy: RSVPs
	 * on both sides of the split point, a canceled occurrence among the rows
	 * that move, repeated meta, and a password. `test_a_successful_split_changes
	 * _the_observable_surface()` establishes that this same comparison does move
	 * when the split succeeds, so a rolled-back state and a never-attempted one
	 * are not the same observation.
	 *
	 * @dataProvider durable_phases
	 *
	 * @covers ::advance_revision
	 * @covers ::copy_meta
	 * @covers ::create_forward_post
	 * @covers ::datetime_blob
	 * @covers ::join_series
	 * @covers ::migrate_rsvps
	 * @covers ::move_occurrences
	 * @covers ::phase
	 * @covers ::record
	 * @covers ::restore
	 * @covers ::revision_snapshot
	 * @covers ::roll_back
	 * @covers ::rule_phase
	 * @covers ::run_phases
	 * @covers ::settle_caches
	 * @covers ::snapshot
	 * @covers ::split_forward
	 * @covers ::split_identity
	 * @covers ::split_owned_series
	 * @covers ::verify_partition
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::memberships
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::migrate_owner
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::restore_memberships
	 *
	 * @param string $phase Phase to fail after.
	 *
	 * @return void
	 */
	public function test_a_failure_after_any_durable_phase_rolls_the_whole_split_back( string $phase ): void {
		$origin_id = $this->arrange_rich_series();
		$before    = $this->whole_surface();
		$result    = $this->split_failing_after( $phase, $origin_id, $this->identifiers[2] );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			sprintf( 'Failed to assert a failure %s aborts the split.', $phase )
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			sprintf(
				'Failed to assert a failure %s left every post, row, comment, term, meta value and option'
					. ' exactly as it found them.',
				$phase
			)
		);
	}

	/**
	 * Acceptance 3: a failure after the origin's rule demoted it rolls back too.
	 *
	 * Splitting at the second occurrence leaves the origin holding one date, and
	 * one date is not a series: the origin is demoted to a plain event, which
	 * removes its rule, deletes its occurrence row and deletes the occurrence
	 * term whose relationship rows carry that date's RSVPs. It is the most
	 * destructive thing a split does and the hardest to reverse.
	 *
	 * @covers ::rule_phase
	 * @covers ::snapshot
	 * @covers ::restore
	 * @covers ::roll_back
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::memberships
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::restore_memberships
	 *
	 * @return void
	 */
	public function test_a_failure_after_a_demoting_origin_rule_rolls_the_split_back(): void {
		$origin_id = $this->arrange_rich_series();

		$this->assertSame(
			array( $this->rsvp_of( $this->identifiers[0] ) ),
			$this->roster( $origin_id, $this->identifiers[0] ),
			'Failed to arrange an RSVP on the date the demotion would unscope.'
		);

		$before = $this->whole_surface();
		$result = $this->split_failing_after( 'verify_partition', $origin_id, $this->identifiers[1] );

		$this->assertInstanceOf( WP_Error::class, $result, 'Failed to assert the demoting split aborted.' );
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert a demoted origin was restored whole, RSVP relationships included.'
		);
		$this->assertSame(
			array( $this->rsvp_of( $this->identifiers[0] ) ),
			$this->roster( $origin_id, $this->identifiers[0] ),
			'Failed to assert the restored RSVP is readable again through the production roster.'
		);
	}

	/**
	 * Acceptance 3: a failure after the forward rule demoted the new post rolls back.
	 *
	 * Splitting at the last occurrence of a `COUNT` rule gives the forward post
	 * exactly one date, so it is demoted the same way, and the occurrence term
	 * it drops is the one the split has just renamed onto it, carrying a
	 * pre-split RSVP.
	 *
	 * @covers ::rule_phase
	 * @covers ::roll_back
	 *
	 * @return void
	 */
	public function test_a_failure_after_a_demoting_forward_rule_rolls_the_split_back(): void {
		$origin_id = $this->arrange_rich_series();
		$before    = $this->whole_surface();
		$result    = $this->split_failing_after( 'origin_rule', $origin_id, $this->identifiers[4] );

		$this->assertInstanceOf( WP_Error::class, $result, 'Failed to assert the demoting split aborted.' );
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert a demoted forward post was rolled back whole.'
		);
		$this->assertSame(
			array( $this->rsvp_of( $this->identifiers[4] ) ),
			$this->roster( $origin_id, $this->identifiers[4] ),
			'Failed to assert the last date\'s RSVP is readable again on the origin.'
		);
	}

	/**
	 * A forward-rule projection the database refuses aborts and rolls the split back.
	 *
	 * `Occurrences::project()` reports a refused write as a `WP_Error`, and
	 * the split used to discard that report: the phases kept running against
	 * rows the projection never wrote, and the response claimed success. The
	 * failure is produced at the database itself, by redirecting the
	 * projection's first insert to a nonexistent table, the same seam the
	 * projection suite's own write-failure tests use. Only the first insert
	 * is broken, so the rollback's re-projection can restore the origin.
	 *
	 * @covers ::rule_phase
	 * @covers ::write_rule
	 * @covers ::apply_forward_rule
	 * @covers ::roll_back
	 *
	 * @return void
	 */
	public function test_a_forward_rule_projection_the_database_refuses_rolls_the_split_back(): void {
		$origin_id = $this->arrange_rich_series();
		$before    = $this->whole_surface();
		$filter    = $this->break_nth_occurrence_insert( 1 );

		$result = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		remove_filter( 'query', $filter );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a refused forward projection aborts the split instead of reporting success.'
		);
		$this->assertSame(
			'gatherpress_occurrence_write_failed',
			$result->get_error_code(),
			'Failed to assert the database refusal itself is what surfaces.'
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert a refused forward projection left every post, row, comment, term and meta value as found.'
		);
	}

	/**
	 * An origin-cap projection the database refuses aborts and rolls the split back.
	 *
	 * The second projection of a split, so the forward side's rule is already
	 * written and its own projection has succeeded by the time this one
	 * fails; the rollback has real work on both sides.
	 *
	 * @covers ::rule_phase
	 * @covers ::write_rule
	 * @covers ::apply_capped_rule
	 * @covers ::roll_back
	 *
	 * @return void
	 */
	public function test_an_origin_cap_projection_the_database_refuses_rolls_the_split_back(): void {
		$origin_id = $this->arrange_rich_series();
		$before    = $this->whole_surface();
		$filter    = $this->break_nth_occurrence_insert( 2 );

		$result = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		remove_filter( 'query', $filter );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a refused origin-cap projection aborts the split.'
		);
		$this->assertSame(
			'gatherpress_occurrence_write_failed',
			$result->get_error_code(),
			'Failed to assert the database refusal itself is what surfaces.'
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert a refused origin-cap projection left the whole observable surface as found.'
		);
	}

	/**
	 * A rollback whose own re-projection fails reports both failures.
	 *
	 * The rollback's final step re-derives the origin's rows from its
	 * restored rule, and that projection can be refused by the same broken
	 * database that caused the abort. Swallowing it would report a clean
	 * rollback that never happened; the report instead carries the original
	 * failure first and a second, distinctly named
	 * `gatherpress_split_rollback_failed` after it, so the caller learns the
	 * tree may be inconsistent. The rollback's code is deliberately *not*
	 * reused for that second entry: a database refusing every write makes
	 * both failures `gatherpress_occurrence_write_failed`, and
	 * `WP_Error::add()` would fold the second into the first exactly when the
	 * operator most needs to see it. The underlying refusal travels in the
	 * entry's data instead. The occurrence rows themselves are already back
	 * where they were, restored by the move undo, so the failed re-projection
	 * loses no rows.
	 *
	 * @covers ::roll_back
	 * @covers ::split_owned_series
	 *
	 * @return void
	 */
	public function test_a_rollback_whose_reprojection_fails_reports_both_failures(): void {
		$origin_id = $this->arrange_rich_series();
		$filter    = null;

		// Break the occurrence inserts only once every commit-path projection
		// has succeeded, so the first failing write is the rollback's own.
		$inject = function ( $outcome, string $completed ) use ( &$filter ) {
			if ( 'verify_partition' === $completed ) {
				$filter = $this->break_nth_occurrence_insert( 1 );

				// Carries a status of its own so the assertions below can tell
				// whose data a REST response would read once the two failures
				// share one `WP_Error`.
				return new WP_Error( 'gatherpress_injected', 'Injected failure.', array( 'status' => 400 ) );
			}

			return $outcome;
		};

		add_filter( 'gatherpress_split_phase_complete', $inject, 10, 2 );

		$result = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		remove_filter( 'gatherpress_split_phase_complete', $inject, 10 );

		if ( null !== $filter ) {
			remove_filter( 'query', $filter );
		}

		$this->assertInstanceOf( WP_Error::class, $result, 'Failed to assert the injected failure aborts the split.' );
		$this->assertSame(
			array( 'gatherpress_injected', 'gatherpress_split_rollback_failed' ),
			$result->get_error_codes(),
			'Failed to assert the report carries the original failure and a named rollback failure after it.'
		);
		$this->assertSame(
			'gatherpress_occurrence_write_failed',
			$result->get_error_data( 'gatherpress_split_rollback_failed' )['rollback_error_code'],
			'Failed to assert the rollback entry carries the refusal that actually broke the rollback.'
		);
		$this->assertSame(
			400,
			$result->get_error_data()['status'],
			'Failed to assert the first failure still governs the status the route reports.'
		);
		$this->assertSame(
			$this->identifiers,
			$this->identifiers_for( $origin_id ),
			'Failed to assert the move undo restored every row even though the re-projection was refused.'
		);
	}

	/**
	 * Acceptance 3: a series term that cannot be created aborts rather than
	 * leaving two unrelated posts.
	 *
	 * `Series::join()` answers 0 when the term can neither be created nor
	 * recovered, and that answer used to be discarded. The result was two posts
	 * that resolve as two unrelated series: a permalink through the origin can
	 * no longer find the rows that moved, and nothing reports a failure.
	 *
	 * @covers ::join_series
	 *
	 * @return void
	 */
	public function test_a_series_term_that_cannot_be_created_aborts_the_split(): void {
		$origin_id = $this->arrange_rich_series();
		$before    = $this->whole_surface();

		$refuse = static function () {
			return new WP_Error( 'refused', 'No terms today.' );
		};

		add_filter( 'pre_insert_term', $refuse );

		$result = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		remove_filter( 'pre_insert_term', $refuse );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a split that cannot record the two posts as one series is refused.'
		);
		$this->assertSame(
			'gatherpress_split_series_not_joined',
			$result->get_error_code(),
			'Failed to assert the refusal names the series join rather than something generic.'
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert the refused split left everything as it found it.'
		);
	}

	/**
	 * Split a two-occurrence series so both sides demote to plain events.
	 *
	 * The exact case of body step 14, and the only split whose result leaves
	 * the site without a single recurrence rule: demotion removes both rules
	 * and all occurrence rows, so `gatherpress_has_recurring_events`
	 * recomputes to `'0'` while the series term relationship persists. The
	 * sanity assertions are what make the fresh-request tests below tests of
	 * the registration gate and nothing else: were any rule left standing, the
	 * recurring flag alone would register the taxonomy and the gate under
	 * test could never act.
	 *
	 * @since 0.36.0
	 *
	 * @return array{0: int, 1: int} Origin and forward post IDs.
	 */
	protected function split_two_dates_into_plain_events(): array {
		$anchor    = $this->relative_anchor();
		$origin_id = $this->create_relative_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 1,
				'weekdays'  => array( 2 ),
				'end_type'  => 'count',
				'count'     => 2,
			),
			$anchor,
			$anchor->modify( '+2 hours' )
		);

		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();

		$identifiers = $this->identifiers_for( $origin_id );

		$this->assertCount( 2, $identifiers, 'Fixture setup: the series must project exactly two occurrences.' );

		$result  = Splitter::get_instance()->split_forward( $origin_id, $identifiers[1] );
		$forward = (int) $result['forward_post_id'];

		$this->assertTrue( (bool) $result['split'], 'Fixture setup: the split must succeed.' );
		$this->assertFalse(
			(bool) $result['origin_recurring'],
			'Fixture setup: the origin side must demote to a plain event.'
		);
		$this->assertFalse(
			(bool) $result['forward_recurring'],
			'Fixture setup: the forward side must demote to a plain event.'
		);
		$this->assertFalse(
			Recurrence_Query::site_has_recurring_events(),
			'Fixture setup: demoting both sides must leave the site with no recurring events at all.'
		);

		return array( $origin_id, $forward );
	}

	/**
	 * Simulate the next request's registration pass.
	 *
	 * Taxonomy registration is process global, so the registration the split
	 * performed mid-request is torn down first; only the production
	 * `registered_post_type` hook can bring it back, exactly as the next real
	 * request would.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function simulate_fresh_request_registration(): void {
		if ( taxonomy_exists( Series::TAXONOMY ) ) {
			unregister_taxonomy( Series::TAXONOMY );
		}

		Series::get_instance()->flush_memo();

		// Firing a core registration hook, not declaring a plugin one: this is
		// the production entry point every next request registers through.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'registered_post_type', Event::POST_TYPE, get_post_type_object( Event::POST_TYPE ) );
	}

	/**
	 * Both halves of a fully demoted split still resolve as one series next request.
	 *
	 * Splitting a two-occurrence series at its second date demotes both sides
	 * to plain events and turns the recurring flag off, but the durable series
	 * relationship the changelog promises for links and calendars persists in
	 * the taxonomy. On the next request the taxonomy must therefore still be
	 * registered and the two events must still resolve as one series, or the
	 * relationship silently stops being readable while its rows sit in the
	 * database.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Series::register
	 * @covers \GatherPress\Core\Event\Recurrence\Series::resolve_post_ids
	 *
	 * @return void
	 */
	public function test_two_date_split_fragments_resolve_as_one_series_after_a_fresh_request(): void {
		list( $origin_id, $forward ) = $this->split_two_dates_into_plain_events();

		$this->simulate_fresh_request_registration();

		$this->assertTrue(
			taxonomy_exists( Series::TAXONOMY ),
			'A site whose only series is a fully demoted split must still register the series taxonomy on the'
				. ' next request.'
		);

		$expected = array( min( $origin_id, $forward ), max( $origin_id, $forward ) );

		$this->assertSame(
			$expected,
			Series::get_instance()->resolve_post_ids( $origin_id ),
			'The demoted origin must still resolve to both fragments of the split series.'
		);

		Series::get_instance()->flush_memo();

		$this->assertSame(
			$expected,
			Series::get_instance()->resolve_post_ids( $forward ),
			'The demoted forward post must still resolve to both fragments of the split series.'
		);
	}

	/**
	 * A fully demoted split's calendar continuity survives the next request.
	 *
	 * The aggregate assertion the immediate split response cannot stand in
	 * for: the fragment's single export must still carry every fragment of
	 * the series, and its `RELATED-TO` must still point at the `UID` the
	 * subscription was first taken out against, on a request where no
	 * recurrence rule exists anywhere on the site.
	 *
	 * @covers \GatherPress\Core\Calendar\Setup::series_component_post_ids
	 * @covers \GatherPress\Core\Calendar\Setup::get_ical_file
	 *
	 * @return void
	 */
	public function test_a_two_date_splits_calendar_continuity_survives_a_fresh_request(): void {
		list( $origin_id, $forward ) = $this->split_two_dates_into_plain_events();

		$this->simulate_fresh_request_registration();

		$this->go_to( get_permalink( $forward ) );

		$this->assertSame(
			array( min( $origin_id, $forward ), max( $origin_id, $forward ) ),
			Calendar_Setup::get_instance()->series_component_post_ids(),
			'The fragment\'s single export must carry every fragment of the demoted split series.'
		);

		$ical = Calendar_Setup::get_instance()->get_ical_file();

		$this->assertStringContainsString(
			sprintf( 'UID:gatherpress_%d', $origin_id ),
			$ical,
			'The origin fragment\'s component must be part of the export.'
		);
		$this->assertStringContainsString(
			sprintf( 'UID:gatherpress_%d', $forward ),
			$ical,
			'The forward fragment\'s component must be part of the export.'
		);
		$this->assertStringContainsString(
			sprintf( 'RELATED-TO:gatherpress_%d', $origin_id ),
			$ical,
			'The fragment must keep pointing at the UID the subscription was first taken out against.'
		);
	}

	/**
	 * A rolled-back split leaves the split-series flag off.
	 *
	 * The join phase records the flag the moment the term is created; a later
	 * phase's failure rolls the term back out. The flag is recomputed from
	 * storage on that deletion, so a site whose only split ever was rolled
	 * back stays a site with no split series and keeps its byte-identical
	 * SQL promise.
	 *
	 * The option name is asserted literally rather than through a class
	 * constant, because the stored name is a site contract: renaming the
	 * constant must break this test.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Series::refresh_has_split_series
	 *
	 * @return void
	 */
	public function test_a_rolled_back_split_leaves_the_split_series_flag_off(): void {
		$origin_id = $this->create_and_project();
		$result    = $this->split_failing_after( 'verify_partition', $origin_id, $this->identifiers[2] );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Fixture setup: the injected failure must abort the split.'
		);
		$this->assertSame(
			'0',
			get_option( 'gatherpress_has_split_series' ),
			'A rolled back split must clear the flag its own join recorded, recomputed from the deleted term.'
		);
	}

	/**
	 * The control for every rollback assertion above.
	 *
	 * Rule 3a #8: a fixture where the rolled-back state and the never-attempted
	 * state are indistinguishable proves nothing. This asserts that the same
	 * whole-surface comparison **does** change when the split is allowed to
	 * finish, so the equalities above are load-bearing.
	 *
	 * @covers ::advance_revision
	 * @covers ::copy_meta
	 * @covers ::create_forward_post
	 * @covers ::datetime_blob
	 * @covers ::join_series
	 * @covers ::migrate_rsvps
	 * @covers ::move_occurrences
	 * @covers ::phase
	 * @covers ::result
	 * @covers ::revision_snapshot
	 * @covers ::rule_phase
	 * @covers ::run_phases
	 * @covers ::settle_caches
	 * @covers ::snapshot
	 * @covers ::split_forward
	 * @covers ::split_identity
	 * @covers ::split_owned_series
	 * @covers ::verify_partition
	 *
	 * @return void
	 */
	public function test_a_successful_split_changes_the_observable_surface(): void {
		$origin_id = $this->arrange_rich_series();
		$before    = $this->whole_surface();
		$result    = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		$this->assertTrue( $result['split'], 'Failed to assert the control split succeeded.' );
		$this->assertNotSame(
			$before,
			$this->whole_surface(),
			'Failed to assert a completed split moves the very comparison the rollback tests rely on.'
		);
	}

	/**
	 * Split the series with a failure injected after one named phase.
	 *
	 * @since 0.36.0
	 *
	 * @param string $phase         Phase to fail after.
	 * @param int    $post_id       Post to split.
	 * @param string $recurrence_id Occurrence to split at.
	 *
	 * @return array|WP_Error The split result.
	 */
	protected function split_failing_after( string $phase, int $post_id, string $recurrence_id ) {
		$inject = static function ( $outcome, string $completed ) use ( $phase ) {
			return $completed === $phase ? new WP_Error( 'gatherpress_injected', 'Injected failure.' ) : $outcome;
		};

		add_filter( 'gatherpress_split_phase_complete', $inject, 10, 2 );

		$result = Splitter::get_instance()->split_forward( $post_id, $recurrence_id );

		remove_filter( 'gatherpress_split_phase_complete', $inject, 10 );

		return $result;
	}

	/**
	 * Build a series whose every store carries something a partial rollback would lose.
	 *
	 * An RSVP on every date, a canceled occurrence among the ones that move,
	 * repeated meta, and a password. A rollback that restored the occurrence
	 * rows but not the RSVP relationships, or the rule but not the cancellation,
	 * fails against this fixture and would pass against a bare one.
	 *
	 * @since 0.36.0
	 *
	 * @return int The series post ID.
	 */
	protected function arrange_rich_series(): int {
		$origin_id = $this->create_and_project();

		wp_update_post(
			array(
				'ID'            => $origin_id,
				'post_password' => 'correct-horse',
			)
		);

		add_post_meta( $origin_id, 'extension_schedule', 'first' );
		add_post_meta( $origin_id, 'extension_schedule', 'second' );

		Occurrences::get_instance()->set_status(
			$origin_id,
			$this->identifiers[3],
			Occurrences::STATUS_CANCELED
		);

		wp_set_current_user( 0 );

		foreach ( $this->identifiers as $index => $recurrence_id ) {
			$this->rsvps[ $recurrence_id ] = $this->issue_token(
				$origin_id,
				sprintf( 'responder-%d@example.test', $index ),
				(string) $recurrence_id
			)['comment_id'];
		}

		Context::get_instance()->clear();
		Context::flush_resolved();

		return $origin_id;
	}

	/**
	 * The RSVP comment `arrange_rich_series()` left on one occurrence.
	 *
	 * @since 0.36.0
	 *
	 * @param string $recurrence_id Occurrence identifier.
	 *
	 * @return int The comment ID.
	 */
	protected function rsvp_of( string $recurrence_id ): int {
		return (int) $this->rsvps[ $recurrence_id ];
	}

	/**
	 * The RSVP-impact count resolves every occurrence term in one query.
	 *
	 * The editor calls the impact route on every change to a rule, and reducing
	 * a maximum count can remove hundreds of occurrences at once. Resolving one
	 * term per removed identifier made the cost of previewing a change
	 * proportional to how much the change removed.
	 *
	 * Asserted as a query count against the term table rather than as a wall
	 * time, and the fixture is 60 identifiers so a per-identifier
	 * implementation cannot pass by accident.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::count_rsvps
	 *
	 * @return void
	 */
	public function test_the_rsvp_impact_count_resolves_every_term_in_one_query(): void {
		global $wpdb;

		$origin_id = $this->create_and_project();

		wp_set_current_user( 0 );

		$this->issue_token( $origin_id, 'ada@example.test', $this->identifiers[0] );

		$candidates = $this->identifiers;
		$padding    = 60 - count( $candidates );

		for ( $index = 0; $index < $padding; $index++ ) {
			$candidates[] = sprintf( '2099%04dT180000', 1231 - $index );
		}

		$previous      = $wpdb->queries;
		$wpdb->queries = array();
		$count         = Rsvp_Occurrence::get_instance()->count_rsvps( $origin_id, $candidates );
		$term_queries  = array_filter(
			array_column( $wpdb->queries, 0 ),
			static function ( string $query ) use ( $wpdb ): bool {
				return str_contains( $query, $wpdb->term_taxonomy ) && str_contains( $query, 'slug' );
			}
		);
		$wpdb->queries = $previous;

		$this->assertNotEmpty(
			$wpdb->queries,
			'SAVEQUERIES must be on for this assertion to mean anything.'
		);
		$this->assertSame(
			1,
			$count,
			'Failed to assert the count still reports the one approved RSVP among the candidates.'
		);
		$this->assertCount(
			1,
			$term_queries,
			'Failed to assert 60 candidate occurrences resolve their terms in a single slug lookup.'
		);
	}

	/**
	 * Moving an RSVP owner onto a slug that already exists is a failure, not a skip.
	 *
	 * Two occurrences whose terms merged would merge their RSVPs, which is data
	 * loss rather than a skippable edge. The caller is told, and the partial
	 * move undoes itself.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::migrate_owner
	 *
	 * @return void
	 */
	public function test_migrating_onto_a_taken_slug_fails_and_undoes_itself(): void {
		$origin_id = $this->create_and_project();

		wp_set_current_user( 0 );

		$issued = $this->issue_token( $origin_id, 'ada@example.test', $this->identifiers[0] );

		// A term already sitting on the destination slug, exactly as a second
		// split of the same series would leave one.
		wp_insert_term(
			Rsvp_Occurrence::term_slug( 12345, $this->identifiers[0] ),
			Rsvp_Occurrence::TAXONOMY,
			array( 'slug' => Rsvp_Occurrence::term_slug( 12345, $this->identifiers[0] ) )
		);

		$result = Rsvp_Occurrence::get_instance()->migrate_owner(
			$origin_id,
			12345,
			array( $this->identifiers[0] )
		);

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a taken destination slug is reported rather than silently skipped.'
		);
		$this->assertSame(
			array(
				'series_post_id' => $origin_id,
				'recurrence_id'  => $this->identifiers[0],
			),
			Rsvp_Occurrence::occurrence_for_comment( $issued['comment_id'] ),
			'Failed to assert the refused migration left the RSVP on its original occurrence.'
		);
		$this->assertSame(
			$origin_id,
			(int) get_comment( $issued['comment_id'] )->comment_post_ID,
			'Failed to assert the refused migration left the comment on its original post.'
		);
	}

	/**
	 * Migrating between the same post, or with nothing to migrate, is a no-op.
	 *
	 * Both degenerate arms exist so a caller does not have to guard them, and
	 * both are asserted so neither is an untested early return.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::migrate_owner
	 *
	 * @return void
	 */
	public function test_a_degenerate_owner_migration_moves_nothing(): void {
		$expected = array(
			'terms'    => 0,
			'comments' => array(),
		);

		$this->assertSame(
			$expected,
			Rsvp_Occurrence::get_instance()->migrate_owner( 12, 12, array( '20260903T180000' ) ),
			'Failed to assert migrating a post onto itself moves nothing.'
		);
		$this->assertSame(
			$expected,
			Rsvp_Occurrence::get_instance()->migrate_owner( 12, 13, array() ),
			'Failed to assert migrating an empty identifier set moves nothing.'
		);
		$this->assertSame(
			$expected,
			Rsvp_Occurrence::get_instance()->migrate_owner( 12, 13, array( '20260903T180000' ) ),
			'Failed to assert an occurrence nobody has RSVPd to has no term to move.'
		);
	}

	/**
	 * Reading a series term before the taxonomy exists answers 0 rather than crashing.
	 *
	 * `get_the_terms()` answers a `WP_Error` for an unregistered taxonomy, and
	 * `isset()` on an offset of an object that is not `ArrayAccess` is a fatal
	 * `Error` in PHP 8 rather than a falsy read. The split reads this before it
	 * registers the taxonomy, so the error shape is reachable in production.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Series::term_id_for_post
	 *
	 * @return void
	 */
	public function test_reading_a_series_term_from_an_unregistered_taxonomy_answers_zero(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );

		if ( taxonomy_exists( Series::TAXONOMY ) ) {
			unregister_taxonomy( Series::TAXONOMY );
		}

		$this->assertSame(
			0,
			Series::get_instance()->term_id_for_post( $post_id ),
			'Failed to assert an unregistered series taxonomy reads as no membership rather than crashing.'
		);
	}

	/**
	 * Splitting a series that is already split joins the term it already has.
	 *
	 * The second split must not create a second series term: keeping the series
	 * out of a chain requiring traversal is what makes a thrice-split series one
	 * read, and it only holds while every fragment carries the same term.
	 *
	 * @covers ::join_series
	 *
	 * @return void
	 */
	public function test_a_second_split_joins_the_series_term_the_first_created(): void {
		$origin_id = $this->create_and_project();

		$first  = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[1] );
		$middle = (int) $first['forward_post_id'];
		$second = Splitter::get_instance()->split_forward( $middle, $this->identifiers[3] );
		$last   = (int) $second['forward_post_id'];

		$this->assertTrue( $second['split'], 'Failed to assert the second split happened.' );

		$terms = get_terms(
			array(
				'taxonomy'   => Series::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		$this->assertCount(
			1,
			is_wp_error( $terms ) ? array() : $terms,
			'Failed to assert two splits produced one series term rather than a chain.'
		);
		$this->assertSame(
			array( $origin_id, $middle, $last ),
			Series::get_instance()->resolve_post_ids( $last ),
			'Failed to assert all three fragments resolve as one series in a single read.'
		);
	}

	/**
	 * A partial occurrence move aborts rather than half-splitting the series.
	 *
	 * The rows that did not move keep answering under the origin while their
	 * RSVP terms and comments have already been told to expect the sibling, so a
	 * move that reports fewer rows than it was asked for is a failure rather
	 * than a smaller success.
	 *
	 * The partial move is produced by appending `LIMIT 1` to the real `UPDATE`
	 * through the `query` filter, which is a data-plane rewrite rather than DDL:
	 * a `CREATE`/`DROP` here would implicitly commit the surrounding transaction
	 * and leak this fixture into every later test in the run.
	 *
	 * @covers ::move_occurrences
	 *
	 * @return void
	 */
	public function test_a_partial_occurrence_move_aborts_and_rolls_back(): void {
		global $wpdb;

		$origin_id = $this->arrange_rich_series();
		$before    = $this->whole_surface();
		$table     = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$limit = static function ( string $query ) use ( $table ): string {
			return str_starts_with( $query, 'UPDATE' ) && str_contains( $query, $table )
				&& str_contains( $query, 'SET series_post_id' )
				? $query . ' LIMIT 1'
				: $query;
		};

		add_filter( 'query', $limit );

		$result = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		remove_filter( 'query', $limit );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a move that relocated fewer rows than asked for is refused.'
		);
		$this->assertSame(
			'gatherpress_split_rows_not_moved',
			$result->get_error_code(),
			'Failed to assert the refusal names the incomplete move.'
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert the partial move was undone and nothing else was touched.'
		);
	}

	/**
	 * A partition that does not match what the split promised aborts it.
	 *
	 * The final check exists for a rule rewrite that reports success and
	 * projects something else. It is driven here by a listener that removes a
	 * row between the rule phases and the check, which is exactly the shape of
	 * the failure it defends against.
	 *
	 * @covers ::verify_partition
	 *
	 * @return void
	 */
	public function test_a_partition_that_does_not_match_the_promise_aborts_the_split(): void {
		global $wpdb;

		$origin_id = $this->arrange_rich_series();
		$before    = $this->whole_surface();
		$table     = sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix );

		$steal = static function ( $outcome, string $phase, int $origin ) use ( $wpdb, $table ) {
			if ( 'origin_rule' === $phase ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query(
					$wpdb->prepare( 'DELETE FROM %i WHERE series_post_id = %d LIMIT 1', $table, $origin )
				);
			}

			return $outcome;
		};

		add_filter( 'gatherpress_split_phase_complete', $steal, 10, 3 );

		$result = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		remove_filter( 'gatherpress_split_phase_complete', $steal, 10 );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a partition that lost a row is refused rather than reported as a split.'
		);
		$this->assertSame(
			'gatherpress_split_partition_mismatch',
			$result->get_error_code(),
			'Failed to assert the refusal names the partition rather than something generic.'
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert the aborted split restored the row it lost along with everything else.'
		);
	}

	/**
	 * A split the splitter refuses is reported by the route as that error.
	 *
	 * The route's own guards answer 400, 403 and 404; a failure the splitter
	 * raises part-way through has to travel out as itself rather than as a
	 * generic 500 or, worse, as a 200 describing a split that was rolled back.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rest_Api::split_series
	 *
	 * @return void
	 */
	public function test_the_route_reports_a_split_the_splitter_refused(): void {
		$origin_id = $this->create_and_project();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$before = $this->whole_surface();
		$inject = static function ( $outcome, string $phase ) {
			return 'join_series' === $phase
				? new WP_Error( 'gatherpress_injected', 'Injected failure.', array( 'status' => 500 ) )
				: $outcome;
		};

		add_filter( 'gatherpress_split_phase_complete', $inject, 10, 2 );

		$response = $this->dispatch(
			'POST',
			'split-series',
			array(
				'post_id'       => $origin_id,
				'recurrence_id' => $this->identifiers[2],
			)
		);

		remove_filter( 'gatherpress_split_phase_complete', $inject, 10 );

		$this->assertSame(
			500,
			$response->get_status(),
			'Failed to assert a refused split travels out of the route as an error.'
		);
		$this->assertSame(
			'gatherpress_injected',
			$response->as_error()->get_error_code(),
			'Failed to assert the route reports the splitter\'s own error rather than replacing it.'
		);
		$this->assertSame(
			$before,
			$this->whole_surface(),
			'Failed to assert the refused split left everything as it found it.'
		);
	}

	/**
	 * An RSVP comment that cannot be moved undoes the whole migration.
	 *
	 * The term rename and the comment move are two writes with no shared
	 * transaction, so the second failing has to put the first back. The failure
	 * is produced by rewriting the comment `UPDATE` through the `query` filter,
	 * which is a data-plane rewrite rather than DDL.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::migrate_owner
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::revert_migration
	 *
	 * @return void
	 */
	public function test_an_rsvp_comment_that_cannot_be_moved_undoes_the_rename(): void {
		global $wpdb;

		$origin_id = $this->create_and_project();

		wp_set_current_user( 0 );

		$issued = $this->issue_token( $origin_id, 'ada@example.test', $this->identifiers[0] );

		$break = static function ( string $query ) use ( $wpdb ): string {
			return str_starts_with( $query, 'UPDATE' ) && str_contains( $query, $wpdb->comments )
				? sprintf( 'UPDATE %s SET no_such_column = 1 WHERE comment_ID = 0', $wpdb->comments )
				: $query;
		};

		$suppressed = $wpdb->suppress_errors( true );

		add_filter( 'query', $break );

		$result = Rsvp_Occurrence::get_instance()->migrate_owner(
			$origin_id,
			12345,
			array( $this->identifiers[0] )
		);

		remove_filter( 'query', $break );
		$wpdb->suppress_errors( $suppressed );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a comment that cannot be moved is reported rather than silently skipped.'
		);
		$this->assertSame(
			'gatherpress_rsvp_comment_not_moved',
			$result->get_error_code(),
			'Failed to assert the failure names the comment move.'
		);
		$this->assertSame(
			array(
				'series_post_id' => $origin_id,
				'recurrence_id'  => $this->identifiers[0],
			),
			Rsvp_Occurrence::occurrence_for_comment( $issued['comment_id'] ),
			'Failed to assert the renamed term was renamed back when the comment move failed.'
		);
	}

	/**
	 * Reading memberships skips occurrences that carry nothing to restore.
	 *
	 * Two arms, both reachable in production: an occurrence nobody has RSVPd to
	 * has no term at all, and a term whose only RSVP has since been deleted has
	 * no objects.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::memberships
	 *
	 * @return void
	 */
	public function test_memberships_skips_occurrences_with_nothing_attached(): void {
		$origin_id = $this->create_and_project();
		$slug      = Rsvp_Occurrence::term_slug( $origin_id, $this->identifiers[1] );

		wp_insert_term( $slug, Rsvp_Occurrence::TAXONOMY, array( 'slug' => $slug ) );

		$this->assertSame(
			array(),
			Rsvp_Occurrence::get_instance()->memberships(
				$origin_id,
				array( $this->identifiers[0], $this->identifiers[1] )
			),
			'Failed to assert an occurrence with no term, and a term with no RSVPs, both read as nothing.'
		);
	}

	/**
	 * An identity whose row has gone since it was resolved is a 404, not a crash.
	 *
	 * Resolution and use are two reads, and a concurrent split or a canceled
	 * projection can remove the row between them. The split refuses rather than
	 * proceeding against a row it no longer has.
	 *
	 * @covers ::split_identity
	 *
	 * @return void
	 */
	public function test_an_identity_whose_row_has_gone_is_refused(): void {
		$origin_id = $this->create_and_project();
		$identity  = Occurrence_Identity::resolve( $origin_id, $this->identifiers[2] );

		$this->assertNotNull( $identity, 'Failed to arrange a resolved identity.' );

		Occurrences::get_instance()->delete_for_post( $origin_id );
		Context::flush_resolved();

		$result = Splitter::get_instance()->split_identity( $identity );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert an identity whose row has gone is refused.'
		);
		$this->assertSame(
			'gatherpress_occurrence_not_found',
			$result->get_error_code(),
			'Failed to assert the refusal names the missing occurrence.'
		);
	}

	/**
	 * An RSVP migration that fails mid-split aborts the split.
	 *
	 * The destination occurrence term already existing is the real shape of this
	 * failure, arising from a series split twice or a partial import, and
	 * merging two occurrences' RSVPs into one term would be data loss. The collision is
	 * arranged from the phase seam, which is the first moment the forward post
	 * ID exists.
	 *
	 * @covers ::migrate_rsvps
	 * @covers ::run_phases
	 *
	 * @return void
	 */
	public function test_an_rsvp_migration_failure_aborts_the_split(): void {
		$origin_id = $this->create_and_project();

		wp_set_current_user( 0 );

		$issued  = $this->issue_token( $origin_id, 'ada@example.test', $this->identifiers[3] );
		$collide = function ( $outcome, string $phase, int $origin, int $forward ) {
			if ( 'create_forward_post' === $phase ) {
				$slug = Rsvp_Occurrence::term_slug( $forward, $this->identifiers[3] );

				wp_insert_term( $slug, Rsvp_Occurrence::TAXONOMY, array( 'slug' => $slug ) );
			}

			return $outcome;
		};

		add_filter( 'gatherpress_split_phase_complete', $collide, 10, 4 );

		$result = Splitter::get_instance()->split_forward( $origin_id, $this->identifiers[2] );

		remove_filter( 'gatherpress_split_phase_complete', $collide, 10 );

		$this->assertInstanceOf(
			WP_Error::class,
			$result,
			'Failed to assert a split whose RSVP migration cannot complete is refused.'
		);
		$this->assertSame(
			'gatherpress_rsvp_term_not_renamed',
			$result->get_error_code(),
			'Failed to assert the refusal names the RSVP term rather than something generic.'
		);
		$this->assertSame(
			array( $issued['comment_id'] ),
			$this->roster( $origin_id, $this->identifiers[3] ),
			'Failed to assert the RSVP is still readable on the origin through the production roster.'
		);
		$this->assertSame(
			$this->identifiers,
			$this->identifiers_for( $origin_id ),
			'Failed to assert every occurrence row came back to the origin.'
		);
	}

	/**
	 * A series that carries no calendar revision yet still rolls back to carrying none.
	 *
	 * Every site that upgrades into the revision meta starts without it, so
	 * "restore what was there" has to include restoring its absence. Writing an
	 * empty string instead would make the series report a revision it never had.
	 *
	 * @covers ::revision_snapshot
	 * @covers ::roll_back
	 *
	 * @return void
	 */
	public function test_a_rollback_restores_the_absence_of_a_calendar_revision(): void {
		$origin_id = $this->create_and_project();

		delete_post_meta( $origin_id, Revision::META_KEY );

		$this->assertFalse(
			metadata_exists( 'post', $origin_id, Revision::META_KEY ),
			'Failed to arrange a series carrying no stored revision.'
		);

		$result = $this->split_failing_after( 'advance_revision', $origin_id, $this->identifiers[2] );

		$this->assertInstanceOf( WP_Error::class, $result, 'Failed to assert the split aborted.' );
		$this->assertFalse(
			metadata_exists( 'post', $origin_id, Revision::META_KEY ),
			'Failed to assert the rollback restored the absence of the revision rather than writing an empty one.'
		);
	}

	/**
	 * Undoing a migration skips an occurrence whose term is already gone.
	 *
	 * The guard is defensive: the identifiers handed to the undo are exactly the
	 * ones whose terms were renamed, so the only way to reach it is a store that
	 * lost the term in between. Invoked directly because no fixture can produce
	 * that state from outside, and because xdebug does not trace a private
	 * helper's body reliably through its caller.
	 *
	 * @covers \GatherPress\Core\Event\Recurrence\Rsvp_Occurrence::revert_migration
	 *
	 * @return void
	 */
	public function test_undoing_a_migration_skips_an_occurrence_with_no_term(): void {
		Utility::invoke_hidden_method(
			Rsvp_Occurrence::get_instance(),
			'revert_migration',
			array( 4242, 2424, array( '20260903T180000' ) )
		);

		$this->assertFalse(
			get_term_by( 'slug', Rsvp_Occurrence::term_slug( 2424, '20260903T180000' ), Rsvp_Occurrence::TAXONOMY ),
			'Failed to assert undoing a migration of a term that does not exist creates nothing.'
		);
	}
}
