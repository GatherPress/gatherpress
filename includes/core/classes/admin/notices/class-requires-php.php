<?php
/**
 * Notice shown when the site's PHP is below GatherPress's minimum.
 *
 * Loaded from `requirements-check.php` before the autoloader exists. See
 * class-base.php for the syntax constraints that applies to.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Core\Admin\Notices;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Requires_Php.
 *
 * Blocking notice: when this applies, GatherPress does not load at all.
 *
 * @since 0.34.1
 */
final class Requires_Php extends Base {

	/**
	 * Unique slug identifying this notice.
	 *
	 * @since 0.34.1
	 *
	 * @return string The slug.
	 */
	public function get_slug(): string {
		return 'gatherpress_requires_php';
	}

	/**
	 * The notice's type.
	 *
	 * @since 0.34.1
	 *
	 * @return string One of the TYPE_* constants.
	 */
	public function get_type(): string {
		return self::TYPE_ERROR;
	}

	/**
	 * Whether the notice can be closed for the current page view.
	 *
	 * A blocking requirement is not something to wave away -- the plugin is
	 * inert until it is resolved.
	 *
	 * @since 0.34.1
	 *
	 * @return bool Always false.
	 */
	public function is_dismissible(): bool {
		return false;
	}

	/**
	 * Whether the site's PHP falls below the current minimum.
	 *
	 * `requirements-check.php` uses this to decide whether to halt loading, so
	 * the condition and the notice cannot drift apart.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when PHP is too old to run GatherPress.
	 */
	public function applies(): bool {
		return version_compare( PHP_VERSION, GATHERPRESS_REQUIRES_PHP, '<' );
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
			/* translators: %1$s: minimum PHP version, %2$s: current PHP version. */
			esc_html__(
				'GatherPress requires %1$s or higher. Your current PHP version is %2$s. Please upgrade.',
				'gatherpress'
			),
			esc_html( GATHERPRESS_REQUIRES_PHP ),
			esc_html( phpversion() )
		);
	}
}
