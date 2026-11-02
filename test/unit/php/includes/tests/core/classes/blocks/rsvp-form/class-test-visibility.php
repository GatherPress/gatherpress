<?php
/**
 * Class handles unit tests for GatherPress\Core\Blocks\Rsvp_Form\Visibility.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core\Blocks\Rsvp_Form;

use GatherPress\Core\Blocks\Rsvp_Form\Visibility;
use GatherPress\Tests\Base;

/**
 * Class Test_Visibility.
 *
 * @coversDefaultClass \GatherPress\Core\Blocks\Rsvp_Form\Visibility
 */
class Test_Visibility extends Base {

	/**
	 * A rule that is not a JSON object changes nothing, and neither does an empty one.
	 *
	 * @since TBD
	 *
	 * @covers ::__construct
	 * @covers ::should_show
	 *
	 * @return void
	 */
	public function test_should_show_without_rules(): void {
		$visibility = new Visibility( false, false );

		$this->assertNull( $visibility->should_show( 'invalid-json' ), 'Should return null for invalid JSON' );
		$this->assertNull( $visibility->should_show( '{}' ), 'Should return null for an empty rule' );
	}

	/**
	 * A whenPast rule decides once the event has passed.
	 *
	 * @since TBD
	 *
	 * @covers ::should_show
	 *
	 * @return void
	 */
	public function test_should_show_when_past(): void {
		$past = new Visibility( false, true );

		$this->assertTrue( $past->should_show( '{"whenPast":"show"}' ) );
		$this->assertFalse( $past->should_show( '{"whenPast":"hide"}' ) );
	}

	/**
	 * A block with only a whenPast rule is the inverse before the event has passed.
	 *
	 * @since TBD
	 *
	 * @covers ::should_show
	 *
	 * @return void
	 */
	public function test_should_show_before_past_with_only_when_past(): void {
		$upcoming = new Visibility( false, false );

		$this->assertFalse(
			$upcoming->should_show( '{"whenPast":"show"}' ),
			'A past-only block waits for the event to pass.'
		);
		$this->assertTrue( $upcoming->should_show( '{"whenPast":"hide"}' ) );
	}

	/**
	 * An onSuccess rule follows whether the form was just submitted.
	 *
	 * @since TBD
	 *
	 * @covers ::should_show
	 *
	 * @return void
	 */
	public function test_should_show_on_success(): void {
		$success = new Visibility( true, false );
		$default = new Visibility( false, false );

		$this->assertTrue( $success->should_show( '{"onSuccess":"show"}' ) );
		$this->assertFalse( $success->should_show( '{"onSuccess":"hide"}' ) );
		$this->assertFalse( $default->should_show( '{"onSuccess":"show"}' ) );
		$this->assertTrue( $default->should_show( '{"onSuccess":"hide"}' ) );
	}

	/**
	 * A canceled event shows what whenCanceled asks for and hides the RSVP flow.
	 *
	 * @since TBD
	 *
	 * @covers ::should_show
	 * @covers ::should_show_when_canceled
	 *
	 * @return void
	 */
	public function test_should_show_when_canceled(): void {
		$canceled = new Visibility( false, false, true );
		$upcoming = new Visibility( false, false );

		$this->assertTrue( $canceled->should_show( '{"whenCanceled":"show"}' ) );
		$this->assertFalse( $canceled->should_show( '{"whenCanceled":"hide"}' ) );
		$this->assertFalse( $canceled->should_show( '{"whenPast":"show"}' ), 'A past-only block hides when canceled.' );
		$this->assertFalse( $canceled->should_show( '{"onSuccess":"hide"}' ), 'A form field hides when canceled.' );
		$this->assertNull( $canceled->should_show( '{"other":"value"}' ) );
		$this->assertFalse( $upcoming->should_show( '{"whenCanceled":"show"}' ), 'A canceled notice hides otherwise.' );
		$this->assertNull( $upcoming->should_show( '{"whenCanceled":"hide"}' ) );
	}

	/**
	 * A canceled notice is found at any depth, unless a parent hides it.
	 *
	 * @since TBD
	 *
	 * @covers ::has_canceled_notice
	 * @covers ::should_show_when_canceled
	 *
	 * @return void
	 */
	public function test_has_canceled_notice(): void {
		$notice = array(
			'attrs' => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'whenCanceled' => 'show' ),
				),
			),
		);

		$this->assertFalse( Visibility::has_canceled_notice( array() ) );
		$this->assertFalse( Visibility::has_canceled_notice( array( array( 'innerBlocks' => array() ) ) ) );
		$this->assertTrue( Visibility::has_canceled_notice( array( $notice ) ) );
		$this->assertTrue(
			Visibility::has_canceled_notice( array( array( 'innerBlocks' => array( $notice ) ) ) ),
			'A nested canceled notice is found.'
		);

		$hidden_group = array(
			'attrs'       => array(
				'metadata' => array(
					'gatherpressRsvpFormVisibility' => array( 'whenPast' => 'hide' ),
				),
			),
			'innerBlocks' => array( $notice ),
		);

		$this->assertFalse(
			Visibility::has_canceled_notice( array( $hidden_group ) ),
			'A notice inside a block hidden for a canceled event does not count.'
		);
	}
}
