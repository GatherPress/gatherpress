<?php
/**
 * Class is responsible for BuddyPress related functionality.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BuddyPress
 */
class BuddyPress {

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
		if ( ! $this->is_buddypress_available() ) {
			add_action( 'admin_notices', array( $this, 'buddypress_dependency' ) );

			return;
		}

		add_action( 'bp_notification_settings', array( $this, 'event_notification_settings' ), 1 );
		add_action( 'bp_register_theme_packages', array( $this, 'register_theme_packages' ) );
	}

	/**
	 * Setting for receiving event announcements in BuddyPress notifications.
	 * @todo fix the template below. See previous theme repo.
	 */
	public function event_notification_settings() {
		$notification_event_announce = bp_get_user_meta( bp_displayed_user_id(), 'notification_event_announce', true );
		$args                        = array(
			'announce' => ! empty( $notification_event_announce ) ? $notification_event_announce : 'yes',
		);

		echo Helper::render_template(
			GP_CORE_PATH . '/template-parts/buddypress/email/event-notification-settings.php',
			$args
		);
	}

	/**
	 * Warning message for BuddyPress dependency.
	 */
	public function buddypress_dependency() {
		printf(
			'<div class="error"><p>%s</p></div>',
			esc_html__( 'Warning: GatherPress requires the BuddyPress plugin to function.', 'gatherpress' )
		);
	}

	/**
	 * Check if BuddyPress is enabled.
	 *
	 * @return bool
	 */
	public function is_buddypress_available() : bool {
		return (bool) function_exists( 'buddypress' );
	}

	/**
	 * Registers GatherPress theme packages.
	 */
	public function register_theme_packages() {
		bp_register_theme_package(
			array(
				'id'      => 'gp-default',
				'name'    => __( 'GatherPress Default', 'gatherpress' ),
				'version' => GP_THEME_VERSION,
				'dir'     => trailingslashit( GP_CORE_PATH . '/bp-templates/gp-default' ),
				'url'     => trailingslashit( GP_CORE_URL . '/bp-templates/gp-default' ),
			)
		);
	}

}
