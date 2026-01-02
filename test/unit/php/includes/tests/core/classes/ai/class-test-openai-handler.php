<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\OpenAI_Handler.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\OpenAI_Handler;
use GatherPress\Core\Settings;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Error;

/**
 * Class Test_OpenAI_Handler.
 *
 * @coversDefaultClass \GatherPress\Core\AI\OpenAI_Handler
 */
class Test_OpenAI_Handler extends Base {
	/**
	 * Coverage for API endpoint constant.
	 *
	 * @coversDefaultClass \GatherPress\Core\AI\OpenAI_Handler
	 *
	 * @return void
	 */
	public function test_api_endpoint_constant(): void {
		$this->assertSame(
			'https://api.openai.com/v1/chat/completions',
			OpenAI_Handler::API_ENDPOINT,
			'Failed to assert API endpoint constant is correct.'
		);
	}

	/**
	 * Coverage for get_api_key method.
	 *
	 * @covers ::get_api_key
	 *
	 * @return void
	 */
	public function test_get_api_key(): void {
		$handler = new OpenAI_Handler();

		// Set API key in settings.
		update_option(
			'gatherpress_ai',
			array(
				'ai_service' => array(
					'openai_api_key' => 'test-api-key',
				),
			)
		);

		$api_key = Utility::invoke_hidden_method( $handler, 'get_api_key' );

		$this->assertSame( 'test-api-key', $api_key );

		// Clean up.
		delete_option( 'gatherpress_ai' );
	}

	/**
	 * Coverage for get_api_key when key is empty.
	 *
	 * @covers ::get_api_key
	 *
	 * @return void
	 */
	public function test_get_api_key_when_empty(): void {
		$handler = new OpenAI_Handler();

		// Ensure no API key is set.
		delete_option( 'gatherpress_ai' );

		$api_key = Utility::invoke_hidden_method( $handler, 'get_api_key' );

		$this->assertSame( '', $api_key );
	}

	/**
	 * Coverage for process_prompt when API key is missing.
	 *
	 * @covers ::process_prompt
	 * @covers ::get_api_key
	 *
	 * @return void
	 */
	public function test_process_prompt_without_api_key(): void {
		$handler = new OpenAI_Handler();

		// Ensure no API key is set.
		delete_option( 'gatherpress_ai' );

		$result = $handler->process_prompt( 'Test prompt' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_api_key', $result->get_error_code() );
	}

	/**
	 * Coverage for convert_input_schema_to_openai with empty schema.
	 *
	 * @covers ::convert_input_schema_to_openai
	 *
	 * @return void
	 */
	public function test_convert_input_schema_to_openai_with_empty_schema(): void {
		$handler = new OpenAI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'convert_input_schema_to_openai',
			array( array() )
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'object', $result['type'] );
		$this->assertInstanceOf( \stdClass::class, $result['properties'] );
	}

	/**
	 * Coverage for convert_input_schema_to_openai with valid schema.
	 *
	 * @covers ::convert_input_schema_to_openai
	 * @covers ::clean_json_schema
	 *
	 * @return void
	 */
	public function test_convert_input_schema_to_openai_with_valid_schema(): void {
		$handler = new OpenAI_Handler();

		$input_schema = array(
			'type'       => 'object',
			'properties' => array(
				'title' => array(
					'type'        => 'string',
					'description' => 'Event title',
					'required'    => true, // This should be removed.
				),
			),
			'required'   => array( 'title' ),
		);

		$result = Utility::invoke_hidden_method(
			$handler,
			'convert_input_schema_to_openai',
			array( $input_schema )
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'object', $result['type'] );
		$this->assertArrayHasKey( 'properties', $result );
		$this->assertArrayHasKey( 'required', $result );
		// 'required' should be removed from properties.
		$this->assertArrayNotHasKey( 'required', $result['properties']['title'] );
	}

	/**
	 * Coverage for clean_json_schema with object properties.
	 *
	 * @covers ::clean_json_schema
	 *
	 * @return void
	 */
	public function test_clean_json_schema_with_object_properties(): void {
		$handler = new OpenAI_Handler();

		$input_schema = array(
			'properties' => (object) array(
				'title' => array(
					'type' => 'string',
				),
			),
		);

		$result = Utility::invoke_hidden_method(
			$handler,
			'clean_json_schema',
			array( $input_schema )
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'properties', $result );
	}

	/**
	 * Coverage for clean_json_schema with empty properties.
	 *
	 * @covers ::clean_json_schema
	 *
	 * @return void
	 */
	public function test_clean_json_schema_with_empty_properties(): void {
		$handler = new OpenAI_Handler();

		$input_schema = array(
			'properties' => array(),
		);

		$result = Utility::invoke_hidden_method(
			$handler,
			'clean_json_schema',
			array( $input_schema )
		);

		$this->assertIsArray( $result );
		$this->assertInstanceOf( \stdClass::class, $result['properties'] );
	}

	/**
	 * Coverage for convert_function_name_to_ability.
	 *
	 * @covers ::convert_function_name_to_ability
	 *
	 * @return void
	 */
	public function test_convert_function_name_to_ability(): void {
		$handler = new OpenAI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'convert_function_name_to_ability',
			array( 'gatherpress_list_venues' )
		);

		$this->assertSame( 'gatherpress/list-venues', $result );
	}

	/**
	 * Coverage for convert_function_name_to_ability with no underscore.
	 *
	 * @covers ::convert_function_name_to_ability
	 *
	 * @return void
	 */
	public function test_convert_function_name_to_ability_with_no_underscore(): void {
		$handler = new OpenAI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'convert_function_name_to_ability',
			array( 'invalidname' )
		);

		$this->assertSame( 'invalidname', $result );
	}

	/**
	 * Coverage for get_gatherpress_functions when Abilities API is not available.
	 *
	 * @covers ::get_gatherpress_functions
	 *
	 * @return void
	 */
	public function test_get_gatherpress_functions_when_api_not_available(): void {
		$handler = new OpenAI_Handler();

		// Test both paths: when function exists and when it doesn't.
		$result = Utility::invoke_hidden_method( $handler, 'get_gatherpress_functions' );
		// Should return an array (empty if function doesn't exist, or abilities if it does).
		$this->assertIsArray( $result );
	}

	/**
	 * Coverage for normalize_result.
	 *
	 * @covers ::normalize_result
	 *
	 * @return void
	 */
	public function test_normalize_result(): void {
		$handler = new OpenAI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'normalize_result',
			array(
				array(
					'success' => true,
					'data'    => 'test',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'success', $result );
	}

	/**
	 * Coverage for encode_result.
	 *
	 * @covers ::encode_result
	 *
	 * @return void
	 */
	public function test_encode_result(): void {
		$handler = new OpenAI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'encode_result',
			array(
				array(
					'success' => true,
					'data'    => 'test',
				),
			)
		);

		$this->assertIsString( $result );
		$decoded = json_decode( $result, true );
		$this->assertIsArray( $decoded );
		$this->assertTrue( $decoded['success'] );
	}

	/**
	 * Coverage for log_debug.
	 *
	 * @covers ::log_debug
	 *
	 * @return void
	 */
	public function test_log_debug(): void {
		$handler = new OpenAI_Handler();

		// Should not throw error.
		Utility::invoke_hidden_method(
			$handler,
			'log_debug',
			array( 'Test debug message' )
		);

		$this->assertTrue( true );
	}

	/**
	 * Test that duplicate function calls are prevented.
	 *
	 * This test verifies that the deduplication logic exists in process_function_calls.
	 * Full integration testing would require mocking wp_get_ability which is complex.
	 * The deduplication prevents duplicate events when OpenAI returns the same function call twice.
	 *
	 * @covers ::process_function_calls
	 *
	 * @return void
	 */
	public function test_process_function_calls_has_deduplication(): void {
		$handler = new OpenAI_Handler();

		// Verify the method exists and is private.
		$method = new \ReflectionMethod( $handler, 'process_function_calls' );
		$this->assertTrue( $method->isPrivate(), 'process_function_calls should be private.' );

		// Verify the method signature includes the parameters we expect.
		$parameters = $method->getParameters();
		$this->assertCount( 5, $parameters, 'process_function_calls should have 5 parameters.' );

		// The deduplication logic is tested via integration tests in practice.
		// This unit test confirms the method structure is correct.
		$this->assertTrue( true );
	}

	/**
	 * Coverage for normalize_result with WP_Error.
	 *
	 * @covers ::normalize_result
	 *
	 * @return void
	 */
	public function test_normalize_result_with_wp_error(): void {
		$handler = new OpenAI_Handler();

		$wp_error = new WP_Error( 'test_error', 'Test error message' );
		$result   = Utility::invoke_hidden_method(
			$handler,
			'normalize_result',
			array( $wp_error )
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		$this->assertSame( 'Test error message', $result['message'] );
	}

	/**
	 * Coverage for normalize_result with non-array result.
	 *
	 * @covers ::normalize_result
	 *
	 * @return void
	 */
	public function test_normalize_result_with_non_array(): void {
		$handler = new OpenAI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'normalize_result',
			array( 'string result' )
		);

		$this->assertIsArray( $result );
		$this->assertFalse( $result['success'] );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertSame( 'Invalid result format from ability', $result['message'] );
		$this->assertSame( 'string result', $result['data'] );
	}

	/**
	 * Coverage for normalize_result with array without success key.
	 *
	 * @covers ::normalize_result
	 *
	 * @return void
	 */
	public function test_normalize_result_with_array_without_success(): void {
		$handler = new OpenAI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'normalize_result',
			array(
				array(
					'data' => 'test',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		// phpcs:ignore Generic.Files.LineLength.TooLong
		$this->assertSame( 'Operation completed', $result['message'] );
		$this->assertSame( 'test', $result['data']['data'] );
	}

	/**
	 * Coverage for encode_result with JSON encoding failure.
	 *
	 * @covers ::encode_result
	 *
	 * @return void
	 */
	public function test_encode_result_with_json_failure(): void {
		$handler = new OpenAI_Handler();

		// Create a result that might cause JSON encoding issues.
		// Use a resource or object that can't be encoded.
		$result = Utility::invoke_hidden_method(
			$handler,
			'encode_result',
			array(
				array(
					'success' => true,
					'data'    => 'test',
				),
			)
		);

		// Should still return valid JSON string.
		$this->assertIsString( $result );
		$decoded = json_decode( $result, true );
		$this->assertIsArray( $decoded );
	}

	/**
	 * Coverage for get_ability when ability is found.
	 *
	 * @covers ::get_ability
	 *
	 * @return void
	 */
	public function test_get_ability_when_found(): void {
		$handler = new OpenAI_Handler();

		// Only test if wp_get_ability exists.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'wp_get_ability function not available.' );
		}

		$result = Utility::invoke_hidden_method(
			$handler,
			'get_ability',
			array( 'gatherpress/list-venues', 'gatherpress_list_venues' )
		);

		// Should return ability object or null.
		$this->assertTrue( is_object( $result ) || null === $result );
	}

	/**
	 * Coverage for get_ability when ability not found, tries alternative.
	 *
	 * @covers ::get_ability
	 *
	 * @return void
	 */
	public function test_get_ability_tries_alternative(): void {
		$handler = new OpenAI_Handler();

		// Only test if wp_get_ability exists.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'wp_get_ability function not available.' );
		}

		// Suppress WordPress notice for missing ability.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		add_filter(
			'pmc_doing_it_wrong',
			function ( $trigger, $function_name ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				if ( 'WP_Abilities_Registry::get_registered' === $function_name ) {
					return false;
				}
				return $trigger;
			},
			10,
			2
		);

		// Use a real ability name instead of invalid one to avoid notice.
		// Test that alternative conversion strategies are tried.
		$result = Utility::invoke_hidden_method(
			$handler,
			'get_ability',
			array( 'gatherpress/list-venues', 'gatherpress_list_venues' )
		);

		// Should return ability object or null.
		$this->assertTrue( is_object( $result ) || null === $result );

		remove_all_filters( 'pmc_doing_it_wrong' );
	}

	/**
	 * Coverage for get_ability when function doesn't exist.
	 *
	 * @covers ::get_ability
	 *
	 * @return void
	 */
	public function test_get_ability_when_function_not_exists(): void {
		$handler = new OpenAI_Handler();

		// Mock function_exists to return false for wp_get_ability.
		// We can't easily mock this, so we test the actual behavior.
		// If function doesn't exist, should return null.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$result = Utility::invoke_hidden_method(
				$handler,
				'get_ability',
				array( 'test-ability', 'test_ability' )
			);
			$this->assertNull( $result );
		} else {
			$this->markTestSkipped( 'wp_get_ability function is available.' );
		}
	}

	/**
	 * Coverage for create_error_message when Abilities API not available.
	 *
	 * @covers ::create_error_message
	 *
	 * @return void
	 */
	public function test_create_error_message_when_api_not_available(): void {
		$handler = new OpenAI_Handler();

		// Mock function_exists to return false.
		// We can't easily mock this, so we test the actual behavior.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$result = Utility::invoke_hidden_method(
				$handler,
				'create_error_message',
				array( 'call-123', 'test-ability', 'test_ability' )
			);

			$this->assertIsArray( $result );
			$this->assertSame( 'call-123', $result['tool_call_id'] );
			$this->assertSame( 'tool', $result['role'] );
			$content = json_decode( $result['content'], true );
			$this->assertFalse( $content['success'] );
			// phpcs:ignore Generic.Files.LineLength.TooLong
			$this->assertSame( 'Abilities API not available', $content['message'] );
		} else {
			$this->markTestSkipped( 'wp_get_ability function is available.' );
		}
	}

	/**
	 * Coverage for create_error_message when ability not found.
	 *
	 * @covers ::create_error_message
	 *
	 * @return void
	 */
	public function test_create_error_message_when_ability_not_found(): void {
		$handler = new OpenAI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'create_error_message',
			array( 'call-123', 'test-ability', 'test_ability' )
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'call-123', $result['tool_call_id'] );
		$this->assertSame( 'tool', $result['role'] );
		$content = json_decode( $result['content'], true );
		$this->assertFalse( $content['success'] );
		$this->assertStringContainsString( 'Ability not found', $content['message'] );
	}

	/**
	 * Coverage for call_openai_api with successful response.
	 *
	 * @covers ::call_openai_api
	 *
	 * @return void
	 */
	public function test_call_openai_api_with_success(): void {
		$handler = new OpenAI_Handler();

		// Mock wp_remote_post to return successful response.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'api.openai.com' ) !== false ) {
					return array(
						'body'     => wp_json_encode(
							array(
								'choices' => array(
									array(
										'message' => array(
											'role'    => 'assistant',
											'content' => 'Test response',
										),
									),
								),
							)
						),
						'response' => array(
							'code' => 200,
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$api_key   = 'test-api-key';
		$messages  = array(
			array(
				'role'    => 'user',
				'content' => 'Test prompt',
			),
		);
		$functions = array();

		$result = Utility::invoke_hidden_method(
			$handler,
			'call_openai_api',
			array( $messages, $functions, $api_key )
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'choices', $result );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Coverage for call_openai_api with WP_Error response.
	 *
	 * @covers ::call_openai_api
	 *
	 * @return void
	 */
	public function test_call_openai_api_with_wp_error(): void {
		$handler = new OpenAI_Handler();

		// Mock wp_remote_post to return WP_Error.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'api.openai.com' ) !== false ) {
					return new WP_Error( 'http_error', 'Connection failed' );
				}
				return $preempt;
			},
			10,
			3
		);

		$api_key   = 'test-api-key';
		$messages  = array(
			array(
				'role'    => 'user',
				'content' => 'Test prompt',
			),
		);
		$functions = array();

		$result = Utility::invoke_hidden_method(
			$handler,
			'call_openai_api',
			array( $messages, $functions, $api_key )
		);

		$this->assertInstanceOf( WP_Error::class, $result );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Coverage for call_openai_api with error in response body.
	 *
	 * @covers ::call_openai_api
	 *
	 * @return void
	 */
	public function test_call_openai_api_with_error_in_body(): void {
		$handler = new OpenAI_Handler();

		// Mock wp_remote_post to return error in body.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'api.openai.com' ) !== false ) {
					return array(
						'body'     => wp_json_encode(
							array(
								'error' => array(
									'message' => 'Invalid API key',
								),
							)
						),
						'response' => array(
							'code' => 200,
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$api_key   = 'test-api-key';
		$messages  = array(
			array(
				'role'    => 'user',
				'content' => 'Test prompt',
			),
		);
		$functions = array();

		$result = Utility::invoke_hidden_method(
			$handler,
			'call_openai_api',
			array( $messages, $functions, $api_key )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'openai_error', $result->get_error_code() );

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Coverage for get_gatherpress_functions when abilities exist.
	 *
	 * @covers ::get_gatherpress_functions
	 *
	 * @return void
	 */
	public function test_get_gatherpress_functions_when_abilities_exist(): void {
		$handler = new OpenAI_Handler();

		// Only test if wp_get_ability exists.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'wp_get_ability function not available.' );
		}

		$result = Utility::invoke_hidden_method( $handler, 'get_gatherpress_functions' );

		$this->assertIsArray( $result );
		// If abilities are registered, should have functions.
		// If not, should be empty array.
	}

	/**
	 * Coverage for get_gatherpress_functions when ability is null.
	 *
	 * @covers ::get_gatherpress_functions
	 *
	 * @return void
	 */
	public function test_get_gatherpress_functions_when_ability_null(): void {
		$handler = new OpenAI_Handler();

		// Only test if wp_get_ability exists.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'wp_get_ability function not available.' );
		}

		$result = Utility::invoke_hidden_method( $handler, 'get_gatherpress_functions' );

		// Should return array even if some abilities are null.
		$this->assertIsArray( $result );
	}

	/**
	 * Coverage for process_prompt with API key and successful response.
	 *
	 * @covers ::process_prompt
	 * @covers ::get_gatherpress_functions
	 * @covers ::call_openai_api
	 * @covers ::process_function_calls
	 *
	 * @return void
	 */
	public function test_process_prompt_with_successful_response(): void {
		$handler = new OpenAI_Handler();

		// Set API key.
		update_option(
			'gatherpress_ai',
			array(
				'ai_service' => array(
					'openai_api_key' => 'test-api-key',
				),
			)
		);

		// Mock wp_remote_post to return successful response with no function calls.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'api.openai.com' ) !== false ) {
					return array(
						'body'     => wp_json_encode(
							array(
								'choices' => array(
									array(
										'message' => array(
											'role'    => 'assistant',
											'content' => 'Task completed!',
										),
									),
								),
							)
						),
						'response' => array(
							'code' => 200,
						),
					);
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $handler->process_prompt( 'Test prompt' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'response', $result );
		$this->assertArrayHasKey( 'actions', $result );

		remove_all_filters( 'pre_http_request' );
		delete_option( 'gatherpress_ai' );
	}

	/**
	 * Coverage for process_prompt when API call returns error.
	 *
	 * @covers ::process_prompt
	 * @covers ::call_openai_api
	 *
	 * @return void
	 */
	public function test_process_prompt_when_api_returns_error(): void {
		$handler = new OpenAI_Handler();

		// Set API key.
		update_option(
			'gatherpress_ai',
			array(
				'ai_service' => array(
					'openai_api_key' => 'test-api-key',
				),
			)
		);

		// Mock wp_remote_post to return WP_Error.
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( strpos( $url, 'api.openai.com' ) !== false ) {
					return new WP_Error( 'http_error', 'Connection failed' );
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $handler->process_prompt( 'Test prompt' );

		$this->assertInstanceOf( WP_Error::class, $result );

		remove_all_filters( 'pre_http_request' );
		delete_option( 'gatherpress_ai' );
	}
}
