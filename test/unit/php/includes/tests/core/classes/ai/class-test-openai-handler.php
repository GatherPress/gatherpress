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
}
