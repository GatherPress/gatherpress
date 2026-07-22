<?php
/**
 * Shared base for notices warning about an upcoming requirement bump.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Core\Admin\Notices;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Upcoming_Requirement.
 *
 * Advisory, not blocking: the site works today, but a coming release raises a
 * floor it does not yet meet.
 *
 * Exists so the release that raises the requirements is named in exactly one
 * place, and so the comparison is written once rather than per notice.
 *
 * @since 0.34.1
 */
abstract class Upcoming_Requirement extends Base {

	/**
	 * The GatherPress release that raises the requirements.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const UPCOMING_VERSION = '0.35.0';

	/**
	 * The version this notice requires.
	 *
	 * @since 0.34.1
	 *
	 * @return string A version string.
	 */
	abstract public function get_required_version(): string;

	/**
	 * The version this site currently runs.
	 *
	 * @since 0.34.1
	 *
	 * @return string A version string.
	 */
	abstract public function get_current_version(): string;

	/**
	 * The notice's type.
	 *
	 * @since 0.34.1
	 *
	 * @return string One of the TYPE_* constants.
	 */
	public function get_type(): string {
		return self::TYPE_WARNING;
	}

	/**
	 * Capability required to see the notice.
	 *
	 * Updating PHP or WordPress is not something a subscriber or an editor can
	 * act on, and on multisite it is not something a site administrator can act
	 * on either. `update_plugins` lines up with who can actually respond.
	 *
	 * @since 0.34.1
	 *
	 * @return string The capability.
	 */
	public function get_capability(): string {
		return 'update_plugins';
	}

	/**
	 * Whether dismissing the notice is remembered across page loads.
	 *
	 * Deliberately false. These notices can be closed for the current page
	 * view, but they come back on the next load and keep coming back until the
	 * site is actually updated. A requirement warning that can be silenced
	 * forever defeats its own purpose, and would be silenced by exactly the
	 * person who most needs to see it.
	 *
	 * @since 0.34.1
	 *
	 * @return bool Always false.
	 */
	public function is_persistent(): bool {
		return false;
	}

	/**
	 * Whether this site falls below the upcoming requirement.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when the site does not yet meet the coming floor.
	 */
	public function applies(): bool {
		return $this->is_below( $this->get_current_version(), $this->get_required_version() );
	}

	/**
	 * Compare two versions.
	 *
	 * Takes both versions as arguments rather than reading the environment, so
	 * the comparison is testable in both directions without the suite having to
	 * run on an old PHP or an old WordPress.
	 *
	 * @since 0.34.1
	 *
	 * @param string $current_version  The version in use.
	 * @param string $required_version The version being required.
	 *
	 * @return bool True when the current version is older than required.
	 */
	public function is_below( string $current_version, string $required_version ): bool {
		return version_compare( $current_version, $required_version, '<' );
	}
}
