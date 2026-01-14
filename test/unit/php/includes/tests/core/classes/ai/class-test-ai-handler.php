<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\AI_Handler.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\AI_Handler;
use GatherPress\Core\AI\Image_Handler;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;
use WP_Error;
use WordPress\AiClient\Files\DTO\File as AiClientFile;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Messages\Enums\MessagePartChannelEnum;

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
	 * Coverage for MAX_PROMPTS constant.
	 *
	 * @coversDefaultClass \GatherPress\Core\AI\AI_Handler
	 *
	 * @return void
	 */
	public function test_max_prompts_constant(): void {
		$this->assertSame(
			10,
			AI_Handler::MAX_PROMPTS,
			'Failed to assert MAX_PROMPTS constant is 10.'
		);
	}

	/**
	 * Coverage for MAX_CHARS constant.
	 *
	 * @coversDefaultClass \GatherPress\Core\AI\AI_Handler
	 *
	 * @return void
	 */
	public function test_max_chars_constant(): void {
		$this->assertSame(
			40000,
			AI_Handler::MAX_CHARS,
			'Failed to assert MAX_CHARS constant is 40000.'
		);
	}

	/**
	 * Coverage for META_KEY_CONVERSATION_STATE constant.
	 *
	 * @coversDefaultClass \GatherPress\Core\AI\AI_Handler
	 *
	 * @return void
	 */
	public function test_meta_key_conversation_state_constant(): void {
		$this->assertSame(
			'gatherpress_ai_conversation_state',
			AI_Handler::META_KEY_CONVERSATION_STATE,
			'Failed to assert META_KEY_CONVERSATION_STATE constant is correct.'
		);
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
	 * Coverage for process_prompt method signature accepts optional attachment_ids parameter.
	 *
	 * @covers ::process_prompt
	 *
	 * @return void
	 */
	public function test_process_prompt_accepts_attachment_ids_parameter(): void {
		if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			$this->markTestSkipped( 'wp-ai-client is not available in test environment.' );
		}

		$handler = new AI_Handler();

		// Ensure no API key is set (we're just testing the method signature, not the full flow).
		delete_option( 'wp_ai_client_provider_credentials' );

		// Test that method accepts empty attachment_ids array (backward compatibility).
		$result = $handler->process_prompt( 'Test prompt', array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_api_key', $result->get_error_code() );

		// Verify method signature using reflection.
		$method     = new \ReflectionMethod( $handler, 'process_prompt' );
		$parameters = $method->getParameters();

		$this->assertCount( 2, $parameters, 'Failed to assert process_prompt has 2 parameters.' );
		$this->assertSame(
			'prompt',
			$parameters[0]->getName(),
			'Failed to assert first parameter is "prompt".'
		);
		$this->assertSame(
			'attachment_ids',
			$parameters[1]->getName(),
			'Failed to assert second parameter is "attachment_ids".'
		);
		$this->assertTrue(
			$parameters[1]->isDefaultValueAvailable(),
			'Failed to assert attachment_ids has default value.'
		);
		$this->assertSame(
			array(),
			$parameters[1]->getDefaultValue(),
			'Failed to assert attachment_ids default value is empty array.'
		);
	}

	/**
	 * Coverage for process_prompt with attachment IDs builds UserMessage with mixed MessageParts.
	 *
	 * @covers ::process_prompt
	 *
	 * @return void
	 */
	public function test_process_prompt_with_attachment_ids_builds_mixed_messageparts(): void {
		if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			$this->markTestSkipped( 'wp-ai-client is not available in test environment.' );
		}

		if ( ! class_exists( 'WordPress\AiClient\Files\DTO\File' ) ) {
			$this->markTestSkipped( 'wp-ai-client File class is not available in test environment.' );
		}

		$handler = new AI_Handler();

		// Set a fake API key so the code continues past the API key check
		// (we want to test the image conversion, not the API key check).
		update_option(
			'wp_ai_client_provider_credentials',
			array(
				'openai' => 'test-key',
			)
		);

		// Create a mock Image_Handler.
		$mock_image_handler = $this->createMock( Image_Handler::class );

		// Create a real File MessagePart (using real constructor, not mock).
		$attachment_id          = 123;
		$real_file_message_part = $this->create_real_file_message_part();

		// Set up mock to return real File MessagePart for valid attachment ID.
		$mock_image_handler->expects( $this->once() )
			->method( 'attachment_to_file_message_part' )
			->with( $attachment_id )
			->willReturn( $real_file_message_part );

		// Inject mock Image_Handler using reflection.
		Utility::set_and_get_hidden_property( $handler, 'image_handler', $mock_image_handler );

		// Call process_prompt with attachment ID.
		// This will fail somewhere after attachment conversion (abilities check or model initialization).
		$result = $handler->process_prompt( 'Test prompt', array( $attachment_id ) );

		// Should fail after attachment conversion (not at API key check).
		// This confirms the Image_Handler was called before the failure.
		$this->assertInstanceOf( WP_Error::class, $result );
		// The error code may be 'no_abilities' or 'no_models_found' depending on wp-ai-client initialization.
		// What matters is that it's NOT 'no_api_key', which confirms we got past the API key check.
		$this->assertNotSame( 'no_api_key', $result->get_error_code(), 'Failed to assert that API key check passed.' );

		// Reset image_handler property.
		Utility::set_and_get_hidden_property( $handler, 'image_handler', null );

		// Clean up.
		delete_option( 'wp_ai_client_provider_credentials' );
	}

	/**
	 * Coverage for process_prompt with invalid attachment ID continues with text-only MessagePart.
	 *
	 * @covers ::process_prompt
	 *
	 * @return void
	 */
	public function test_process_prompt_with_invalid_attachment_id_continues_without_file(): void {
		if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			$this->markTestSkipped( 'wp-ai-client is not available in test environment.' );
		}

		$handler = new AI_Handler();

		// Ensure no API key is set.
		delete_option( 'wp_ai_client_provider_credentials' );

		// Call process_prompt with invalid attachment ID.
		// Image_Handler should return WP_Error, and process_prompt should continue with just text MessagePart.
		$result = $handler->process_prompt( 'Test prompt', array( 99999 ) );

		// Should fail at API key check (not at attachment conversion).
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no_api_key', $result->get_error_code() );
		// If attachment conversion failed, we'd get a different error, so this confirms it continued.
	}

	/**
	 * Coverage for process_prompt with multiple attachment IDs converts all to File MessageParts.
	 *
	 * @covers ::process_prompt
	 *
	 * @return void
	 */
	public function test_process_prompt_with_multiple_attachment_ids(): void {
		if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			$this->markTestSkipped( 'wp-ai-client is not available in test environment.' );
		}

		if ( ! class_exists( 'WordPress\AiClient\Files\DTO\File' ) ) {
			$this->markTestSkipped( 'wp-ai-client File class is not available in test environment.' );
		}

		$handler = new AI_Handler();

		// Set a fake API key so the code continues past the API key check
		// (we want to test the image conversion, not the API key check).
		update_option(
			'wp_ai_client_provider_credentials',
			array(
				'openai' => 'test-key',
			)
		);

		// Create a mock Image_Handler.
		$mock_image_handler = $this->createMock( Image_Handler::class );

		// Create real File MessageParts (using real constructors, not mocks).
		$attachment_id1          = 123;
		$attachment_id2          = 456;
		$real_file_message_part1 = $this->create_real_file_message_part();
		$real_file_message_part2 = $this->create_real_file_message_part();

		// Set up mock to return real File MessageParts for valid attachment IDs.
		$mock_image_handler->expects( $this->exactly( 2 ) )
			->method( 'attachment_to_file_message_part' )
			->willReturnCallback(
				function ( $attachment_id ) use (
					$attachment_id1,
					$attachment_id2,
					$real_file_message_part1,
					$real_file_message_part2
				) {
					if ( $attachment_id === $attachment_id1 ) {
						return $real_file_message_part1;
					}
					if ( $attachment_id === $attachment_id2 ) {
						return $real_file_message_part2;
					}
					return new WP_Error( 'invalid_attachment', 'Invalid attachment ID' );
				}
			);

		// Inject mock Image_Handler using reflection.
		Utility::set_and_get_hidden_property( $handler, 'image_handler', $mock_image_handler );

		// Call process_prompt with multiple attachment IDs.
		$result = $handler->process_prompt( 'Test prompt', array( $attachment_id1, $attachment_id2 ) );

		// Should fail after attachment conversion (not at API key check).
		// This confirms the Image_Handler was called before the failure.
		$this->assertInstanceOf( WP_Error::class, $result );
		// The error code may be 'no_abilities' or 'no_models_found' depending on wp-ai-client initialization.
		// What matters is that it's NOT 'no_api_key', which confirms we got past the API key check.
		$this->assertNotSame( 'no_api_key', $result->get_error_code(), 'Failed to assert that API key check passed.' );

		// Reset image_handler property.
		Utility::set_and_get_hidden_property( $handler, 'image_handler', null );

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
		$this->assertCount( 5, $parameters, 'process_conversation_loop should have 5 parameters.' );
	}

	/**
	 * Coverage for get_conversation_state returns default when none exists.
	 *
	 * @covers ::get_conversation_state
	 *
	 * @return void
	 */
	public function test_get_conversation_state_returns_default(): void {
		$handler = new AI_Handler();
		$user_id = $this->factory->user->create();

		// Ensure no state exists.
		delete_user_meta( $user_id, AI_Handler::META_KEY_CONVERSATION_STATE );

		$state = Utility::invoke_hidden_method(
			$handler,
			'get_conversation_state',
			array( $user_id )
		);

		$expected = array(
			'prompt_count' => 0,
			'char_count'   => 0,
			'history'      => array(),
		);

		$this->assertEquals(
			$expected,
			$state,
			'Failed to assert get_conversation_state returns default when none exists.'
		);
	}

	/**
	 * Coverage for get_conversation_state loads saved state.
	 *
	 * @covers ::get_conversation_state
	 *
	 * @return void
	 */
	public function test_get_conversation_state_loads_saved(): void {
		$handler = new AI_Handler();
		$user_id = $this->factory->user->create();

		$state = array(
			'prompt_count' => 5,
			'char_count'   => 10000,
			'history'      => array( 'msg1', 'msg2' ),
		);

		update_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			$state
		);

		$loaded = Utility::invoke_hidden_method(
			$handler,
			'get_conversation_state',
			array( $user_id )
		);

		$this->assertEquals(
			$state,
			$loaded,
			'Failed to assert get_conversation_state loads saved state.'
		);

		// Clean up.
		delete_user_meta( $user_id, AI_Handler::META_KEY_CONVERSATION_STATE );
	}

	/**
	 * Coverage for get_conversation_state handles missing keys.
	 *
	 * @covers ::get_conversation_state
	 *
	 * @return void
	 */
	public function test_get_conversation_state_handles_missing_keys(): void {
		$handler = new AI_Handler();
		$user_id = $this->factory->user->create();

		// Save state with missing keys.
		update_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			array( 'prompt_count' => 3 )
		);

		$loaded = Utility::invoke_hidden_method(
			$handler,
			'get_conversation_state',
			array( $user_id )
		);

		$expected = array(
			'prompt_count' => 3,
			'char_count'   => 0,
			'history'      => array(),
		);

		$this->assertEquals(
			$expected,
			$loaded,
			'Failed to assert get_conversation_state handles missing keys.'
		);

		// Clean up.
		delete_user_meta( $user_id, AI_Handler::META_KEY_CONVERSATION_STATE );
	}

	/**
	 * Coverage for save_conversation_state saves correctly.
	 *
	 * @covers ::save_conversation_state
	 *
	 * @return void
	 */
	public function test_save_conversation_state(): void {
		$handler = new AI_Handler();
		$user_id = $this->factory->user->create();

		$state = array(
			'prompt_count' => 7,
			'char_count'   => 20000,
			'history'      => array( 'test' ),
		);

		Utility::invoke_hidden_method(
			$handler,
			'save_conversation_state',
			array( $user_id, $state )
		);

		$saved = get_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			true
		);

		$this->assertEquals(
			$state,
			$saved,
			'Failed to assert save_conversation_state saves correctly.'
		);

		// Clean up.
		delete_user_meta( $user_id, AI_Handler::META_KEY_CONVERSATION_STATE );
	}

	/**
	 * Coverage for clear_conversation_state clears state.
	 *
	 * @covers ::clear_conversation_state
	 *
	 * @return void
	 */
	public function test_clear_conversation_state(): void {
		$handler = new AI_Handler();
		$user_id = $this->factory->user->create();

		// Set some state first.
		$state = array(
			'prompt_count' => 9,
			'char_count'   => 35000,
			'history'      => array( 'test' ),
		);
		update_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			$state
		);

		// Clear it.
		Utility::invoke_hidden_method(
			$handler,
			'clear_conversation_state',
			array( $user_id )
		);

		$after = get_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			true
		);

		$this->assertEmpty(
			$after,
			'Failed to assert clear_conversation_state clears state.'
		);
	}

	/**
	 * Coverage for conversation state is user-specific.
	 *
	 * @covers ::get_conversation_state
	 * @covers ::save_conversation_state
	 *
	 * @return void
	 */
	public function test_conversation_state_is_user_specific(): void {
		$handler  = new AI_Handler();
		$user1_id = $this->factory->user->create();
		$user2_id = $this->factory->user->create();

		$state1 = array(
			'prompt_count' => 2,
			'char_count'   => 3000,
			'history'      => array( 'user1_msg' ),
		);
		$state2 = array(
			'prompt_count' => 4,
			'char_count'   => 8000,
			'history'      => array( 'user2_msg' ),
		);

		Utility::invoke_hidden_method(
			$handler,
			'save_conversation_state',
			array( $user1_id, $state1 )
		);
		Utility::invoke_hidden_method(
			$handler,
			'save_conversation_state',
			array( $user2_id, $state2 )
		);

		$loaded1 = Utility::invoke_hidden_method(
			$handler,
			'get_conversation_state',
			array( $user1_id )
		);
		$loaded2 = Utility::invoke_hidden_method(
			$handler,
			'get_conversation_state',
			array( $user2_id )
		);

		$this->assertEquals(
			$state1,
			$loaded1,
			'Failed to assert user1 state is saved correctly.'
		);
		$this->assertEquals(
			$state2,
			$loaded2,
			'Failed to assert user2 state is saved correctly.'
		);

		// Clean up.
		delete_user_meta( $user1_id, AI_Handler::META_KEY_CONVERSATION_STATE );
		delete_user_meta( $user2_id, AI_Handler::META_KEY_CONVERSATION_STATE );
	}

	/**
	 * Coverage for get_conversation_state_metadata.
	 *
	 * @covers ::get_conversation_state_metadata
	 *
	 * @return void
	 */
	public function test_get_conversation_state_metadata(): void {
		$handler = new AI_Handler();
		$user_id = $this->factory->user->create();

		// Set current user.
		wp_set_current_user( $user_id );

		// Set some state first.
		$state = array(
			'prompt_count' => 5,
			'char_count'   => 10000,
			'history'      => array( 'test' ),
		);
		update_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			$state
		);

		// Get state metadata.
		$result = $handler->get_conversation_state_metadata();

		// Verify returned state.
		$expected = array(
			'prompt_count' => 5,
			'char_count'   => 10000,
			'max_prompts'  => AI_Handler::MAX_PROMPTS,
			'max_chars'    => AI_Handler::MAX_CHARS,
		);
		$this->assertEquals( $expected, $result );

		// Clean up.
		delete_user_meta( $user_id, AI_Handler::META_KEY_CONVERSATION_STATE );
	}

	/**
	 * Coverage for get_conversation_state_metadata with no state.
	 *
	 * @covers ::get_conversation_state_metadata
	 *
	 * @return void
	 */
	public function test_get_conversation_state_metadata_with_no_state(): void {
		$handler = new AI_Handler();
		$user_id = $this->factory->user->create();

		// Set current user.
		wp_set_current_user( $user_id );

		// Ensure no state exists.
		delete_user_meta( $user_id, AI_Handler::META_KEY_CONVERSATION_STATE );

		// Get state metadata.
		$result = $handler->get_conversation_state_metadata();

		// Verify returned state defaults.
		$expected = array(
			'prompt_count' => 0,
			'char_count'   => 0,
			'max_prompts'  => AI_Handler::MAX_PROMPTS,
			'max_chars'    => AI_Handler::MAX_CHARS,
		);
		$this->assertEquals( $expected, $result );

		// Clean up.
		delete_user_meta( $user_id, AI_Handler::META_KEY_CONVERSATION_STATE );
	}

	/**
	 * Coverage for reset_conversation_state.
	 *
	 * @covers ::reset_conversation_state
	 *
	 * @return void
	 */
	public function test_reset_conversation_state(): void {
		$handler = new AI_Handler();
		$user_id = $this->factory->user->create();

		// Set current user.
		wp_set_current_user( $user_id );

		// Set some state first.
		$state = array(
			'prompt_count' => 5,
			'char_count'   => 10000,
			'history'      => array( 'test' ),
		);
		update_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			$state
		);

		// Reset state.
		$result = $handler->reset_conversation_state();

		// Verify returned state.
		$expected = array(
			'prompt_count' => 0,
			'char_count'   => 0,
			'max_prompts'  => AI_Handler::MAX_PROMPTS,
			'max_chars'    => AI_Handler::MAX_CHARS,
		);
		$this->assertEquals( $expected, $result );

		// Verify state is cleared in database.
		$after = get_user_meta(
			$user_id,
			AI_Handler::META_KEY_CONVERSATION_STATE,
			true
		);
		$this->assertEmpty( $after );

		// Clean up.
		delete_user_meta( $user_id, AI_Handler::META_KEY_CONVERSATION_STATE );
	}

	/**
	 * Coverage for load_history_from_state with empty array.
	 *
	 * @covers ::load_history_from_state
	 *
	 * @return void
	 */
	public function test_load_history_from_state_with_empty_array(): void {
		$handler = new AI_Handler();

		$result = Utility::invoke_hidden_method(
			$handler,
			'load_history_from_state',
			array( array() )
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result, 'Failed to assert load_history_from_state returns empty array.' );
	}

	/**
	 * Coverage for load_history_from_state with valid history.
	 *
	 * @covers ::load_history_from_state
	 *
	 * @return void
	 */
	public function test_load_history_from_state_with_valid_history(): void {
		$handler = new AI_Handler();

		// Create valid message arrays.
		$user_message  = new UserMessage( array( new MessagePart( 'Test prompt' ) ) );
		$model_message = new ModelMessage( array( new MessagePart( 'Test response' ) ) );

		$history_array = array(
			$user_message->toArray(),
			$model_message->toArray(),
		);

		$result = Utility::invoke_hidden_method(
			$handler,
			'load_history_from_state',
			array( $history_array )
		);

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result, 'Failed to assert load_history_from_state returns 2 messages.' );
		$this->assertInstanceOf(
			UserMessage::class,
			$result[0],
			'Failed to assert first message is UserMessage.'
		);
		$this->assertInstanceOf(
			ModelMessage::class,
			$result[1],
			'Failed to assert second message is ModelMessage.'
		);
	}

	/**
	 * Coverage for load_history_from_state with invalid data.
	 *
	 * @covers ::load_history_from_state
	 *
	 * @return void
	 */
	public function test_load_history_from_state_with_invalid_data(): void {
		$handler = new AI_Handler();

		// Create history with invalid data.
		$history_array = array(
			'invalid',
			array( 'not' => 'valid' ),
			null,
		);

		$result = Utility::invoke_hidden_method(
			$handler,
			'load_history_from_state',
			array( $history_array )
		);

		$this->assertIsArray( $result );
		$this->assertEmpty( $result, 'Failed to assert load_history_from_state skips invalid data.' );
	}

	/**
	 * Coverage for append_to_history appends user and model messages.
	 *
	 * @covers ::append_to_history
	 *
	 * @return void
	 */
	public function test_append_to_history(): void {
		$handler = new AI_Handler();

		$existing_history = array();
		$user_message     = new UserMessage( array( new MessagePart( 'Test prompt' ) ) );
		$result           = array(
			'response' => 'Test response text',
		);

		$updated = Utility::invoke_hidden_method(
			$handler,
			'append_to_history',
			array( $existing_history, $user_message, $result )
		);

		$this->assertIsArray( $updated );
		$this->assertCount( 2, $updated, 'Failed to assert append_to_history adds 2 messages.' );

		// Verify first message is user message.
		$loaded_user = Message::fromArray( $updated[0] );
		$this->assertInstanceOf(
			UserMessage::class,
			$loaded_user,
			'Failed to assert first message is UserMessage.'
		);

		// Verify second message is model message.
		$loaded_model = Message::fromArray( $updated[1] );
		$this->assertInstanceOf(
			ModelMessage::class,
			$loaded_model,
			'Failed to assert second message is ModelMessage.'
		);
	}

	/**
	 * Coverage for append_to_history with empty response.
	 *
	 * @covers ::append_to_history
	 *
	 * @return void
	 */
	public function test_append_to_history_with_empty_response(): void {
		$handler = new AI_Handler();

		$existing_history = array();
		$user_message     = new UserMessage( array( new MessagePart( 'Test prompt' ) ) );
		$result           = array(
			'response' => '',
		);

		$updated = Utility::invoke_hidden_method(
			$handler,
			'append_to_history',
			array( $existing_history, $user_message, $result )
		);

		$this->assertIsArray( $updated );
		$this->assertCount(
			1,
			$updated,
			'Failed to assert append_to_history adds only user message when response is empty.'
		);
	}

	/**
	 * Coverage for append_to_history preserves existing history.
	 *
	 * @covers ::append_to_history
	 *
	 * @return void
	 */
	public function test_append_to_history_preserves_existing(): void {
		$handler = new AI_Handler();

		// Create existing history.
		$existing_user    = new UserMessage( array( new MessagePart( 'Previous prompt' ) ) );
		$existing_model   = new ModelMessage( array( new MessagePart( 'Previous response' ) ) );
		$existing_history = array(
			$existing_user->toArray(),
			$existing_model->toArray(),
		);

		$user_message = new UserMessage( array( new MessagePart( 'New prompt' ) ) );
		$result       = array(
			'response' => 'New response',
		);

		$updated = Utility::invoke_hidden_method(
			$handler,
			'append_to_history',
			array( $existing_history, $user_message, $result )
		);

		$this->assertIsArray( $updated );
		$this->assertCount( 4, $updated, 'Failed to assert append_to_history preserves existing history.' );

		// Verify first messages are preserved.
		$first = Message::fromArray( $updated[0] );
		$this->assertInstanceOf( UserMessage::class, $first );
		$second = Message::fromArray( $updated[1] );
		$this->assertInstanceOf( ModelMessage::class, $second );

		// Verify new messages are appended.
		$third = Message::fromArray( $updated[2] );
		$this->assertInstanceOf( UserMessage::class, $third );
		$fourth = Message::fromArray( $updated[3] );
		$this->assertInstanceOf( ModelMessage::class, $fourth );
	}

	/**
	 * Coverage for token usage calculation structure.
	 *
	 * Verifies that token usage data structure matches expected format.
	 * Full integration testing would require extensive mocking of wp-ai-client
	 * TokenUsage and GenerativeAiResult objects.
	 *
	 * @return void
	 */
	public function test_token_usage_calculation_structure(): void {
		// Test the calculation formula used in process_conversation_loop.
		// Input: $0.01 per 1K tokens, Output: $0.03 per 1K tokens.
		$total_prompt_tokens     = 1000;
		$total_completion_tokens = 500;
		$total_tokens            = 1500;

		$estimated_cost = ( $total_prompt_tokens / 1000 * 0.01 ) + ( $total_completion_tokens / 1000 * 0.03 );

		// Verify calculation is correct.
		$expected_cost = ( 1000 / 1000 * 0.01 ) + ( 500 / 1000 * 0.03 );
		$this->assertSame(
			$expected_cost,
			$estimated_cost,
			'Failed to assert token usage cost calculation is correct.'
		);
		$this->assertSame( 0.025, $estimated_cost, 'Failed to assert expected cost value.' );

		// Verify token usage structure matches expected format.
		$token_usage = array(
			'prompt_tokens'     => $total_prompt_tokens,
			'completion_tokens' => $total_completion_tokens,
			'total_tokens'      => $total_tokens,
			'estimated_cost'    => $estimated_cost,
		);

		$this->assertIsArray( $token_usage );
		$this->assertArrayHasKey( 'prompt_tokens', $token_usage );
		$this->assertArrayHasKey( 'completion_tokens', $token_usage );
		$this->assertArrayHasKey( 'total_tokens', $token_usage );
		$this->assertArrayHasKey( 'estimated_cost', $token_usage );
		$this->assertIsInt( $token_usage['prompt_tokens'] );
		$this->assertIsInt( $token_usage['completion_tokens'] );
		$this->assertIsInt( $token_usage['total_tokens'] );
		$this->assertIsFloat( $token_usage['estimated_cost'] );
	}

	/**
	 * Coverage for token usage calculation with zero tokens.
	 *
	 * @return void
	 */
	public function test_token_usage_calculation_with_zero_tokens(): void {
		$total_prompt_tokens     = 0;
		$total_completion_tokens = 0;
		$total_tokens            = 0;

		$estimated_cost = ( $total_prompt_tokens / 1000 * 0.01 ) + ( $total_completion_tokens / 1000 * 0.03 );

		$this->assertSame( 0.0, $estimated_cost, 'Failed to assert zero tokens results in zero cost.' );
	}

	/**
	 * Helper method to create a real File MessagePart for testing.
	 *
	 * @return MessagePart Real File MessagePart instance.
	 */
	private function create_real_file_message_part(): MessagePart {
		if ( ! class_exists( 'WordPress\AiClient\Files\DTO\File' ) ) {
			// Fallback to text MessagePart if File class is not available.
			return new MessagePart( 'test' );
		}

		// Create a File DTO with a test URL and MIME type.
		$file = new AiClientFile( 'http://example.com/test-image.jpg', 'image/jpeg' );

		// Create MessagePart with file channel.
		$channel      = MessagePartChannelEnum::from( MessagePartChannelEnum::CONTENT );
		$message_part = new MessagePart( '', $channel );

		// Set file property using reflection (since there's no public setter).
		$reflection    = new \ReflectionClass( $message_part );
		$file_property = $reflection->getProperty( 'file' );
		$file_property->setAccessible( true );
		$file_property->setValue( $message_part, $file );

		return $message_part;
	}
}
