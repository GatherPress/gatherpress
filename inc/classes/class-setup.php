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

		$this->setup_hooks();
	}

	/**
	 * Instantiate singletons.
	 */
	protected function instantiate_classes() {
		Assets::get_instance();
		Attendee::get_instance();
		Block::get_instance();
		BuddyPress::get_instance();
		Email::get_instance();
		Event::get_instance();
		Query::get_instance();
		Rest_Api::get_instance();
		Role::get_instance();
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_filter( 'block_categories', array( $this, 'block_category' ) );
	}

	/**
	 * Add GatherPress block category.
	 *
	 * @param array $categories All the registered block categories.
	 *
	 * @return array
	 */
	public function block_category( $categories ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'gatherpress',
					'title' => __( 'GatherPress', 'gatherpress' ),
				),
			)
		);
	}

}
