<?php
/**
 * Notice warning that a coming release raises the PHP requirement.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Core\Admin\Notices;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Upcoming_Php_Requirement.
 *
 * @since 0.34.1
 */
final class Upcoming_Php_Requirement extends Upcoming_Requirement {

	/**
	 * The PHP version GatherPress will require as of UPCOMING_VERSION.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const REQUIRES_PHP = '8.1';

	/**
	 * Unique slug identifying this notice.
	 *
	 * @since 0.34.1
	 *
	 * @return string The slug.
	 */
	public function get_slug(): string {
		return 'gatherpress_upcoming_php_requirement';
	}

	/**
	 * The PHP version this notice requires.
	 *
	 * @since 0.34.1
	 *
	 * @return string A version string.
	 */
	public function get_required_version(): string {
		return self::REQUIRES_PHP;
	}

	/**
	 * The PHP version this site currently runs.
	 *
	 * @since 0.34.1
	 *
	 * @return string A version string.
	 */
	public function get_current_version(): string {
		return PHP_VERSION;
	}

	/**
	 * The notice's message.
	 *
	 * @since 0.34.1
	 *
	 * @return string The translated, escaped message.
	 */
	public function get_message(): string {
		return sprintf(
			/* translators: %1$s: GatherPress version, %2$s: required PHP, %3$s: current PHP. */
			esc_html__(
				// phpcs:disable Generic.Files.LineLength.TooLong
				'GatherPress %1$s will require PHP %2$s or higher. This site is running PHP %3$s. Update PHP, or ask your host to, to keep receiving GatherPress updates.',
				// phpcs:enable Generic.Files.LineLength.TooLong
				'gatherpress'
			),
			esc_html( self::UPCOMING_VERSION ),
			esc_html( $this->get_required_version() ),
			esc_html( $this->get_current_version() )
		);
	}
}
