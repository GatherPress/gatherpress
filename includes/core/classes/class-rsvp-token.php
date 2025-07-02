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
	private ?string $token;

	public function __construct( int $comment_id ) {
		$this->comment = get_comment( $comment_id );
	}

	public function set_token( string $token ): self {

		return $this;
	}

	public function get_token(): ?string {
		return $this->token;
	}

	public function approve(): void {

	}

	public function generate_token(): void {
		$token = wp_generate_password( 32, false );

		if ( $this->comment ) {
			add_comment_meta(
				$this->comment->ID,
				sprintf( '_%s_token', Rsvp::COMMENT_TYPE ),
				$token
			);
		}
	}

	public function get_comment(): ?WP_Comment {
		return $this->comment;
	}

	public function is_valid(): bool {
		return true;
	}
}
