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
}
