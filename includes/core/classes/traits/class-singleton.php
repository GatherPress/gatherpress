<?php
/**
 * The Singleton trait defines a method for ensuring a class has only one instance.
 *
 * This trait is responsible for implementing the Singleton design pattern in classes
 * that need to have a single instance throughout the application.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // @codeCoverageIgnore Prevent direct access.
}

/**
 * Singleton Trait.
 */
trait Singleton {

	/**
	 * The single instance of the class.
	 *
	 * @var ?self|null The instance of the class.
	 */
	private static ?self $instance = null;

	/**
	 * Get the instance of the Singleton class.
	 *
	 * If an instance does not exist, it creates one; otherwise, it returns the existing instance.
	 *
	 * @return self The instance of the class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
