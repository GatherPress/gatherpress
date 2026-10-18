<?php
/**
 * RSVP confirmation emails.
 *
 * @package GatherPress\Core\Rsvp
 * @since TBD
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Mailer;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Utility;

/**
 * Class Email.
 *
 * Decides who the RSVP confirmation email goes to and what it says. Someone
 * who RSVPs without an account gets a link that confirms their RSVP. Mailer
 * queues and sends it.
 *
 * @since TBD
 *
 * @phpstan-import-type Recipient from Mailer
 * @phpstan-import-type Payload from Mailer
 */
final class Email {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The Mailer type for RSVP confirmation emails.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const CONFIRMATION_TYPE = 'rsvp_confirmation';

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 */
	protected function __construct() {
		$this->register_mail_types();
	}

	/**
	 * Tell Mailer who the confirmation email goes to and what it says.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	private function register_mail_types(): void {
		// The link is what confirms the RSVP, so it skips the opt-in.
		Mailer::get_instance()->register(
			self::CONFIRMATION_TYPE,
			array(
				'context'    => Mailer::CONTEXT_RSVP,
				'recipients' => $this->get_confirmation_recipient( ... ),
				'compose'    => $this->compose_confirmation_email( ... ),
			)
		);
	}

	/**
	 * Queue the confirmation email for an RSVP.
	 *
	 * @since TBD
	 *
	 * @param int $comment_id The RSVP comment ID, which already has a token.
	 *
	 * @return bool True when the email is queued.
	 */
	public function queue_confirmation( int $comment_id ): bool {
		return Mailer::get_instance()->queue( self::CONFIRMATION_TYPE, array( 'comment_id' => $comment_id ) );
	}

	/**
	 * Get the person a queued confirmation email goes to.
	 *
	 * @since TBD
	 *
	 * @param array $payload The queued email: its `comment_id`.
	 * @phpstan-param Payload $payload
	 *
	 * @return array<int, Recipient> The RSVP's email address, or nothing when it has no token to send.
	 */
	private function get_confirmation_recipient( array $payload ): array {
		$token   = new Token( (int) ( $payload['comment_id'] ?? 0 ) );
		$comment = $token->get_comment();

		// The link carries the token, so without one there is nothing to send.
		if ( ! $comment || '' === $token->get_token() ) {
			return array();
		}

		return array(
			array(
				'is_user'    => false,
				'user_id'    => 0,
				'comment_id' => (int) $comment->comment_ID,
				'email'      => $token->get_email(),
				'name'       => $comment->comment_author,
			),
		);
	}

	/**
	 * Build a queued confirmation email.
	 *
	 * @since TBD
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 *
	 * @param array $recipient The person who RSVP'd, required by Mailer's compose signature.
	 * @param array $payload   The queued email: its `comment_id`.
	 * @phpstan-param Recipient $recipient
	 * @phpstan-param Payload $payload
	 *
	 * @return array{subject: string, body: string, headers: array<int, string>} The email.
	 */
	private function compose_confirmation_email( array $recipient, array $payload ): array {
		$token = new Token( (int) ( $payload['comment_id'] ?? 0 ) );
		$post  = $token->get_post();
		$title = $post ? get_the_title( $post ) : __( 'this event', 'gatherpress' );

		return array(
			'subject' => sprintf(
				/* translators: %s: Event title. */
				__( 'Confirm your RSVP for %s', 'gatherpress' ),
				$title
			),
			'body'    => Utility::render_template(
				sprintf( '%s/includes/templates/admin/emails/rsvp-token-confirmation.php', GATHERPRESS_CORE_PATH ),
				array(
					'event_id'  => $post ? $post->ID : 0,
					'token_url' => $token->generate_url(),
				)
			),
			'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
		);
	}
}
