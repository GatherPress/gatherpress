<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Occurrence_Identity.
 *
 * The class is the shared resolve-authorize-use seam, so the tests that matter
 * are the ones about what it refuses: a well-formed identifier naming no row,
 * an identifier belonging to another series, and a comparison where one side is
 * absent. Its consumers are driven end to end elsewhere; this file pins the
 * primitive's own branches so a later caller cannot silently widen them.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Context;
use GatherPress\Core\Event\Recurrence\Occurrence_Identity;
use GatherPress\Core\Event\Recurrence\Query as Recurrence_Query;
use GatherPress\Core\Event\Recurrence\Rsvp_Occurrence;
use GatherPress\Core\Rsvp\Rsvp;
use GatherPress\Core\Rsvp\Setup as Rsvp_Setup;
use GatherPress\Tests\Base;
use ReflectionClass;

/**
 * Class Test_Occurrence_Identity.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Occurrence_Identity
 */
class Test_Occurrence_Identity extends Base {

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
	 * Ensure the occurrence table and RSVP taxonomies exist, with no context leaking in.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
		Rsvp_Setup::get_instance()->register_taxonomy();
		Context::get_instance()->clear();
		Context::flush_resolved();
	}

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
	 * Create the reference recurring series and project it.
	 *
	 * @since 0.36.0
	 *
	 * @return int The projected series post ID.
	 */
	protected function project_series(): int {
		$post_id = $this->create_recurring_event( self::WEEKLY_RULE );

		\GatherPress\Core\Event\Recurrence\Meta::get_instance()->set_recurrence( $post_id );
		\GatherPress\Core\Event\Recurrence\Occurrences::get_instance()->project( $post_id );

		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();

		return $post_id;
	}

	/**
	 * Only a canonical `Ymd\THis` string is accepted, and syntax is all that is checked.
	 *
	 * Both arms matter. The true arm is what lets a REST argument validator run
	 * before authorization at all; the false arm is what keeps a malformed
	 * string refusable from the string alone.
	 *
	 * @covers ::is_canonical
	 *
	 * @return void
	 */
	public function test_is_canonical_accepts_only_the_canonical_shape(): void {
		$this->assertTrue(
			Occurrence_Identity::is_canonical( '20260903T180000' ),
			'Failed to assert a canonical identifier is accepted.'
		);

		// The newline case is the PCRE trap: `$` matches before a final newline,
		// so a `$`-anchored pattern accepts a value the docblock's "and nothing
		// else" contract refuses. `Form::posted_occurrence()` reads the posted
		// field with no sanitizer, so the unsanitized value reaches this gate.
		$bad_identifiers = array(
			'',
			'not-a-recurrence-id',
			'20260903180000',
			'20260903T1800',
			'20260903t180000 ',
			"20260903T180000\n",
		);

		foreach ( $bad_identifiers as $bad ) {
			$this->assertFalse(
				Occurrence_Identity::is_canonical( $bad ),
				sprintf( 'Failed to assert "%s" is refused as a non-canonical identifier.', $bad )
			);
		}
	}

	/**
	 * A malformed identifier never reaches storage.
	 *
	 * The guard is not decorative: without it a fabricated string would cost a
	 * query per call on every route that resolves one.
	 *
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_resolve_refuses_a_malformed_identifier_without_a_query(): void {
		global $wpdb;

		$post_id = $this->project_series();
		$before  = count( $wpdb->queries );

		$this->assertNull(
			Occurrence_Identity::resolve( $post_id, 'not-a-recurrence-id' ),
			'Failed to assert a malformed identifier resolves to nothing.'
		);
		$this->assertSame(
			$before,
			count( $wpdb->queries ),
			'Failed to assert a malformed identifier is refused without touching storage.'
		);
	}

	/**
	 * A well-formed identifier naming no row of this series resolves to nothing.
	 *
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_resolve_refuses_an_identifier_that_names_no_row(): void {
		$post_id = $this->project_series();

		$this->assertNull(
			Occurrence_Identity::resolve( $post_id, self::UNKNOWN_OCCURRENCE ),
			'Failed to assert an unknown occurrence resolves to nothing.'
		);
	}

	/**
	 * A resolved identity names the post that owns the occurrence row.
	 *
	 * @covers ::resolve
	 * @covers ::term_slug
	 * @covers ::to_array
	 *
	 * @return void
	 */
	public function test_resolve_names_the_owning_post(): void {
		$post_id       = $this->project_series();
		$recurrence_id = $this->expected_weekly_set()[0]['recurrence_id'];
		$identity      = Occurrence_Identity::resolve( $post_id, $recurrence_id );

		$this->assertNotNull( $identity, 'Failed to resolve a real occurrence of the series.' );
		$this->assertSame(
			$post_id,
			$identity->owner_post_id,
			'Failed to assert the identity names the post that owns the occurrence row.'
		);
		$this->assertSame(
			$recurrence_id,
			$identity->recurrence_id,
			'Failed to assert the identity carries the canonical recurrence identifier.'
		);
		$this->assertSame(
			Rsvp_Occurrence::term_slug( $post_id, $recurrence_id ),
			$identity->term_slug(),
			'Failed to assert the identity composes the occurrence term slug.'
		);
		$this->assertSame(
			array(
				'series_post_id' => $post_id,
				'recurrence_id'  => $recurrence_id,
			),
			$identity->to_array(),
			'Failed to assert the identity expresses itself in the composite-key array shape.'
		);
	}

	/**
	 * A stored RSVP resolves to the occurrence its own term names, or to nothing.
	 *
	 * @covers ::for_comment
	 * @covers ::current
	 *
	 * @return void
	 */
	public function test_for_comment_and_current_read_the_occurrence_in_play(): void {
		$post_id       = $this->project_series();
		$recurrence_id = $this->expected_weekly_set()[0]['recurrence_id'];

		$this->assertNull(
			Occurrence_Identity::current( $post_id ),
			'Failed to assert no identity is in play outside occurrence context.'
		);

		Context::get_instance()->set_for_series( $post_id, $recurrence_id );

		$current = Occurrence_Identity::current( $post_id );

		$this->assertNotNull( $current, 'Failed to assert the entered occurrence is the identity in play.' );
		$this->assertSame(
			$recurrence_id,
			$current->recurrence_id,
			'Failed to assert the ambient identity is the occurrence context was entered for.'
		);

		$user_id = $this->factory->user->create();
		$rsvp    = new Rsvp( $post_id );

		$rsvp->save( $user_id, 'attending' );

		$stored = $rsvp->find( $user_id );

		Context::get_instance()->clear();

		$this->assertNotNull( $stored, 'Failed to store an RSVP to read an identity back from.' );

		$identity = Occurrence_Identity::for_comment( (int) $stored->comment->comment_ID );

		$this->assertNotNull( $identity, 'Failed to assert a stamped RSVP carries an identity.' );
		$this->assertSame(
			$post_id,
			$identity->owner_post_id,
			'Failed to assert the stored identity names the occurrence owner.'
		);
		$this->assertSame(
			$recurrence_id,
			$identity->recurrence_id,
			'Failed to assert the stored identity names the occurrence that was booked.'
		);

		$series_wide = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$this->assertNull(
			Occurrence_Identity::for_comment( (int) $series_wide ),
			'Failed to assert an RSVP carrying no occurrence term resolves to no identity.'
		);
	}

	/**
	 * Absence is a value, not a wildcard.
	 *
	 * All four combinations are asserted because the authorization rule is
	 * symmetric: a credential for one occurrence must not act series-wide, and
	 * a series-wide credential must not act on an occurrence.
	 *
	 * @covers ::matches
	 *
	 * @return void
	 */
	public function test_matches_treats_absence_as_a_value(): void {
		$post_id = $this->project_series();
		$set     = $this->expected_weekly_set();
		$first   = Occurrence_Identity::resolve( $post_id, $set[0]['recurrence_id'] );
		$second  = Occurrence_Identity::resolve( $post_id, $set[1]['recurrence_id'] );

		$this->assertNotNull( $first, 'Failed to resolve the first fixture occurrence.' );
		$this->assertNotNull( $second, 'Failed to resolve the second fixture occurrence.' );

		$this->assertTrue(
			Occurrence_Identity::matches( null, null ),
			'Failed to assert two series-wide identities match.'
		);
		$this->assertTrue(
			Occurrence_Identity::matches( $first, $first ),
			'Failed to assert an identity matches itself.'
		);
		$this->assertFalse(
			Occurrence_Identity::matches( $first, null ),
			'Failed to assert an occurrence identity does not match a series-wide request.'
		);
		$this->assertFalse(
			Occurrence_Identity::matches( null, $first ),
			'Failed to assert a series-wide identity does not match an occurrence request.'
		);
		$this->assertFalse(
			Occurrence_Identity::matches( $first, $second ),
			'Failed to assert two different occurrences of one series do not match.'
		);
	}

	/**
	 * The same identifier on two different series is two different identities.
	 *
	 * The composite is the identity. Comparing recurrence identifiers
	 * alone would let a credential for one series act on another that happens to
	 * meet at the same moment.
	 *
	 * @covers ::matches
	 * @covers ::resolve
	 *
	 * @return void
	 */
	public function test_matches_distinguishes_the_same_moment_on_two_series(): void {
		$first_post_id  = $this->project_series();
		$second_post_id = $this->project_series();
		$recurrence_id  = $this->expected_weekly_set()[0]['recurrence_id'];

		$first  = Occurrence_Identity::resolve( $first_post_id, $recurrence_id );
		$second = Occurrence_Identity::resolve( $second_post_id, $recurrence_id );

		$this->assertNotNull( $first, 'Failed to resolve the occurrence on the first series.' );
		$this->assertNotNull( $second, 'Failed to resolve the occurrence on the second series.' );
		$this->assertSame(
			$first->recurrence_id,
			$second->recurrence_id,
			'Failed to arrange two series meeting at the same moment.'
		);
		$this->assertFalse(
			Occurrence_Identity::matches( $first, $second ),
			'Failed to assert the same moment on two series is two identities.'
		);
	}

	/**
	 * On a site with no recurring events nothing resolves and nothing is queried.
	 *
	 * The seam is reached from the RSVP write paths, so it has to be
	 * free where recurrence is not in use.
	 *
	 * @covers ::resolve
	 * @covers ::current
	 * @covers ::for_comment
	 *
	 * @return void
	 */
	public function test_nothing_resolves_on_a_site_with_no_recurring_events(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => Event::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		Recurrence_Query::refresh_has_recurring_events();
		Context::flush_resolved();

		$comment_id = $this->factory->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => Rsvp::COMMENT_TYPE,
			)
		);

		$this->assertNull(
			Occurrence_Identity::resolve( $post_id, '20260903T180000' ),
			'Failed to assert nothing resolves on a site with no recurring events.'
		);
		$this->assertNull(
			Occurrence_Identity::current( $post_id ),
			'Failed to assert no identity is in play on a site with no recurring events.'
		);
		$this->assertNull(
			Occurrence_Identity::for_comment( (int) $comment_id ),
			'Failed to assert a comment carries no identity on a site with no recurring events.'
		);
	}

	/**
	 * The constructor is private, and every identity comes from a resolver.
	 *
	 * A hand-built pair would be an owner nobody verified, which is the defect
	 * the class exists to remove, so the constructor is unreachable from
	 * outside. The direct invoke is also what gives its body coverage: xdebug
	 * does not reliably trace a private constructor reached only from `new
	 * self()` inside the same class.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test_the_constructor_is_private_and_assigns_the_composite(): void {
		$reflection = new ReflectionClass( Occurrence_Identity::class );

		$this->assertTrue(
			$reflection->getConstructor()->isPrivate(),
			'Failed to assert an identity cannot be hand-built from outside the class.'
		);

		$constructor = $reflection->getConstructor();

		$constructor->setAccessible( true );

		$identity = $reflection->newInstanceWithoutConstructor();

		$constructor->invoke( $identity, 42, '20260903T180000' );

		$this->assertSame( 42, $identity->owner_post_id, 'Failed to assert the owner is assigned.' );
		$this->assertSame(
			'20260903T180000',
			$identity->recurrence_id,
			'Failed to assert the recurrence identifier is assigned.'
		);
	}
}
