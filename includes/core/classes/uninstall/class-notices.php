<?php
/**
 * Uninstall task that removes the admin notice bookkeeping.
 *
 * @package GatherPress\Core\Uninstall
 * @since 0.36.0
 */

namespace GatherPress\Core\Uninstall;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Admin\Notices\Base as Notice;
use GatherPress\Core\Admin\Notices\Setup as Notices_Setup;

/**
 * Class Notices.
 *
 * Removes the options the admin notices keep: which notices have been
 * dismissed, and whether the plugin has been activated on this site.
 *
 * @since 0.36.0
 */
final class Notices extends Base {

	/**
	 * Always applies.
	 *
	 * Bookkeeping rather than data. Like the transients, nothing a reinstall
	 * would want back is lost by removing it, so this task needs no opt-in
	 * setting to gate it.
	 *
	 * @since 0.36.0
	 *
	 * @return bool Always true.
	 */
	public function applies(): bool {
		return true;
	}

	/**
	 * Remove the notice options from the current site.
	 *
	 * The dismissal record is shared by every notice, and anything else a
	 * notice keeps is read from the notice itself, so a new notice is cleaned
	 * up by declaring its options rather than by registering another task.
	 *
	 * All per-site: dismissal is recorded per site, and so is activation,
	 * even when the plugin is network activated.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function uninstall_site(): void {
		delete_option( Notice::OPTION_NAME );

		foreach ( Notices_Setup::get_instance()->get_notices() as $notice ) {
			foreach ( $notice->get_options() as $option ) {
				delete_option( $option );
			}
		}
	}
}
