<?php
/**
 * Class is responsible for BuddyPress related functionality.
 *
 * @package GatherPress
 * @subpackage BuddyPress
 * @since 1.0.0
 */

namespace GatherPress\BuddyPress;

use \GatherPress\Core\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Setup
 */
class Setup {

	use Singleton;

	/**
	 * BuddyPress constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_filter( 'gatherpress_settings_sub_pages', array( $this, 'set_sub_page' ) );
		add_action( 'bp_notification_settings', array( $this, 'event_notification_settings' ), 1 );
	}

	/**
	 * Setup BuddyPress settings page in admin.
	 *
	 * @param array $sub_pages List of setting sub pages.
	 *
	 * @return array
	 */
	public function set_sub_page( array $sub_pages ): array {
		$sub_pages['buddypress'] = array(
			'name' => __( 'BuddyPress', 'gatherpress' ),
		);

		return $sub_pages;
	}

	/**
	 * Setting for receiving event announcements in BuddyPress notifications.
	 *
	 * @todo fix the template below. See previous theme repo.
	 */
	public function event_notification_settings() {
		$notification_event_announce = bp_get_user_meta( bp_displayed_user_id(), 'notification_event_announce', true );
		$args                        = array(
			'announce' => ! empty( $notification_event_announce ) ? $notification_event_announce : 'yes',
		);

		echo Utility::render_template( //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			GATHERPRESS_CORE_PATH . '/templates/buddypress/email/event-notification-settings.php',
			$args
		);
	}

}
