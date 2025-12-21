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
		$keys      = array(
			'Africa',
			'America',
			'Antarctica',
			'Arctic',
			'Asia',
			'Atlantic',
			'Australia',
			'Europe',
			'Indian',
			'UTC',
			'Manual Offsets',
		);

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
	 * @param string|boolean $timezone_string The timezone string to simulate getting from WordPress settings
	 *                                        for testing.
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

	/**
	 * Coverage for ensure_user_authentication method.
	 *
	 * @covers ::ensure_user_authentication
	 *
	 * @return void
	 */
	public function test_ensure_user_authentication(): void {
		// Test when no user is determined (anonymous context).
		$user_id = Utility::ensure_user_authentication();

		$this->assertFalse( $user_id, 'Should return false when no user can be determined' );
		$this->assertSame( 0, get_current_user_id(), 'Current user ID should remain 0 for anonymous context' );

		// Test with a logged-in user context.
		$test_user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $test_user_id );

		// Mock the determine_current_user filter to return our test user.
		add_filter(
			'determine_current_user',
			static function () use ( $test_user_id ) {
				return $test_user_id;
			}
		);

		$authenticated_user_id = Utility::ensure_user_authentication();

		$this->assertSame( $test_user_id, $authenticated_user_id, 'Should return the authenticated user ID' );
		$this->assertSame(
			$test_user_id,
			get_current_user_id(),
			'Current user should be set to the authenticated user'
		);

		// Clean up.
		wp_set_current_user( 0 );
		remove_all_filters( 'determine_current_user' );
	}

	/**
	 * Data provider for has_css_class test.
	 *
	 * @return array
	 */
	public function data_has_css_class(): array {
		return array(
			// Basic positive cases.
			array(
				'button primary',
				'button',
				true,
				'Should find exact class match',
			),
			array(
				'button primary',
				'primary',
				true,
				'Should find second class',
			),
			array(
				'single-class',
				'single-class',
				true,
				'Should find single class',
			),
			// BEM naming cases that caused the original issue.
			array(
				'gatherpress-modal--type-rsvp-form',
				'gatherpress-modal--type-rsvp-form',
				true,
				'Should find exact BEM class match',
			),
			array(
				'gatherpress-modal--type-rsvp-form other-class',
				'gatherpress-modal--type-rsvp-form',
				true,
				'Should find BEM class in multiple classes',
			),
			array(
				'gatherpress-modal--type-rsvp-form',
				'gatherpress-modal--type-rsvp',
				false,
				'Should NOT match substring of BEM class (original bug)',
			),
			array(
				'gatherpress-modal--type-rsvp other-class',
				'gatherpress-modal--type-rsvp',
				true,
				'Should find exact BEM base class',
			),
			// Negative cases.
			array(
				'button primary',
				'secondary',
				false,
				'Should not find non-existent class',
			),
			array(
				'button-primary',
				'button',
				false,
				'Should not match partial class names',
			),
			array(
				'my-button',
				'button',
				false,
				'Should not match substring',
			),
			// Edge cases.
			array(
				'',
				'button',
				false,
				'Should handle empty class string',
			),
			array(
				'button primary',
				'',
				false,
				'Should handle empty target class',
			),
			array(
				'   button   primary   ',
				'button',
				true,
				'Should handle extra whitespace',
			),
			array(
				"button\tprimary\ntertiary",
				'primary',
				true,
				'Should handle different whitespace characters',
			),
			array(
				'button  primary',
				'primary',
				true,
				'Should handle multiple spaces',
			),
			// Null handling cases.
			array(
				null,
				'button',
				false,
				'Should handle null class string gracefully',
			),
		);
	}

	/**
	 * Coverage for has_css_class method.
	 *
	 * @dataProvider data_has_css_class
	 *
	 * @covers ::has_css_class
	 *
	 * @param string|null $class_string The CSS class string to search in.
	 * @param string      $target_class The specific class to search for.
	 * @param bool        $expected     Expected result.
	 * @param string      $message      Test assertion message.
	 *
	 * @return void
	 */
	public function test_has_css_class(
		?string $class_string,
		string $target_class,
		bool $expected,
		string $message
	): void {
		$this->assertSame(
			$expected,
			Utility::has_css_class( $class_string, $target_class ),
			$message
		);
	}

	/**
	 * Tests get_http_input method with various sanitizers.
	 *
	 * @since 1.0.0
	 * @covers ::get_http_input
	 *
	 * @return void
	 */
	public function test_get_http_input_with_sanitizers(): void {
		// Set up mock data using pre_ filter.
		$mock_data = array(
			INPUT_POST => array(
				'text_field'     => '  Test Value  ',
				'email_field'    => 'test@example.com',
				'url_field'      => 'https://example.com',
				'textarea_field' => "Target line\nTarget line",
				'key_field'      => 'some-key_123',
				'title_field'    => 'Test Title 123',
				'user_field'     => 'testuser',
				'file_field'     => '/path/to/file.txt',
				'html_field'     => '<script>alert("XSS")</script>Hello',
			),
			INPUT_GET  => array(
				'page'    => '5',
				'search'  => 'test query',
				'success' => '1',
			),
		);

		// Enable mocking via pre_ filter.
		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $mock_data ) {
				return $mock_data[ $type ][ $var_name ] ?? null;
			},
			10,
			3
		);

		// Test default sanitization (sanitize_text_field).
		$result = Utility::get_http_input( INPUT_POST, 'text_field' );
		$this->assertEquals( 'Test Value', $result, 'Default sanitization should trim whitespace.' );

		// Test email sanitization.
		$result = Utility::get_http_input( INPUT_POST, 'email_field', 'sanitize_email' );
		$this->assertEquals( 'test@example.com', $result, 'Email should be sanitized.' );

		// Test URL sanitization.
		$result = Utility::get_http_input( INPUT_POST, 'url_field', 'sanitize_url' );
		$this->assertEquals( 'https://example.com', $result, 'URL should be sanitized.' );

		// Test textarea sanitization.
		$result = Utility::get_http_input( INPUT_POST, 'textarea_field', 'sanitize_textarea_field' );
		$this->assertEquals( "Target line\nTarget line", $result, 'Textarea should preserve line breaks.' );

		// Test key sanitization.
		$result = Utility::get_http_input( INPUT_POST, 'key_field', 'sanitize_key' );
		$this->assertEquals( 'some-key_123', $result, 'Key should be sanitized.' );

		// Test title sanitization.
		$result = Utility::get_http_input( INPUT_POST, 'title_field', 'sanitize_title' );
		$this->assertEquals( 'test-title-123', $result, 'Title should be sanitized to slug format.' );

		// Test user sanitization.
		$result = Utility::get_http_input( INPUT_POST, 'user_field', 'sanitize_user' );
		$this->assertEquals( 'testuser', $result, 'Username should be sanitized.' );

		// Test file name sanitization.
		$result = Utility::get_http_input( INPUT_POST, 'file_field', 'sanitize_file_name' );
		$this->assertEquals( 'pathtofile.txt', $result, 'File name should be sanitized.' );

		// Test HTML sanitization with wp_kses_post.
		$result = Utility::get_http_input( INPUT_POST, 'html_field', 'wp_kses_post' );
		$this->assertEquals(
			'alert("XSS")Hello',
			$result,
			'Script tags should be removed by wp_kses_post, content preserved.'
		);

		// Test custom sanitization function.
		$result = Utility::get_http_input(
			INPUT_POST,
			'text_field',
			static function ( $value ) {
				return strtoupper( trim( $value ) );
			}
		);
		$this->assertEquals( 'TEST VALUE', $result, 'Custom sanitizer should be applied.' );

		// Test GET parameters.
		$result = Utility::get_http_input( INPUT_GET, 'page', 'absint' );
		$this->assertEquals( '5', $result, 'Page number should be sanitized as absolute integer.' );

		$result = Utility::get_http_input( INPUT_GET, 'search' );
		$this->assertEquals( 'test query', $result, 'Search query should be sanitized with default sanitizer.' );

		// Test non-existent field.
		$result = Utility::get_http_input( INPUT_POST, 'nonexistent' );
		$this->assertEquals( '', $result, 'Non-existent field should return empty string.' );

		// Test with null sanitizer (should use default).
		$result = Utility::get_http_input( INPUT_POST, 'text_field', null );
		$this->assertEquals( 'Test Value', $result, 'Null sanitizer should use default sanitize_text_field.' );

		// Clean up filters.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests get_http_input method with special characters and encoding.
	 *
	 * @since 1.0.0
	 * @covers ::get_http_input
	 *
	 * @return void
	 */
	public function test_get_http_input_special_characters(): void {
		// Set up mock data with special characters.
		$mock_data = array(
			INPUT_POST => array(
				'quoted_field'  => 'Test with "quotes" and \'apostrophes\'',
				'slashed_field' => 'Test with \\backslashes\\ and /forward/',
				'unicode_field' => 'Test with émojis 🎉 and ünicode',
				'entity_field'  => 'Test &amp; entities &lt;html&gt;',
			),
		);

		add_filter(
			'gatherpress_pre_get_http_input',
			static function ( $pre_value, $type, $var_name ) use ( $mock_data ) {
				return $mock_data[ $type ][ $var_name ] ?? null;
			},
			10,
			3
		);

		// Test handling of quotes.
		$result = Utility::get_http_input( INPUT_POST, 'quoted_field' );
		$this->assertEquals( 'Test with "quotes" and \'apostrophes\'', $result );

		// Test handling of slashes.
		$result = Utility::get_http_input( INPUT_POST, 'slashed_field' );
		$this->assertEquals( 'Test with backslashes and /forward/', $result );

		// Test handling of unicode.
		$result = Utility::get_http_input( INPUT_POST, 'unicode_field' );
		$this->assertEquals( 'Test with émojis 🎉 and ünicode', $result );

		// Test handling of HTML entities.
		$result = Utility::get_http_input( INPUT_POST, 'entity_field' );
		$this->assertEquals( 'Test &amp; entities &lt;html&gt;', $result );

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_http_input' );
	}

	/**
	 * Tests get_wp_referer method.
	 *
	 * @since 1.0.0
	 * @covers ::get_wp_referer
	 *
	 * @return void
	 */
	public function test_get_wp_referer(): void {
		// Test when referer is available.
		$expected_referer = 'https://example.com/test-page';

		add_filter(
			'gatherpress_pre_get_wp_referer',
			static function () use ( $expected_referer ) {
				return $expected_referer;
			}
		);

		$result = Utility::get_wp_referer();

		$this->assertEquals(
			$expected_referer,
			$result,
			'Should return the mocked referer URL.'
		);

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_wp_referer' );

		// Test when referer is false.
		add_filter(
			'gatherpress_pre_get_wp_referer',
			static function () {
				return false;
			}
		);

		$result = Utility::get_wp_referer();

		$this->assertFalse(
			$result,
			'Should return false when no referer is available.'
		);

		// Clean up.
		remove_all_filters( 'gatherpress_pre_get_wp_referer' );
	}

	/**
	 * Tests get_wp_referer without filter (normal WordPress behavior).
	 *
	 * @since 1.0.0
	 * @covers ::get_wp_referer
	 *
	 * @return void
	 */
	public function test_get_wp_referer_without_filter(): void {
		// When no pre-filter is applied, it should call wp_get_referer().
		// In test environment, wp_get_referer() typically returns false.
		$result = Utility::get_wp_referer();

		// Should return whatever wp_get_referer() returns (typically false in tests).
		$this->assertFalse(
			$result,
			'Should return false when wp_get_referer has no referer in test environment.'
		);
	}
}
