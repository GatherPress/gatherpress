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
			'You are an AI assistant helping manage GatherPress events. Today is %s. WORKFLOW FOR RECURRING EVENTS: Step 1) When user asks for recurring events (like "3rd Tuesday for 3 months"), immediately call calculate-dates with the pattern and number of occurrences. Step 2) Use the exact dates returned by calculate-dates to create events - do NOT calculate dates yourself. Step 3) Create events with those dates. IMPORTANT: When a user mentions a venue by name, call list-venues to get the correct venue ID. Always create events as drafts by default for safety. All event dates MUST be in the future (after %s).',
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
		// Check if AI plugin's calculate-dates ability is available.
		$ai_ability              = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'ai/calculate-dates' ) : null;
		$calculate_dates_ability = $ai_ability ? 'ai/calculate-dates' : 'gatherpress/calculate-dates';

		$abilities = array(
			'gatherpress/list-venues',
			'gatherpress/list-events',
			'gatherpress/list-topics',
			$calculate_dates_ability,
			'gatherpress/create-venue',
			'gatherpress/create-topic',
			'gatherpress/create-event',
			'gatherpress/update-venue',
			'gatherpress/update-event',
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

			$functions[] = array(
				'name'        => str_replace( '/', '_', $ability_name ),
				'description' => $ability->get_description(),
				'parameters'  => $this->convert_parameters_to_schema( $ability_name ),
			);
		}

		return $functions;
	}

	/**
	 * Convert ability parameters to OpenAI function schema.
	 *
	 * @param string $ability_name Ability name.
	 * @return array OpenAI-compatible parameter schema.
	 */
	private function convert_parameters_to_schema( $ability_name ) {
		// Define schemas for each ability.
		$schemas = array(
			'gatherpress/list-venues'     => array(
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
			),
			'gatherpress/list-events'     => array(
				'type'       => 'object',
				'properties' => array(
					'max_number' => array(
						'type'        => 'integer',
						'description' => 'Maximum number of events to return',
					),
				),
			),
			'gatherpress/list-topics'     => array(
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
			),
			'gatherpress/calculate-dates' => array(
				'type'       => 'object',
				'properties' => array(
					'pattern'     => array(
						'type'        => 'string',
						'description' => 'The recurrence pattern (e.g., "3rd Tuesday", "every Monday", "first Friday")',
					),
					'occurrences' => array(
						'type'        => 'integer',
						'description' => 'Number of occurrences to calculate',
					),
					'start_date'  => array(
						'type'        => 'string',
						'description' => 'Optional starting date in Y-m-d format (defaults to today)',
					),
				),
				'required'   => array( 'pattern', 'occurrences' ),
			),
			'ai/calculate-dates'          => array(
				'type'       => 'object',
				'properties' => array(
					'pattern'     => array(
						'type'        => 'string',
						'description' => 'The recurrence pattern (e.g., "3rd Tuesday", "every Monday", "first Friday")',
					),
					'occurrences' => array(
						'type'        => 'integer',
						'description' => 'Number of occurrences to calculate',
					),
					'start_date'  => array(
						'type'        => 'string',
						'description' => 'Optional starting date in Y-m-d format (defaults to today)',
					),
				),
				'required'   => array( 'pattern', 'occurrences' ),
			),
			'gatherpress/create-venue'    => array(
				'type'       => 'object',
				'properties' => array(
					'name'    => array(
						'type'        => 'string',
						'description' => 'Name of the venue',
					),
					'address' => array(
						'type'        => 'string',
						'description' => 'Full address of the venue',
					),
					'phone'   => array(
						'type'        => 'string',
						'description' => 'Phone number',
					),
					'website' => array(
						'type'        => 'string',
						'description' => 'Website URL',
					),
				),
				'required'   => array( 'name', 'address' ),
			),
			'gatherpress/create-topic'    => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array(
						'type'        => 'string',
						'description' => 'Name of the topic',
					),
					'description' => array(
						'type'        => 'string',
						'description' => 'Description of the topic',
					),
					'parent_id'   => array(
						'type'        => 'integer',
						'description' => 'Parent topic ID for hierarchical topics',
					),
				),
				'required'   => array( 'name' ),
			),
			'gatherpress/create-event'    => array(
				'type'       => 'object',
				'properties' => array(
					'title'          => array(
						'type'        => 'string',
						'description' => 'Event title',
					),
					'datetime_start' => array(
						'type'        => 'string',
						'description' => 'Start date/time in Y-m-d H:i:s format (e.g., 2025-01-21 19:00:00)',
					),
					'datetime_end'   => array(
						'type'        => 'string',
						'description' => 'End date/time in Y-m-d H:i:s format',
					),
					'venue_id'       => array(
						'type'        => 'integer',
						'description' => 'ID of the venue',
					),
					'description'    => array(
						'type'        => 'string',
						'description' => 'Event description',
					),
					'post_status'    => array(
						'type'        => 'string',
						'description' => 'Post status (draft or publish)',
						'enum'        => array( 'draft', 'publish' ),
					),
					'topic_ids'      => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Array of topic IDs to assign to this event',
					),
				),
				'required'   => array( 'title', 'datetime_start' ),
			),
			'gatherpress/update-venue'    => array(
				'type'       => 'object',
				'properties' => array(
					'venue_id' => array(
						'type'        => 'integer',
						'description' => 'ID of venue to update',
					),
					'name'     => array(
						'type'        => 'string',
						'description' => 'New name',
					),
					'address'  => array(
						'type'        => 'string',
						'description' => 'New address',
					),
					'phone'    => array(
						'type'        => 'string',
						'description' => 'New phone',
					),
					'website'  => array(
						'type'        => 'string',
						'description' => 'New website',
					),
				),
				'required'   => array( 'venue_id' ),
			),
			'gatherpress/update-event'    => array(
				'type'       => 'object',
				'properties' => array(
					'event_id'       => array(
						'type'        => 'integer',
						'description' => 'ID of event to update',
					),
					'title'          => array(
						'type'        => 'string',
						'description' => 'New title',
					),
					'datetime_start' => array(
						'type'        => 'string',
						'description' => 'New start date/time in Y-m-d H:i:s format',
					),
					'datetime_end'   => array(
						'type'        => 'string',
						'description' => 'New end date/time',
					),
					'venue_id'       => array(
						'type'        => 'integer',
						'description' => 'New venue ID',
					),
					'description'    => array(
						'type'        => 'string',
						'description' => 'New description',
					),
					'post_status'    => array(
						'type'        => 'string',
						'description' => 'New status',
					),
					'topic_ids'      => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'integer' ),
						'description' => 'Array of topic IDs to assign to this event',
					),
				),
				'required'   => array( 'event_id' ),
			),
		);

		return $schemas[ $ability_name ] ?? array(
			'type'                 => 'object',
			'properties'           => new \stdClass(),
			'additionalProperties' => false,
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
		$actions_taken = array();
		$iterations    = 0;

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

				// Convert function name back to ability name.
				$ability_name = str_replace( '_', '/', $function_name );

				if ( ! function_exists( 'wp_get_ability' ) ) {
					$messages[] = array(
						'tool_call_id' => $tool_call['id'],
						'role'         => 'tool',
						'content'      => 'Error: Abilities API not available',
					);
					continue;
				}

				$ability = wp_get_ability( $ability_name );

				if ( ! $ability ) {
					$messages[] = array(
						'tool_call_id' => $tool_call['id'],
						'role'         => 'tool',
						'content'      => 'Error: Ability not found',
					);
					continue;
				}

				// Execute the ability.
				$result = $ability->execute( $arguments );

				if ( is_wp_error( $result ) ) {
					$messages[] = array(
						'tool_call_id' => $tool_call['id'],
						'role'         => 'tool',
						'content'      => 'Error: ' . $result->get_error_message(),
					);
				} else {
					$messages[] = array(
						'tool_call_id' => $tool_call['id'],
						'role'         => 'tool',
						'content'      => wp_json_encode( $result ),
					);

					// Track what was done.
					$actions_taken[] = array(
						'ability' => $ability_name,
						'args'    => $arguments,
						'result'  => $result,
					);
				}
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
}
