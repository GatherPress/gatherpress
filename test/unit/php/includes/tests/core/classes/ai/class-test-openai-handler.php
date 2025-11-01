<?php
/**
 * Class handles unit tests for GatherPress\Core\AI\OpenAI_Handler.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core\AI;

use GatherPress\Core\AI\OpenAI_Handler;
use GatherPress\Tests\Base;

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
}

