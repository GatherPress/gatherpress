<?php
/**
 * Class handles unit tests for GatherPress\Core\Endpoints\Endpoint.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Endpoints;

use GatherPress\Core\Endpoints\Endpoint;
use GatherPress\Core\Endpoints\Endpoint_Redirect;
use GatherPress\Core\Endpoints\Endpoint_Template;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Endpoint.
 *
 * @coversDefaultClass \GatherPress\Core\Endpoints\Endpoint
 * @group              endpoints
 */
class Test_Endpoint extends Base {
	/**
	 * Coverage for __construct method.
	 *
	 * @covers ::__construct
	 *
	 * @return void
	 */
	public function test___construct(): void {
		$query_var = 'query_var';
		$post_type = 'gatherpress_event';
		$callback  = function(){};
		$types     = array(
			new Endpoint_Template( 'endpoint_template_1', $callback ),
			new Endpoint_Template( 'endpoint_template_2', $callback ),
			new Endpoint_Redirect( 'endpoint_redirect_1', $callback ),
		);
		$reg_ex    = 'reg_ex';

		$instance  = new Endpoint(
			$query_var,
			$post_type,
			$callback,
			$types,
			$reg_ex,
		);

		$this->assertSame( $query_var, $instance->query_var, 'Failed to assert that query_var is persisted.' );
		$this->assertSame( get_post_type_object( $post_type ), $instance->type_object, 'Failed to assert that type_object is persisted.' );
		$this->assertSame( $callback, $instance->validation_callback, 'Failed to assert that validation_callback is persisted.' );
		$this->assertSame( $types, $instance->types, 'Failed to assert that endpoint types are persisted.' );
		$this->assertSame( $reg_ex, $instance->reg_ex, 'Failed to assert that reg_ex is persisted.' );
		$this->assertSame( 'post_type', $instance->object_type, 'Failed to assert that object_type is set by default.' );
	}

	/**
	 * Coverage for get_slugs method.
	 *
	 * @covers ::get_slugs
	 *
	 * @return void
	 */
	public function test_get_slugs(): void {
		// ini_set('display_errors', '1');
		// ini_set('display_startup_errors', '1');
		// error_reporting(E_ALL);

		$callback = function(){};
		$instance = new Endpoint(
			'query_var',
			// 'post', // has rewrite=false , why?
			'gatherpress_event',
			$callback,
			array(
				new Endpoint_Template( 'endpoint_template_1', $callback ),
				new Endpoint_Template( 'endpoint_template_2', $callback ),
				new Endpoint_Redirect( 'endpoint_redirect_1', $callback ),
			),
			'reg_ex',
		);
		$this->assertSame(
			array(
				'endpoint_template_1',
				'endpoint_template_2',
				'endpoint_redirect_1',
			),
			Utility::invoke_hidden_method( $instance, 'get_slugs' ),
			'Failed to assert that endpoint slugs match.'
		);
	}

}
