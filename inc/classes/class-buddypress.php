<?php

namespace GatherPress\Inc;

use \GatherPress\Inc\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BuddyPress {

	use Singleton;

	/**
	 * BuddyPress constructor.
	 */
	protected function __construct() {

		$this->_setup_hooks();

	}

	/**
	 * Setup hooks.
	 */
	protected function _setup_hooks() : void {

		if ( ! $this->is_buddypress_available() ) {
			add_action( 'admin_notices', [ $this, 'buddypress_dependency' ] );

			return;
		}

		add_action( 'bp_notification_settings', [ $this, 'event_notification_settings' ], 1 );

	}

	public function event_notification_settings() {

		$args = [
			'announce' => bp_get_user_meta( bp_displayed_user_id(), 'notification_event_announce', true ) ?: 'yes',
		];

		echo Helper::render_template(
			GATHERPRESS_CORE_PATH . '/template-parts/buddypress/email/event-notification-settings.php',
			$args
		);

	}

	/**
	 * Warning message for BuddyPress dependency.
	 */
	public function buddypress_dependency() : void {

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

}

//EOF
