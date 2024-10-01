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

	/**
	 * Coverage for get_rewrite_atts method.
	 *
	 * @covers ::get_rewrite_atts
	 *
	 * @return void
	 */
	public function test_get_rewrite_atts(): void {
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

		$this->assertSame(
			array(
				'gatherpress_event' => '$matches[1]',
				'query_var'         => '$matches[2]',
			),
			$instance->get_rewrite_atts(),
			'Failed to assert that rewrite attributes match.'
		);
	}

	/**
	 * Coverage for maybe_flush_rewrite_rules method.
	 *
	 * @covers ::maybe_flush_rewrite_rules
	 *
	 * @return void
	 */
	public function test_maybe_flush_rewrite_rules(): void {}

	/**
	 * Coverage for allow_query_vars method.
	 *
	 * @covers ::allow_query_vars
	 *
	 * @return void
	 */
	public function test_allow_query_vars(): void {
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

		$this->assertSame(
			array(
				'apples',
				'oranges',
				'query_var',
			),
			$instance->allow_query_vars( array( 'apples', 'oranges' )),
			'Failed to assert that merged query variables match.'
		);
	}

	/**
	 * Coverage for has_feed_template method.
	 *
	 * @covers ::has_feed_template
	 *
	 * @return void
	 */
	public function test_has_feed_template(): void {
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

		$this->assertEmpty(
			Utility::invoke_hidden_method( $instance, 'has_feed_template' ),
			'Failed to assert, endpoint is not for feeds.'
		);

		$types     = array(
			new Endpoint_Redirect( 'endpoint_redirect_1', $callback ),
		);
		$reg_ex    = 'reg_ex/feed/';
		$instance  = new Endpoint(
			$query_var,
			$post_type,
			$callback,
			$types,
			$reg_ex,
		);

		$this->assertEmpty(
			Utility::invoke_hidden_method( $instance, 'has_feed_template' ),
			'Failed to assert, endpoint is for feeds, but has no Endpoint_Template type.'
		);

		$types     = array(
			new Endpoint_Template( 'endpoint_template_1', $callback ),
			new Endpoint_Template( 'endpoint_template_2', $callback ),
		);
		$reg_ex    = 'reg_ex/feed/';
		$instance  = new Endpoint(
			$query_var,
			$post_type,
			$callback,
			$types,
			$reg_ex,
		);

		$this->assertSame(
			'endpoint_template_1',
			Utility::invoke_hidden_method( $instance, 'has_feed_template' ),
			'Failed to assert, that feed template is found.'
		);
	}


}
