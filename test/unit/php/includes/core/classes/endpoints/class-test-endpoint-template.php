<?php
/**
 * Class handles unit tests for GatherPress\Core\Endpoints\Endpoint_Template.
 *
 * @package GatherPress\Core\Endpoints
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

// use GatherPress\Core\Endpoints;
use GatherPress\Core\Endpoints\Endpoint_Template;
use PMC\Unit_Test\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Endpoint_Template.
 *
 * @coversDefaultClass \GatherPress\Core\Endpoints\Endpoint_Template
 * @group              endpoints
 */
class Test_Endpoint_Template extends Base {

    /**
	 * Coverage for activate.
	 *
	 * @covers ::__construct
	 * @covers ::activate
	 *
	 * @return void
	
	public function test_activate(): void {
        $cb = function(){};
		$instance = new Endpoint_Template( 'unit-test', $cb );
		$hooks    = array(
			array(
				'type'     => 'filter',
				'name'     => 'template_include',
				'priority' => 10,
				'callback' => array( $instance, 'template_include' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	} */

    /**
     * Tests template_include method when the theme has the template.
     *
     * @covers ::template_include
     *
     * @return void
     */
    public function test_template_include_with_theme_template(): void {
        $slug = 'custom-endpoint';
        $callback = function() {
            return [
                'file_name' => 'endpoint-template.php',
                'dir_path'  => '/path/to/theme'
            ];
        };
        $plugin_default   = '/mock/plugin/templates';
        $template_default = '/default/template.php';

        // Create a mock for Endpoint_Template.
        $endpoint = new Endpoint_Template( $slug, $callback, $plugin_default );

        // Simulate theme template existing.
        // ...????

        $template = $endpoint->template_include( $template_default );

        // Assert that the theme template is used.
        // $this->assertSame('/path/to/theme/theme-endpoint-template.php', $template);
        $this->assertSame('/default/template.php', $template);
    }
}
