<?php
/**
 * Identity Object.
 *
 * @package GatherPress
 */

namespace GatherPress\Core\Rsvp\Response;

use InvalidArgumentException;

/**
 * Identity.
 *
 * @copyright 2025 André Menrath <andre.menrath@posteo.de>
 */
final class Identity {
	/**
	 * Display name of this identity.
	 *
	 * @var string
	 */
	public string $display_name;

	/**
	 * Constructor of an Identity.
	 *
	 * @throws InvalidArgumentException When trying to construct an invalid Identity (e.g. URL as user ID).
	 *
	 * @param Identity_Type $type  Identity type.
	 * @param string|int    $value Identity value.
	 */
	public function __construct(
		public readonly Identity_Type $type,
		public readonly string|int $value
	) {
		$this->assert_valid();
	}

	/**
	 * Validate identity.
	 *
	 * @throws InvalidArgumentException When trying to set an unknown Identity type.
	 * @return void
	 */
	private function assert_valid(): void {
		switch ( $this->type ) {
			case Identity_Type::EMAIL:
				if ( ! filter_var( $this->value, FILTER_VALIDATE_EMAIL ) ) {
					throw new InvalidArgumentException( 'Invalid email.' );
				}
				break;

			case Identity_Type::URL:
				if ( ! filter_var( $this->value, FILTER_VALIDATE_URL ) ) {
					throw new InvalidArgumentException( 'Invalid URL.' );
				}
				break;

			case Identity_Type::WP_USER_ID:
				if ( ! \is_int( $this->value ) ) {
					throw new InvalidArgumentException( 'Invalid ID.' );
				}
				if ( ! get_user_by( 'id', $this->value ) ) {
					throw new InvalidArgumentException( 'User does not exist.' );
				}
				break;
			case Identity_Type::EXTERNAL_ID:
				if ( ! \is_int( $this->value ) ) {
					throw new InvalidArgumentException( 'Invalid ID.' );
				}
				break;
			default:
				throw new InvalidArgumentException( 'Invalid Identity_Type' );
		}
	}
}
