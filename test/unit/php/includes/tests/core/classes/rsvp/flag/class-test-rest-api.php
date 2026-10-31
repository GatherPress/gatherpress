<?php
/**
 * Class handles unit tests for GatherPress\Core\Rsvp\Flag\Rest_Api.
 *
 * @package GatherPress\Core\Rsvp\Flag
 * @since TBD
 */

namespace GatherPress\Tests\Core\Rsvp\Flag;

use GatherPress\Core\Event;
use GatherPress\Core\Rsvp;
use GatherPress\Core\Rsvp\Flag\Base;
use GatherPress\Core\Rsvp\Flag\Check_In_Registry;
use GatherPress\Core\Rsvp\Flag\No_Show;
use GatherPress\Core\Rsvp\Flag\Rest_Api;
use GatherPress\Core\Rsvp\Flag\Setup;
use GatherPress\Tests\Base as Base_Unit_Test;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Test_Rest_Api.
 *
 * @coversDefaultClass \GatherPress\Core\Rsvp\Flag\Rest_Api
 */
class Test_Rest_Api extends Base_Unit_Test {

	/**
	 * Register the taxonomy and the route.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Setup::get_instance()->register_taxonomy();
		Rest_Api::get_instance()->register_endpoints();
	}

	/**
	 * Create an RSVP.
	 *
	 * @param string $approved The comment approval status.
	 *
	 * @return int The RSVP ID.
	 */
	private function make_rsvp( string $approved = '1' ): int {
		$event_id = $this->mock->post( array( 'post_type' => Event::POST_TYPE ) )->get()->ID;

		return (int) wp_insert_comment(
			array(
				'comment_post_ID'  => $event_id,
				'comment_type'     => Rsvp::COMMENT_TYPE,
				'comment_approved' => $approved,
				'user_id'          => 1,
			)
		);
	}

	/**
	 * Send a check-in request.
	 *
	 * @param int    $rsvp_id The RSVP ID.
	 * @param string $state   The state to set.
	 *
	 * @return WP_REST_Response The response.
	 */
	private function request( int $rsvp_id, string $state ): WP_REST_Response {
		$request = new WP_REST_Request(
			'POST',
			sprintf( '/%s/rsvp/%d/check-in', GATHERPRESS_REST_NAMESPACE, $rsvp_id )
		);

		$request->set_param( 'state', $state );

		return rest_do_request( $request );
	}

	/**
	 * The route registers on rest_api_init.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$this->assert_hooks(
			array(
				array(
					'type'     => 'action',
					'name'     => 'rest_api_init',
					'priority' => 10,
					'callback' => array( Rest_Api::get_instance(), 'register_endpoints' ),
				),
			),
			Rest_Api::get_instance()
		);
	}

	/**
	 * Only someone who can manage RSVPs may set a check-in.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_requires_the_rsvp_capability(): void {
		$rsvp_id = $this->make_rsvp();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'author' ) ) );

		$this->assertSame( 403, $this->request( $rsvp_id, 'no-show' )->get_status() );
		$this->assertSame( array(), Setup::get_instance()->get_flags( $rsvp_id ), 'Nothing is saved.' );
	}

	/**
	 * A state that is not registered is refused.
	 *
	 * @covers ::register_endpoints
	 *
	 * @return void
	 */
	public function test_refuses_an_unknown_state(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 400, $this->request( $this->make_rsvp(), 'unknown' )->get_status() );
	}

	/**
	 * Only an approved RSVP can be checked in.
	 *
	 * @covers ::set_check_in
	 *
	 * @return void
	 */
	public function test_refuses_an_rsvp_that_is_not_approved(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( 400, $this->request( $this->make_rsvp( '0' ), 'no-show' )->get_status() );
		$this->assertSame( 400, $this->request( 999999, 'no-show' )->get_status(), 'A missing RSVP is refused.' );
	}

	/**
	 * A state that cannot be stored reports an error.
	 *
	 * @covers ::set_check_in
	 *
	 * @return void
	 */
	public function test_reports_a_state_that_was_not_saved(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$rsvp_id = $this->make_rsvp();

		unregister_taxonomy( Base::TAXONOMY );

		$status = $this->request( $rsvp_id, 'no-show' )->get_status();

		Setup::get_instance()->register_taxonomy();

		$this->assertSame( 500, $status );
	}

	/**
	 * The route sets the state and answers with its value, name and icon.
	 *
	 * @covers ::set_check_in
	 *
	 * @return void
	 */
	public function test_sets_the_state(): void {
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$rsvp_id  = $this->make_rsvp();
		$response = $this->request( $rsvp_id, 'no-show' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'no-show', $response->get_data()['value'] );
		$this->assertSame( 'No-show', $response->get_data()['label'] );
		$this->assertStringContainsString( '<svg', $response->get_data()['icon'] );
		$this->assertInstanceOf( No_Show::class, Check_In_Registry::get_instance()->get_state( $rsvp_id ) );

		$this->assertSame( 'not-recorded', $this->request( $rsvp_id, 'not-recorded' )->get_data()['value'] );
		$this->assertNull( Check_In_Registry::get_instance()->get_state( $rsvp_id ) );
	}
}
