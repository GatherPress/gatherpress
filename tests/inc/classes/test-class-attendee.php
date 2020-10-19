<?php
/**
 * Class handles unit tests for GatherPress\Inc\Attendee.
 *
 * @package GatherPress
 * @subpackage Tests
 * @since 1.0.0
 */

namespace GatherPress\Tests\Inc;

use GatherPress\Inc\Attendee;

/**
 * Class Test_Attendee.
 *
 * @coversDefaultClass GatherPress\Inc\Attendee
 */
class Test_Attendee extends \WP_UnitTestCase {

	/**
	 * Coverage for get_attendee method.
	 *
	 * @covers ::get_attendee
	 */
	public function test_get_attendee() {
		$post_id  = $this->factory->post->create(
			array(
				'post_type' => 'gp_event',
			)
		);
		$attendee = new Attendee( $post_id );
		$user_id  = $this->factory->user->create();
		$status   = 'attending';

		$this->assertEmpty( $attendee->get_attendee( 0 ) );
		$this->assertEmpty( $attendee->get_attendee( $user_id ) );

		$attendee->save_attendee( $user_id, $status );

		$data = $attendee->get_attendee( $user_id );

		$this->assertSame( $post_id, intval( $data['post_id'] ) );
		$this->assertSame( $user_id, intval( $data['user_id'] ) );
		$this->assertSame( $status, $data['status'] );
		$this->assertInternalType( 'int', strtotime( $data['timestamp'] ) );
		$this->assertNotEmpty( $data['id'] );
	}

	/**
	 * Coverage for save_attendee method.
	 *
	 * @covers ::save_attendee
	 */
	public function test_save_attendee() {
		$post_id  = $this->factory->post->create(
			array(
				'post_type' => 'gp_event',
			)
		);
		$attendee = new Attendee( $post_id );
		$user_id  = $this->factory->user->create();
		$status   = 'attending';

		$this->assertSame( $status, $attendee->save_attendee( $user_id, $status ) );

		$status = 'not_attending';

		$this->assertSame( $status, $attendee->save_attendee( $user_id, $status ) );

		$this->assertEmpty( $attendee->save_attendee( 0, $status ) );

		$status = 'unittest';

		$this->assertEmpty( $attendee->save_attendee( $user_id, $status ) );
	}
}
