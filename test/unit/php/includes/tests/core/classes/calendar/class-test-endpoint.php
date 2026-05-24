<?php
/**
 * Class handles unit tests for GatherPress\Core\Calendar\Endpoint.
 *
 * @package GatherPress\Core\Calendar
 * @since 0.34.0
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

		// With an entity filter, only matching subclass slugs are returned.
		$this->assertSame(
			array( 'endpoint_template_1', 'endpoint_template_2' ),
			array_values( Utility::invoke_hidden_method( $instance, 'get_slugs', array( Template::class ) ) ),
			'Filtering by Template class should return only Template slugs.'
		);
		$this->assertSame(
			array( 'endpoint_redirect_1' ),
			array_values( Utility::invoke_hidden_method( $instance, 'get_slugs', array( Redirect::class ) ) ),
			'Filtering by Redirect class should return only Redirect slugs.'
		);
	}

	/**
	 * Coverage for init method — confirms the public method registers a
	 * rewrite rule, hooks query_vars, and hooks template_redirect.
	 *
	 * @covers ::init
	 *
	 * @return void
	 */
	public function test_init_registers_rule_and_hooks(): void {
		$query_var = 'gatherpress_test_init';
		$callback  = function () {};
		$types     = array( new Template( 'endpoint_template_1', $callback ) );
		$reg_ex    = '%s/([^/]+)/(%s)/?$';

		remove_all_filters( 'query_vars' );
		remove_all_actions( 'template_redirect' );
		delete_option( 'rewrite_rules' );

		$instance = new Endpoint(
			$query_var,
			'gatherpress_event',
			$callback,
			$types,
			$reg_ex,
		);

		// init() is called from __construct; verify the rule registered globally.
		global $wp_rewrite;
		$pattern = Utility::invoke_hidden_method( $instance, 'get_regex_pattern' );
		$this->assertArrayHasKey(
			$pattern,
			$wp_rewrite->extra_rules_top,
			'init() should add the generated rewrite rule with `top` priority.'
		);

		$this->assertTrue(
			has_filter( 'query_vars', array( $instance, 'allow_query_vars' ) ) > 0,
			'init() should hook allow_query_vars onto query_vars.'
		);
		$this->assertTrue(
			has_action( 'template_redirect', array( $instance, 'template_redirect' ) ) > 0,
			'init() should hook template_redirect.'
		);
	}

	/**
	 * Coverage for is_valid_registration when called before `init` fires.
	 *
	 * @covers ::is_valid_registration
	 *
	 * @return void
	 */
	public function test_is_valid_registration_bails_before_init(): void {
		global $wp_actions;
		$saved = $wp_actions['init'] ?? 0;
		// Temporarily reset the init counter so is_valid_registration() takes
		// the pre-init bail branch; the real value is restored below.
		$wp_actions['init'] = 0; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Suppress the E_USER_WARNING that wp_trigger_error() raises on the
		// pre-init bail — the warning is expected and the test asserts the
		// registration was skipped.
		$instance = @new Endpoint( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gatherpress_pre_init',
			'gatherpress_event',
			function () {},
			array( new Template( 'foo', function () {} ) ),
			'reg_ex',
		);

		$wp_actions['init'] = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertEndpointDidNotRegister(
			$instance,
			'Endpoint should not register when init has not fired yet.'
		);
	}

	/**
	 * Coverage for is_valid_registration with an empty types list.
	 *
	 * @covers ::is_valid_registration
	 *
	 * @return void
	 */
	public function test_is_valid_registration_bails_on_empty_types(): void {
		$instance = @new Endpoint( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gatherpress_empty_types',
			'gatherpress_event',
			function () {},
			array(),
			'reg_ex',
		);

		$this->assertEndpointDidNotRegister(
			$instance,
			'Empty types array should short-circuit registration.'
		);
	}

	/**
	 * Coverage for is_valid_registration with an unsupported object_type.
	 *
	 * @covers ::is_valid_registration
	 *
	 * @return void
	 */
	public function test_is_valid_registration_bails_on_unsupported_object_type(): void {
		$instance = @new Endpoint( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gatherpress_bad_object',
			'gatherpress_event',
			function () {},
			array( new Template( 'foo', function () {} ) ),
			'reg_ex',
			'option',
		);

		$this->assertEndpointDidNotRegister(
			$instance,
			'Unsupported object_type should short-circuit registration.'
		);
	}

	/**
	 * Coverage for is_valid_registration with the `sitewide` object_type —
	 * the switch returns true early without resolving a type_object. Direct
	 * invocation since the Sitewide_Feed instance Calendar\Setup creates
	 * during bootstrap runs before the xdebug coverage tracer is active.
	 *
	 * @covers ::is_valid_registration
	 *
	 * @return void
	 */
	public function test_is_valid_registration_accepts_sitewide(): void {
		// Build a bare Endpoint then drive `is_valid_registration` through
		// reflection with object_type='sitewide'.
		$callback = function () {};
		$endpoint = new Endpoint(
			'gatherpress_sitewide_direct',
			'gatherpress_event',
			$callback,
			array( new Template( 'foo', $callback ) ),
			'reg_ex',
		);
		$result   = Utility::invoke_hidden_method(
			$endpoint,
			'is_valid_registration',
			array( '', array( new Template( 'foo', $callback ) ), 'sitewide' )
		);
		$this->assertTrue(
			$result,
			'is_valid_registration should return true early for the sitewide object_type.'
		);
	}

	/**
	 * Coverage for is_valid_registration with the `taxonomy` object_type.
	 *
	 * @covers ::is_valid_registration
	 *
	 * @return void
	 */
	public function test_is_valid_registration_resolves_taxonomy(): void {
		$instance = new Endpoint(
			'query_var',
			'gatherpress_topic',
			function () {},
			array( new Template( 'foo', function () {} ) ),
			'reg_ex',
			'taxonomy',
		);

		$this->assertSame(
			get_taxonomy( 'gatherpress_topic' ),
			$instance->type_object,
			'Taxonomy object_type should populate type_object via get_taxonomy().'
		);
		$this->assertSame(
			'taxonomy',
			$instance->object_type,
			'object_type should be persisted as taxonomy.'
		);
	}

	/**
	 * Coverage for is_valid_registration when the post type doesn't exist.
	 *
	 * @covers ::is_valid_registration
	 *
	 * @return void
	 */
	public function test_is_valid_registration_bails_when_type_object_missing(): void {
		$instance = @new Endpoint( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gatherpress_missing_pt',
			'unregistered_post_type',
			function () {},
			array( new Template( 'foo', function () {} ) ),
			'reg_ex',
		);

		$this->assertEndpointDidNotRegister(
			$instance,
			'Unregistered post type should short-circuit registration.'
		);
	}

	/**
	 * Coverage for is_valid_registration when the post type has rewrites disabled.
	 *
	 * @covers ::is_valid_registration
	 *
	 * @return void
	 */
	public function test_is_valid_registration_bails_when_rewrites_disabled(): void {
		register_post_type(
			'no_rewrite_pt',
			array(
				'public'  => true,
				'rewrite' => false,
			)
		);

		$instance = @new Endpoint( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gatherpress_no_rewrite',
			'no_rewrite_pt',
			function () {},
			array( new Template( 'foo', function () {} ) ),
			'reg_ex',
		);

		unregister_post_type( 'no_rewrite_pt' );

		$this->assertEndpointDidNotRegister(
			$instance,
			'Post type with rewrite=false should short-circuit registration.'
		);
	}

	/**
	 * Helper: assert that an Endpoint failed registration by checking the
	 * typed `query_var` property is uninitialized via reflection. Using the
	 * property directly throws "must not be accessed before initialization".
	 *
	 * @param Endpoint $instance The endpoint to inspect.
	 * @param string   $message  Assertion failure message.
	 * @return void
	 */
	private function assertEndpointDidNotRegister( Endpoint $instance, string $message ): void {
		$reflection = new \ReflectionProperty( $instance, 'query_var' );
		$this->assertFalse(
			$reflection->isInitialized( $instance ),
			$message
		);
	}

	/**
	 * Coverage for template_redirect — activates the matching endpoint type
	 * when the query is valid and the requested slug matches a registered type.
	 *
	 * @covers ::template_redirect
	 *
	 * @return void
	 */
	public function test_template_redirect_activates_matching_type(): void {
		$activated = false;

		$callback       = '__return_true';
		$template       = $this->getMockBuilder( Template::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'activate' ) )
			->getMock();
		$template->slug = 'endpoint_template_1';
		$template->expects( $this->once() )
			->method( 'activate' )
			->willReturnCallback(
				function () use ( &$activated ) {
					$activated = true;
				}
			);

		$instance = new Endpoint(
			'gatherpress_calendar',
			'gatherpress_event',
			$callback,
			array( $template ),
			'reg_ex',
		);

		$this->mock->wp(
			array(
				'query_vars' => array(
					'gatherpress_calendar' => 'endpoint_template_1',
				),
			)
		);

		$instance->template_redirect();

		$this->mock->wp()->reset();

		$this->assertTrue(
			$activated,
			'template_redirect() should call activate() on the matching endpoint type.'
		);
	}

	/**
	 * Coverage for template_redirect — short-circuits when the query is invalid.
	 *
	 * @covers ::template_redirect
	 *
	 * @return void
	 */
	public function test_template_redirect_bails_when_query_invalid(): void {
		$template       = $this->getMockBuilder( Template::class )
			->disableOriginalConstructor()
			->onlyMethods( array( 'activate' ) )
			->getMock();
		$template->slug = 'endpoint_template_1';
		$template->expects( $this->never() )->method( 'activate' );

		$instance = new Endpoint(
			'gatherpress_calendar',
			'gatherpress_event',
			'__return_false',
			array( $template ),
			'reg_ex',
		);

		$instance->template_redirect();

		// No assertions beyond the never() expectation above — PHPUnit reports it.
		$this->assertTrue( true, 'Reached end of test without calling activate().' );
	}
}
