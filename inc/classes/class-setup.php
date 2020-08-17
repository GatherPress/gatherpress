<?php
/**
 * Class is responsible for executing plugin setups.
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
 * Class Setup.
 */
class Setup {

	use Singleton;

	/**
	 * Setup constructor.
	 */
	protected function __construct() {
		$this->instantiate_classes();
	}

	/**
	 * Instantiate singletons.
	 */
	protected function instantiate_classes() {
		Assets::get_instance();
		Attendee::get_instance();
		BuddyPress::get_instance();
		Email::get_instance();
		Event::get_instance();
		Query::get_instance();
		Rest_Api::get_instance();
		Role::get_instance();
	}

}
