<?php
/**
 * Handles OpenAI API integration.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\AI;

use GatherPress\Core\Settings;
use WP_Error;

/**
 * Class OpenAI_Handler.
 *
 * Manages communication with OpenAI API and ability execution.
 *
 * @since 1.0.0
 */
class OpenAI_Handler {
	/**
	 * OpenAI API endpoint.
	 *
	 * @var string
	 */
	const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Process a user prompt with OpenAI.
	 *
	 * @param string $prompt User's natural language prompt.
	 * @return array|WP_Error Result of processing or error.
	 */
	public function process_prompt( $prompt ) {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'OpenAI API key is not configured.', 'gatherpress' ) );
		}

		// Get available GatherPress abilities.
		$functions = $this->get_gatherpress_functions();

		// Build messages for OpenAI.
		$current_date   = gmdate( 'Y-m-d' );
		$system_content = sprintf(
			'You are an AI assistant for GatherPress events. Today is %s.

Rules:
- For recurring events: Call calculate-dates first, then use those exact dates. Do NOT calculate dates yourself.
- When user mentions a venue by name, call list-venues to get the venue ID.
- Always create events as drafts. Event dates must be after %s.
- If success=true: Display the data (even if empty - say "No X found", '
					. 'don\'t say "error"). Only say "error" if success=false.',
			$current_date,
			$current_date
		);

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_content,
			),
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		// Call OpenAI API.
		$response = $this->call_openai_api( $messages, $functions, $api_key );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Process function calls if any.
		return $this->process_function_calls( $response, $messages, $functions, $api_key );
	}

	/**
	 * Get OpenAI API key from settings.
	 *
	 * @return string API key or empty string.
	 */
	private function get_api_key(): string {
		$settings = Settings::get_instance();
		$api_key  = $settings->get_value( 'ai', 'ai_service', 'openai_api_key' );

		return $api_key ? (string) $api_key : '';
	}

	/**
	 * Get GatherPress abilities as OpenAI functions.
	 *
	 * @return array Array of function definitions for OpenAI.
	 */
	private function get_gatherpress_functions() {
		// Check if external AI plugin's calculate-dates ability is available.
		// If so, use it instead of GatherPress's own implementation.
		$calculate_dates_ability = Abilities_Integration::get_calculate_dates_ability();

		$abilities = array(
			'gatherpress/list-venues',
			'gatherpress/list-events',
			'gatherpress/list-topics',
			'gatherpress/search-events',
			$calculate_dates_ability,
			'gatherpress/create-venue',
			'gatherpress/create-topic',
			'gatherpress/create-event',
			'gatherpress/update-venue',
			'gatherpress/update-event',
			'gatherpress/update-events-batch',
		);

		$functions = array();

		// Only get abilities if Abilities API is available.
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return array();
		}

		foreach ( $abilities as $ability_name ) {
			$ability = wp_get_ability( $ability_name );
			if ( ! $ability ) {
				continue;
			}

			$input_schema = $ability->get_input_schema();
			// Convert input_schema to OpenAI format if needed.
			$parameters = $this->convert_input_schema_to_openai( $input_schema );

			$functions[] = array(
				'name'        => str_replace( '/', '_', $ability_name ),
				'description' => $ability->get_description(),
				'parameters'  => $parameters,
			);
		}

		return $functions;
	}

	/**
	 * Convert ability input_schema to OpenAI function schema format.
	 *
	 * In v0.4.0, all abilities use JSON Schema format in input_schema.
	 * We just need to clean it up (remove invalid 'required' from properties,
	 * ensure empty properties is {}, etc.).
	 *
	 * @param array $input_schema The ability's input_schema (JSON Schema format).
	 * @return array OpenAI-compatible JSON Schema format.
	 */
	private function convert_input_schema_to_openai(
		array $input_schema
	): array {

		// Empty schema? Return minimal valid schema.
		if ( empty( $input_schema ) ) {
			return array(
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
			);
		}

		// Should already be in JSON Schema format in v0.4.0 - just clean it up.
		return $this->clean_json_schema( $input_schema );
	}

	/**
	 * Clean JSON Schema: remove 'required' from properties (only valid at top level),
	 * and ensure empty properties is {} (not []).
	 *
	 * @param array $input_schema JSON Schema format.
	 * @return array Cleaned schema compatible with OpenAI.
	 */
	private function clean_json_schema( array $input_schema ): array {
		// Handle properties - convert object to array, ensure empty is {}.
		$properties = array();
		if ( isset( $input_schema['properties'] ) ) {
			if ( is_object( $input_schema['properties'] ) ) {
				$properties = (array) $input_schema['properties'];
			} elseif ( is_array( $input_schema['properties'] ) ) {
				$properties = $input_schema['properties'];
			}
		}

		// Clean each property: remove 'required' (invalid in property definitions).
		$cleaned = array();
		foreach ( $properties as $name => $def ) {
			if ( is_array( $def ) ) {
				unset( $def['required'] ); // Invalid in property definitions.
			}
			$cleaned[ $name ] = $def;
		}

		// Ensure empty properties is {} (stdClass) not [] (array) for JSON encoding.
		$properties_value = empty( $cleaned ) ? new \stdClass() : $cleaned;

		return array(
			'type'                 => 'object',
			'properties'           => $properties_value,
			'additionalProperties' => $input_schema['additionalProperties'] ?? false,
			'required'             => $input_schema['required'] ?? array(),
		);
	}

	/**
	 * Call OpenAI API.
	 *
	 * @param array  $messages  Chat messages.
	 * @param array  $functions Available functions.
	 * @param string $api_key   OpenAI API key.
	 * @return array|WP_Error API response or error.
	 */
	private function call_openai_api( $messages, $functions, $api_key ) {
		$body = array(
			'model'       => 'gpt-4o-mini',
			'messages'    => $messages,
			'tools'       => array_map(
				function ( $func ) {
					return array(
						'type'     => 'function',
						'function' => $func,
					);
				},
				$functions
			),
			'tool_choice' => 'auto',
		);

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			return new WP_Error(
				'openai_error',
				$data['error']['message'] ?? 'Unknown OpenAI error'
			);
		}

		return $data;
	}

	/**
	 * Process function calls from OpenAI response.
	 *
	 * @param array  $openai_response OpenAI API response.
	 * @param array  $messages        Current message history.
	 * @param array  $functions       Available functions.
	 * @param string $api_key         OpenAI API key.
	 * @param int    $max_iterations  Maximum conversation iterations to prevent loops.
	 * @return array|WP_Error Result with actions taken, or error.
	 */
	private function process_function_calls( $openai_response, $messages, $functions, $api_key, $max_iterations = 15 ) {
		$actions_taken  = array();
		$iterations     = 0;
		$executed_calls = array(); // Track executed function calls to prevent duplicates.

		while ( $iterations < $max_iterations ) {
			++$iterations;
			$choice     = $openai_response['choices'][0] ?? null;
			$message    = $choice['message'] ?? null;
			$tool_calls = $message['tool_calls'] ?? array();

			// If no function calls, we're done.
			if ( empty( $tool_calls ) ) {
				return array(
					'response' => $message['content'] ?? 'Task completed!',
					'actions'  => $actions_taken,
				);
			}

			// Add assistant message to conversation.
			$messages[] = $message;

			// Execute each function call.
			foreach ( $tool_calls as $tool_call ) {
				$function_name = $tool_call['function']['name'] ?? '';
				$arguments     = json_decode( $tool_call['function']['arguments'] ?? '{}', true );

				// Create a unique key for this function call to detect duplicates.
				$call_key = md5( $function_name . wp_json_encode( $arguments ) );

				// Skip if we've already executed this exact function call.
				if ( isset( $executed_calls[ $call_key ] ) ) {
					// Use the previous result instead of re-executing.
					$messages[] = array(
						'tool_call_id' => $tool_call['id'],
						'role'         => 'tool',
						'content'      => $this->encode_result( $executed_calls[ $call_key ] ),
					);
					continue;
				}

				// Convert function name to ability name and get the ability.
				$ability_name = $this->convert_function_name_to_ability( $function_name );
				$ability      = $this->get_ability( $ability_name, $function_name );

				if ( ! $ability ) {
					$messages[] = $this->create_error_message( $tool_call['id'], $ability_name, $function_name );
					continue;
				}

				// Execute the ability and handle the result.
				$result = $this->execute_ability( $ability, $arguments, $ability_name );
				$result = $this->normalize_result( $result );

				// Store the result to prevent duplicate execution.
				$executed_calls[ $call_key ] = $result;

				$messages[] = array(
					'tool_call_id' => $tool_call['id'],
					'role'         => 'tool',
					'content'      => $this->encode_result( $result ),
				);

				// Track what was done.
				$actions_taken[] = array(
					'ability' => $ability_name,
					'args'    => $arguments,
					'result'  => $result,
				);
			}

			// Get next response from OpenAI (might have more function calls).
			$openai_response = $this->call_openai_api( $messages, $functions, $api_key );

			if ( is_wp_error( $openai_response ) ) {
				return $openai_response;
			}
		}

		// Max iterations reached.
		return array(
			'response' => 'Task partially completed. Maximum iterations reached.',
			'actions'  => $actions_taken,
		);
	}

	/**
	 * Convert OpenAI function name to ability name.
	 *
	 * OpenAI normalizes function names, replacing slashes and hyphens with underscores.
	 * This method restores the original ability name format.
	 *
	 * @param string $function_name OpenAI function name (e.g., "gatherpress_list_venues").
	 * @return string Ability name (e.g., "gatherpress/list-venues").
	 */
	private function convert_function_name_to_ability( string $function_name ): string {
		$pos = strpos( $function_name, '_' );
		if ( false === $pos ) {
			return $function_name;
		}

		// Replace first underscore with slash.
		$ability_name = substr_replace( $function_name, '/', $pos, 1 );
		// Replace remaining underscores with hyphens.
		$ability_name = substr_replace(
			$ability_name,
			str_replace( '_', '-', substr( $ability_name, $pos + 1 ) ),
			$pos + 1
		);

		$this->log_debug( sprintf( 'Converting function "%s" to ability "%s"', $function_name, $ability_name ) );

		return $ability_name;
	}

	/**
	 * Get ability by name, trying multiple conversion strategies.
	 *
	 * @param string $ability_name   Primary ability name to try.
	 * @param string $function_name  Original function name for fallback.
	 * @return object|null WP_Ability object or null if not found.
	 */
	private function get_ability( string $ability_name, string $function_name ): ?object {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return null;
		}

		$ability = wp_get_ability( $ability_name );
		if ( $ability ) {
			return $ability;
		}

		// Try alternative conversions.
		$alt_ability_name = str_replace( '-', '/', str_replace( '_', '/', $function_name ) );
		if ( $alt_ability_name !== $ability_name ) {
			$ability = wp_get_ability( $alt_ability_name );
			if ( $ability ) {
				return $ability;
			}
		}

		// Try with gatherpress namespace prefix.
		if ( strpos( $alt_ability_name, 'gatherpress' ) === false ) {
			$ability = wp_get_ability( 'gatherpress/' . $alt_ability_name );
			if ( $ability ) {
				return $ability;
			}
		}

		return null;
	}

	/**
	 * Create error message for missing ability.
	 *
	 * @param string $tool_call_id   Tool call ID.
	 * @param string $ability_name    Ability name that was tried.
	 * @param string $function_name   Original function name.
	 * @return array Error message array.
	 */
	private function create_error_message( string $tool_call_id, string $ability_name, string $function_name ): array {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return array(
				'tool_call_id' => $tool_call_id,
				'role'         => 'tool',
				'content'      => wp_json_encode(
					array(
						'success' => false,
						'message' => __( 'Abilities API not available', 'gatherpress' ),
					)
				),
			);
		}

		return array(
			'tool_call_id' => $tool_call_id,
			'role'         => 'tool',
			'content'      => wp_json_encode(
				array(
					'success' => false,
					'message' => sprintf(
						/* translators: 1: ability name, 2: function name */
						__( 'Ability not found: %1$s (original function: %2$s)', 'gatherpress' ),
						$ability_name,
						$function_name
					),
				)
			),
		);
	}

	/**
	 * Execute an ability and handle exceptions.
	 *
	 * @param object $ability      WP_Ability object.
	 * @param array  $arguments    Arguments to pass to ability.
	 * @param string $ability_name Ability name for logging.
	 * @return array|WP_Error Result from ability execution.
	 */
	private function execute_ability( object $ability, array $arguments, string $ability_name ) {
		try {
			$result = $ability->execute( $arguments );
			$this->log_debug(
				sprintf(
					'Executed ability "%s", result: %s',
					$ability_name,
					is_array( $result ) && isset( $result['success'] )
						? sprintf(
							'success=%s, message=%s',
							$result['success'] ? 'true' : 'false',
							$result['message'] ?? 'none'
						)
						: 'non-array result'
				)
			);
			return $result;
		} catch ( \Exception $e ) {
			$this->log_debug( sprintf( 'Exception executing ability "%s": %s', $ability_name, $e->getMessage() ) );
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Exception during ability execution: %s', 'gatherpress' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Normalize ability result to consistent format.
	 *
	 * @param array|WP_Error|mixed $result Raw result from ability.
	 * @return array Normalized result with success, message, and data fields.
	 */
	private function normalize_result( $result ): array {
		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'message' => $result->get_error_message(),
			);
		}

		if ( ! is_array( $result ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid result format from ability', 'gatherpress' ),
				'data'    => $result,
			);
		}

		if ( ! isset( $result['success'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'Operation completed', 'gatherpress' ),
				'data'    => $result,
			);
		}

		return $result;
	}

	/**
	 * Encode result as JSON with error handling.
	 *
	 * @param array $result Result to encode.
	 * @return string JSON-encoded result.
	 */
	private function encode_result( array $result ): string {
		$json_result = wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json_result ) {
			$json_result = wp_json_encode(
				array(
					'success' => false,
					'message' => __( 'Error: Unable to encode result as JSON', 'gatherpress' ),
				)
			);
		}
		return $json_result;
	}

	/**
	 * Log debug message if debugging is enabled.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	private function log_debug( string $message ): void {
		// Debug logging removed for production.
	}
}
