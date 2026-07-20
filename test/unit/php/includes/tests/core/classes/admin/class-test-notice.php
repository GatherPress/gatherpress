<?php
/**
 * Class handles unit tests for GatherPress\Core\Admin\Notice.
 *
 * @package GatherPress\Core\Admin
 * @since 0.34.1
 */

namespace GatherPress\Tests\Core\Admin;

use GatherPress\Core\Admin\Notice;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Notice.
 *
 * @coversDefaultClass \GatherPress\Core\Admin\Notice
 */
class Test_Notice extends Base {

	/**
	 * Clear the dismissal option between tests so state does not leak.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Notice::OPTION_NAME );

		parent::tearDown();
	}

	/**
	 * Coverage for the constructor's defaults.
	 *
	 * @covers ::__construct
	 * @covers ::get_slug
	 * @covers ::get_type
	 * @covers ::is_persistent
	 *
	 * @return void
	 */
	public function test_constructor_defaults(): void {
		$notice = new Notice( 'gatherpress_test' );

		$this->assertSame(
			'gatherpress_test',
			$notice->get_slug(),
			'Failed to assert that the slug was stored.'
		);
		$this->assertSame(
			Notice::TYPE_INFO,
			$notice->get_type(),
			'Failed to assert that the type defaulted to info.'
		);
		$this->assertFalse(
			$notice->is_persistent(),
			'Failed to assert that a notice is non-persistent by default.'
		);
		$this->assertTrue(
			Utility::get_hidden_property( $notice, 'dismissible' ),
			'Failed to assert that a notice is dismissible by default.'
		);
	}

	/**
	 * Coverage for the constructor's overrides.
	 *
	 * @covers ::__construct
	 * @covers ::get_type
	 * @covers ::is_persistent
	 *
	 * @return void
	 */
	public function test_constructor_overrides(): void {
		$notice = new Notice(
			'gatherpress_test',
			array(
				'type'        => Notice::TYPE_ERROR,
				'dismissible' => false,
				'persistent'  => true,
				'capability'  => 'manage_options',
			)
		);

		$this->assertSame(
			Notice::TYPE_ERROR,
			$notice->get_type(),
			'Failed to assert that the type was overridden.'
		);
		$this->assertTrue(
			$notice->is_persistent(),
			'Failed to assert that the notice was marked persistent.'
		);
		$this->assertFalse(
			Utility::get_hidden_property( $notice, 'dismissible' ),
			'Failed to assert that the notice was marked non-dismissible.'
		);
		$this->assertSame(
			'manage_options',
			Utility::get_hidden_property( $notice, 'capability' ),
			'Failed to assert that the capability was stored.'
		);
	}

	/**
	 * Coverage for get_message with a plain string.
	 *
	 * @covers ::get_message
	 *
	 * @return void
	 */
	public function test_get_message_from_string(): void {
		$notice = new Notice( 'gatherpress_test', array( 'message' => 'Plain message.' ) );

		$this->assertSame(
			'Plain message.',
			$notice->get_message(),
			'Failed to assert that a string message was returned as-is.'
		);
	}

	/**
	 * Coverage for get_message with a callable.
	 *
	 * @covers ::get_message
	 *
	 * @return void
	 */
	public function test_get_message_from_callable(): void {
		$notice = new Notice(
			'gatherpress_test',
			array(
				'message' => static function (): string {
					return 'Deferred message.';
				},
			)
		);

		$this->assertSame(
			'Deferred message.',
			$notice->get_message(),
			'Failed to assert that a callable message was resolved at call time.'
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
		update_option( Notice::OPTION_NAME, array( 'gatherpress_test' => time() ) );

		$notice = new Notice( 'gatherpress_test' );

		$this->assertFalse(
			$notice->is_dismissed(),
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
		update_option( Notice::OPTION_NAME, 'corrupted' );

		$notice = new Notice( 'gatherpress_test', array( 'persistent' => true ) );

		$this->assertFalse(
			$notice->is_dismissed(),
			'Failed to assert that a non-array option was treated as no dismissals.'
		);
	}

	/**
	 * Coverage for is_dismissed and dismiss on a persistent notice.
	 *
	 * @covers ::is_dismissed
	 * @covers ::dismiss
	 *
	 * @return void
	 */
	public function test_dismiss_records_the_slug(): void {
		$notice = new Notice( 'gatherpress_test', array( 'persistent' => true ) );

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

		$dismissed = get_option( Notice::OPTION_NAME );

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
		update_option( Notice::OPTION_NAME, 'corrupted' );

		$notice = new Notice( 'gatherpress_test', array( 'persistent' => true ) );

		$this->assertTrue(
			$notice->dismiss(),
			'Failed to assert that dismissal succeeded despite a corrupted option.'
		);
		$this->assertIsArray(
			get_option( Notice::OPTION_NAME ),
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
		$notice = new Notice( 'gatherpress_test' );

		$this->assertFalse(
			$notice->dismiss(),
			'Failed to assert that dismissing a non-persistent notice did nothing.'
		);
		$this->assertFalse(
			get_option( Notice::OPTION_NAME, false ),
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
		$notice = new Notice( 'gatherpress_test' );

		$this->assertTrue(
			$notice->should_render(),
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
		$notice = new Notice( 'gatherpress_test', array( 'capability' => 'manage_options' ) );

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
		$notice = new Notice( 'gatherpress_test', array( 'persistent' => true ) );

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
	 * Coverage for should_render's condition callback.
	 *
	 * @covers ::should_render
	 *
	 * @return void
	 */
	public function test_should_render_respects_condition(): void {
		$failing = new Notice(
			'gatherpress_failing',
			array(
				'condition' => static function (): bool {
					return false;
				},
			)
		);
		$passing = new Notice(
			'gatherpress_passing',
			array(
				'condition' => static function (): bool {
					return true;
				},
			)
		);

		$this->assertFalse(
			$failing->should_render(),
			'Failed to assert that a false condition suppressed the notice.'
		);
		$this->assertTrue(
			$passing->should_render(),
			'Failed to assert that a true condition allowed the notice.'
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
		$transient = new Notice( 'gatherpress_test' );

		$this->assertSame(
			'',
			$transient->get_dismiss_url(),
			'Failed to assert that a non-persistent notice has no dismissal URL.'
		);

		$persistent = new Notice( 'gatherpress_test', array( 'persistent' => true ) );
		$url        = $persistent->get_dismiss_url();

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
		$notice = new Notice( 'gatherpress_test' );
		$output = Utility::buffer_and_return( array( $notice, 'render' ) );

		$this->assertSame(
			'',
			$output,
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
		$notice = new Notice(
			'gatherpress_test',
			array(
				'message' => 'Something to say.',
				'type'    => Notice::TYPE_WARNING,
			)
		);

		$output = Utility::buffer_and_return( array( $notice, 'render' ) );

		$this->assertStringContainsString(
			'Something to say.',
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
		$notice = new Notice(
			'gatherpress_test',
			array(
				'message'    => 'Something to say.',
				'persistent' => true,
			)
		);

		$output = Utility::buffer_and_return( array( $notice, 'render' ) );

		$this->assertStringContainsString(
			'gatherpress_dismiss_notice=gatherpress_test',
			$output,
			'Failed to assert that a persistent notice offered a dismissal link.'
		);
	}
}
