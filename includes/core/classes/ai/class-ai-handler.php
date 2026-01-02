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

		// Create prompt builder with user prompt.
		$builder = AI_Client::prompt(
			new UserMessage( array( new MessagePart( $prompt ) ) )
		);

		// Set system instruction.
		$builder->using_system_instruction( $system_content );

		// Register abilities with the builder by creating function declarations.
		$function_declarations = array();
		foreach ( $abilities as $ability_name ) {
			$ability = wp_get_ability( $ability_name );
			if ( ! $ability ) {
				continue;
			}

			// Convert ability name to function name (e.g., "gatherpress/list-events" -> "wpab__gatherpress__list_events").
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

		// Store original user message for conversation loop.
		$original_user_message = new UserMessage( array( new MessagePart( $prompt ) ) );

		// Process the conversation loop.
		return $this->process_conversation_loop( $builder, $original_user_message, $system_content, $function_declarations );
	}

	/**
	 * Process conversation loop with function calling.
	 *
	 * @since 1.0.0
	 *
	 * @param Prompt_Builder        $builder              The prompt builder instance.
	 * @param UserMessage           $original_user_message The original user message.
	 * @param string                $system_instruction   The system instruction.
	 * @param array<FunctionDeclaration> $function_declarations Function declarations.
	 * @return array|WP_Error Result with actions taken, or error.
	 */
	private function process_conversation_loop(
		Prompt_Builder $builder,
		UserMessage $original_user_message,
		string $system_instruction,
		array $function_declarations
	) {
		$actions_taken       = array();
		$iterations          = 0;
		$executed_calls      = array(); // Track executed function calls to prevent duplicates.
		$conversation_history = array();

		while ( $iterations < self::MAX_ITERATIONS ) {
			++$iterations;

			// Generate result from AI.
			$result = $builder->generate_result();

			if ( $result instanceof WP_Error ) {
				return $result;
			}

			// Get the first candidate message.
			$candidates = $result->getCandidates();
			if ( empty( $candidates ) ) {
				return new WP_Error(
					'no_candidates',
					__( 'AI model returned no candidates.', 'gatherpress' )
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

				return array(
					'response' => $response_text,
					'actions'  => $actions_taken,
				);
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
			$conversation_history = array_merge( $conversation_history, $function_response_messages );

			// Rebuild builder with all messages in correct order.
			// Build the full conversation array: [original_user, model1, user1, model2, user2, ...]
			$all_messages = array( $original_user_message );
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
		$args   = $call->getArgs();
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
}
