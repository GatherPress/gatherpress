<?php
/**
 * Class handles unit tests for the no-JavaScript RSVP form on an occurrence page.
 *
 * The classic fallback posts natively to `wp-comments-post.php`, which loads
 * WordPress and calls `wp_handle_comment_submission()` without running the main
 * query, so `wp` never fires and `Event\Recurrence\Context::sync()` never runs.
 * Every test here therefore drives `wp_handle_comment_submission()`, the
 * function that endpoint calls, rather than invoking the two RSVP handlers
 * directly: a test that called `preprocess_rsvp_comment()` and
 * `handle_rsvp_comment_post()` by hand would pass while the filters that connect
 * them to a real submission were never registered.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use DateTimeImmutable;
use DateTimeZone;
use GatherPress\Core\Blocks\Rsvp_Form as Rsvp_Form_Block;
use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Rsvp\Cache;
use GatherPress\Core\Rsvp\Form;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Core\Rsvp\Token;
use GatherPress\Core\Settings;
use GatherPress\Core\Setup;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WPDieException;

/**
 * Class Test_Rsvp_Classic_Form.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Form
 */
class Test_Rsvp_Classic_Form extends Base {

	use Occurrence_Fixtures;

	/**
	 * The reference weekly rule, matching `Occurrence_Fixtures::expected_weekly_set()`.
	 *
	 * @since 0.36.0
	 * @var array
	 */
	const WEEKLY_RULE = array(
		'frequency' => 'weekly',
		'interval'  => 2,
		'weekdays'  => array( 2, 4 ),
		'end_type'  => 'count',
		'count'     => 5,
	);

	/**
	 * A well-formed identifier that names no occurrence of anything.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const UNKNOWN_OCCURRENCE = '20991231T235959';

	/**
	 * Recurrence identifier of the projected set's first occurrence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	protected string $occurrence_a = '';

	/**
	 * Recurrence identifier of the projected set's second occurrence.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	protected string $occurrence_b = '';

	/**
	 * Posted values the `gatherpress_pre_get_http_input` shim serves.
	 *
	 * @since 0.36.0
	 * @var array<string, string>
	 */
	protected array $posted = array();

	/**
	 * Series-widening filters installed by `widen_series()`, for teardown.
	 *
	 * @since 0.36.0
	 * @var callable[]
	 */
	protected array $series_filters = array();

	/**
	 * Ensure the occurrence table and RSVP taxonomies exist, with no context leaking in.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
		Rsvp_Setup::get_instance()->register_taxonomy();
		Settings::get_instance()->set( 'enable_open_rsvp', true );
		Context::get_instance()->clear();
		Context::flush_resolved();

		$this->posted = array();

		add_filter( 'gatherpress_pre_get_http_input', array( $this, 'serve_posted_value' ), 10, 3 );
	}

	/**
	 * Leave no posted values, filters or occurrence context behind.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		foreach ( $this->series_filters as $filter ) {
			remove_filter( 'gatherpress_series_post_ids', $filter, 10 );
		}

		$this->series_filters = array();

		remove_filter( 'gatherpress_pre_get_http_input', array( $this, 'serve_posted_value' ), 10 );
		remove_all_filters( 'preprocess_comment' );
		remove_all_actions( 'comment_post' );
		remove_all_filters( 'comments_open' );
		remove_all_filters( 'allow_empty_comment' );
		remove_all_filters( 'pre_comment_approved' );

		unset( $_SERVER['REQUEST_METHOD'] );

		Context::get_instance()->clear();
		Context::flush_resolved();

		parent::tearDown();
	}

	/**
	 * Serve the posted values this test arranged.
	 *
	 * `Utility::get_http_input()` reads `filter_input( INPUT_POST, … )`, which
	 * cannot see values a test assigns to `$_POST`, so the shim the utility
	 * already exposes for tests is what stands in for the request body.
	 *
	 * @since 0.36.0
	 *
	 * @param mixed  $pre_value The short-circuit value, null by default.
	 * @param int    $type      Input type constant.
	 * @param string $var_name  Variable being read.
	 *
	 * @return string|null The arranged value, or null to fall through.
	 */
	public function serve_posted_value( $pre_value, $type, $var_name ) {
		if ( INPUT_POST !== $type ) {
			return $pre_value;
		}

		return $this->posted[ $var_name ] ?? '';
	}

	/**
	 * Build the anchor every fixture in this class is dated from.
	 *
	 * Thirty days out, because the submission path refuses a past event, and a
	 * pinned calendar date would silently stop writing anything the day real
	 * time crossed it.
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
	 * Create the reference recurring event, project it, and read back two occurrences.
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

		$rows = Occurrences::get_instance()->select_for_series( array( $post_id ) );

		$this->assertGreaterThan( 1, count( $rows ), 'Failed to project two sibling occurrences.' );

		$this->occurrence_a = (string) $rows[0]['recurrence_id'];
		$this->occurrence_b = (string) $rows[1]['recurrence_id'];

		return $post_id;
	}

	/**
	 * Create an ordinary, non-recurring event with a future date.
	 *
	 * @since 0.36.0
	 *
	 * @return int The event post ID.
	 */
	protected function create_plain_event(): int {
		$anchor  = $this->relative_anchor();
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
					'timezone'      => 'America/New_York',
				)
			)
		);

		\GatherPress\Core\Event\Setup::get_instance()->set_datetimes( $post_id );

		return $post_id;
	}

	/**
	 * Widen a series so `$member` resolves to both posts.
	 *
	 * Installs the `gatherpress_series_post_ids` filter the forward split will
	 * populate for real, which is the only seam by which a multi-post series can
	 * exist. `Series` is final with a protected constructor precisely so no test
	 * can fake one any other way.
	 *
	 * @since 0.36.0
	 *
	 * @param int $member The post the request names.
	 * @param int $owner  The post the occurrence rows live on.
	 *
	 * @return void
	 */
	protected function widen_series( int $member, int $owner ): void {
		$filter = static function ( array $post_ids, int $post_id ) use ( $member, $owner ): array {
			return ( $member === $post_id || $owner === $post_id )
				? array( $member, $owner )
				: $post_ids;
		};

		add_filter( 'gatherpress_series_post_ids', $filter, 10, 2 );

		Context::flush_resolved();

		$this->series_filters[] = $filter;
	}

	/**
	 * Count the RSVP comments stored on one post, whatever their approval state.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id The post whose stored RSVPs are counted.
	 *
	 * @return int The number of stored RSVP comments.
	 */
	protected function stored_rsvp_count( int $post_id ): int {
		return (int) get_comments(
			array(
				'post_id' => $post_id,
				'type'    => Rsvp::COMMENT_TYPE,
				'count'   => true,
				'status'  => 'all',
			)
		);
	}

	/**
	 * Submit the classic form the way `wp-comments-post.php` does.
	 *
	 * @since 0.36.0
	 *
	 * @param int         $post_id       Event post ID the form posts to.
	 * @param string      $email         Responder email.
	 * @param string|null $recurrence_id Occurrence the rendered form carried, if any.
	 *
	 * @return int The created comment ID.
	 */
	protected function submit( int $post_id, string $email, ?string $recurrence_id ): int {
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->posted = array(
			'comment_post_ID'          => (string) $post_id,
			'gatherpress_rsvp_form_id' => 'gatherpress_rsvp_form_1',
			'author'                   => 'Ada Lovelace',
			'email'                    => $email,
		);

		if ( null !== $recurrence_id ) {
			$this->posted[ Form::RECURRENCE_ID_FIELD ] = $recurrence_id;
		}

		Form::get_instance()->initialize_rsvp_form_handling();

		$comment = wp_handle_comment_submission(
			array(
				'comment_post_ID' => $post_id,
				'author'          => 'Ada Lovelace',
				'email'           => $email,
				'comment'         => '',
			)
		);

		$this->assertNotWPError( $comment, 'Failed to arrange an accepted classic RSVP submission.' );

		return (int) $comment->comment_ID;
	}

	/**
	 * Redeem the confirmation link the submission issued.
	 *
	 * A classic submission is stored unapproved, and every roster read is scoped
	 * to approved responses, so redemption is what makes the response visible to
	 * the production API the assertions read through.
	 *
	 * @since 0.36.0
	 *
	 * @param int $comment_id The stored RSVP comment.
	 *
	 * @return void
	 */
	protected function redeem( int $comment_id ): void {
		( new Token( $comment_id ) )->approve_comment();
	}

	/**
	 * Read one occurrence's roster through the production API.
	 *
	 * `Rsvp::responses()` is what every consumer reads. Asserting on the
	 * occurrence taxonomy instead passes on a stored row that no roster can
	 * actually see.
	 *
	 * @since 0.36.0
	 *
	 * @param int         $post_id       Event post ID.
	 * @param string|null $recurrence_id Occurrence to scope to, or null for the series.
	 *
	 * @return int[] Attending comment IDs.
	 */
	protected function roster( int $post_id, ?string $recurrence_id ): array {
		if ( null !== $recurrence_id ) {
			Context::get_instance()->set_for_series( $post_id, $recurrence_id );
		}

		$responses = ( new Rsvp( $post_id ) )->responses();

		Context::get_instance()->clear();

		return array_map( 'intval', array_column( $responses['attending']['records'], 'comment_id' ) );
	}

	/**
	 * The rendered form carries the scope it was rendered for on every form.
	 *
	 * `data-wp-context` already carried it, but only the interactivity runtime
	 * reads that, which is exactly the runtime this path exists for the absence
	 * of. Without a posted field the submission arrives with no occurrence
	 * identity at all, and no amount of server-side care downstream can recover
	 * it. Inferring it from `HTTP_REFERER` is refused deliberately, because a
	 * referer is attacker-controlled and routinely stripped.
	 *
	 * A recurring event's form emits the marker in series context too, with the
	 * explicit `series` value, so the handler can tell an intentional
	 * series-wide submission apart from a stale pre-upgrade page or a cached
	 * form that never carried the field. Only a non-recurring event's form
	 * stays byte-identical to its previous markup.
	 *
	 * @covers \GatherPress\Core\Blocks\Rsvp_Form::transform_block_content
	 * @covers \GatherPress\Core\Blocks\Rsvp_Form::occurrence_input
	 *
	 * @return void
	 */
	public function test_the_rendered_form_carries_the_occurrence_it_was_rendered_for(): void {
		$post_id = $this->create_and_project();
		$block   = array(
			'blockName'   => 'gatherpress/rsvp-form',
			'attrs'       => array( 'postId' => $post_id ),
			'innerBlocks' => array(),
		);

		$outside = Rsvp_Form_Block::get_instance()->transform_block_content(
			'<div class="wp-block-gatherpress-rsvp-form"></div>',
			$block
		);

		// The literal wire value is asserted, not a constant, so a change to
		// the marker format fails here rather than passing a tautology.
		$this->assertStringContainsString(
			sprintf(
				'<input type="hidden" name="%s" value="series">',
				Form::RECURRENCE_ID_FIELD
			),
			$outside,
			'Failed to assert a recurring event form outside occurrence context carries the explicit series marker.'
		);

		$plain_id   = $this->create_plain_event();
		$plain_form = Rsvp_Form_Block::get_instance()->transform_block_content(
			'<div class="wp-block-gatherpress-rsvp-form"></div>',
			array(
				'blockName'   => 'gatherpress/rsvp-form',
				'attrs'       => array( 'postId' => $plain_id ),
				'innerBlocks' => array(),
			)
		);

		$this->assertStringNotContainsString(
			Form::RECURRENCE_ID_FIELD,
			$plain_form,
			'Failed to assert a non-recurring event form is byte-identical to its previous markup.'
		);

		Context::get_instance()->set_for_series( $post_id, $this->occurrence_a );

		$inside = Rsvp_Form_Block::get_instance()->transform_block_content(
			'<div class="wp-block-gatherpress-rsvp-form"></div>',
			$block
		);

		Context::get_instance()->clear();

		$this->assertStringContainsString(
			sprintf(
				'<input type="hidden" name="%s" value="%s">',
				Form::RECURRENCE_ID_FIELD,
				$this->occurrence_a
			),
			$inside,
			'Failed to assert the rendered form posts the occurrence it was rendered for.'
		);
	}

	/**
	 * A JavaScript-disabled submission lands on the occurrence it names.
	 *
	 * Only the REST path stamped the occurrence term, so a response made from
	 * an occurrence page with JavaScript unavailable was stored series-wide:
	 * it appeared on every date, and duplicate detection then refused the same
	 * responder on all the others.
	 *
	 * @covers ::preprocess_rsvp_comment
	 * @covers ::handle_rsvp_comment_post
	 * @covers ::posted_occurrence
	 *
	 * @return void
	 */
	public function test_a_classic_submission_lands_on_the_occurrence_it_names(): void {
		$post_id    = $this->create_and_project();
		$comment_id = $this->submit( $post_id, 'ada@example.test', $this->occurrence_a );

		$this->redeem( $comment_id );

		$this->assertSame(
			array( Rsvp_Occurrence::term_slug( $post_id, $this->occurrence_a ) ),
			array_map(
				'strval',
				wp_get_object_terms( $comment_id, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'slugs' ) )
			),
			'Failed to assert the classic submission was stamped with the occurrence it named.'
		);
		$this->assertSame(
			array( $comment_id ),
			$this->roster( $post_id, $this->occurrence_a ),
			'Failed to assert the response is on the roster of the date it was made from.'
		);
		$this->assertSame(
			array(),
			$this->roster( $post_id, $this->occurrence_b ),
			'Failed to assert the response is absent from the roster of every other date.'
		);
	}

	/**
	 * One responder can book two dates through the classic form.
	 *
	 * The consequence a term assertion cannot see. Duplicate detection is
	 * series-wide without an occurrence, so before the fix the second date was
	 * refused with 409 and the visitor was told they had already RSVPd to an
	 * event they had not.
	 *
	 * @covers ::preprocess_rsvp_comment
	 * @covers ::has_duplicate_rsvp
	 * @covers ::duplicate_occurrence_clause
	 *
	 * @return void
	 */
	public function test_the_same_responder_can_book_two_dates_through_the_classic_form(): void {
		$post_id = $this->create_and_project();

		// Core's comment flood check refuses a second comment from the same
		// address within 15 seconds. It is a site-wide anti-spam rate limit
		// rather than an identity rule, and it is deliberately left in place in
		// production: relaxing it on a public unauthenticated endpoint would be
		// a worse trade than asking a visitor to pause between two dates. It is
		// suppressed here, at a priority past the one `check_comment_flood_db()`
		// installs, so the assertion is about occurrence scoping rather than
		// about the clock.
		add_filter( 'wp_is_comment_flood', '__return_false', PHP_INT_MAX );

		$first = $this->submit( $post_id, 'ada@example.test', $this->occurrence_a );

		$this->redeem( $first );

		$second = $this->submit( $post_id, 'ada@example.test', $this->occurrence_b );

		$this->redeem( $second );

		$this->assertNotSame( $first, $second, 'Failed to arrange two distinct responses.' );
		$this->assertSame(
			array( $first ),
			$this->roster( $post_id, $this->occurrence_a ),
			'Failed to assert the first date carries exactly its own response.'
		);
		$this->assertSame(
			array( $second ),
			$this->roster( $post_id, $this->occurrence_b ),
			'Failed to assert the second date carries exactly its own response.'
		);

		remove_filter( 'wp_is_comment_flood', '__return_false', PHP_INT_MAX );
	}

	/**
	 * The same date still refuses a second response from the same responder.
	 *
	 * The other half of the verdict. Scoping the duplicate check to the
	 * occurrence must not disable it, or one responder could fill a date.
	 *
	 * @covers ::preprocess_rsvp_comment
	 * @covers ::has_duplicate_rsvp
	 *
	 * @return void
	 */
	public function test_the_same_date_still_refuses_a_duplicate_responder(): void {
		$post_id = $this->create_and_project();

		$this->redeem( $this->submit( $post_id, 'ada@example.test', $this->occurrence_a ) );

		$this->expectException( WPDieException::class );

		$this->submit( $post_id, 'ada@example.test', $this->occurrence_a );
	}

	/**
	 * A fabricated occurrence is refused rather than silently widened.
	 *
	 * The hidden field is user-controllable, so it is validated against the
	 * event's own series exactly as the REST argument is. Silently treating an
	 * unresolvable value as the series is the failure this subsystem exists to
	 * avoid: the visitor believes they booked one date, the response lands on
	 * all of them, and nothing afterwards can tell that apart from a deliberate
	 * series RSVP.
	 *
	 * @covers ::posted_occurrence
	 *
	 * @return void
	 */
	public function test_a_fabricated_occurrence_writes_nothing(): void {
		$post_id = $this->create_and_project();
		$before  = get_comments(
			array(
				'post_id' => $post_id,
				'type'    => Rsvp::COMMENT_TYPE,
				'count'   => true,
				'status'  => 'all',
			)
		);
		$died    = false;

		try {
			$this->submit( $post_id, 'ada@example.test', self::UNKNOWN_OCCURRENCE );
		} catch ( WPDieException $e ) {
			$died = true;
		}

		$this->assertTrue( $died, 'Failed to assert a fabricated occurrence is refused.' );
		$this->assertSame(
			$before,
			get_comments(
				array(
					'post_id' => $post_id,
					'type'    => Rsvp::COMMENT_TYPE,
					'count'   => true,
					'status'  => 'all',
				)
			),
			'Failed to assert a refused classic submission wrote nothing at all.'
		);
	}

	/**
	 * An occurrence owned by an unviewable sibling is refused, not written.
	 *
	 * The REST twin re-checks the resolved owner's viewability before writing,
	 * and calls that re-check a security property: without it, naming a
	 * viewable sibling wrote an RSVP onto a private owner nobody authorized.
	 * The classic path follows the identical resolve-then-use sequence, so it
	 * must apply the identical rule. `wp-comments-post.php` evaluates
	 * `comment_status`, `post_status`, `is_status_viewable()` and
	 * `post_password_required()` against the named post only, and none of the
	 * checks that run after the rewrite is a viewability check.
	 *
	 * The refusal must be byte-identical to the one a fabricated identifier
	 * receives, or the difference tells an unauthenticated visitor which
	 * occurrences of the unviewable owner exist, one candidate at a time.
	 *
	 * @covers ::preprocess_rsvp_comment
	 * @covers ::posted_occurrence
	 *
	 * @return void
	 */
	public function test_a_classic_submission_cannot_land_on_an_unviewable_owner(): void {
		$owner_post_id = $this->create_and_project();
		$named_post_id = $this->create_plain_event();

		$this->widen_series( $named_post_id, $owner_post_id );

		wp_update_post(
			array(
				'ID'          => $owner_post_id,
				'post_status' => 'draft',
			)
		);

		Context::flush_resolved();

		// The reference refusal: a fabricated identifier on the same fixture.
		$reference = null;

		try {
			$this->submit( $named_post_id, 'ada@example.test', self::UNKNOWN_OCCURRENCE );
		} catch ( WPDieException $e ) {
			$reference = array( $e->getMessage(), $e->getCode() );
		}

		$this->assertNotNull( $reference, 'Failed to arrange the fabricated-identifier reference refusal.' );

		$refusal = null;

		try {
			$this->submit( $named_post_id, 'grace@example.test', $this->occurrence_a );
		} catch ( WPDieException $e ) {
			$refusal = array( $e->getMessage(), $e->getCode() );
		}

		// The count is asserted first so a regression reports the actual harm:
		// the RSVP landed on the draft owner.
		$this->assertSame(
			0,
			$this->stored_rsvp_count( $owner_post_id ),
			'Failed to assert the refused submission wrote nothing onto the draft owner.'
		);
		$this->assertSame(
			0,
			$this->stored_rsvp_count( $named_post_id ),
			'Failed to assert the refused submission wrote nothing onto the named post either.'
		);
		$this->assertNotNull(
			$refusal,
			'Failed to assert a submission naming a draft owner\'s occurrence is refused.'
		);
		$this->assertSame(
			$reference,
			$refusal,
			'Failed to assert the draft-owner refusal is byte-identical to the fabricated-identifier one.'
		);
	}

	/**
	 * The same widened submission still lands when the owner is viewable.
	 *
	 * The other half of the verdict, with a discriminating value: the stored
	 * comment belongs to the owner, which is not the post the submission named,
	 * so a guard that refused every cross-post owner, or a rewrite that stopped
	 * happening at all, fails here rather than passing vacuously.
	 *
	 * @covers ::preprocess_rsvp_comment
	 * @covers ::posted_occurrence
	 *
	 * @return void
	 */
	public function test_a_classic_submission_still_lands_on_a_viewable_sibling_owner(): void {
		$owner_post_id = $this->create_and_project();
		$named_post_id = $this->create_plain_event();

		$this->widen_series( $named_post_id, $owner_post_id );

		$comment_id = $this->submit( $named_post_id, 'ada@example.test', $this->occurrence_a );

		$this->redeem( $comment_id );

		$this->assertSame(
			$owner_post_id,
			(int) get_comment( $comment_id )->comment_post_ID,
			'Failed to assert the submission landed on the post that owns the occurrence.'
		);
		$this->assertSame(
			array( Rsvp_Occurrence::term_slug( $owner_post_id, $this->occurrence_a ) ),
			array_map(
				'strval',
				wp_get_object_terms( $comment_id, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'slugs' ) )
			),
			'Failed to assert the stored response is stamped with the owner\'s occurrence.'
		);
		$this->assertSame(
			array( $comment_id ),
			$this->roster( $owner_post_id, $this->occurrence_a ),
			'Failed to assert the response is on the owner occurrence\'s roster.'
		);
	}

	/**
	 * A password-protected published owner still accepts through a sibling.
	 *
	 * This pins the deliberate boundary of the owner guard: viewability only,
	 * which is exactly what the REST twin re-checks, so the two paths keep one
	 * contract. `comments_open()` is not re-run against the owner because this
	 * path force-opens comments for RSVP submissions and the real write gates,
	 * `Rsvp::is_enabled()` and `allows_open_rsvp()`, already run against the
	 * owner after the rewrite. `post_password_required()` is not re-run either:
	 * a password gates reading the post's content, the REST twin accepts the
	 * same submission without it, and core's own password check keeps running
	 * against the named post exactly as it always did.
	 *
	 * @covers ::preprocess_rsvp_comment
	 * @covers ::posted_occurrence
	 *
	 * @return void
	 */
	public function test_a_password_protected_owner_still_accepts_through_a_sibling(): void {
		$owner_post_id = $this->create_and_project();
		$named_post_id = $this->create_plain_event();

		$this->widen_series( $named_post_id, $owner_post_id );

		wp_update_post(
			array(
				'ID'            => $owner_post_id,
				'post_password' => 'opensesame',
			)
		);

		Context::flush_resolved();

		$comment_id = $this->submit( $named_post_id, 'ada@example.test', $this->occurrence_a );

		$this->assertSame(
			$owner_post_id,
			(int) get_comment( $comment_id )->comment_post_ID,
			'Failed to assert a password-protected published owner still accepts the submission.'
		);
	}

	/**
	 * A classic submission invalidates the counts it just changed.
	 *
	 * The third consequence, and the least obvious one. This path never
	 * reaches `Rsvp\Storage::save()` or the REST route's
	 * `handle_rsvp_creation()`, which is where every other write invalidates, so
	 * `Cache::` appeared nowhere on it at all. Both keys are asserted because
	 * both are wrong after the write: the response changes the occurrence's
	 * counts and the series' own.
	 *
	 * @covers ::handle_rsvp_comment_post
	 * @covers \GatherPress\Core\Rsvp\Cache::delete
	 *
	 * @return void
	 */
	public function test_a_classic_submission_invalidates_both_cache_keys(): void {
		$post_id = $this->create_and_project();

		Cache::set( $post_id, array( 'all' => array( 'count' => 99 ) ) );
		Cache::set( $post_id, array( 'all' => array( 'count' => 42 ) ), $this->occurrence_a );

		// Both keys must be warm and distinct before the write, or "both were
		// invalidated" is satisfied by there having been nothing to invalidate.
		$this->assertSame(
			array( 'all' => array( 'count' => 99 ) ),
			Cache::get( $post_id ),
			'Failed to warm the series-wide cache entry the submission must invalidate.'
		);
		$this->assertSame(
			array( 'all' => array( 'count' => 42 ) ),
			Cache::get( $post_id, $this->occurrence_a ),
			'Failed to warm the occurrence cache entry the submission must invalidate.'
		);

		$this->submit( $post_id, 'ada@example.test', $this->occurrence_a );

		$this->assertNotSame(
			array( 'all' => array( 'count' => 42 ) ),
			Cache::get( $post_id, $this->occurrence_a ),
			'Failed to assert a classic submission invalidated the occurrence-scoped cache key.'
		);
		$this->assertNotSame(
			array( 'all' => array( 'count' => 99 ) ),
			Cache::get( $post_id ),
			'Failed to assert a classic submission invalidated the series-wide cache key.'
		);
	}

	/**
	 * A submission naming no occurrence behaves exactly as it always did.
	 *
	 * The parity half. A non-recurring event renders no hidden field, so the
	 * response is stored series-wide with no occurrence term and the duplicate
	 * check stays series-wide too.
	 *
	 * @covers ::preprocess_rsvp_comment
	 * @covers ::handle_rsvp_comment_post
	 *
	 * @return void
	 */
	public function test_a_submission_naming_no_occurrence_is_unchanged(): void {
		$post_id = $this->create_plain_event();

		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();

		$comment_id = $this->submit( $post_id, 'ada@example.test', null );

		$this->redeem( $comment_id );

		$this->assertSame(
			array(),
			array_map(
				'strval',
				wp_get_object_terms( $comment_id, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'slugs' ) )
			),
			'Failed to assert a submission naming no occurrence carries no occurrence term.'
		);
		$this->assertSame(
			array( $comment_id ),
			$this->roster( $post_id, null ),
			'Failed to assert the response is readable series-wide, which is what series-wide names.'
		);
	}

	/**
	 * A recurring event refuses a submission missing the scope marker.
	 *
	 * Every form this branch renders for a recurring event carries the marker,
	 * as an occurrence identifier or as the explicit `series` value, so a
	 * submission without it can only come from markup rendered before the
	 * upgrade: a reverse proxy or page cache holding old occurrence-page HTML,
	 * posted with JavaScript unavailable. Accepting it would silently write a
	 * series-wide RSVP the visitor believes is for one date, indistinguishable
	 * afterwards from an intentional series RSVP. The refusal asks for a
	 * reload, which re-renders the form with the marker.
	 *
	 * @covers ::posted_occurrence
	 * @covers ::preprocess_rsvp_comment
	 *
	 * @return void
	 */
	public function test_a_recurring_event_refuses_a_submission_missing_the_scope_marker(): void {
		$post_id = $this->create_and_project();
		$before  = get_comments(
			array(
				'post_id' => $post_id,
				'type'    => Rsvp::COMMENT_TYPE,
				'count'   => true,
				'status'  => 'all',
			)
		);
		$died    = false;

		try {
			$this->submit( $post_id, 'ada@example.test', null );
		} catch ( WPDieException $e ) {
			$died = true;
		}

		$this->assertTrue(
			$died,
			'Failed to assert a recurring event refuses a submission missing the scope marker.'
		);
		$this->assertSame(
			$before,
			get_comments(
				array(
					'post_id' => $post_id,
					'type'    => Rsvp::COMMENT_TYPE,
					'count'   => true,
					'status'  => 'all',
				)
			),
			'Failed to assert the refused marker-less submission wrote nothing at all.'
		);
	}

	/**
	 * An explicit series marker writes an intentional series-wide RSVP.
	 *
	 * The value a recurring event's form posts outside occurrence context. It
	 * is accepted as a deliberate series-wide submission: no occurrence term is
	 * written, the response reads series-wide, and it appears on no single
	 * date's roster.
	 *
	 * @covers ::posted_occurrence
	 * @covers ::preprocess_rsvp_comment
	 * @covers ::handle_rsvp_comment_post
	 *
	 * @return void
	 */
	public function test_an_explicit_series_marker_writes_a_series_wide_rsvp(): void {
		$post_id    = $this->create_and_project();
		$comment_id = 0;

		try {
			$comment_id = $this->submit( $post_id, 'ada@example.test', 'series' );
		} catch ( WPDieException $e ) {
			$this->fail( 'Failed to assert the explicit series marker is accepted; refused with: ' . $e->getMessage() );
		}

		$this->redeem( $comment_id );

		$this->assertSame(
			array(),
			array_map(
				'strval',
				wp_get_object_terms( $comment_id, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'slugs' ) )
			),
			'Failed to assert an explicit series submission carries no occurrence term.'
		);
		$this->assertSame(
			array( $comment_id ),
			$this->roster( $post_id, null ),
			'Failed to assert an explicit series submission is readable series-wide.'
		);
		$this->assertSame(
			array(),
			$this->roster( $post_id, $this->occurrence_a ),
			'Failed to assert an explicit series submission is absent from a single date\'s roster.'
		);
	}

	/**
	 * A non-recurring event still accepts a marker-less submission on a recurring site.
	 *
	 * The refusal is gated per event, not per site: a non-recurring event never
	 * renders the marker, so its submissions legitimately arrive without one,
	 * and refusing them would break every ordinary event the moment any other
	 * event on the site became recurring.
	 *
	 * @covers ::posted_occurrence
	 * @covers ::preprocess_rsvp_comment
	 *
	 * @return void
	 */
	public function test_a_non_recurring_event_accepts_a_marker_less_submission_on_a_recurring_site(): void {
		$this->create_and_project();

		$post_id    = $this->create_plain_event();
		$comment_id = $this->submit( $post_id, 'ada@example.test', null );

		$this->redeem( $comment_id );

		$this->assertSame(
			array(),
			array_map(
				'strval',
				wp_get_object_terms( $comment_id, Rsvp_Occurrence::TAXONOMY, array( 'fields' => 'slugs' ) )
			),
			'Failed to assert the non-recurring event\'s response carries no occurrence term.'
		);
		$this->assertSame(
			array( $comment_id ),
			$this->roster( $post_id, null ),
			'Failed to assert the non-recurring event still accepts its ordinary marker-less submission.'
		);
	}

	/**
	 * Direct invokes of the classic path's private helpers.
	 *
	 * Xdebug does not reliably trace a private method reached only from a short
	 * delegation in the same class, so each arm is driven directly as well as
	 * end to end. The `wp_die` arm of `posted_occurrence()` is covered by the
	 * fabricated-occurrence test above; the arms here are the two it cannot
	 * reach.
	 *
	 * @covers ::posted_occurrence
	 * @covers ::leave_posted_occurrence
	 * @covers ::bypass_core_duplicate_check
	 *
	 * @return void
	 */
	public function test_the_classic_path_helpers_cover_every_arm(): void {
		$post_id  = $this->create_and_project();
		$plain_id = $this->create_plain_event();
		$instance = Form::get_instance();

		$this->posted = array();

		$refused = false;

		try {
			Utility::invoke_hidden_method( $instance, 'posted_occurrence', array( $post_id ) );
		} catch ( WPDieException $e ) {
			$refused = true;
		}

		$this->assertTrue(
			$refused,
			'Failed to assert the resolver refuses a marker-less submission to a recurring event.'
		);
		$this->assertNull(
			Utility::invoke_hidden_method( $instance, 'posted_occurrence', array( $plain_id ) ),
			'Failed to assert a marker-less submission to a non-recurring event resolves to none.'
		);

		$this->posted = array( Form::RECURRENCE_ID_FIELD => 'series' );

		try {
			$this->assertNull(
				Utility::invoke_hidden_method( $instance, 'posted_occurrence', array( $post_id ) ),
				'Failed to assert the explicit series marker resolves to none rather than to an occurrence.'
			);
		} catch ( WPDieException $e ) {
			$this->fail( 'Failed to assert the explicit series marker is accepted; refused with: ' . $e->getMessage() );
		}

		$this->posted = array( Form::RECURRENCE_ID_FIELD => $this->occurrence_a );

		$identity = Utility::invoke_hidden_method( $instance, 'posted_occurrence', array( $post_id ) );

		$this->assertNotNull( $identity, 'Failed to assert a posted occurrence resolves.' );
		$this->assertSame(
			$this->occurrence_a,
			$identity->recurrence_id,
			'Failed to assert the posted occurrence is the one that resolves.'
		);

		// Nothing entered context, so the restore is a no-op rather than a clear.
		Context::get_instance()->set_for_series( $post_id, $this->occurrence_b );

		Utility::invoke_hidden_method( $instance, 'leave_posted_occurrence', array() );

		$this->assertNotNull(
			Context::get_instance()->current(),
			'Failed to assert a teardown with nothing to leave does not clear somebody else\'s context.'
		);

		Context::get_instance()->clear();

		$this->assertSame(
			0,
			$instance->bypass_core_duplicate_check( 42, array( 'comment_type' => Rsvp::COMMENT_TYPE ) ),
			'Failed to assert core\'s content-identity duplicate match is waived for an RSVP.'
		);
		$this->assertSame(
			42,
			$instance->bypass_core_duplicate_check( 42, array( 'comment_type' => 'comment' ) ),
			'Failed to assert an ordinary comment keeps core\'s duplicate protection.'
		);
	}

	/**
	 * The teardown restores the context a submission displaced.
	 *
	 * Driven through a real submission rather than through the helper, because
	 * the property the helper reads is set on the other side of the request.
	 *
	 * @covers ::leave_posted_occurrence
	 *
	 * @return void
	 */
	public function test_a_submission_leaves_no_occurrence_context_behind(): void {
		$post_id = $this->create_and_project();

		$this->submit( $post_id, 'ada@example.test', $this->occurrence_a );

		$this->assertNull(
			Context::get_instance()->current(),
			'Failed to assert the submission left no occurrence context standing.'
		);
	}
}
