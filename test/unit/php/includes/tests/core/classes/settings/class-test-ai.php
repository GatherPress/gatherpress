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

	/**
	 * Coverage for set_sub_page method when wp_register_ability exists.
	 *
	 * @covers ::set_sub_page
	 *
	 * @return void
	 */
	public function test_set_sub_page_when_ability_api_available(): void {
		$instance = AI::get_instance();

		// Mock wp_register_ability function to exist.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'wp_register_ability function not available.' );
		}

		$sub_pages = array( 'existing' => 'page' );
		$result    = $instance->set_sub_page( $sub_pages );

		// Should call parent::set_sub_page and return modified array.
		$this->assertIsArray( $result, 'Failed to assert result is an array.' );
	}

	/**
	 * Coverage for set_sub_page method when wp_register_ability does not exist.
	 *
	 * @covers ::set_sub_page
	 *
	 * @return void
	 */
	public function test_set_sub_page_when_ability_api_not_available(): void {
		$instance = AI::get_instance();

		$sub_pages = array( 'existing' => 'page' );

		// If wp_register_ability doesn't exist, it should return unchanged array (line 87).
		// We can't easily mock function_exists, so we test the actual behavior.
		// If the function exists in the test environment, the test will still pass
		// but won't hit line 87. If it doesn't exist, it will hit line 87.
		$result = $instance->set_sub_page( $sub_pages );

		$this->assertIsArray( $result, 'Failed to assert result is an array.' );

		// If function doesn't exist, result should be exactly the same as input (line 87).
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->assertSame( $sub_pages, $result, 'Failed to assert unchanged array when function does not exist.' );
		}
	}
}
