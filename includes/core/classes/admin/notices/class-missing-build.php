<?php
/**
 * Notice shown when the plugin's build directory is absent.
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
 * Class Missing_Build.
 *
 * Blocking notice: when this applies, GatherPress does not load at all.
 *
 * Build files are not committed to version control, so this is what a
 * first-time contributor sees after cloning the repository and activating the
 * plugin without running the build.
 *
 * @since 0.34.1
 */
final class Missing_Build extends Base {

	/**
	 * Unique slug identifying this notice.
	 *
	 * @since 0.34.1
	 *
	 * @return string The slug.
	 */
	public function get_slug() {
		return 'gatherpress_missing_build';
	}

	/**
	 * The notice's type.
	 *
	 * @since 0.34.1
	 *
	 * @return string One of the TYPE_* constants.
	 */
	public function get_type() {
		return self::TYPE_ERROR;
	}

	/**
	 * Whether the notice can be closed for the current page view.
	 *
	 * @since 0.34.1
	 *
	 * @return bool Always false.
	 */
	public function is_dismissible() {
		return false;
	}

	/**
	 * Whether the build directory is missing.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when the build directory is absent.
	 */
	public function applies() {
		return ! is_dir( GATHERPRESS_CORE_PATH . '/build' );
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
			/* translators: %1$s: build command, %2$s: the plugin path */
			esc_html__(
				// phpcs:disable Generic.Files.LineLength.TooLong
				'Please run %1$s in the %2$s plugin directory to generate required assets. This is needed after first cloning the plugin for development.',
				// phpcs:enable Generic.Files.LineLength.TooLong
				'gatherpress'
			),
			'<code>npm run build</code>',
			'<code>gatherpress</code>'
		);
	}
}
