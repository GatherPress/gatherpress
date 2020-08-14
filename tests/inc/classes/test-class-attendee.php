<?php
namespace GatherPress\Tests\Inc;

use GatherPress\Inc\Attendee;

/**
 * @coversDefaultClass GatherPress\Inc\Attendee
 */
class Test_Attendee extends \WP_UnitTestCase {

	/**
	 * @covers ::get_attendee
	 */
	public function test_get_attendee() {

		$instance = Attendee::get_instance();
		$post_id  = $this->factory->post->create(
			[
				'post_type' => 'gp_event'
			]
		);
		$user_id  = $this->factory->user->create();
		$status   = 'attending';

		$this->assertEmpty( $instance->get_attendee( 0, 0 ) );
		$this->assertEmpty( $instance->get_attendee( $post_id, $user_id ) );

		$instance->save_attendee( $post_id, $user_id, $status );

		$data = $instance->get_attendee( $post_id, $user_id );

		$this->assertSame( $post_id, intval( $data['post_id'] ) );
		$this->assertSame( $user_id, intval( $data['user_id'] ) );
		$this->assertSame( $status, $data['status'] );
		$this->assertInternalType( 'int', strtotime( $data['timestamp'] ) );
		$this->assertNotEmpty( $data['id'] );

	}

	/**
	 * @covers ::save_attendee
	 */
	public function test_save_attendee() {

		$instance = Attendee::get_instance();
		$post_id  = $this->factory->post->create(
			[
				'post_type' => 'gp_event'
			]
		);
		$user_id  = $this->factory->user->create();
		$status   = 'attending';

		$this->assertSame( $status, $instance->save_attendee( $post_id, $user_id, $status ) );

		$status = 'not_attending';

		$this->assertSame( $status, $instance->save_attendee( $post_id, $user_id, $status ) );

		$this->assertEmpty( $instance->save_attendee( 0, 0, $status ) );

		$status = 'unittest';

		$this->assertEmpty( $instance->save_attendee( $post_id, $user_id, $status ) );
	}
}

// EOF
