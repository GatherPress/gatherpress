<?php
/**
 * Class handles unit tests for GatherPress\Core\Mailer.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Mailer;
use GatherPress\Tests\Base;

/**
 * Class Test_Mailer.
 *
 * @coversDefaultClass \GatherPress\Core\Mailer
 */
class Test_Mailer extends Base {

	/**
	 * Check that recipients without email addresses are rejected.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_rejects_empty_email(): void {
		$recipient = $this->recipient( array( 'email' => '' ) );

		$this->assertFalse( Mailer::get_instance()->is_eligible( $recipient ) );
	}

	/**
	 * Check that users are eligible by default.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_accepts_user_by_default(): void {
		$user_id = $this->factory->user->create();

		$this->assertTrue(
			Mailer::get_instance()->is_eligible(
				$this->recipient(
					array(
						'user_id' => $user_id,
						'email'   => get_userdata( $user_id )->user_email,
					)
				)
			)
		);
	}

	/**
	 * Check that opted-out users are rejected.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_rejects_opted_out_user(): void {
		$user_id = $this->factory->user->create();
		update_user_meta( $user_id, 'gatherpress_event_updates_opt_in', '0' );

		$this->assertFalse(
			Mailer::get_instance()->is_eligible(
				$this->recipient( array( 'user_id' => $user_id ) )
			)
		);
	}

	/**
	 * Check that anonymous RSVP recipients honor comment opt-out meta.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_rejects_opted_out_comment(): void {
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $this->factory->post->create(),
				'comment_author'       => 'Anonymous',
				'comment_author_email' => 'anonymous@example.test',
				'comment_approved'     => 1,
			)
		);
		update_comment_meta( $comment_id, 'gatherpress_event_updates_opt_in', '0' );

		$this->assertFalse(
			Mailer::get_instance()->is_eligible(
				$this->recipient(
					array(
						'is_user'    => false,
						'comment_id' => $comment_id,
						'email'      => 'anonymous@example.test',
					)
				)
			)
		);
	}

	/**
	 * Check that anonymous RSVP recipients without opt-out meta are eligible.
	 *
	 * @covers ::is_eligible
	 *
	 * @return void
	 */
	public function test_is_eligible_accepts_comment_without_opt_out(): void {
		$this->assertTrue(
			Mailer::get_instance()->is_eligible(
				$this->recipient(
					array(
						'is_user' => false,
						'email'   => 'anonymous@example.test',
					)
				)
			)
		);
	}

	/**
	 * Check that non-user recipients keep the current context.
	 *
	 * @covers ::switch_context
	 *
	 * @return void
	 */
	public function test_switch_context_skips_non_user(): void {
		$before = get_current_user_id();

		$this->assertFalse(
			Mailer::get_instance()->switch_context( $this->recipient( array( 'is_user' => false ) ) )
		);
		$this->assertSame( $before, get_current_user_id() );
	}

	/**
	 * Check that user recipients become the current user.
	 *
	 * @covers ::switch_context
	 *
	 * @return void
	 */
	public function test_switch_context_sets_user(): void {
		$user_id = $this->factory->user->create();

		$this->assertFalse(
			Mailer::get_instance()->switch_context( $this->recipient( array( 'user_id' => $user_id ) ) )
		);
		$this->assertSame( $user_id, get_current_user_id() );
	}

	/**
	 * Check that restoring context makes the sender current again.
	 *
	 * @covers ::restore_context
	 *
	 * @return void
	 */
	public function test_restore_context_sets_sender(): void {
		$sender_id = $this->factory->user->create();
		$target_id = $this->factory->user->create();
		wp_set_current_user( $target_id );

		Mailer::get_instance()->restore_context( false, get_userdata( $sender_id ) );

		$this->assertSame( $sender_id, get_current_user_id() );
	}

	/**
	 * Check that delivery normalizes the subject and passes headers to wp_mail.
	 *
	 * @covers ::deliver
	 *
	 * @return void
	 */
	public function test_deliver_normalizes_subject_and_headers(): void {
		$captured = array();
		add_filter(
			'pre_wp_mail',
			static function ( $previous, $atts ) use ( &$captured ): bool {
				$captured = $atts;
				return true;
			},
			10,
			2
		);

		$result = Mailer::get_instance()->deliver(
			'recipient@example.test',
			"Tom &amp; Jerry\\'s update",
			'<p>Message</p>',
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		remove_all_filters( 'pre_wp_mail' );

		$this->assertTrue( $result );
		$this->assertSame( "Tom & Jerry's update", $captured['subject'] );
		$this->assertSame( array( 'Content-Type: text/html; charset=UTF-8' ), $captured['headers'] );
	}

	/**
	 * Check that delivery returns false when wp_mail rejects the message.
	 *
	 * @covers ::deliver
	 *
	 * @return void
	 */
	public function test_deliver_returns_false_when_mail_fails(): void {
		add_filter( 'pre_wp_mail', '__return_false' );
		$result = Mailer::get_instance()->deliver( 'recipient@example.test', 'Subject', 'Body' );
		remove_filter( 'pre_wp_mail', '__return_false' );

		$this->assertFalse( $result );
	}

	/**
	 * Build a recipient row for the tests.
	 *
	 * @param array $overrides Recipient values to override.
	 *
	 * @return array<string, bool|int|string> Recipient row.
	 */
	private function recipient( array $overrides = array() ): array {
		return array_merge(
			array(
				'is_user'    => true,
				'user_id'    => 0,
				'comment_id' => 0,
				'email'      => 'recipient@example.test',
				'name'       => 'Recipient',
			),
			$overrides
		);
	}
}
