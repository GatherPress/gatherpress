<?php
/**
 * Trait is responsible for setting a class as a singleton.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Singleton {

	/**
	 * Instance of class.
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Get the instance of the Singleton class.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
