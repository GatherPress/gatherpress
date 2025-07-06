<?php
/**
 * Manages RSVP related functionality for events.
 *
 * This class is responsible for handling all operations related to RSVPs for events, including
 * retrieving RSVP information, saving RSVPs, checking attending limits, and more.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core;

use WP_Comment;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

class Rsvp_Token {
	private ?WP_Comment $comment = null;
	private string $token = '';

	const NAME = 'gatherpress_rsvp_token';

	public function __construct( int $comment_id ) {
		if ( Rsvp::COMMENT_TYPE !== get_comment_type( $comment_id ) ) {
			return;
		}

		$this->comment = get_comment( $comment_id );
	}

	public function get_token(): string {
		if ( $this->token ) {
			return $this->token;
		}

		if ( ! $this->comment ) {
			return '';
		}

		$token = (string) get_comment_meta(
			$this->comment->ID,
			sprintf( '_%s', static::NAME ),
			true
		);

		$this->token = $token;

		return $this->token;
	}

	public function approve_comment(): void {
		if ( ! $this->comment ) {
			return;
		}

		wp_set_comment_status( $this->comment->ID, 'approve' );
	}

	public function generate_token(): self {
		if ( ! $this->comment ) {
			return $this;
		}

		$this->token = wp_generate_password( 32, false );

		update_comment_meta(
			$this->comment->ID,
			sprintf( '_%s', static::NAME ),
			$this->token
		);

		return $this;
	}

	public function get_comment(): ?WP_Comment {
		return $this->comment;
	}

	public function is_valid( string $token ): bool {
		return ! empty( $token ) && $this->get_token() === $token;
	}
}
