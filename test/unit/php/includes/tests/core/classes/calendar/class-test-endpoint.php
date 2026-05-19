<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Endpoint.
 *
 * @package GatherPress\Core\Calendar
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Calendar;

use GatherPress\Core\Calendar\Endpoint;
use GatherPress\Core\Calendar\Redirect;
use GatherPress\Core\Calendar\Template;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Endpoint.
 *
 * @coversDefaultClass \GatherPress\Core\Calendar\Endpoint
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
		$callback  = function () {};
		$types     = array(
			new Template( 'endpoint_template_1', $callback ),
			new Template( 'endpoint_template_2', $callback ),
			new Redirect( 'endpoint_redirect_1', $callback ),
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
			$query_var,
			$instance->query_var,
			'Failed to assert that query_var is persisted.'
		);
		$this->assertSame(
			get_post_type_object( $post_type ),
			$instance->type_object,
			'Failed to assert that type_object is persisted.'
		);
		$this->assertSame(
			$callback,
			$instance->validation_callback,
			'Failed to assert that validation_callback is persisted.'
		);
		$this->assertSame(
			$types,
			$instance->types,
			'Failed to assert that endpoint types are persisted.'
		);
		$this->assertSame(
			$reg_ex,
			$instance->reg_ex,
			'Failed to assert that reg_ex is persisted.'
		);
		$this->assertSame(
			'post_type',
			$instance->object_type,
			'Failed to assert that object_type is set by default.'
		);
	}

	/**
	 * Coverage for get_regex_pattern method.
	 *
	 * @covers ::get_regex_pattern
	 *
	 * @return void
	 */
	public function test_get_regex_pattern(): void {
		$query_var = 'query_var';
		$post_type = 'gatherpress_event';
		$callback  = function () {};
		$types     = array(
			new Template( 'endpoint_template_1', $callback ),
			new Template( 'endpoint_template_2', $callback ),
			new Redirect( 'endpoint_redirect_1', $callback ),
		);
		// Regular expression to match singular event endpoints.
		// Example: 'event/my-sample-event/(custom-endpoint)(/)'.
		$reg_ex   = '%s/([^/]+)/(%s)/?$';
		$instance = new Endpoint(
			$query_var,
			$post_type,
			$callback,
			$types,
			$reg_ex,
		);

		$this->assertSame(
			'event/([^/]+)/(endpoint_template_1|endpoint_template_2|endpoint_redirect_1)/?$',
			Utility::invoke_hidden_method( $instance, 'get_regex_pattern' ),
			'Failed to assert that the generated regex pattern matches.'
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
		$callback  = function () {};
		$types     = array(
			new Template( 'endpoint_template_1', $callback ),
			new Template( 'endpoint_template_2', $callback ),
			new Redirect( 'endpoint_redirect_1', $callback ),
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
	public function test_maybe_flush_rewrite_rules(): void {
		$query_var = 'query_var';
		$post_type = 'gatherpress_event';
		$callback  = function () {};
		$types     = array(
			new Template( 'endpoint_template_1', $callback ),
			new Template( 'endpoint_template_2', $callback ),
			new Redirect( 'endpoint_redirect_1', $callback ),
		);
		// Regular expression to match singular event endpoints.
		// Example: 'event/my-sample-event/(custom-endpoint)(/)'.
		$reg_ex   = '%s/([^/]+)/(%s)/?$';
		$instance = new Endpoint(
			$query_var,
			$post_type,
			$callback,
			$types,
			$reg_ex,
		);

		// Build the regular expression pattern and target URL for this endpoint.
		$reg_ex_pattern = Utility::invoke_hidden_method( $instance, 'get_regex_pattern' );
		$rewrite_url    = add_query_arg( $instance->get_rewrite_atts(), 'index.php' );

		// First scenario: the rewrite_rules option holds a stale rule for this
		// pattern (or no rule at all). maybe_flush_rewrite_rules() should
		// delete the option so WP rebuilds the rules on the next request — the
		// project moved off the custom `gatherpress_flush_rewrite_rules_flag`
		// in favor of this lighter pattern (see Setup::schedule_rewrite_flush).
		update_option( 'rewrite_rules', array( $reg_ex_pattern => 'stale-target' ) );
		Utility::invoke_hidden_method( $instance, 'maybe_flush_rewrite_rules', array( $reg_ex_pattern, $rewrite_url ) );
		$this->assertFalse(
			get_option( 'rewrite_rules' ),
			'rewrite_rules option should be deleted when the stored rule diverges from the desired target.'
		);

		// Repopulate as if WP had flushed and re-saved.
		flush_rewrite_rules( false );
		$this->assertContains(
			$reg_ex_pattern,
			array_keys( get_option( 'rewrite_rules' ) ),
			'GatherPress rewrite rule should be present after flush.'
		);
		$this->assertSame(
			$rewrite_url,
			get_option( 'rewrite_rules' )[ $reg_ex_pattern ],
			'Stored target should match the freshly generated rewrite_url.'
		);

		// Second scenario: the rewrite_rules option already matches the
		// desired target. maybe_flush_rewrite_rules() should be a no-op.
		Utility::invoke_hidden_method( $instance, 'maybe_flush_rewrite_rules', array( $reg_ex_pattern, $rewrite_url ) );
		$this->assertSame(
			$rewrite_url,
			get_option( 'rewrite_rules' )[ $reg_ex_pattern ] ?? null,
			'rewrite_rules option should be left untouched when the stored rule already matches.'
		);
	}

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
		$callback  = function () {};
		$types     = array(
			new Template( 'endpoint_template_1', $callback ),
			new Template( 'endpoint_template_2', $callback ),
			new Redirect( 'endpoint_redirect_1', $callback ),
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
			$instance->allow_query_vars( array( 'apples', 'oranges' ) ),
			'Failed to assert that merged query variables match.'
		);
	}

	/**
	 * Coverage for has_feed method.
	 *
	 * @covers ::has_feed
	 *
	 * @return void
	 */
	public function test_has_feed(): void {
		$query_var = 'query_var';
		$post_type = 'gatherpress_event';
		$callback  = function () {};
		$types     = array(
			new Template( 'endpoint_template_1', $callback ),
			new Template( 'endpoint_template_2', $callback ),
			new Redirect( 'endpoint_redirect_1', $callback ),
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
			$instance->has_feed(),
			'Failed to assert, endpoint is not for feeds.'
		);

		$types    = array(
			new Redirect( 'endpoint_redirect_1', $callback ),
		);
		$reg_ex   = 'reg_ex/feed/';
		$instance = new Endpoint(
			$query_var,
			$post_type,
			$callback,
			$types,
			$reg_ex,
		);

		$this->assertEmpty(
			$instance->has_feed(),
			'Failed to assert, endpoint is for feeds, but has no Template type.'
		);

		$types    = array(
			new Template( 'endpoint_template_1', $callback ),
			new Template( 'endpoint_template_2', $callback ),
		);
		$reg_ex   = 'reg_ex/feed/';
		$instance = new Endpoint(
			$query_var,
			$post_type,
			$callback,
			$types,
			$reg_ex,
		);

		$this->assertSame(
			'endpoint_template_1',
			$instance->has_feed(),
			'Failed to assert, that feed template is found.'
		);
	}

	/**
	 * Coverage for is_valid_query method.
	 *
	 * @covers ::is_valid_query
	 *
	 * @return void
	 */
	public function test_is_valid_query(): void {
		$query_var = 'query_var';
		$post_type = 'gatherpress_event';
		$callback  = '__return_true';
		$types     = array(
			new Template( 'endpoint_template_1', $callback ),
			new Template( 'endpoint_template_2', $callback ),
			new Redirect( 'endpoint_redirect_1', $callback ),
		);
		$reg_ex    = 'reg_ex';
		$instance  = new Endpoint(
			$query_var,
			$post_type,
			$callback,
			$types,
			$reg_ex,
		);

		$this->mock->wp(
			array(
				'query_vars' => array(
					$query_var => 'endpoint_template_1',
				),
			)
		);

		$this->assertTrue(
			$instance->is_valid_query(),
			'Failed to validate the prepared query.'
		);

		$callback   = '__return_false';
		$instance_2 = new Endpoint(
			$query_var,
			$post_type,
			$callback,
			$types,
			$reg_ex,
		);

		$this->assertFalse(
			$instance_2->is_valid_query(),
			'Failed to validate the prepared query.'
		);
		$this->mock->wp()->reset();

		$this->mock->wp(
			array(
				'is_category' => true,
				'query_vars'  => array(
					'cat' => 'category-slug',
				),
			)
		);

		$this->assertFalse(
			$instance->is_valid_query(),
			'Failed to validate the prepared query.'
		);

		$this->mock->wp()->reset();
	}

	/**
	 * Coverage for get_slugs method.
	 *
	 * @covers ::get_slugs
	 *
	 * @return void
	 */
	public function test_get_slugs(): void {
		$query_var = 'query_var';
		$post_type = 'gatherpress_event';
		$callback  = function () {};
		$types     = array(
			new Template( 'endpoint_template_1', $callback ),
			new Template( 'endpoint_template_2', $callback ),
			new Redirect( 'endpoint_redirect_1', $callback ),
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
}
