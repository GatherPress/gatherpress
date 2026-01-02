<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\AI_Handler.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\AI_Handler;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Error;

/**
 * Class Test_AI_Handler.
 *
 * @coversDefaultClass \GatherPress\Core\AI\AI_Handler
 */
class Test_AI_Handler extends Base {
	/**
	 * Coverage for MAX_ITERATIONS constant.
	 *
	 * @coversDefaultClass \GatherPress\Core\AI\AI_Handler
	 *
	 * @return void
	 */
	public function test_max_iterations_constant(): void {
		$this->assertSame(
			15,
			AI_Handler::MAX_ITERATIONS,
			'Failed to assert MAX_ITERATIONS constant is 15.'
		);
	}

	/**
	 * Coverage for process_prompt when wp-ai-client is not available.
	 *
	 * @covers ::process_prompt
	 *
	 * @return void
	 */
	public function test_process_prompt_when_wp_ai_client_not_available(): void {
		// Only test if class doesn't exist (would need to mock, but we'll test actual behavior).
		// Since we can't easily remove the class once loaded, we'll skip if it exists.
		if ( class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			$this->markTestSkipped( 'wp-ai-client is available in test environment.' );
		}

		$handler = new AI_Handler();
		$result  = $handler->process_prompt( 'Test prompt' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wp_ai_client_not_available', $result->get_error_code() );
	}

	/**
	 * Coverage for process_prompt when API key is not configured.
	 *
	 * @covers ::process_prompt
	 * @covers ::has_api_key
	 *
	 * @return void
	 */
	public function test_process_prompt_without_api_key(): void {
		if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			$this->markTestSkipped( 'wp-ai-client is not available in test environment.' );
		}

		$handler = new AI_Handler();

		// Ensure no API key is set.
		delete_option( 'wp_ai_client_provider_credentials' );

		$result = $handler->process_prompt( 'Test prompt' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_api_key', $result->get_error_code() );
	}

	/**
	 * Coverage for process_prompt when no abilities are available.
	 *
	 * @covers ::process_prompt
	 * @covers ::get_gatherpress_abilities
	 *
	 * @return void
	 */
	public function test_process_prompt_without_abilities(): void {
		if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			$this->markTestSkipped( 'wp-ai-client is not available in test environment.' );
		}

		$handler = new AI_Handler();

		// Set API key.
		update_option(
			'wp_ai_client_provider_credentials',
			array(
				'openai' => 'test-api-key',
			)
		);

		// Only test if wp_get_ability doesn't exist.
		if ( function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API is available in test environment.' );
		}

		$result = $handler->process_prompt( 'Test prompt' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_abilities', $result->get_error_code() );

		// Clean up.
		delete_option( 'wp_ai_client_provider_credentials' );
	}

	/**
	 * Coverage for has_api_key when API key is configured.
	 *
	 * @covers ::has_api_key
	 *
	 * @return void
	 */
	public function test_has_api_key_when_configured(): void {
		$handler = new AI_Handler();

		// Set API key.
		update_option(
			'wp_ai_client_provider_credentials',
			array(
				'openai' => 'test-api-key',
			)
		);

		$has_key = Utility::invoke_hidden_method( $handler, 'has_api_key' );

		$this->assertTrue( $has_key );

		// Clean up.
		delete_option( 'wp_ai_client_provider_credentials' );
	}

	/**
	 * Coverage for has_api_key when API key is not configured.
	 *
	 * @covers ::has_api_key
	 *
	 * @return void
	 */
	public function test_has_api_key_when_not_configured(): void {
		$handler = new AI_Handler();

		// Ensure no API key is set.
		delete_option( 'wp_ai_client_provider_credentials' );

		$has_key = Utility::invoke_hidden_method( $handler, 'has_api_key' );

		$this->assertFalse( $has_key );
	}

	/**
	 * Coverage for has_api_key when credentials option is not an array.
	 *
	 * @covers ::has_api_key
	 *
	 * @return void
	 */
	public function test_has_api_key_when_option_not_array(): void {
		$handler = new AI_Handler();

		// Set option to non-array value.
		update_option( 'wp_ai_client_provider_credentials', 'invalid' );

		$has_key = Utility::invoke_hidden_method( $handler, 'has_api_key' );

		$this->assertFalse( $has_key );

		// Clean up.
		delete_option( 'wp_ai_client_provider_credentials' );
	}

	/**
	 * Coverage for has_api_key when credentials array has empty values.
	 *
	 * @covers ::has_api_key
	 *
	 * @return void
	 */
	public function test_has_api_key_when_empty_values(): void {
		$handler = new AI_Handler();

		// Set option with empty values.
		update_option(
			'wp_ai_client_provider_credentials',
			array(
				'openai' => '',
				'google' => null,
			)
		);

		$has_key = Utility::invoke_hidden_method( $handler, 'has_api_key' );

		$this->assertFalse( $has_key );

		// Clean up.
		delete_option( 'wp_ai_client_provider_credentials' );
	}

	/**
	 * Coverage for get_gatherpress_abilities when Abilities API is not available.
	 *
	 * @covers ::get_gatherpress_abilities
	 *
	 * @return void
	 */
	public function test_get_gatherpress_abilities_when_api_not_available(): void {
		$handler = new AI_Handler();

		// Only test if wp_get_ability doesn't exist.
		if ( function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API is available in test environment.' );
		}

		$abilities = Utility::invoke_hidden_method( $handler, 'get_gatherpress_abilities' );

		$this->assertIsArray( $abilities );
		$this->assertEmpty( $abilities );
	}

	/**
	 * Coverage for get_gatherpress_abilities when Abilities API is available.
	 *
	 * @covers ::get_gatherpress_abilities
	 *
	 * @return void
	 */
	public function test_get_gatherpress_abilities_when_api_available(): void {
		$handler = new AI_Handler();

		// Only test if wp_get_ability exists.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'Abilities API is not available in test environment.' );
		}

		$abilities = Utility::invoke_hidden_method( $handler, 'get_gatherpress_abilities' );

		$this->assertIsArray( $abilities );
		// Should return an array (may be empty if no abilities are registered).
	}

	/**
	 * Coverage for function_name_to_ability_name with valid function name.
	 *
	 * @covers ::function_name_to_ability_name
	 *
	 * @return void
	 */
	public function test_function_name_to_ability_name_with_valid_name(): void {
		$handler = new AI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'function_name_to_ability_name',
			array( 'wpab__gatherpress__list_venues' )
		);

		$this->assertSame( 'gatherpress/list_venues', $result );
	}

	/**
	 * Coverage for function_name_to_ability_name with null.
	 *
	 * @covers ::function_name_to_ability_name
	 *
	 * @return void
	 */
	public function test_function_name_to_ability_name_with_null(): void {
		$handler = new AI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'function_name_to_ability_name',
			array( null )
		);

		$this->assertNull( $result );
	}

	/**
	 * Coverage for function_name_to_ability_name with invalid prefix.
	 *
	 * @covers ::function_name_to_ability_name
	 *
	 * @return void
	 */
	public function test_function_name_to_ability_name_with_invalid_prefix(): void {
		$handler = new AI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'function_name_to_ability_name',
			array( 'invalid_function_name' )
		);

		$this->assertNull( $result );
	}

	/**
	 * Coverage for function_name_to_ability_name with empty string.
	 *
	 * @covers ::function_name_to_ability_name
	 *
	 * @return void
	 */
	public function test_function_name_to_ability_name_with_empty_string(): void {
		$handler = new AI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'function_name_to_ability_name',
			array( '' )
		);

		$this->assertNull( $result );
	}

	/**
	 * Coverage for function_name_to_ability_name with multiple underscores.
	 *
	 * @covers ::function_name_to_ability_name
	 *
	 * @return void
	 */
	public function test_function_name_to_ability_name_with_multiple_underscores(): void {
		$handler = new AI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'function_name_to_ability_name',
			array( 'wpab__gatherpress__create__event' )
		);

		$this->assertSame( 'gatherpress/create/event', $result );
	}

	/**
	 * Coverage for is_ability_call with valid ability call.
	 *
	 * This test verifies the method exists and structure is correct.
	 * Full testing would require creating FunctionCall objects which is complex.
	 *
	 * @covers ::is_ability_call
	 *
	 * @return void
	 */
	public function test_is_ability_call_method_exists(): void {
		$handler = new AI_Handler();

		// Verify the method exists and is private.
		$method = new \ReflectionMethod( $handler, 'is_ability_call' );
		$this->assertTrue( $method->isPrivate(), 'is_ability_call should be private.' );

		// Verify the method signature.
		$parameters = $method->getParameters();
		$this->assertCount( 1, $parameters, 'is_ability_call should have 1 parameter.' );
	}

	/**
	 * Coverage for has_ability_calls method exists.
	 *
	 * This test verifies the method exists and structure is correct.
	 * Full testing would require creating Message objects which is complex.
	 *
	 * @covers ::has_ability_calls
	 *
	 * @return void
	 */
	public function test_has_ability_calls_method_exists(): void {
		$handler = new AI_Handler();

		// Verify the method exists and is private.
		$method = new \ReflectionMethod( $handler, 'has_ability_calls' );
		$this->assertTrue( $method->isPrivate(), 'has_ability_calls should be private.' );

		// Verify the method signature.
		$parameters = $method->getParameters();
		$this->assertCount( 1, $parameters, 'has_ability_calls should have 1 parameter.' );
	}

	/**
	 * Coverage for extract_text_content method exists.
	 *
	 * This test verifies the method exists and structure is correct.
	 * Full testing would require creating Message objects which is complex.
	 *
	 * @covers ::extract_text_content
	 *
	 * @return void
	 */
	public function test_extract_text_content_method_exists(): void {
		$handler = new AI_Handler();

		// Verify the method exists and is private.
		$method = new \ReflectionMethod( $handler, 'extract_text_content' );
		$this->assertTrue( $method->isPrivate(), 'extract_text_content should be private.' );

		// Verify the method signature.
		$parameters = $method->getParameters();
		$this->assertCount( 1, $parameters, 'extract_text_content should have 1 parameter.' );
	}

	/**
	 * Coverage for execute_ability method exists.
	 *
	 * This test verifies the method exists and structure is correct.
	 * Full testing would require creating FunctionCall objects and mocking abilities.
	 *
	 * @covers ::execute_ability
	 *
	 * @return void
	 */
	public function test_execute_ability_method_exists(): void {
		$handler = new AI_Handler();

		// Verify the method exists and is private.
		$method = new \ReflectionMethod( $handler, 'execute_ability' );
		$this->assertTrue( $method->isPrivate(), 'execute_ability should be private.' );

		// Verify the method signature.
		$parameters = $method->getParameters();
		$this->assertCount( 1, $parameters, 'execute_ability should have 1 parameter.' );
	}

	/**
	 * Coverage for process_conversation_loop method exists.
	 *
	 * This test verifies the method exists and structure is correct.
	 * Full testing would require extensive mocking of wp-ai-client.
	 *
	 * @covers ::process_conversation_loop
	 *
	 * @return void
	 */
	public function test_process_conversation_loop_method_exists(): void {
		$handler = new AI_Handler();

		// Verify the method exists and is private.
		$method = new \ReflectionMethod( $handler, 'process_conversation_loop' );
		$this->assertTrue( $method->isPrivate(), 'process_conversation_loop should be private.' );

		// Verify the method signature.
		$parameters = $method->getParameters();
		$this->assertCount( 4, $parameters, 'process_conversation_loop should have 4 parameters.' );
	}
}
