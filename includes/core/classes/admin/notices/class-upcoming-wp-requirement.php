<?php
/**
 * Notice warning that a coming release raises the WordPress requirement.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Core\Admin\Notices;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Upcoming_Wp_Requirement.
 *
 * @since 0.34.1
 */
final class Upcoming_Wp_Requirement extends Upcoming_Requirement {

	/**
	 * The WordPress version GatherPress will require as of UPCOMING_VERSION.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const REQUIRES_WP = '7.0';

	/**
	 * Unique slug identifying this notice.
	 *
	 * @since 0.34.1
	 *
	 * @return string The slug.
	 */
	public function get_slug() {
		return 'gatherpress_upcoming_wp_requirement';
	}

	/**
	 * The WordPress version this notice requires.
	 *
	 * @since 0.34.1
	 *
	 * @return string A version string.
	 */
	public function get_required_version() {
		return self::REQUIRES_WP;
	}

	/**
	 * The WordPress version this site currently runs.
	 *
	 * @since 0.34.1
	 *
	 * @return string A version string.
	 */
	public function get_current_version() {
		return get_bloginfo( 'version' );
	}

	/**
	 * The notice's message.
	 *
	 * @since 0.34.1
	 *
	 * @return string The translated, escaped message.
	 */
	public function get_message() {
		return sprintf(
			/* translators: %1$s: GatherPress version, %2$s: required WP, %3$s: current WP. */
			esc_html__(
				// phpcs:disable Generic.Files.LineLength.TooLong
				'GatherPress %1$s will require WordPress %2$s or higher. This site is running WordPress %3$s. Update WordPress to keep receiving GatherPress updates.',
				// phpcs:enable Generic.Files.LineLength.TooLong
				'gatherpress'
			),
			esc_html( self::UPCOMING_VERSION ),
			esc_html( $this->get_required_version() ),
			esc_html( $this->get_current_version() )
		);
	}
}
