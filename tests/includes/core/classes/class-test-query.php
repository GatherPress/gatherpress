<?php
/**
 * Class handles unit tests for GatherPress\Core\Query.
 *
 * @package GatherPress
 * @subpackage Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Query;
use PMC\Unit_Test\Base;

/**
 * Class Test_Query.
 *
 * @coversDefaultClass \GatherPress\Core\Query
 */
class Test_Query extends Base {

	/**
	 * Coverage for setup_hooks.
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Query::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'pre_get_posts',
				'priority' => 10,
				'callback' => array( $instance, 'pre_get_posts' ),
			),
			array(
				'type'     => 'filter',
				'name'     => 'posts_clauses',
				'priority' => 10,
				'callback' => array( $instance, 'admin_order_events' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

}
