<?php
/**
 * Unit tests for the uninstall task that removes notice bookkeeping.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Uninstall;

use GatherPress\Core\Admin\Notices\Base as Notice;
use GatherPress\Core\Admin\Notices\Setup as Notices_Setup;
use GatherPress\Core\Admin\Notices\Welcome;
use GatherPress\Core\Uninstall\Notices;
use GatherPress\Tests\Base as Unit_Test_Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Notices.
 *
 * @coversDefaultClass \GatherPress\Core\Uninstall\Notices
 */
class Test_Notices extends Unit_Test_Base {

	/**
	 * The task runs without an opt-in setting.
	 *
	 * Bookkeeping rather than data, so it is safe the way the transient wipe
	 * is safe.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::applies
	 *
	 * @return void
	 */
	public function test_applies_without_an_opt_in(): void {
		$this->assertTrue(
			( new Notices() )->applies(),
			'Failed to assert the notice cleanup needs no opt-in.'
		);
	}

	/**
	 * Uninstalling removes the shared record and whatever notices own.
	 *
	 * The activation record is not named here: it is read from the notice
	 * that owns it, so a future notice with its own state is cleaned up
	 * without touching this task.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_uninstall_site_removes_the_notice_options(): void {
		update_option( Notice::OPTION_NAME, array( 'gatherpress_welcome' => time() ) );
		update_option( Welcome::OPTION_ACTIVATED, true );

		Utility::invoke_hidden_method( new Notices(), 'uninstall_site' );

		$this->assertFalse(
			get_option( Notice::OPTION_NAME, false ),
			'Failed to assert the dismissal record was removed.'
		);

		$this->assertFalse(
			get_option( Welcome::OPTION_ACTIVATED, false ),
			'Failed to assert the activation record was removed.'
		);
	}

	/**
	 * A notice's own options are discovered rather than hardcoded.
	 *
	 * @since 0.36.0
	 *
	 * @covers ::uninstall_site
	 *
	 * @return void
	 */
	public function test_uninstall_site_removes_options_a_notice_declares(): void {
		$registry = Notices_Setup::get_instance();
		$notice   = new class() extends Notice {

			/**
			 * Unique slug identifying this notice.
			 *
			 * @return string The slug.
			 */
			public function get_slug(): string {
				return 'gatherpress_test_uninstall';
			}

			/**
			 * The notice's message.
			 *
			 * @return string The message.
			 */
			public function get_message(): string {
				return 'Test message.';
			}

			/**
			 * Options this notice owns.
			 *
			 * @return string[] Option names.
			 */
			public function get_options(): array {
				return array( 'gatherpress_test_uninstall_option' );
			}
		};

		$registry->add( $notice );
		update_option( 'gatherpress_test_uninstall_option', 'kept' );

		Utility::invoke_hidden_method( new Notices(), 'uninstall_site' );

		$this->assertFalse(
			get_option( 'gatherpress_test_uninstall_option', false ),
			'Failed to assert an option a notice declares is removed.'
		);
	}
}
