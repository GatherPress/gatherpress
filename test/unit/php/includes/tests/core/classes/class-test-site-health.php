<?php
/**
 * Class handles unit tests for GatherPress\Core\Site_Health.
 *
 * @package GatherPress\Tests\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use GatherPress\Core\Site_Health;
use GatherPress\Tests\Base;

/**
 * Class Test_Site_Health.
 *
 * @coversDefaultClass \GatherPress\Core\Site_Health
 */
class Test_Site_Health extends Base {

	/**
	 * Test instance of Site_Health.
	 *
	 * @since 1.0.0
	 *
	 * @var Site_Health
	 */
	protected Site_Health $instance;

	/**
	 * Set up test environment.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->instance = Site_Health::get_instance();
	}

	/**
	 * Coverage for setup_hooks.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::__construct
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$hooks = array(
			array(
				'type'     => 'filter',
				'name'     => 'site_status_tests',
				'priority' => 10,
				'callback' => array( $this->instance, 'register_site_status_tests' ),
			),
		);

		$this->assert_hooks( $hooks, $this->instance );
	}

	/**
	 * Registers the pretty permalinks direct Site Health test.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::register_site_status_tests
	 *
	 * @return void
	 */
	public function test_register_site_status_tests(): void {
		$tests = $this->instance->register_site_status_tests(
			array(
				'direct' => array(),
				'async'  => array(),
			)
		);

		$this->assertArrayHasKey( Site_Health::PRETTY_PERMALINKS_TEST, $tests['direct'] );
		$this->assertSame(
			array( $this->instance, 'test_pretty_permalinks' ),
			$tests['direct'][ Site_Health::PRETTY_PERMALINKS_TEST ]['test']
		);
	}

	/**
	 * Returns a good result when pretty permalinks are enabled.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::test_pretty_permalinks
	 *
	 * @return void
	 */
	public function test_pretty_permalinks_returns_good_when_structure_is_set(): void {
		add_filter(
			'pre_option_permalink_structure',
			static function (): string {
				return '/%postname%/';
			}
		);

		$result = $this->instance->test_pretty_permalinks();

		remove_all_filters( 'pre_option_permalink_structure' );

		$this->assertSame( 'good', $result['status'] );
		$this->assertSame( Site_Health::PRETTY_PERMALINKS_TEST, $result['test'] );
		$this->assertSame( '', $result['actions'] );
	}

	/**
	 * Returns a recommended result when permalinks are Plain.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::test_pretty_permalinks
	 *
	 * @return void
	 */
	public function test_pretty_permalinks_returns_recommended_when_plain(): void {
		add_filter(
			'pre_option_permalink_structure',
			static function (): string {
				return '';
			}
		);

		$result = $this->instance->test_pretty_permalinks();

		remove_all_filters( 'pre_option_permalink_structure' );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString(
			admin_url( 'options-permalink.php' ),
			$result['actions']
		);
	}
}
