<?php
/**
 * Settings AI class file for GatherPress.
 *
 * This file contains the AI class definition, which handles the "AI" settings
 * page in GatherPress, providing options for configuring AI service providers
 * and API keys.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class AI.
 *
 * This class handles the "AI" settings page in GatherPress, providing options
 * for configuring AI service providers and API keys. It extends the Base class
 * to inherit common settings page functionality.
 *
 * @since 1.0.0
 */
class AI extends Base {
	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Get the slug for the AI section.
	 *
	 * This method returns the slug used to identify the AI section.
	 *
	 * @since 1.0.0
	 *
	 * @return string The slug for the AI section.
	 */
	protected function get_slug(): string {
		return 'ai';
	}

	/**
	 * Get the name for the AI section.
	 *
	 * This method returns the localized name for the AI section.
	 *
	 * @since 1.0.0
	 *
	 * @return string The localized name for the AI section.
	 */
	protected function get_name(): string {
		return __( 'AI', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying AI.
	 *
	 * This method returns the priority at which AI should be displayed.
	 *
	 * @since 1.0.0
	 *
	 * @return int The priority for displaying AI.
	 */
	protected function get_priority(): int {
		return 10;
	}

	/**
	 * Callback function to set the sub-page for GatherPress.
	 *
	 * Only adds the AI settings page if the Abilities API is available.
	 *
	 * @since 1.0.0
	 *
	 * @param array $sub_pages An array of sub-pages for GatherPress.
	 * @return array Modified array with the sub-page added, or unchanged if Abilities API not available.
	 */
	public function set_sub_page( array $sub_pages ): array {
		// Only show AI settings if Abilities API is available.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return $sub_pages;
		}

		return parent::set_sub_page( $sub_pages );
	}

	/**
	 * Get an array of sections and options for the AI settings page.
	 *
	 * This method defines the sections and their respective options for the "AI" settings page
	 * in GatherPress. It provides structured data that represents the configuration choices available
	 * to users on this page.
	 *
	 * @since 1.0.0
	 *
	 * @return array An array representing the sections and options for the "AI" settings page.
	 */
	protected function get_sections(): array {
		return array(
			'ai_service' => array(
				'name'        => __( 'AI Service Configuration', 'gatherpress' ),
				'description' => __(
					'Configure your AI service provider and API credentials. '
					. 'This enables AI-powered features in GatherPress.',
					'gatherpress'
				),
				'options'     => array(
					'service_provider' => array(
						'labels'      => array(
							'name' => __( 'Service Provider', 'gatherpress' ),
						),
						'description' => __( 'Select the AI service provider you want to use.', 'gatherpress' ),
						'field'       => array(
							'label'   => __( 'Selected AI Service Provider:', 'gatherpress' ),
							'type'    => 'select',
							'options' => array(
								'default' => 'openai',
								'items'   => array(
									'openai' => __( 'OpenAI', 'gatherpress' ),
								),
							),
						),
					),
					'openai_api_key'   => array(
						'labels'      => array(
							'name' => __( 'OpenAI API Key', 'gatherpress' ),
						),
						'description' => __(
							'Enter your OpenAI API key. This plugin uses the OpenAI API. '
							. 'You need to provide your own API key and will be charged by OpenAI for usage. '
							. 'Typical costs are $0.01-0.10 per prompt.',
							'gatherpress'
						),
						'field'       => array(
							'label'   => __( 'OpenAI API Key', 'gatherpress' ),
							'type'    => 'password',
							'size'    => 'regular',
							'options' => array(
								'default' => '',
							),
						),
					),
				),
			),
		);
	}
}
