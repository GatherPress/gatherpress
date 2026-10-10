<?php
/**
 * Class handles unit tests for GatherPress\Core\Renamed_Keys.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Event;
use GatherPress\Core\Renamed_Keys;
use GatherPress\Tests\Base;

/**
 * Class Test_Renamed_Keys.
 *
 * @coversDefaultClass \GatherPress\Core\Renamed_Keys
 */
class Test_Renamed_Keys extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @since  TBD
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Renamed_Keys::get_instance();
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'get_post_metadata',
				'priority' => 10,
				'callback' => array( $instance, 'answer_with_former_name' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * Coverage for option_names.
	 *
	 * @since  TBD
	 * @covers ::option_names
	 *
	 * @return void
	 */
	public function test_option_names_lists_the_former_name_second(): void {
		$this->assertSame(
			array( 'capacity', 'max_attendance_limit' ),
			Renamed_Keys::option_names( 'capacity' ),
			'Failed to assert a renamed setting answers to both names.'
		);
	}

	/**
	 * Coverage for option_names.
	 *
	 * @since  TBD
	 * @covers ::option_names
	 *
	 * @return void
	 */
	public function test_option_names_is_just_the_name_when_nothing_was_renamed(): void {
		$this->assertSame(
			array( 'date_format' ),
			Renamed_Keys::option_names( 'date_format' ),
			'Failed to assert an untouched setting answers to one name.'
		);
	}

	/**
	 * Coverage for answer_with_former_name.
	 *
	 * The guest limit is the second key in the map, and asserting it here is
	 * what makes removing its compatibility registration fail the suite
	 * rather than pass quietly.
	 *
	 * @since  TBD
	 * @covers ::answer_with_former_name
	 *
	 * @return void
	 */
	public function test_guest_limit_reads_the_pre_036_meta_key(): void {
		$post_id = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		update_post_meta( $post_id, 'gatherpress_max_guest_limit', 6 );

		$this->assertSame(
			'6',
			get_post_meta( $post_id, 'gatherpress_guest_limit', true ),
			'Failed to assert an event saved under the old guest limit name keeps it.'
		);
	}

	/**
	 * Coverage for answer_with_former_name.
	 *
	 * @since  TBD
	 * @covers ::answer_with_former_name
	 *
	 * @return void
	 */
	public function test_guest_limit_prefers_its_own_row_over_the_old_one(): void {
		$post_id = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		update_post_meta( $post_id, 'gatherpress_max_guest_limit', 6 );
		update_post_meta( $post_id, 'gatherpress_guest_limit', 2 );

		$this->assertSame(
			'2',
			get_post_meta( $post_id, 'gatherpress_guest_limit', true ),
			'Failed to assert the current guest limit key wins over the one it replaced.'
		);
	}

	/**
	 * Coverage for answer_with_former_name.
	 *
	 * @since  TBD
	 * @covers ::answer_with_former_name
	 *
	 * @return void
	 */
	public function test_capacity_reads_the_pre_036_meta_key(): void {
		$post_id = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		update_post_meta( $post_id, 'gatherpress_max_attendance_limit', 100 );

		$this->assertSame(
			'100',
			get_post_meta( $post_id, 'gatherpress_capacity', true ),
			'Failed to assert an event saved under the old name keeps its limit.'
		);
	}

	/**
	 * Coverage for answer_with_former_name.
	 *
	 * @since  TBD
	 * @covers ::answer_with_former_name
	 *
	 * @return void
	 */
	public function test_capacity_prefers_its_own_row_over_the_old_one(): void {
		$post_id = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		update_post_meta( $post_id, 'gatherpress_max_attendance_limit', 100 );
		update_post_meta( $post_id, 'gatherpress_capacity', 25 );

		$this->assertSame(
			'25',
			get_post_meta( $post_id, 'gatherpress_capacity', true ),
			'Failed to assert the current key wins over the one it replaced.'
		);
	}

	/**
	 * Coverage for answer_with_former_name.
	 *
	 * A zero is a real answer, "no limit", and PHP would call it empty, so the
	 * filter has to test for the row rather than for a truthy value.
	 *
	 * @since  TBD
	 * @covers ::answer_with_former_name
	 *
	 * @return void
	 */
	public function test_capacity_reads_a_legacy_zero(): void {
		$post_id = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		update_post_meta( $post_id, 'gatherpress_max_attendance_limit', 0 );

		$this->assertSame(
			'0',
			get_post_meta( $post_id, 'gatherpress_capacity', true ),
			'Failed to assert a legacy no-limit setting survives the rename.'
		);
	}

	/**
	 * Coverage for answer_with_former_name.
	 *
	 * @since  TBD
	 * @covers ::answer_with_former_name
	 *
	 * @return void
	 */
	public function test_capacity_falls_through_when_neither_row_exists(): void {
		$post_id = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		// The registered default is frozen at registration time, so it is read
		// back from the registry rather than from the live setting, which a
		// neighboring test can have moved.
		$registered = get_registered_meta_keys( 'post', Event::POST_TYPE );

		$this->assertSame(
			(string) $registered['gatherpress_capacity']['default'],
			(string) get_post_meta( $post_id, 'gatherpress_capacity', true ),
			'Failed to assert an untouched event is left to its registered default.'
		);
	}

	/**
	 * Coverage for answer_with_former_name.
	 *
	 * @since  TBD
	 * @covers ::answer_with_former_name
	 *
	 * @return void
	 */
	public function test_other_meta_keys_are_left_alone(): void {
		$post_id = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		update_post_meta( $post_id, 'gatherpress_max_attendance_limit', 100 );

		$this->assertNotSame(
			'100',
			get_post_meta( $post_id, 'gatherpress_max_guest_limit', true ),
			'Failed to assert the filter only answers for capacity.'
		);
	}

	/**
	 * Coverage for answer_with_former_name.
	 *
	 * @since  TBD
	 * @covers ::answer_with_former_name
	 *
	 * @return void
	 */
	public function test_capacity_answers_a_non_single_read(): void {
		$post_id = $this->factory()->post->create(
			array( 'post_type' => Event::POST_TYPE )
		);

		update_post_meta( $post_id, 'gatherpress_max_attendance_limit', 100 );

		$this->assertSame(
			array( '100' ),
			get_post_meta( $post_id, 'gatherpress_capacity', false ),
			'Failed to assert a non-single read is answered as a list.'
		);
	}

	/**
	 * Coverage for normalize_value with legacy rsvp_mode values.
	 *
	 * @since  TBD
	 * @covers ::normalize_value
	 *
	 * @return void
	 */
	public function test_normalize_value_maps_legacy_rsvp_mode(): void {
		$this->assertSame(
			'enabled',
			Renamed_Keys::normalize_value( 'rsvp_mode', 'all_on' ),
			'Failed to assert legacy all_on maps to enabled.'
		);

		$this->assertSame(
			'per_event_enabled',
			Renamed_Keys::normalize_value( 'rsvp_mode', 'per_event_on' ),
			'Failed to assert legacy per_event_on maps to per_event_enabled.'
		);

		$this->assertSame(
			'per_event_disabled',
			Renamed_Keys::normalize_value( 'rsvp_mode', 'per_event_off' ),
			'Failed to assert legacy per_event_off maps to per_event_disabled.'
		);

		$this->assertSame(
			'disabled',
			Renamed_Keys::normalize_value( 'rsvp_mode', 'disabled' ),
			'Failed to assert disabled is untouched.'
		);

		$this->assertSame(
			'enabled',
			Renamed_Keys::normalize_value( 'rsvp_mode', 'enabled' ),
			'Failed to assert current enabled is untouched.'
		);
	}

	/**
	 * Coverage for normalize_value with legacy cleanup values.
	 *
	 * @since  TBD
	 * @covers ::normalize_value
	 *
	 * @return void
	 */
	public function test_normalize_value_maps_legacy_cleanup_values(): void {
		$this->assertSame(
			'enabled',
			Renamed_Keys::normalize_value( 'enable_rsvp_cleanup', 'on' ),
			'Failed to assert legacy cleanup on maps to enabled.'
		);

		$this->assertSame(
			'disabled',
			Renamed_Keys::normalize_value( 'enable_rsvp_cleanup', 'off' ),
			'Failed to assert legacy cleanup off maps to disabled.'
		);

		$this->assertSame(
			'enabled',
			Renamed_Keys::normalize_value( 'rsvp_cleanup_switch', 'on' ),
			'Failed to assert legacy rsvp_cleanup_switch on maps to enabled.'
		);

		$this->assertSame(
			'disabled',
			Renamed_Keys::normalize_value( 'rsvp_cleanup_switch', 'off' ),
			'Failed to assert legacy rsvp_cleanup_switch off maps to disabled.'
		);
	}

	/**
	 * Coverage for normalize_value with non-string values or unmapped options.
	 *
	 * @since  TBD
	 * @covers ::normalize_value
	 *
	 * @return void
	 */
	public function test_normalize_value_leaves_non_strings_and_unmapped_keys_untouched(): void {
		$this->assertSame(
			100,
			Renamed_Keys::normalize_value( 'capacity', 100 ),
			'Failed to assert numeric capacity value is untouched.'
		);

		$this->assertSame(
			123,
			Renamed_Keys::normalize_value( 'rsvp_mode', 123 ),
			'Failed to assert non-string rsvp_mode value is untouched.'
		);

		$this->assertSame(
			'on',
			Renamed_Keys::normalize_value( 'unrelated_option', 'on' ),
			'Failed to assert unmapped option string is untouched.'
		);
	}
}
