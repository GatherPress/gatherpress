<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Recurrence\Meta.
 *
 * @package GatherPress\Core\Event\Recurrence
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Event\Recurrence;

use GatherPress\Core\Event;
use GatherPress\Core\Event\Recurrence\Meta;
use GatherPress\Core\Event\Recurrence\Occurrences;
use GatherPress\Core\Event\Recurrence\Query;
use GatherPress\Core\Event\Recurrence\Rule;
use GatherPress\Core\Utility;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility as PMC_Utility;
use stdClass;
use WP_REST_Request;

/**
 * Class Test_Meta.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Recurrence\Meta
 */
class Test_Meta extends Base {

	use Occurrence_Fixtures;

	/**
	 * Start every test from an occurrence table that holds nothing, and leave
	 * it that way afterwards.
	 *
	 * The timezone-transition tests assert on projected rows, not only on the
	 * derived mirrors, so the table has to exist, and the bootstrap creates it.
	 * It also has to be empty in both directions. Occurrence rows that outlive
	 * the test that projected them, while post IDs restart from the rollback,
	 * let a later test's brand-new post inherit an earlier series' ID and
	 * resolve occurrences it never had.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		gatherpress_reset_custom_tables();
		$this->clear_occurrences();
	}

	/**
	 * Leave no projected occurrence row behind for a later test class.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->clear_occurrences();

		parent::tearDown();
	}

	/**
	 * Empty the occurrence table.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function clear_occurrences(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare( 'DELETE FROM %i', sprintf( Occurrences::TABLE_FORMAT, $wpdb->prefix ) )
		);
	}

	/**
	 * Coverage for `__construct` and `setup_hooks`.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Meta::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'registered_post_type',
				'priority' => 11,
				'callback' => array( $instance, 'register' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'wp_after_insert_post',
				'priority' => 10,
				'callback' => array( $instance, 'set_recurrence' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_queue_reconciliation' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'added_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_revalidate_for_datetime' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_queue_reconciliation' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'updated_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_revalidate_for_datetime' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_queue_reconciliation' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'deleted_post_meta',
				'priority' => 10,
				'callback' => array( $instance, 'maybe_revalidate_for_datetime' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * `register()` registers the writable blob plus the ten read-only mirrors
	 * for a post type declaring `gatherpress-event-date` support, and wires
	 * the REST readonly-strip filter.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_on_event_date_supporting_post_type(): void {
		$instance = Meta::get_instance();

		$instance->register( Event::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertArrayHasKey( 'gatherpress_recurrence', $meta );
		$this->assertTrue( $meta['gatherpress_recurrence']['show_in_rest'] );

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertArrayHasKey( $derived_key, $meta, "Expected {$derived_key} to be registered." );
			$this->assertTrue( $meta[ $derived_key ]['show_in_rest'] );
		}

		$this->assertNotFalse(
			has_filter(
				sprintf( 'rest_pre_insert_%s', Event::POST_TYPE ),
				array( $instance, 'filter_readonly_meta' )
			)
		);
	}

	/**
	 * `register()` is a no-op for a post type without `gatherpress-event-date`
	 * support: no meta registers, and no REST filter wires.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_register_skips_unsupported_post_type(): void {
		$instance = Meta::get_instance();

		$instance->register( 'post' );

		$meta = get_registered_meta_keys( 'post', 'post' );

		$this->assertArrayNotHasKey( 'gatherpress_recurrence', $meta );
		$this->assertFalse(
			has_filter( 'rest_pre_insert_post', array( $instance, 'filter_readonly_meta' ) )
		);
	}

	/**
	 * The writable `gatherpress_recurrence` key registers with
	 * `Utility::can_edit_post_meta`; each of the ten derived mirrors registers
	 * with `__return_false`, so the writable/read-only split matches the
	 * documented shape.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_writable_and_readonly_meta_split(): void {
		$instance = Meta::get_instance();
		$instance->register( Event::POST_TYPE );

		$meta = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertSame(
			array( Utility::class, 'can_edit_post_meta' ),
			$meta['gatherpress_recurrence']['auth_callback']
		);

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'__return_false',
				$meta[ $derived_key ]['auth_callback'],
				"Expected {$derived_key} to be registered read-only."
			);
		}
	}

	/**
	 * The five numeric mirrors register with `type => integer`, so REST reads
	 * back a JSON number rather than a string. That is the whole justification
	 * for decomposing into individually typed mirrors in the first place.
	 *
	 * @covers ::register
	 *
	 * @return void
	 */
	public function test_numeric_mirrors_register_with_integer_type(): void {
		$instance = Meta::get_instance();
		$instance->register( Event::POST_TYPE );

		$meta            = get_registered_meta_keys( 'post', Event::POST_TYPE );
		$integer_mirrors = array(
			'gatherpress_recurrence_interval',
			'gatherpress_recurrence_monthly_day',
			'gatherpress_recurrence_monthly_ordinal',
			'gatherpress_recurrence_monthly_weekday',
			'gatherpress_recurrence_count',
		);

		foreach ( $integer_mirrors as $meta_key ) {
			$this->assertSame( 'integer', $meta[ $meta_key ]['type'], "Expected {$meta_key} to register as integer." );
		}

		$string_mirrors = array_diff( Meta::DERIVED_META_KEYS, $integer_mirrors );

		foreach ( $string_mirrors as $meta_key ) {
			$this->assertSame( 'string', $meta[ $meta_key ]['type'], "Expected {$meta_key} to register as string." );
		}
	}

	/**
	 * `set_recurrence()` is a no-op on a post type without
	 * `gatherpress-event-date` support.
	 *
	 * @covers ::set_recurrence
	 *
	 * @return void
	 */
	public function test_set_recurrence_skips_unsupported_post_type(): void {
		$post_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		add_post_meta( $post_id, 'gatherpress_recurrence', wp_json_encode( array( 'frequency' => 'daily' ) ) );

		Meta::get_instance()->set_recurrence( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ) );
	}

	/**
	 * `set_recurrence()` does not write mirrors immediately when the post
	 * carries no recurrence blob yet. It defers the decision to `shutdown`
	 * rather than treating "not written yet" the same as "removed", since
	 * `wp_after_insert_post` can fire before a REST/editor/duplicate caller's
	 * separate `add_post_meta()` call for the blob has landed.
	 *
	 * @covers ::set_recurrence
	 *
	 * @return void
	 */
	public function test_set_recurrence_defers_when_no_blob_yet(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$instance = Meta::get_instance();

		$instance->set_recurrence( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ) );
		$this->assertNotFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_recurrence' ) ),
			'A shutdown resolution should be scheduled for the post with no blob yet.'
		);
	}

	/**
	 * The deferred `wp_after_insert_post` → `shutdown` path resolves correctly
	 * when the blob arrives after `set_recurrence()` ran but before shutdown.
	 * That is the exact ordering a first publish produces when this class's hook
	 * happens to run before the blob-writing caller. The fix is robust to hook
	 * order because it never decides from a mid-request read, only from the
	 * state at shutdown.
	 *
	 * @covers ::set_recurrence
	 * @covers ::resolve_pending_recurrence
	 * @covers ::write_recurrence
	 * @covers ::write_mirrors
	 *
	 * @return void
	 */
	public function test_deferred_first_publish_resolves_into_a_recurring_event(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$instance = Meta::get_instance();

		// wp_after_insert_post fires before the blob-writing caller runs.
		$instance->set_recurrence( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ) );

		// The blob (and the datetime blob its timezone is read from) land
		// afterward, in the same request, exactly as REST/editor writes do.
		add_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => '2026-09-03 18:00:00',
					'dateTimeEnd'   => '2026-09-03 20:00:00',
					'timezone'      => 'America/New_York',
				)
			)
		);
		add_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'count',
					'count'     => 3,
				)
			)
		);

		// Simulates the shutdown hook firing.
		$instance->resolve_pending_recurrence();

		$this->assertSame( 'daily', get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ) );
		$this->assertInstanceOf( Rule::class, Rule::from_post( $post_id ) );
	}

	/**
	 * `resolve_pending_recurrence()` clears any (necessarily stale) mirrors
	 * when the blob is still empty at shutdown, which is a genuine removal
	 * rather than a late arrival.
	 *
	 * @covers ::resolve_pending_recurrence
	 * @covers ::clear_mirrors
	 *
	 * @return void
	 */
	public function test_resolve_pending_recurrence_clears_mirrors_when_blob_never_arrives(): void {
		$post_id  = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);
		$instance = Meta::get_instance();

		$instance->set_recurrence( $post_id );
		$this->assertSame( 'daily', get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ) );

		// The rule is removed entirely, so the blob is gone by shutdown.
		delete_post_meta( $post_id, Meta::META_KEY );
		$instance->set_recurrence( $post_id );

		$instance->resolve_pending_recurrence();

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'',
				get_post_meta( $post_id, $derived_key, true ),
				"Expected {$derived_key} to be cleared."
			);
		}
		$this->assertNull( Rule::from_post( $post_id ) );
	}

	/**
	 * `resolve_pending_recurrence()` skips a post whose type no longer
	 * supports `gatherpress-event-date` by the time shutdown runs. The post
	 * can be gone, or its type support can have changed, between the insert
	 * hook and shutdown.
	 *
	 * Everything the write path needs is arranged, so the post type is the
	 * only reason the mirrors stay unwritten: the blob is stored, it decodes
	 * to a valid rule, and the `gatherpress_datetime` blob carries a named
	 * timezone. A bare `post` with no rule would reach the same empty mirrors
	 * through the blob check instead, and would stay green with this guard
	 * deleted.
	 *
	 * @covers ::resolve_pending_recurrence
	 *
	 * @return void
	 */
	public function test_resolve_pending_recurrence_skips_unsupported_post_type(): void {
		$post_id  = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);
		$instance = Meta::get_instance();

		PMC_Utility::set_and_get_hidden_property( $instance, 'pending_recurrence', array( $post_id => true ) );

		// The support disappears between the insert hook deferring the post
		// and `shutdown` running, which is the race the guard exists for.
		set_post_type( $post_id, 'post' );

		$this->assertNotEmpty(
			get_post_meta( $post_id, Meta::META_KEY, true ),
			'Failed to assert that the rule blob survived the post type change.'
		);

		$instance->resolve_pending_recurrence();

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'',
				get_post_meta( $post_id, $derived_key, true ),
				"Expected {$derived_key} to be left unwritten for an unsupported post type."
			);
		}
	}

	/**
	 * `set_recurrence()` writes all ten mirrors from a valid rule when the
	 * blob is already present on this pass, which is the immediate,
	 * non-deferred path.
	 *
	 * @covers ::set_recurrence
	 * @covers ::write_recurrence
	 * @covers ::write_mirrors
	 * @covers ::read_timezone
	 *
	 * @return void
	 */
	public function test_set_recurrence_writes_mirrors_for_valid_rule(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'count'     => 3,
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );

		$this->assertSame( 'daily', get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'gatherpress_recurrence_interval', true ) );
		$this->assertSame( 'count', get_post_meta( $post_id, 'gatherpress_recurrence_end_type', true ) );
		$this->assertSame( '3', get_post_meta( $post_id, 'gatherpress_recurrence_count', true ) );

		$this->assertInstanceOf( Rule::class, Rule::from_post( $post_id ) );
	}

	/**
	 * `set_recurrence()` clears the mirrors, rather than leaving a previous
	 * rule's mirrors in place, when the stored blob decodes to an invalid
	 * rule. A stale `frequency` mirror is what makes
	 * `Query::refresh_has_recurring_events()` keep believing the site
	 * has a recurring event that was, in fact, just invalidated.
	 *
	 * @covers ::set_recurrence
	 * @covers ::write_recurrence
	 * @covers ::clear_mirrors
	 *
	 * @return void
	 */
	public function test_set_recurrence_clears_mirrors_when_rule_becomes_invalid(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );
		$this->assertSame( 'daily', get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ) );

		// The blob is overwritten with a structurally invalid rule (weekly,
		// no weekdays), as an editor round-trip that clears the weekday
		// selection but leaves the frequency as weekly would produce.
		update_post_meta( $post_id, Meta::META_KEY, wp_json_encode( array( 'frequency' => 'weekly' ) ) );

		Meta::get_instance()->set_recurrence( $post_id );

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'',
				get_post_meta( $post_id, $derived_key, true ),
				"Expected {$derived_key} to be cleared."
			);
		}
		$this->assertNull( Rule::from_post( $post_id ) );
	}

	/**
	 * `set_recurrence()` clears the mirrors when a later save moves the
	 * series onto a fixed-offset timezone, which `Timezone_Guard` refuses:
	 * a fixed offset carries no DST rules, so a series anchored on one
	 * silently drifts. The rejection must not leave the previous rule's
	 * mirrors behind, or the event goes on describing itself as recurring
	 * with a rule the plugin has just refused to honor.
	 *
	 * Driven through the real `wp_after_insert_post` hook rather than by
	 * calling `write_recurrence()` directly, so the assertion covers the
	 * path a REST write actually takes. That write can carry a fixed offset
	 * without ever passing through the editor.
	 *
	 * @covers ::set_recurrence
	 * @covers ::write_recurrence
	 * @covers ::clear_mirrors
	 * @covers ::read_timezone
	 *
	 * @return void
	 */
	public function test_set_recurrence_clears_mirrors_when_timezone_becomes_a_fixed_offset(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );

		$this->assertSame(
			'daily',
			get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ),
			'Failed to assert the fixture started out with valid recurrence mirrors.'
		);
		$this->assertTrue(
			Query::site_has_recurring_events(),
			'Failed to assert the fixture site started out with a recurring event.'
		);

		// The same rule, re-saved after the series moves onto a fixed UTC
		// offset. That is the shape Utility::maybe_convert_utc_offset()
		// produces for a site whose timezone is set as an offset rather than a
		// tz-database identifier.
		update_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $this->reference_anchor_start,
					'dateTimeEnd'   => $this->reference_anchor_end,
					'timezone'      => 'UTC+5:30',
				)
			)
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'wp_after_insert_post', $post_id, get_post( $post_id ), true, null );

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'',
				get_post_meta( $post_id, $derived_key, true ),
				"Failed to assert that {$derived_key} was cleared after the timezone became a fixed offset."
			);
		}

		$this->assertNull(
			Rule::from_post( $post_id ),
			'Failed to assert that no rule is reconstructable after a fixed-offset rejection.'
		);
		$this->assertFalse(
			Query::site_has_recurring_events(),
			'Failed to assert that the has-recurring-events flag was recomputed after the rejection.'
		);
	}

	/**
	 * A blob carrying malformed scalar types is rejected on the real save
	 * path, never coerced into a different valid schedule.
	 *
	 * This is the write boundary a REST or CLI client actually reaches:
	 * the blob lands as post meta and `wp_after_insert_post` derives the
	 * mirrors from it. `(int) 'not-a-number'` is `0`, which the sub-one
	 * clamp turns into interval 1, and `intval( 'not-a-weekday' )` is `0`,
	 * Sunday, so without type validation this exact payload was accepted as
	 * a weekly Sunday schedule.
	 *
	 * @covers ::set_recurrence
	 * @covers ::write_recurrence
	 * @covers ::clear_mirrors
	 *
	 * @return void
	 */
	public function test_set_recurrence_rejects_a_blob_with_malformed_scalar_types(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 'not-a-number',
				'weekdays'  => array( 'not-a-weekday' ),
				'end_type'  => 'never',
			)
		);

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Testing WordPress core hook.
		do_action( 'wp_after_insert_post', $post_id, get_post( $post_id ), true, null );

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'',
				get_post_meta( $post_id, $derived_key, true ),
				"Failed to assert that {$derived_key} stayed clear for a malformed blob."
			);
		}

		$this->assertNull(
			Rule::from_post( $post_id ),
			'Failed to assert that no rule is reconstructable from a malformed blob.'
		);
	}

	/**
	 * `Query::refresh_has_recurring_events()` runs after `write_mirrors()`,
	 * never before it. This is a regression test for that ordering, since
	 * `refresh_has_recurring_events()` reads the frequency mirror directly
	 * from storage and would observe nothing yet if it ran first.
	 *
	 * @covers ::write_recurrence
	 * @covers ::write_mirrors
	 *
	 * @return void
	 */
	public function test_refresh_has_recurring_events_runs_after_write_mirrors(): void {
		delete_option( Query::HAS_RECURRING_OPTION );

		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);

		$this->assertFalse( Query::site_has_recurring_events() );

		Meta::get_instance()->set_recurrence( $post_id );

		$this->assertTrue(
			Query::site_has_recurring_events(),
			'The has-recurring-events flag should read true, which is only possible if the'
			. ' frequency mirror was already on disk when refresh ran.'
		);
	}

	/**
	 * `set_recurrence()` clears the mirrors, rather than writing them, when
	 * the series carries a fixed UTC-offset timezone rather than a named
	 * tz-database identifier. `Timezone_Guard::assert_named()` throws, and
	 * `write_recurrence()`'s catch clears the mirrors instead of letting a
	 * DST-unsafe rule reach the expander. GatherPress normalizes WordPress's
	 * manual-offset option (e.g. `UTC+5.5`) into `+05:30`, and a site left on
	 * a manual UTC offset is a common configuration. This is the live path
	 * the guard exists to close, not a synthetic one.
	 *
	 * @covers ::set_recurrence
	 * @covers ::write_recurrence
	 *
	 * @return void
	 */
	public function test_set_recurrence_clears_mirrors_for_fixed_offset_timezone(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			),
			'+05:30'
		);

		Meta::get_instance()->set_recurrence( $post_id );

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'',
				get_post_meta( $post_id, $derived_key, true ),
				"Expected {$derived_key} to be cleared for a fixed-offset timezone."
			);
		}
		$this->assertNull( Rule::from_post( $post_id ) );
	}

	/**
	 * `write_recurrence()` defers to `shutdown` rather than clearing the
	 * mirrors when the recurrence blob is present but the
	 * `gatherpress_datetime` blob has not been written yet on this pass. That is
	 * the same race `set_recurrence()` already defends against for a missing
	 * recurrence blob, mirrored for a missing timezone. `meta_input` on
	 * `wp_insert_post()`, a WXR import, or a duplication plugin can all write
	 * the recurrence blob before the datetime blob lands.
	 *
	 * @covers ::set_recurrence
	 * @covers ::write_recurrence
	 *
	 * @return void
	 */
	public function test_write_recurrence_defers_when_timezone_not_yet_known(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$instance = $this->create_recurring_event_without_datetime( $post_id );

		$instance->set_recurrence( $post_id );

		$this->assertSame(
			'',
			get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ),
			'Expected mirrors to stay unwritten while the timezone is not yet known.'
		);
		$this->assertNotFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_recurrence' ) ),
			'A shutdown resolution should be scheduled when the timezone is not yet known.'
		);
	}

	/**
	 * `resolve_pending_recurrence()` clears the mirrors, rather than deferring
	 * again, when the timezone is still unknown at `shutdown`. That is the
	 * terminal case of the same race: the datetime blob genuinely never arrived
	 * this request.
	 *
	 * @covers ::resolve_pending_recurrence
	 * @covers ::write_recurrence
	 *
	 * @return void
	 */
	public function test_resolve_pending_recurrence_clears_mirrors_when_timezone_never_resolves(): void {
		$post_id  = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$instance = $this->create_recurring_event_without_datetime( $post_id );

		$instance->set_recurrence( $post_id );
		$instance->resolve_pending_recurrence();

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'',
				get_post_meta( $post_id, $derived_key, true ),
				"Expected {$derived_key} to be cleared once the timezone never resolved."
			);
		}
		$this->assertNull( Rule::from_post( $post_id ) );
	}

	/**
	 * Rewrite a post's `gatherpress_datetime` blob, keeping the anchor and
	 * changing only the timezone, which is the exact edit an organizer makes in
	 * the Date & Time panel.
	 *
	 * @since 0.36.0
	 *
	 * @param int    $post_id  Post to rewrite the datetime blob on.
	 * @param string $timezone Timezone value to store.
	 *
	 * @return void
	 */
	protected function change_timezone( int $post_id, string $timezone ): void {
		update_post_meta(
			$post_id,
			'gatherpress_datetime',
			wp_json_encode(
				array(
					'dateTimeStart' => $this->reference_anchor_start,
					'dateTimeEnd'   => $this->reference_anchor_end,
					'timezone'      => $timezone,
				)
			)
		);
	}

	/**
	 * Changing an existing recurring series to a fixed-offset timezone must
	 * not leave the recurrence active.
	 *
	 * `set_recurrence()` runs on `wp_after_insert_post`, which fires from
	 * inside `wp_insert_post()`, before the request's meta writes land. The
	 * recurrence blob is already stored on an existing series, so
	 * `write_recurrence()` validates immediately, against the timezone the
	 * post had *before* this save. Nothing revisited that decision: the
	 * mirrors and the projected rows stayed active in a state the guard refuses,
	 * while the editor disabled the Repeat control. The stored series and
	 * the visible UI then disagreed about whether the event repeats.
	 *
	 * @covers ::maybe_revalidate_for_datetime
	 * @covers ::resolve_pending_revalidation
	 *
	 * @return void
	 */
	public function test_fixed_offset_timezone_change_disables_an_existing_series(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'count'     => 5,
			)
		);

		$instance = Meta::get_instance();

		$instance->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		$this->assertSame(
			'daily',
			get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ),
			'Precondition: the named-timezone series is active.'
		);
		$this->assertNotEmpty(
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Precondition: the named-timezone series has projected rows.'
		);

		// The real update order: `wp_after_insert_post` fires while the old
		// named timezone is still stored, then the meta write lands.
		$instance->set_recurrence( $post_id );
		$this->change_timezone( $post_id, '+05:30' );

		$this->assertNotFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_revalidation' ) ),
			'A datetime rewrite on a recurring post must queue a final validation pass.'
		);

		$instance->resolve_pending_revalidation();

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'',
				get_post_meta( $post_id, $derived_key, true ),
				"Expected {$derived_key} to be cleared once the timezone became a fixed offset."
			);
		}

		$this->assertNull( Rule::from_post( $post_id ) );
		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Clearing the mirrors is only half of disabling a series. The projected rows must go too.'
		);
	}

	/**
	 * The authored rule survives the rejection, so switching the timezone back
	 * to a named identifier re-derives the mirrors and re-projects rather than
	 * losing the rule to a timezone edit.
	 *
	 * @covers ::maybe_revalidate_for_datetime
	 * @covers ::resolve_pending_revalidation
	 *
	 * @return void
	 */
	public function test_returning_to_a_named_timezone_restores_the_series(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'count'     => 5,
			)
		);

		$instance = Meta::get_instance();

		$instance->set_recurrence( $post_id );
		$this->change_timezone( $post_id, '+05:30' );
		$instance->resolve_pending_revalidation();

		$this->assertSame( '', get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ) );

		$this->change_timezone( $post_id, 'America/New_York' );
		$instance->resolve_pending_revalidation();

		$this->assertSame(
			'daily',
			get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ),
			'The stored rule must come back once the timezone is named again.'
		);
		$this->assertNotEmpty(
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'The series must reproject once it is valid again.'
		);
	}

	/**
	 * Deleting the datetime blob disables the series like any other rewrite.
	 *
	 * A cleanup script, an importer, or one line of WP-CLI
	 * (`wp post meta delete <id> gatherpress_datetime`) can remove the blob
	 * outright. That leaves the series' timezone unknowable, the exact state
	 * the re-validation machinery exists to clear; only the trigger differs,
	 * so `deleted_post_meta` must queue the same shutdown pass the
	 * `added`/`updated` hooks do.
	 *
	 * @covers ::maybe_revalidate_for_datetime
	 * @covers ::resolve_pending_revalidation
	 *
	 * @return void
	 */
	public function test_deleting_the_datetime_meta_disables_an_existing_series(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'count',
				'count'     => 5,
			)
		);

		$instance = Meta::get_instance();

		$instance->set_recurrence( $post_id );
		Occurrences::get_instance()->project( $post_id );

		// A clean slate, so the queue assertion below can only be satisfied
		// by the delete itself.
		remove_action( 'shutdown', array( $instance, 'resolve_pending_revalidation' ), 15 );

		$this->assertSame(
			'daily',
			get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ),
			'Precondition: the series is active.'
		);
		$this->assertNotEmpty(
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'Precondition: the series has projected rows.'
		);

		delete_post_meta( $post_id, 'gatherpress_datetime' );

		$this->assertNotFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_revalidation' ) ),
			'Deleting the datetime blob on a recurring post must queue a final validation pass.'
		);

		$instance->resolve_pending_revalidation();

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertSame(
				'',
				get_post_meta( $post_id, $derived_key, true ),
				"Expected {$derived_key} to be cleared once the series timezone became unknowable."
			);
		}

		$this->assertSame(
			array(),
			Occurrences::get_instance()->select_for_series( array( $post_id ) ),
			'A series with no datetime blob must not keep projected rows.'
		);
	}

	/**
	 * The revalidation trigger is exact: a meta write that is not the datetime
	 * blob, a post type without `gatherpress-event-date` support, and a post
	 * holding no recurrence blob all queue nothing, so an ordinary event save
	 * never pays for this.
	 *
	 * @covers ::maybe_revalidate_for_datetime
	 *
	 * @return void
	 */
	public function test_maybe_revalidate_ignores_everything_but_a_recurring_datetime_write(): void {
		$instance = Meta::get_instance();

		remove_action( 'shutdown', array( $instance, 'resolve_pending_revalidation' ), 15 );

		$recurring_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);

		$plain_event_id = $this->factory->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$unsupported_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		// Right post, wrong meta key.
		$instance->maybe_revalidate_for_datetime( 1, $recurring_id, 'gatherpress_max_guest_limit' );
		$this->assertFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_revalidation' ) ),
			'A meta key other than the datetime blob must queue nothing.'
		);

		// Right meta key, post type without event-date support.
		$instance->maybe_revalidate_for_datetime( 1, $unsupported_id, 'gatherpress_datetime' );
		$this->assertFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_revalidation' ) ),
			'An unsupported post type must queue nothing.'
		);

		// Right meta key and post type, but no recurrence blob to invalidate.
		$instance->maybe_revalidate_for_datetime( 1, $plain_event_id, 'gatherpress_datetime' );
		$this->assertFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_revalidation' ) ),
			'A never-recurring event must queue nothing.'
		);

		// All three conditions met.
		$instance->maybe_revalidate_for_datetime( 1, $recurring_id, 'gatherpress_datetime' );
		$this->assertNotFalse(
			has_action( 'shutdown', array( $instance, 'resolve_pending_revalidation' ) ),
			'A datetime write on a recurring post must queue the final pass.'
		);

		$instance->resolve_pending_revalidation();
	}

	/**
	 * `resolve_pending_revalidation()` clears the mirrors when the recurrence
	 * blob was removed in the same request, and skips a post whose type lost
	 * `gatherpress-event-date` support (or was deleted) before shutdown.
	 *
	 * @covers ::resolve_pending_revalidation
	 *
	 * @return void
	 */
	public function test_resolve_pending_revalidation_clears_and_skips(): void {
		$instance = Meta::get_instance();

		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'daily',
				'interval'  => 1,
				'end_type'  => 'never',
			)
		);

		$instance->set_recurrence( $post_id );

		$unsupported_id = $this->factory->post->create( array( 'post_type' => 'post' ) );

		PMC_Utility::set_and_get_hidden_property(
			$instance,
			'pending_revalidation',
			array(
				$post_id        => true,
				$unsupported_id => true,
			)
		);

		delete_post_meta( $post_id, Meta::META_KEY );

		$instance->resolve_pending_revalidation();

		$this->assertSame(
			'',
			get_post_meta( $post_id, 'gatherpress_recurrence_frequency', true ),
			'A blob removed in the same request must clear the mirrors on the final pass.'
		);
		$this->assertSame(
			array(),
			PMC_Utility::get_hidden_property( $instance, 'pending_revalidation' ),
			'The queue must be drained even when an entry is skipped.'
		);
	}

	/**
	 * Write a `gatherpress_recurrence` blob with no companion
	 * `gatherpress_datetime` blob, simulating a recurrence-before-datetime
	 * write ordering.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Post to write the recurrence blob on.
	 *
	 * @return Meta The `Meta` singleton, for chaining into the calling test.
	 */
	protected function create_recurring_event_without_datetime( int $post_id ): Meta {
		add_post_meta(
			$post_id,
			Meta::META_KEY,
			wp_json_encode(
				array(
					'frequency' => 'daily',
					'interval'  => 1,
					'end_type'  => 'never',
				)
			)
		);

		return Meta::get_instance();
	}

	/**
	 * `filter_readonly_meta` strips the ten derived recurrence keys from a
	 * REST request's `meta` payload.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_derived_meta_is_stripped_from_rest_writes(): void {
		$instance = Meta::get_instance();

		$request = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );

		$meta = array( 'gatherpress_recurrence' => wp_json_encode( array( 'frequency' => 'daily' ) ) );

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$meta[ $derived_key ] = 'stray-client-supplied-value';
		}

		$request->set_param( 'meta', $meta );

		$prepared_post     = new stdClass();
		$prepared_post->ID = 321;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		$this->assertSame( $prepared_post, $result );

		$filtered_meta = $request->get_param( 'meta' );

		$this->assertArrayHasKey( 'gatherpress_recurrence', $filtered_meta );
		$this->assertCount( 1, $filtered_meta );

		foreach ( Meta::DERIVED_META_KEYS as $derived_key ) {
			$this->assertArrayNotHasKey( $derived_key, $filtered_meta );
		}
	}

	/**
	 * `filter_readonly_meta` returns the prepared post unchanged when the
	 * request has no `meta` param.
	 *
	 * @covers ::filter_readonly_meta
	 *
	 * @return void
	 */
	public function test_filter_readonly_meta_with_null_meta(): void {
		$instance = Meta::get_instance();
		$request  = new WP_REST_Request( 'POST', '/wp/v2/gatherpress_event' );

		$prepared_post     = new stdClass();
		$prepared_post->ID = 654;

		$result = $instance->filter_readonly_meta( $prepared_post, $request );

		$this->assertSame( $prepared_post, $result );
		$this->assertNull( $request->get_param( 'meta' ) );
	}

	/**
	 * `sanitize_signed_int()` casts to a signed integer, preserving a negative
	 * value. That is why `intval` (which errors under WP's meta sanitize
	 * callback signature) and `absint()` (which would clamp `-1` to `1`) are
	 * both wrong for `gatherpress_recurrence_monthly_ordinal`.
	 *
	 * @covers ::sanitize_signed_int
	 *
	 * @return void
	 */
	public function test_sanitize_signed_int_preserves_negative_values(): void {
		$this->assertSame( -1, Meta::sanitize_signed_int( '-1' ) );
		$this->assertSame( 3, Meta::sanitize_signed_int( '3' ) );
		$this->assertSame( 0, Meta::sanitize_signed_int( '' ) );
	}

	/**
	 * `remove_recurrence()` clears the blob and every mirror, synchronously.
	 *
	 * The mirrors are the half that matters: `Rule::from_post()` reads them, not
	 * the blob, so a post whose blob is gone while its mirrors survive keeps
	 * expanding a rule it no longer has.
	 *
	 * @covers ::remove_recurrence
	 *
	 * @return void
	 */
	public function test_remove_recurrence_clears_the_blob_and_the_mirrors(): void {
		$post_id = $this->create_recurring_event(
			array(
				'frequency' => 'weekly',
				'interval'  => 2,
				'weekdays'  => array( 2, 4 ),
				'end_type'  => 'count',
				'count'     => 5,
			)
		);

		Meta::get_instance()->set_recurrence( $post_id );

		$this->assertInstanceOf(
			Rule::class,
			Rule::from_post( $post_id ),
			'Failed to assert the fixture is recurring before the removal.'
		);

		Meta::get_instance()->remove_recurrence( $post_id );

		$this->assertSame(
			'',
			(string) get_post_meta( $post_id, Meta::META_KEY, true ),
			'Failed to assert the recurrence blob was deleted.'
		);
		$this->assertNull(
			Rule::from_post( $post_id ),
			'Failed to assert the mirrors were cleared. A surviving mirror keeps the deleted rule alive.'
		);

		foreach ( Meta::DERIVED_META_KEYS as $meta_key ) {
			$this->assertSame(
				'',
				(string) get_post_meta( $post_id, $meta_key, true ),
				sprintf( 'Failed to assert the %s mirror was cleared.', $meta_key )
			);
		}
	}
}
