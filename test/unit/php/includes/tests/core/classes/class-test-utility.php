<?php
/**
 * Class handles unit tests for GatherPress\Core\Utility.
 *
 * @package GatherPress\Core
 * @since 1.0.0
 */

namespace GatherPress\Tests\Core;

use ErrorException;
use GatherPress\Core\Utility;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility as PMC_Utility;

/**
 * Class Test_Utility.
 *
 * @coversDefaultClass \GatherPress\Core\Utility
 */
class Test_Utility extends Base {
	/**
	 * Coverage for render_template method.
	 *
	 * @covers ::render_template
	 *
	 * @throws ErrorException Throws exception if callback to buffer_and_return is not callable.
	 * @return void
	 */
	public function test_render_template(): void {
		$this->assertEmpty( Utility::render_template( '' ) );

		$description   = 'This is a template for testing.';
		$template_path = GATHERPRESS_CORE_PATH . '/test/unit/php/assets/templates/test-template.php';
		$template      = Utility::render_template( $template_path, array( 'description' => $description ) );
		$this->assertStringContainsString( $description, $template );

		$template = PMC_Utility::buffer_and_return(
			array( Utility::class, 'render_template' ),
			array(
				$template_path,
				array( 'description' => $description ),
				false,
			),
		);
		$this->assertEmpty( $template );

		$template = PMC_Utility::buffer_and_return(
			array( Utility::class, 'render_template' ),
			array(
				$template_path,
				array( 'description' => $description ),
				true,
			),
		);
		$this->assertStringContainsString( $description, $template );
	}

	/**
	 * Coverage for prefix_key method.
	 *
	 * @covers ::prefix_key
	 *
	 * @return void
	 */
	public function test_prefix_key(): void {
		$this->assertSame(
			'gatherpress_unittest',
			Utility::prefix_key( 'unittest' ),
			'Assert failed that gatherpress_ prefix is applied.'
		);
		$this->assertSame(
			'gatherpress_unittest',
			Utility::prefix_key( 'gatherpress_unittest' ),
			'Assert failed that gatherpress_ prefix is not reapplied if it exists already.'
		);
	}

	/**
	 * Coverage for unprefix_key method.
	 *
	 * @covers ::unprefix_key
	 *
	 * @return void
	 */
	public function test_unprefix_key() {
		$this->assertSame( 'unittest', Utility::unprefix_key( 'gatherpress_unittest' ) );
	}

	/**
	 * Coverage for timezone_choices method.
	 *
	 * @covers ::timezone_choices
	 *
	 * @return void
	 */
	public function test_timezone_choices(): void {
		$timezones = Utility::timezone_choices();
		$keys      = array( 'Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'UTC', 'Manual Offsets' );

		$this->assertIsArray( $timezones );

		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $timezones );
			$this->assertIsArray( $timezones[ $key ] );
		}
	}

	/**
	 * Data provider for maybe_convert_utc_offset test.
	 *
	 * @return array
	 */
	public function data_maybe_convert_utc_offset(): array {
		return array(
			array(
				'America/New_York',
				'America/New_York',
			),
			array(
				'UTC',
				'UTC',
			),
			array(
				'UTC+9.5',
				'+09:30',
			),
			array(
				'UTC-7.25',
				'-07:15',
			),
			array(
				'UTC-5.75',
				'-05:45',
			),
			array(
				'UTC+1',
				'+01:00',
			),
		);
	}

	/**
	 * Coverage for maybe_convert_utc_offset method.
	 *
	 * @dataProvider data_maybe_convert_utc_offset
	 *
	 * @covers ::maybe_convert_utc_offset
	 *
	 * @param string $input   Value to pass to method.
	 * @param string $expects Expected response.
	 *
	 * @return void
	 */
	public function test_maybe_convert_utc_offset( $input, $expects ): void {
		$this->assertSame(
			$expects,
			Utility::maybe_convert_utc_offset( $input ),
			'Failed to assert that conversion matches.'
		);
	}

	/**
	 * Coverage for list_timezone_and_utc_offsets method.
	 *
	 * @covers ::list_timezone_and_utc_offsets
	 *
	 * @return void
	 */
	public function test_list_timezone_and_utc_offsets(): void {
		$list      = Utility::list_timezone_and_utc_offsets();
		$timezones = array(
			'America/Belem',
			'Asia/Chita',
			'Europe/Vilnius',
			'UTC',
			'-12:00',
			'-00:30',
			'+09:30',
			'+13:45',
		);
		foreach ( $timezones as $timezone ) {
			$this->assertContains( $timezone, $list, 'Failed to assert timezone is in list.' );
		}
	}

	/**
	 * Data provider for get_system_timezone test.
	 *
	 * @return array
	 */
	public function data_get_system_timezone(): array {
		return array(
			array(
				false,
				false,
				'UTC+0',
			),
			array(
				5,
				false,
				'UTC+5',
			),
			array(
				-4,
				false,
				'UTC-4',
			),
			array(
				false,
				'Europe/London',
				'Europe/London',
			),
			array(
				false,
				'Etc/GMT+3',
				'UTC-3',
			),
		);
	}

	/**
	 * Coverage for get_system_timezone method.
	 *
	 * @dataProvider data_get_system_timezone
	 *
	 * @covers ::get_system_timezone
	 *
	 * @param int|boolean    $gmt_offset      The GMT offset to simulate getting from WordPress settings for testing.
	 * @param string|boolean $timezone_string The timezone string to simulate getting from WordPress settings for testing.
	 * @param string         $expects         The expected timezone string result from get_system_timezone.
	 *
	 * @return void
	 */
	public function test_get_system_timezone( $gmt_offset, $timezone_string, $expects ): void {
		$gmt_offset_filter      = add_filter(
			'option_gmt_offset',
			static function () use ( $gmt_offset ) {
				return $gmt_offset;
			}
		);
		$timezone_string_filter = add_filter(
			'option_timezone_string',
			static function () use ( $timezone_string ) {
				return $timezone_string;
			}
		);

		$this->assertSame( $expects, Utility::get_system_timezone() );

		remove_filter( 'option_gmt_offset', $gmt_offset_filter );
		remove_filter( 'option_timezone_string', $timezone_string_filter );
	}

	/**
	 * Coverage for get_login_url.
	 *
	 * @covers ::get_login_url
	 *
	 * @return void
	 */
	public function test_get_login_url(): void {
		$this->assertSame( wp_login_url(), Utility::get_login_url() );

		$post = $this->mock->post()->get();

		$this->assertSame( wp_login_url( get_the_permalink( $post->ID ) ), Utility::get_login_url( $post->ID ) );

		$this->mock->post()->reset();
	}

	/**
	 * Coverage for get_registration_url.
	 *
	 * @covers ::get_registration_url
	 *
	 * @return void
	 */
	public function test_get_registration_url(): void {
		$users_can_register_name    = 'users_can_register';
		$users_can_register_default = get_option( $users_can_register_name );

		update_option( $users_can_register_name, 0 );

		$this->assertEmpty( Utility::get_registration_url() );

		update_option( $users_can_register_name, 1 );

		$this->assertSame( wp_registration_url(), Utility::get_registration_url() );

		$post = $this->mock->post()->get();

		$this->assertSame(
			add_query_arg(
				'redirect',
				get_the_permalink( $post->ID ),
				wp_registration_url()
			),
			Utility::get_registration_url( $post->ID )
		);

		$this->mock->post()->reset();

		update_option( $users_can_register_name, $users_can_register_default );
	}
}
