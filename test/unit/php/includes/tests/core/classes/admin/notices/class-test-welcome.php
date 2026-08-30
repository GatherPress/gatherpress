<?php
/**
 * Unit tests for the post-activation welcome notice.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Admin\Notices;

use GatherPress\Core\Admin\Notices\Base;
use GatherPress\Core\Admin\Notices\Welcome;
use GatherPress\Tests\Base as Unit_Test_Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Welcome.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Notices\Welcome
 */
class Test_Welcome extends Unit_Test_Base {

	/**
	 * The notice under test.
	 *
	 * @var Welcome
	 */
	private Welcome $notice;

	/**
	 * Set up test environment.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->notice = new Welcome();
	}

	/**
	 * Tear down test environment.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( Base::OPTION_NAME );

		parent::tear_down();
	}

	/**
	 * The notice identifies itself and remembers being dismissed.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_slug
	 * @covers ::get_type
	 * @covers ::is_dismissible
	 * @covers ::is_persistent
	 * @covers ::get_capability
	 *
	 * @return void
	 */
	public function test_declares_itself_as_a_persistent_card(): void {
		$this->assertSame( 'gatherpress_welcome', $this->notice->get_slug() );
		$this->assertSame( Base::TYPE_INFO, $this->notice->get_type() );

		// The card draws its own dismiss control, so core's per-view one would
		// sit beside it as a second, weaker X.
		$this->assertFalse(
			$this->notice->is_dismissible(),
			'Failed to assert the card suppresses core\'s per-view dismiss button.'
		);

		$this->assertTrue(
			$this->notice->is_persistent(),
			'Failed to assert dismissing the welcome is remembered.'
		);

		$this->assertSame(
			'edit_posts',
			$this->notice->get_capability(),
			'Failed to assert the notice is gated on the capability its action needs.'
		);
	}


	/**
	 * The card declares the content the notices API renders.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_headline
	 * @covers ::get_message
	 * @covers ::get_action_url
	 * @covers ::get_action_label
	 *
	 * @return void
	 */
	public function test_declares_its_content(): void {
		$this->assertNotEmpty( $this->notice->get_headline() );
		$this->assertNotEmpty( $this->notice->get_message() );
		$this->assertNotEmpty( $this->notice->get_action_label() );

		// Creating an event is the thing the plugin exists to do.
		$this->assertStringContainsString(
			'post-new.php',
			$this->notice->get_action_url(),
			'Failed to assert the call to action opens a new event.'
		);
	}

	/**
	 * The plugins screen is where activation lands, so it shows there.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::applies
	 * @covers ::is_supported_screen
	 *
	 * @return void
	 */
	public function test_applies_on_the_plugins_screen(): void {
		set_current_screen( 'plugins' );

		$this->assertTrue(
			$this->notice->applies(),
			'Failed to assert the welcome applies where activation lands.'
		);
	}

	/**
	 * It also applies on GatherPress screens.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::is_supported_screen
	 *
	 * @return void
	 */
	public function test_applies_on_a_gatherpress_screen(): void {
		set_current_screen( 'edit-gatherpress_event' );

		$this->assertTrue(
			$this->notice->applies(),
			'Failed to assert someone who went straight to Events still finds it.'
		);
	}

	/**
	 * Somebody else's screen is not the place for it.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::is_supported_screen
	 *
	 * @return void
	 */
	public function test_does_not_apply_elsewhere_in_the_admin(): void {
		set_current_screen( 'edit-post' );

		$this->assertFalse(
			$this->notice->applies(),
			'Failed to assert the welcome stays off unrelated screens.'
		);
	}

	/**
	 * With no screen at all there is nothing to render onto.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::is_supported_screen
	 *
	 * @return void
	 */
	public function test_does_not_apply_without_a_screen(): void {

		$this->assertFalse(
			Utility::invoke_hidden_method( $this->notice, 'is_supported_screen' ),
			'Failed to assert an absent screen is not a supported one.'
		);
	}

	/**
	 * The rendered card carries the mark, the headline and the action.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::get_headline
	 *
	 * @return void
	 */
	public function test_renders_as_a_card(): void {
		set_current_screen( 'plugins' );

		$notice = $this->notice;
		$output = Utility::buffer_and_return(
			static function () use ( $notice ): void {
				$notice->render();
			}
		);

		$this->assertStringContainsString( '<svg', $output, 'Failed to assert the mark rendered.' );
		$this->assertStringContainsString(
			'button button-primary',
			$output,
			'Failed to assert the call to action rendered.'
		);
		$this->assertStringContainsString(
			'notice-dismiss',
			$output,
			'Failed to assert the card can be dismissed for good.'
		);
	}
}
