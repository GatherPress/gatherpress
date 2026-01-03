<?php
/**
 * Handles AI integration using wp-ai-client.
 *
 * @package GatherPress\Core\AI
 * @since 1.0.0
 */

namespace GatherPress\Core\AI;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use WP_Error;
use WordPress\AI_Client\AI_Client;
use WordPress\AI_Client\Builders\Prompt_Builder;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\DTO\ModelMessage;
use WordPress\AiClient\Messages\DTO\UserMessage;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;
use WordPress\AiClient\Tools\DTO\FunctionResponse;

/**
 * Class AI_Handler.
 *
 * Manages communication with AI providers via wp-ai-client and ability execution.
 *
 * @since 1.0.0
 */
class AI_Handler {
	/**
	 * Maximum conversation iterations to prevent infinite loops.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_ITERATIONS = 15;

	/**
	 * Maximum prompts before reset.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_PROMPTS = 10;

	/**
	 * Maximum characters before reset.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const MAX_CHARS = 40000;

	/**
	 * User meta key for conversation state.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_CONVERSATION_STATE = 'gatherpress_ai_conversation_state';

	/**
	 * Process a user prompt with AI.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prompt User's natural language prompt.
	 * @return array|WP_Error Result of processing or error.
	 */
	public function process_prompt( $prompt ) {
		// Check if wp-ai-client is available.
		if ( ! class_exists( 'WordPress\AI_Client\AI_Client' ) ) {
			return new WP_Error(
				'wp_ai_client_not_available',
				__( 'wp-ai-client is not available.', 'gatherpress' )
			);
		}

		// Check if API credentials are configured.
		if ( ! $this->has_api_key() ) {
			return new WP_Error(
				'no_api_key',
				__(
					'AI API key is not configured. Please configure your API credentials in Settings > AI Credentials.',
					'gatherpress'
				)
			);
		}

		// Get available GatherPress abilities.
		$abilities = $this->get_gatherpress_abilities();

		if ( empty( $abilities ) ) {
			return new WP_Error(
				'no_abilities',
				__( 'No GatherPress abilities are available.', 'gatherpress' )
			);
		}

		// Build system message.
		$current_date   = gmdate( 'Y-m-d' );
		$system_content = sprintf(
			'You are an AI assistant for GatherPress events. Today is %s.

Rules:
- For recurring events: Call calculate-dates first, then use those exact dates. '
			. 'Do NOT calculate dates yourself.
- When user mentions a venue by name, call list-venues to get the venue ID.
- Always create events as drafts. Event dates must be after %s.
- If success=true: Display the data (even if empty - say "No X found", '
			. 'don\'t say "error"). Only say "error" if success=false.',
			$current_date,
			$current_date
		);

		// Store original user message for conversation loop.
		$original_user_message = new UserMessage( array( new MessagePart( $prompt ) ) );

		// Load conversation state.
		$user_id      = get_current_user_id();
		$state        = $this->get_conversation_state( $user_id );
		$prompt_count = $state['prompt_count'];
		$char_count   = $state['char_count'];

		// Check if limits are exceeded and auto-reset if needed.
		if ( $prompt_count >= self::MAX_PROMPTS || $char_count >= self::MAX_CHARS ) {
			// Clear state to reset conversation.
			$this->clear_conversation_state( $user_id );
			// Reset to defaults.
			$state        = $this->get_conversation_state( $user_id );
			$prompt_count = 0;
			$char_count   = 0;
		}

		// Increment prompt count and add prompt length to char count.
		++$prompt_count;
		$char_count += strlen( $prompt );

		// Load existing conversation history and convert to Message objects.
		$existing_history = $this->load_history_from_state( $state['history'] );

		// Build initial message array with existing history and new user message.
		$initial_messages = array_merge( $existing_history, array( $original_user_message ) );

		// Create prompt builder with history and user prompt.
		$builder = AI_Client::prompt( $initial_messages );

		// Set system instruction.
		$builder->using_system_instruction( $system_content );

		// Register abilities with the builder by creating function declarations.
		$function_declarations = array();
		foreach ( $abilities as $ability_name ) {
			$ability = wp_get_ability( $ability_name );
			if ( ! $ability ) {
				continue;
			}

			// Convert ability name to function name.
			// Example: "gatherpress/list-events" -> "wpab__gatherpress__list_events".
			$ability_name  = $ability->get_name();
			$function_name = 'wpab__' . str_replace( '/', '__', $ability_name );
			$input_schema  = $ability->get_input_schema();

			// Ensure input_schema is either a valid object schema or null.
			// The schema must have 'type' => 'object' and 'properties' (even if empty).
			$schema_for_declaration = null;
			if ( is_array( $input_schema )
				&& isset( $input_schema['type'] )
				&& 'object' === $input_schema['type']
			) {
				// Ensure properties key exists and is an object (not array).
				// Empty array [] serializes to [] in JSON, but we need {} (object).
				if ( ! isset( $input_schema['properties'] ) || empty( $input_schema['properties'] ) ) {
					$input_schema['properties'] = new \stdClass();
				}
				// Use the schema as-is (it's a valid JSON schema object).
				$schema_for_declaration = $input_schema;
			}

			$function_declarations[] = new FunctionDeclaration(
				$function_name,
				$ability->get_description(),
				$schema_for_declaration
			);
		}

		if ( ! empty( $function_declarations ) ) {
			$builder->using_function_declarations( ...$function_declarations );
		}

		// Process the conversation loop.
		$result = $this->process_conversation_loop(
			$builder,
			$original_user_message,
			$system_content,
			$function_declarations,
			$existing_history
		);

		// If result is error, return early without saving state.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Add response length to char count.
		$response_text = isset( $result['response'] ) ? $result['response'] : '';
		$char_count   += strlen( $response_text );

		// Update history: append new user message and AI response.
		$updated_history = $this->append_to_history(
			$state['history'],
			$original_user_message,
			$result
		);

		// Save updated state.
		$updated_state = array(
			'prompt_count' => $prompt_count,
			'char_count'   => $char_count,
			'history'      => $updated_history,
		);
		$this->save_conversation_state( $user_id, $updated_state );

		// Add state metadata to result for frontend display.
		$result['state'] = array(
			'prompt_count' => $prompt_count,
			'char_count'   => $char_count,
			'max_prompts'  => self::MAX_PROMPTS,
			'max_chars'    => self::MAX_CHARS,
		);

		return $result;
	}

	/**
	 * Process conversation loop with function calling.
	 *
	 * @since 1.0.0
	 *
	 * @param Prompt_Builder             $builder              The prompt builder instance.
	 * @param UserMessage                $original_user_message The original user message.
	 * @param string                     $system_instruction   The system instruction.
	 * @param array<FunctionDeclaration> $function_declarations Function declarations.
	 * @param array<Message>             $existing_history     Existing conversation history.
	 * @return array|WP_Error Result with actions taken, or error.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If invalid arguments are provided.
	 */
	private function process_conversation_loop(
		Prompt_Builder $builder,
		UserMessage $original_user_message,
		string $system_instruction,
		array $function_declarations,
		array $existing_history = array()
	) {
		$actions_taken        = array();
		$iterations           = 0;
		$executed_calls       = array(); // Track executed function calls to prevent duplicates.
		$conversation_history = array();
		$model_info           = null; // Store model/provider info from first successful result.

		while ( $iterations < self::MAX_ITERATIONS ) {
			++$iterations;

			// Generate result from AI.
			try {
				$result = $builder->generate_result();
			} catch ( \WordPress\AiClient\Providers\Http\Exception\ClientException $e ) {
				// Handle HTTP client errors (429 rate limit, 401 auth, etc.).
				$message = $e->getMessage();

				// Try to determine which provider was used from the request URL.
				$provider_name = '';
				try {
					$request = $e->getRequest();
					$url     = (string) $request->getUri();
					if (
						strpos( $url, 'api.google.com' ) !== false
						|| strpos( $url, 'generativelanguage.googleapis.com' ) !== false
					) {
						$provider_name = 'Google';
					} elseif ( strpos( $url, 'api.openai.com' ) !== false ) {
						$provider_name = 'OpenAI';
					} elseif ( strpos( $url, 'api.anthropic.com' ) !== false ) {
						$provider_name = 'Anthropic';
					}
				} catch ( \Exception $request_exception ) {
					// Request not available, continue without provider name.
					// Provider name will remain empty string.
					$provider_name = '';
				}

				if ( strpos( $message, '429' ) !== false || strpos( $message, 'Too Many Requests' ) !== false ) {
					$error_msg = __(
						'API rate limit exceeded. You may need to upgrade your API plan or check your quota limits.',
						'gatherpress'
					);
					if ( $provider_name ) {
						/* translators: %s: Provider name (e.g., Google, OpenAI) */
						$rate_limit_msg = __(
							'API rate limit exceeded for %s. Upgrade your API plan or check quota limits.',
							'gatherpress'
						);
						$error_msg      = sprintf( $rate_limit_msg, $provider_name );
					}
					return new WP_Error( 'rate_limit', $error_msg );
				}
				if ( strpos( $message, '401' ) !== false || strpos( $message, 'Unauthorized' ) !== false ) {
					$error_msg = __(
						'Invalid API credentials. Please check your API key in Settings > AI Credentials.',
						'gatherpress'
					);
					if ( $provider_name ) {
						/* translators: %s: Provider name (e.g., Google, OpenAI) */
						$invalid_creds_msg = __(
							'Invalid API credentials for %s. Please check your API key in Settings > AI Credentials.',
							'gatherpress'
						);
						$error_msg         = sprintf( $invalid_creds_msg, $provider_name );
					}
					return new WP_Error( 'invalid_credentials', $error_msg );
				}
				// For other client errors, return the error message.
				return new WP_Error(
					'api_error',
					sprintf(
						/* translators: %s: Error message */
						__( 'API error: %s', 'gatherpress' ),
						$message
					)
				);
			} catch ( \WordPress\AiClient\Common\Exception\InvalidArgumentException $e ) {
				// Handle "No models found" error - credentials not recognized.
				if ( strpos( $e->getMessage(), 'No models found' ) !== false ) {
					$no_models_msg = __(
						'No AI models found. Verify your API credentials in Settings > AI Credentials.',
						'gatherpress'
					);
					return new WP_Error( 'no_models_found', $no_models_msg );
				}
				// Re-throw other InvalidArgumentException errors.
				throw $e;
			}

			// Get the first candidate message.
			$candidates = $result->getCandidates();
			if ( empty( $candidates ) ) {
				return new WP_Error(
					'no_candidates',
					__( 'AI model returned no candidates.', 'gatherpress' )
				);
			}

			// Store model/provider info from first successful result.
			if ( null === $model_info ) {
				$provider_metadata = $result->getProviderMetadata();
				$model_metadata    = $result->getModelMetadata();
				$model_info        = array(
					'provider' => $provider_metadata->getName(),
					'model'    => $model_metadata->getName(),
				);
			}

			$message = $candidates[0]->getMessage();

			// Check if message has ability function calls.
			if ( ! $this->has_ability_calls( $message ) ) {
				// No function calls, we're done. Extract text content.
				$text_content = $this->extract_text_content( $message );

				$response_text = ! empty( $text_content )
					? $text_content
					: __( 'Task completed!', 'gatherpress' );

				$return_data = array(
					'response'   => $response_text,
					'actions'    => $actions_taken,
					'model_info' => $model_info,
				);

				return $return_data;
			}

			// Execute ability calls and get responses.
			// Each function response must be in its own UserMessage (OpenAI API requirement).
			$function_response_messages = array();

			foreach ( $message->getParts() as $part ) {
				if ( $part->getType()->isFunctionCall() ) {
					$function_call = $part->getFunctionCall();
					if ( $function_call instanceof FunctionCall && $this->is_ability_call( $function_call ) ) {
						$function_response = $this->execute_ability( $function_call );

						// Create a separate UserMessage for each function response (OpenAI API requirement).
						$function_response_messages[] = new UserMessage(
							array( new MessagePart( $function_response ) )
						);

						// Track action for response display.
						// Convert function name back to ability name for tracking.
						$function_name = $function_call->getName();
						$ability_name  = $this->function_name_to_ability_name( $function_name );
						$result        = $function_response->getResponse();

						if ( $ability_name ) {
							$actions_taken[] = array(
								'ability' => $ability_name,
								'args'    => $function_call->getArgs(),
								'result'  => $result,
							);
						}
					}
				}
			}

			// Add to conversation history: model message, then each function response message separately.
			$conversation_history[] = $message;
			$conversation_history   = array_merge( $conversation_history, $function_response_messages );

			// Rebuild builder with all messages in correct order.
			// Build the full conversation array:
			// [existing_history..., original_user, model1, user1, model2, user2, ...].
			$all_messages = array_merge( $existing_history, array( $original_user_message ) );
			$all_messages = array_merge( $all_messages, $conversation_history );

			// Create a new builder with the full message array.
			// The PromptBuilder constructor accepts an array of Messages directly.
			$builder = AI_Client::prompt( $all_messages );

			// Re-apply system instruction and function declarations.
			if ( ! empty( $system_instruction ) ) {
				$builder->using_system_instruction( $system_instruction );
			}

			if ( ! empty( $function_declarations ) ) {
				$builder->using_function_declarations( ...$function_declarations );
			}
		}

		return new WP_Error(
			'max_iterations',
			sprintf(
				/* translators: %d: maximum iterations */
				__( 'Maximum conversation iterations (%d) reached.', 'gatherpress' ),
				self::MAX_ITERATIONS
			)
		);
	}

	/**
	 * Extract text content from a message.
	 *
	 * @since 1.0.0
	 *
	 * @param Message $message The message to extract text from.
	 * @return string Extracted text content.
	 */
	private function extract_text_content( Message $message ): string {
		$text_parts = array();

		foreach ( $message->getParts() as $part ) {
			if ( $part->getType()->isText() ) {
				$text = $part->getText();
				if ( $text ) {
					$text_parts[] = $text;
				}
			}
		}

		return implode( ' ', $text_parts );
	}

	/**
	 * Check if a message contains any ability function calls.
	 *
	 * @since 1.0.0
	 *
	 * @param Message $message The message to check.
	 * @return bool True if the message contains ability calls, false otherwise.
	 */
	private function has_ability_calls( Message $message ): bool {
		foreach ( $message->getParts() as $part ) {
			if ( $part->getType()->isFunctionCall() ) {
				$function_call = $part->getFunctionCall();
				if ( $function_call instanceof FunctionCall && $this->is_ability_call( $function_call ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a function call is an ability call.
	 *
	 * @since 1.0.0
	 *
	 * @param FunctionCall $call The function call to check.
	 * @return bool True if the function call is an ability call, false otherwise.
	 */
	private function is_ability_call( FunctionCall $call ): bool {
		$name = $call->getName();
		if ( null === $name ) {
			return false;
		}

		return str_starts_with( $name, 'wpab__' );
	}

	/**
	 * Execute a WordPress ability from a function call.
	 *
	 * @since 1.0.0
	 *
	 * @param FunctionCall $call The function call to execute.
	 * @return FunctionResponse The response from executing the ability.
	 */
	private function execute_ability( FunctionCall $call ): FunctionResponse {
		$function_name = $call->getName() ?? 'unknown';
		$function_id   = $call->getId() ?? 'unknown';

		// Convert function name to ability name.
		$ability_name = $this->function_name_to_ability_name( $function_name );

		if ( ! $ability_name ) {
			return new FunctionResponse(
				$function_id,
				$function_name,
				array(
					'error' => 'Not an ability function call',
					'code'  => 'invalid_ability_call',
				)
			);
		}

		// Get the ability.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new FunctionResponse(
				$function_id,
				$function_name,
				array(
					'error' => 'Abilities API not available',
					'code'  => 'abilities_api_not_available',
				)
			);
		}

		$ability = wp_get_ability( $ability_name );

		// @phpstan-ignore-next-line
		if ( ! $ability instanceof \WP_Ability ) {
			return new FunctionResponse(
				$function_id,
				$function_name,
				array(
					'error' => sprintf( 'Ability "%s" not found', $ability_name ),
					'code'  => 'ability_not_found',
				)
			);
		}

		// Execute the ability.
		$args = $call->getArgs();
		// @phpstan-ignore-next-line
		$result = $ability->execute( $args );

		// Handle WP_Error responses.
		if ( is_wp_error( $result ) ) {
			return new FunctionResponse(
				$function_id,
				$function_name,
				array(
					'error' => $result->get_error_message(),
					'code'  => $result->get_error_code(),
					'data'  => $result->get_error_data(),
				)
			);
		}

		// Return successful response.
		return new FunctionResponse(
			$function_id,
			$function_name,
			$result
		);
	}

	/**
	 * Convert function name to ability name.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $function_name The function name (e.g., "wpab__gatherpress__create_event").
	 * @return string|null The ability name (e.g., "gatherpress/create-event") or null if not an ability call.
	 */
	private function function_name_to_ability_name( ?string $function_name ): ?string {
		if ( ! $function_name || ! str_starts_with( $function_name, 'wpab__' ) ) {
			return null;
		}

		// Remove prefix and convert double underscores to forward slashes.
		$without_prefix = substr( $function_name, strlen( 'wpab__' ) );
		return str_replace( '__', '/', $without_prefix );
	}

	/**
	 * Get all registered GatherPress abilities.
	 *
	 * Uses Abilities_Integration to get the list of ability names,
	 * then filters to only return abilities that are actually registered.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string> Array of ability names.
	 */
	private function get_gatherpress_abilities(): array {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return array();
		}

		// Get ability names from Abilities_Integration (single source of truth).
		$ability_names = Abilities_Integration::get_all_ability_names();

		$abilities = array();

		foreach ( $ability_names as $ability_name ) {
			$ability = wp_get_ability( $ability_name );
			if ( $ability ) {
				$abilities[] = $ability_name;
			}
		}

		return $abilities;
	}

	/**
	 * Check if API key is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if API key is configured, false otherwise.
	 */
	private function has_api_key(): bool {
		// Check if credentials option exists and has at least one non-empty API key.
		$credentials = get_option( 'wp_ai_client_provider_credentials', array() );

		if ( ! is_array( $credentials ) ) {
			return false;
		}

		foreach ( $credentials as $api_key ) {
			if ( ! empty( $api_key ) && is_string( $api_key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get conversation state for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return array Conversation state with prompt_count, char_count, and history.
	 */
	private function get_conversation_state( int $user_id ): array {
		$state = get_user_meta( $user_id, self::META_KEY_CONVERSATION_STATE, true );

		if ( ! is_array( $state ) ) {
			return array(
				'prompt_count' => 0,
				'char_count'   => 0,
				'history'      => array(),
			);
		}

		// Ensure all required keys exist.
		return array(
			'prompt_count' => isset( $state['prompt_count'] ) ? (int) $state['prompt_count'] : 0,
			'char_count'   => isset( $state['char_count'] ) ? (int) $state['char_count'] : 0,
			'history'      => isset( $state['history'] ) && is_array( $state['history'] ) ? $state['history'] : array(),
		);
	}

	/**
	 * Save conversation state for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User ID.
	 * @param array $state   Conversation state to save.
	 * @return void
	 */
	private function save_conversation_state( int $user_id, array $state ): void {
		update_user_meta( $user_id, self::META_KEY_CONVERSATION_STATE, $state );
	}

	/**
	 * Clear conversation state for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function clear_conversation_state( int $user_id ): void {
		delete_user_meta( $user_id, self::META_KEY_CONVERSATION_STATE );
	}

	/**
	 * Reset conversation state for the current user.
	 *
	 * @since 1.0.0
	 *
	 * @return array Reset state metadata.
	 */
	public function reset_conversation_state(): array {
		$user_id = get_current_user_id();
		$this->clear_conversation_state( $user_id );

		return array(
			'prompt_count' => 0,
			'char_count'   => 0,
			'max_prompts'  => self::MAX_PROMPTS,
			'max_chars'    => self::MAX_CHARS,
		);
	}

	/**
	 * Load conversation history from stored state and convert to Message objects.
	 *
	 * @since 1.0.0
	 *
	 * @param array $history_array History stored as arrays.
	 * @return array<Message> Array of Message objects.
	 */
	private function load_history_from_state( array $history_array ): array {
		$messages = array();

		foreach ( $history_array as $message_data ) {
			if ( ! is_array( $message_data ) ) {
				continue;
			}

			try {
				$message    = Message::fromArray( $message_data );
				$messages[] = $message;
			} catch ( \Exception $e ) {
				// Skip invalid message data.
				continue;
			}
		}

		return $messages;
	}

	/**
	 * Append new interaction to conversation history.
	 *
	 * @since 1.0.0
	 *
	 * @param array       $history_array      Existing history as arrays.
	 * @param UserMessage $user_message      The user message to append.
	 * @param array       $result             The AI response result.
	 * @return array Updated history as arrays.
	 */
	private function append_to_history( array $history_array, UserMessage $user_message, array $result ): array {
		// Convert user message to array.
		$user_message_array = $user_message->toArray();
		$history_array[]    = $user_message_array;

		// Create model response message from result.
		$response_text = isset( $result['response'] ) ? $result['response'] : '';
		if ( ! empty( $response_text ) ) {
			$model_message       = new ModelMessage( array( new MessagePart( $response_text ) ) );
			$model_message_array = $model_message->toArray();
			$history_array[]     = $model_message_array;
		}

		return $history_array;
	}
}
