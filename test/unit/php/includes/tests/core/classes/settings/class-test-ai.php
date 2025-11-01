<?php
/**
 * Class handles unit tests for GatherPress\Core\Settings\AI.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\AI;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_AI.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\AI
 */
class Test_AI extends Base {
	/**
	 * Coverage for get_slug method.
	 *
	 * @covers ::get_slug
	 *
	 * @return void
	 */
	public function test_get_slug(): void {
		$instance = AI::get_instance();
		$slug     = Utility::invoke_hidden_method( $instance, 'get_slug' );

		$this->assertSame( 'ai', $slug, 'Failed to assert slug is ai.' );
	}

	/**
	 * Coverage for get_name method.
	 *
	 * @covers ::get_name
	 *
	 * @return void
	 */
	public function test_get_name(): void {
		$instance = AI::get_instance();
		$name     = Utility::invoke_hidden_method( $instance, 'get_name' );

		$this->assertSame( 'AI', $name, 'Failed to assert name is AI.' );
	}

	/**
	 * Coverage for get_priority method.
	 *
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_get_priority(): void {
		$instance = AI::get_instance();
		$priority = Utility::invoke_hidden_method( $instance, 'get_priority' );

		$this->assertEquals( 10, $priority, 'Failed to assert correct priority.' );
	}

	/**
	 * Coverage for get_sections method.
	 *
	 * @covers ::get_sections
	 *
	 * @return void
	 */
	public function test_get_sections(): void {
		$instance = AI::get_instance();

		$sections = Utility::invoke_hidden_method( $instance, 'get_sections' );
		$this->assertSame(
			'AI Service Configuration',
			$sections['ai_service']['name'],
			'Failed to assert name is AI Service Configuration.'
		);
		$this->assertIsArray(
			$sections['ai_service']['options'],
			'Failed to assert options is an array.'
		);
		$this->assertArrayHasKey(
			'service_provider',
			$sections['ai_service']['options'],
			'Failed to assert service_provider option exists.'
		);
		$this->assertArrayHasKey(
			'openai_api_key',
			$sections['ai_service']['options'],
			'Failed to assert openai_api_key option exists.'
		);
		$this->assertSame(
			'openai',
			$sections['ai_service']['options']['service_provider']['field']['options']['default'],
			'Failed to assert service_provider defaults to openai.'
		);
	}
}
