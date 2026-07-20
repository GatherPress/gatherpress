<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Notices\Base.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Tests\Core\Admin\Notices;

use GatherPress\Core\Admin\Notices\Base;
use GatherPress\Tests\Base as Unit_Test_Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Base.
 *
 * Base is abstract, so these tests drive it through a double that implements
 * only the two abstract methods and otherwise defers to the parent, which is
 * what exercises Base's own defaults.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Notices\Base
 */
class Test_Base extends Unit_Test_Base {

	/**
	 * Clear the dismissal option between tests so state does not leak.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Base::OPTION_NAME );

		parent::tearDown();
	}

	/**
	 * Build a concrete Base for testing.
	 *
	 * Any key left out falls through to Base's own implementation.
	 *
	 * @param array $args Optional overrides keyed by the method they replace.
	 *
	 * @return Base The test double.
	 */
	private function make_notice( array $args = array() ): Base {
		return new class( $args ) extends Base {

			/**
			 * Overrides keyed by the method they replace.
			 *
			 * @var array
			 */
			private array $args;

			/**
			 * Class constructor.
			 *
			 * @param array $args Overrides keyed by the method they replace.
			 */
			public function __construct( array $args ) {
				$this->args = $args;
			}

			/**
			 * Unique slug identifying this notice.
			 *
			 * @return string The slug.
			 */
			public function get_slug() {
				return $this->args['slug'] ?? 'gatherpress_test';
			}

			/**
			 * The notice's message.
			 *
			 * @return string The message.
			 */
			public function get_message() {
				return $this->args['message'] ?? 'Test message.';
			}

			/**
			 * The notice's type.
			 *
			 * @return string One of the TYPE_* constants.
			 */
			public function get_type() {
				return $this->args['type'] ?? parent::get_type();
			}

			/**
			 * Whether the notice can be closed for the current page view.
			 *
			 * @return bool True when dismissible.
			 */
			public function is_dismissible() {
				return $this->args['dismissible'] ?? parent::is_dismissible();
			}

			/**
			 * Whether dismissal is remembered across page loads.
			 *
			 * @return bool True when persistent.
			 */
			public function is_persistent() {
				return $this->args['persistent'] ?? parent::is_persistent();
			}

			/**
			 * Capability required to see the notice.
			 *
			 * @return string The capability, or an empty string.
			 */
			public function get_capability() {
				return $this->args['capability'] ?? parent::get_capability();
			}

			/**
			 * Whether the notice's condition currently holds.
			 *
			 * @return bool True when it applies.
			 */
			public function applies() {
				return $this->args['applies'] ?? parent::applies();
			}
		};
	}

	/**
	 * Coverage for the defaults Base provides.
	 *
	 * @covers ::get_type
	 * @covers ::is_dismissible
	 * @covers ::is_persistent
	 * @covers ::get_capability
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_defaults(): void {
		$notice = $this->make_notice();

		$this->assertSame(
			Base::TYPE_INFO,
			$notice->get_type(),
			'Failed to assert that a notice defaults to the info type.'
		);
		$this->assertTrue(
			$notice->is_dismissible(),
			'Failed to assert that a notice is dismissible by default.'
		);
		$this->assertFalse(
			$notice->is_persistent(),
			'Failed to assert that a notice is non-persistent by default.'
		);
		$this->assertSame(
			'',
			$notice->get_capability(),
			'Failed to assert that a notice is ungated by default.'
		);
		$this->assertTrue(
			$notice->applies(),
			'Failed to assert that a notice applies by default.'
		);
	}

	/**
	 * Coverage for is_dismissed on a non-persistent notice.
	 *
	 * @covers ::is_dismissed
	 *
	 * @return void
	 */
	public function test_is_dismissed_is_false_when_not_persistent(): void {
		update_option( Base::OPTION_NAME, array( 'gatherpress_test' => time() ) );

		$this->assertFalse(
			$this->make_notice()->is_dismissed(),
			'Failed to assert that a non-persistent notice is never treated as dismissed.'
		);
	}

	/**
	 * Coverage for is_dismissed when the option holds an unexpected type.
	 *
	 * @covers ::is_dismissed
	 *
	 * @return void
	 */
	public function test_is_dismissed_is_false_when_option_is_not_an_array(): void {
		update_option( Base::OPTION_NAME, 'corrupted' );

		$this->assertFalse(
			$this->make_notice( array( 'persistent' => true ) )->is_dismissed(),
			'Failed to assert that a non-array option was treated as no dismissals.'
		);
	}

	/**
	 * Coverage for dismiss and is_dismissed on a persistent notice.
	 *
	 * @covers ::dismiss
	 * @covers ::is_dismissed
	 *
	 * @return void
	 */
	public function test_dismiss_records_the_slug(): void {
		$notice = $this->make_notice( array( 'persistent' => true ) );

		$this->assertFalse(
			$notice->is_dismissed(),
			'Failed to assert that the notice started undismissed.'
		);
		$this->assertTrue(
			$notice->dismiss(),
			'Failed to assert that the dismissal was recorded.'
		);
		$this->assertTrue(
			$notice->is_dismissed(),
			'Failed to assert that the notice reads as dismissed afterwards.'
		);

		$dismissed = get_option( Base::OPTION_NAME );

		$this->assertArrayHasKey(
			'gatherpress_test',
			$dismissed,
			'Failed to assert that the slug was written to the option.'
		);
		$this->assertIsInt(
			$dismissed['gatherpress_test'],
			'Failed to assert that a timestamp was stored against the slug.'
		);
	}

	/**
	 * Coverage for dismiss when the option holds an unexpected type.
	 *
	 * @covers ::dismiss
	 *
	 * @return void
	 */
	public function test_dismiss_recovers_from_a_corrupted_option(): void {
		update_option( Base::OPTION_NAME, 'corrupted' );

		$this->assertTrue(
			$this->make_notice( array( 'persistent' => true ) )->dismiss(),
			'Failed to assert that dismissal succeeded despite a corrupted option.'
		);
		$this->assertIsArray(
			get_option( Base::OPTION_NAME ),
			'Failed to assert that the option was reset to an array.'
		);
	}

	/**
	 * Coverage for dismiss on a non-persistent notice.
	 *
	 * @covers ::dismiss
	 *
	 * @return void
	 */
	public function test_dismiss_is_a_noop_when_not_persistent(): void {
		$this->assertFalse(
			$this->make_notice()->dismiss(),
			'Failed to assert that dismissing a non-persistent notice did nothing.'
		);
		$this->assertFalse(
			get_option( Base::OPTION_NAME, false ),
			'Failed to assert that no option was written.'
		);
	}

	/**
	 * Coverage for should_render with nothing standing in the way.
	 *
	 * @covers ::should_render
	 *
	 * @return void
	 */
	public function test_should_render_with_no_gates(): void {
		$this->assertTrue(
			$this->make_notice()->should_render(),
			'Failed to assert that an ungated notice renders.'
		);
	}

	/**
	 * Coverage for should_render's capability gate.
	 *
	 * @covers ::should_render
	 *
	 * @return void
	 */
	public function test_should_render_respects_capability(): void {
		$notice = $this->make_notice( array( 'capability' => 'manage_options' ) );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertFalse(
			$notice->should_render(),
			'Failed to assert that a user without the capability was gated out.'
		);

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue(
			$notice->should_render(),
			'Failed to assert that a user with the capability was let through.'
		);
	}

	/**
	 * Coverage for should_render once the notice is dismissed.
	 *
	 * @covers ::should_render
	 *
	 * @return void
	 */
	public function test_should_render_respects_dismissal(): void {
		$notice = $this->make_notice( array( 'persistent' => true ) );

		$this->assertTrue(
			$notice->should_render(),
			'Failed to assert that the notice rendered before dismissal.'
		);

		$notice->dismiss();

		$this->assertFalse(
			$notice->should_render(),
			'Failed to assert that the notice stopped rendering after dismissal.'
		);
	}

	/**
	 * Coverage for should_render when the condition does not hold.
	 *
	 * @covers ::should_render
	 *
	 * @return void
	 */
	public function test_should_render_respects_applies(): void {
		$this->assertFalse(
			$this->make_notice( array( 'applies' => false ) )->should_render(),
			'Failed to assert that a notice whose condition fails does not render.'
		);
	}

	/**
	 * Coverage for get_dismiss_url.
	 *
	 * @covers ::get_dismiss_url
	 *
	 * @return void
	 */
	public function test_get_dismiss_url(): void {
		$this->assertSame(
			'',
			$this->make_notice()->get_dismiss_url(),
			'Failed to assert that a non-persistent notice has no dismissal URL.'
		);

		$url = $this->make_notice( array( 'persistent' => true ) )->get_dismiss_url();

		$this->assertStringContainsString(
			'gatherpress_dismiss_notice=gatherpress_test',
			$url,
			'Failed to assert that the dismissal URL carried the slug.'
		);
		$this->assertStringContainsString(
			'_wpnonce=',
			$url,
			'Failed to assert that the dismissal URL was nonced.'
		);
	}

	/**
	 * Coverage for render with an empty message.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_is_silent_without_a_message(): void {
		$notice = $this->make_notice( array( 'message' => '' ) );

		$this->assertSame(
			'',
			Utility::buffer_and_return( array( $notice, 'render' ) ),
			'Failed to assert that a notice with no message rendered nothing.'
		);
	}

	/**
	 * Coverage for render's markup.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_outputs_the_message(): void {
		$notice = $this->make_notice( array( 'type' => Base::TYPE_WARNING ) );
		$output = Utility::buffer_and_return( array( $notice, 'render' ) );

		$this->assertStringContainsString(
			'Test message.',
			$output,
			'Failed to assert that the message was rendered.'
		);
		$this->assertStringContainsString(
			'notice-warning',
			$output,
			'Failed to assert that the type became a class.'
		);
		$this->assertStringContainsString(
			'gatherpress-test',
			$output,
			'Failed to assert that the slug became the element id.'
		);
	}

	/**
	 * Coverage for render appending the dismissal link.
	 *
	 * @covers ::render
	 *
	 * @return void
	 */
	public function test_render_appends_dismiss_link_when_persistent(): void {
		$notice = $this->make_notice( array( 'persistent' => true ) );
		$output = Utility::buffer_and_return( array( $notice, 'render' ) );

		$this->assertStringContainsString(
			'gatherpress_dismiss_notice=gatherpress_test',
			$output,
			'Failed to assert that a persistent notice offered a dismissal link.'
		);
	}
}
