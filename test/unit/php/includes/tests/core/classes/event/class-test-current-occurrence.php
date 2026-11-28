<?php
/**
 * Class handles unit tests for GatherPress\Core\Event\Current_Occurrence.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Event;

use GatherPress\Core\Event\Current_Occurrence;
use GatherPress\Core\Event\Event;
use GatherPress\Tests\Base;
use WP_Post;

/**
 * Class Test_Current_Occurrence.
 *
 * @coversDefaultClass \GatherPress\Core\Event\Current_Occurrence
 */
class Test_Current_Occurrence extends Base {

	/**
	 * A date as the index gives it.
	 *
	 * @since TBD
	 *
	 * @param string $key The occurrence key.
	 *
	 * @return array<string, string> The date.
	 */
	protected function occurrence( string $key = '20261218T190000' ): array {
		return array(
			'recurrence_id'      => $key,
			'datetime_start'     => '2026-12-18 19:00:00',
			'datetime_start_gmt' => '2026-12-19 00:00:00',
			'datetime_end'       => '2026-12-18 20:30:00',
			'datetime_end_gmt'   => '2026-12-19 01:30:00',
			'timezone'           => 'America/New_York',
		);
	}

	/**
	 * A post carrying a joined row's date.
	 *
	 * @since TBD
	 *
	 * @param int                   $post_id    The post ID.
	 * @param array<string, string> $occurrence The date.
	 *
	 * @return WP_Post The post.
	 */
	protected function row( int $post_id, array $occurrence ): WP_Post {
		$post = get_post( $post_id );

		foreach ( $occurrence as $field => $value ) {
			$post->{Current_Occurrence::PROPERTY_PREFIX . $field} = $value;
		}

		return $post;
	}

	/**
	 * Restore the global post after each test.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function tear_down(): void {
		unset( $GLOBALS['post'] );

		parent::tear_down();
	}

	/**
	 * A row's date reads from its properties, and a row missing one has none.
	 *
	 * @since TBD
	 *
	 * @covers ::from_post
	 *
	 * @return void
	 */
	public function test_from_post(): void {
		$post_id = $this->factory()->post->create();

		$row = $this->row( $post_id, $this->occurrence() );

		$this->assertSame( $this->occurrence(), Current_Occurrence::from_post( $row ) );
		$this->assertNull( Current_Occurrence::from_post( get_post( $post_id ) ), 'A plain post has no date.' );

		$partial = $this->row( $post_id, $this->occurrence() );

		$partial->{Current_Occurrence::PROPERTY_PREFIX . 'timezone'} = '';

		$this->assertNull( Current_Occurrence::from_post( $partial ), 'A row missing a field has no date.' );
	}

	/**
	 * The date comes from the global post when it is that event's row, and a set date wins.
	 *
	 * @since TBD
	 *
	 * @covers ::get
	 * @covers ::set
	 * @covers ::clear
	 *
	 * @return void
	 */
	public function test_get(): void {
		$current = Current_Occurrence::get_instance();
		$post_id = $this->factory()->post->create();
		$other   = $this->factory()->post->create();

		$this->assertNull( $current->get( $post_id ), 'No loop, no date.' );

		$row = $this->row( $post_id, $this->occurrence() );

		$GLOBALS['post'] = $row; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( $this->occurrence(), $current->get( $post_id ), 'The loop row is that event.' );
		$this->assertNull( $current->get( $other ), 'Another event reads its own dates.' );

		$set = $this->occurrence( '20261225T190000' );

		$current->set( $post_id, $set );

		$this->assertSame( $set, $current->get( $post_id ), 'A date set directly wins.' );

		$current->clear( $post_id );

		$this->assertSame( $this->occurrence(), $current->get( $post_id ) );
	}

	/**
	 * The date in the shape Event::get_datetime() returns, with the timezone filtered.
	 *
	 * @since TBD
	 *
	 * @covers ::get_datetime
	 *
	 * @return void
	 */
	public function test_get_datetime(): void {
		$current = Current_Occurrence::get_instance();
		$post_id = $this->factory()->post->create();

		$this->assertNull( $current->get_datetime( null ) );
		$this->assertNull( $current->get_datetime( get_post( $post_id ) ) );

		$current->set( $post_id, $this->occurrence() );

		$timezone = static fn(): string => 'Europe/Paris';

		add_filter( 'gatherpress_timezone', $timezone );

		$this->assertSame(
			array(
				'datetime_start'     => '2026-12-18 19:00:00',
				'datetime_start_gmt' => '2026-12-19 00:00:00',
				'datetime_end'       => '2026-12-18 20:30:00',
				'datetime_end_gmt'   => '2026-12-19 01:30:00',
				'timezone'           => 'Europe/Paris',
			),
			$current->get_datetime( get_post( $post_id ) )
		);

		remove_filter( 'gatherpress_timezone', $timezone );
		$current->clear( $post_id );
	}

	/**
	 * An all-day date has a key without a time.
	 *
	 * @since TBD
	 *
	 * @covers ::is_all_day
	 *
	 * @return void
	 */
	public function test_is_all_day(): void {
		$current = Current_Occurrence::get_instance();
		$post_id = $this->factory()->post->create();

		$this->assertNull( $current->is_all_day( null ) );
		$this->assertNull( $current->is_all_day( get_post( $post_id ) ), 'No date, no answer.' );

		$current->set( $post_id, $this->occurrence() );
		$this->assertFalse( $current->is_all_day( get_post( $post_id ) ) );

		$current->set( $post_id, $this->occurrence( '20261218' ) );
		$this->assertTrue( $current->is_all_day( get_post( $post_id ) ) );

		$current->clear( $post_id );
	}

	/**
	 * An event reads the current date, while its saved dates stay readable.
	 *
	 * @since TBD
	 *
	 * @covers \GatherPress\Core\Event\Event::get_datetime
	 * @covers \GatherPress\Core\Event\Event::get_saved_datetime
	 * @covers \GatherPress\Core\Event\Event::is_all_day
	 *
	 * @return void
	 */
	public function test_event_reads_the_current_date(): void {
		$post_id = $this->factory()->post->create( array( 'post_type' => Event::POST_TYPE ) );
		$event   = new Event( $post_id );

		$event->save_datetimes(
			array(
				'datetime_start' => '2026-12-11 19:00:00',
				'datetime_end'   => '2026-12-11 20:30:00',
				'timezone'       => 'America/New_York',
			)
		);
		Current_Occurrence::get_instance()->set( $post_id, $this->occurrence( '20261218' ) );

		$this->assertSame( '2026-12-18 19:00:00', $event->get_datetime()['datetime_start'] );
		$this->assertSame( '2026-12-11 19:00:00', $event->get_saved_datetime()['datetime_start'] );
		$this->assertTrue( $event->is_all_day(), 'An all-day date makes the event read as all day.' );

		Current_Occurrence::get_instance()->clear( $post_id );

		$this->assertSame( '2026-12-11 19:00:00', $event->get_datetime()['datetime_start'] );
		$this->assertFalse( $event->is_all_day() );
		$this->assertFalse( ( new Event( 0 ) )->is_all_day(), 'Not an event, not all day.' );
		$this->assertSame( '', ( new Event( 0 ) )->get_saved_datetime()['datetime_start'], 'Not an event, no dates.' );
	}
}
