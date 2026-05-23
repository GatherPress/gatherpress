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
	 * Returns an empty string when no theme, block, or plugin template exists.
	 *
	 * @covers ::locate_template
	 *
	 * @return void
	 */
	public function test_locate_template_returns_empty_when_nothing_matches(): void {
		$this->assertSame(
			'',
			Utility::locate_template( 'gatherpress-definitely-missing.php', '/nonexistent/plugin/templates' ),
			'Should return an empty string when no candidate template exists.'
		);
	}

	/**
	 * Resolves a template file placed in the active theme directory.
	 *
	 * @covers ::locate_template
	 *
	 * @return void
	 */
	public function test_locate_template_finds_theme_override(): void {
		$file_name = 'gatherpress-locate-test-' . wp_generate_password( 6, false, false ) . '.php';
		$tmp_dir   = sys_get_temp_dir() . '/gatherpress-locate-theme-' . wp_generate_password( 6, false, false );

		wp_mkdir_p( $tmp_dir );
		$theme_path = trailingslashit( $tmp_dir ) . $file_name;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Tmp scratch dir under sys_get_temp_dir().
		file_put_contents( $theme_path, "<?php // Test stub.\n" );

		$override = static function () use ( $tmp_dir ) {
			return $tmp_dir;
		};
		add_filter( 'stylesheet_directory', $override );
		add_filter( 'template_directory', $override );

		try {
			$result = Utility::locate_template( $file_name, '/nonexistent/plugin/templates' );

			$this->assertSame(
				$theme_path,
				$result,
				'Theme override should win over the plugin fallback directory.'
			);
		} finally {
			remove_filter( 'stylesheet_directory', $override );
			remove_filter( 'template_directory', $override );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Tmp scratch dir.
			unlink( $theme_path );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Tmp scratch dir.
			rmdir( $tmp_dir );
		}
	}

	/**
	 * Falls back to the bundled plugin template and strips the gatherpress_ prefix.
	 *
	 * @covers ::locate_template
	 *
	 * @return void
	 */
	public function test_locate_template_resolves_plugin_fallback_with_prefix_strip(): void {
		$plugin_dir = sprintf( '%s/includes/templates/calendar', GATHERPRESS_CORE_PATH );

		$result = Utility::locate_template(
			'gatherpress_ical-download.php',
			$plugin_dir
		);

		$this->assertSame(
			sprintf( '%s/ical-download.php', $plugin_dir ),
			$result,
			'Should resolve the bundled iCal template and strip the gatherpress_ prefix.'
		);
	}

	/**
	 * Resolves a plugin template when the filename has no gatherpress_ prefix.
	 *
	 * @covers ::locate_template
	 *
	 * @return void
	 */
	public function test_locate_template_plugin_fallback_exact_filename(): void {
		$tmp_template = wp_tempnam( 'gatherpress-locate-plugin' );
		$dir_path     = dirname( $tmp_template );
		$file_name    = basename( $tmp_template );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Tmp scratch file.
		file_put_contents( $tmp_template, "<?php // Test stub.\n" );

		try {
			$result = Utility::locate_template( $file_name, $dir_path );

			$this->assertSame(
				$tmp_template,
				$result,
				'Should resolve an exact plugin filename when no prefix stripping is needed.'
			);
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Tmp scratch file.
			unlink( $tmp_template );
		}
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
	 * `Utility::post_type_label()` reads a single label off a registered
	 * post type so admin UI strings reflect whatever a site builder
	 * filtered the labels to (#1612).
	 *
	 * @covers ::post_type_label
	 *
	 * @return void
	 */
	public function test_post_type_label_returns_registered_label(): void {
		register_post_type(
			'shindig',
			array(
				'public' => false,
				'labels' => array(
					'name'          => 'Shindigs',
					'singular_name' => 'Shindig',
					'add_new_item'  => 'Add New Shindig',
				),
			)
		);

		$this->assertSame( 'Shindigs', Utility::post_type_label( 'name', 'shindig' ) );
		$this->assertSame( 'Shindig', Utility::post_type_label( 'singular_name', 'shindig' ) );
		$this->assertSame( 'Add New Shindig', Utility::post_type_label( 'add_new_item', 'shindig' ) );

		unregister_post_type( 'shindig' );
	}

	/**
	 * `Utility::post_type_label()` returns an empty string for an
	 * unregistered post type rather than warning. Lets call sites fall
	 * back gracefully when the post type isn't (or isn't yet)
	 * registered.
	 *
	 * @covers ::post_type_label
	 *
	 * @return void
	 */
	public function test_post_type_label_unregistered_post_type_returns_empty_string(): void {
		$this->assertSame( '', Utility::post_type_label( 'name', 'definitely_not_a_post_type' ) );
	}

	/**
	 * `Utility::post_type_label()` returns an empty string when the
	 * label key isn't set on the post type, instead of triggering a
	 * notice on the missing dynamic property.
	 *
	 * @covers ::post_type_label
	 *
	 * @return void
	 */
	public function test_post_type_label_missing_key_returns_empty_string(): void {
		register_post_type(
			'shindig',
			array(
				'public' => false,
				'labels' => array(
					'name' => 'Shindigs',
				),
			)
		);

		$this->assertSame( '', Utility::post_type_label( 'no_such_label_key', 'shindig' ) );

		unregister_post_type( 'shindig' );
	}

	/**
	 * Coverage for snake_to_camel method.
	 *
	 * @covers ::snake_to_camel
	 *
	 * @return void
	 */
	public function test_snake_to_camel(): void {
		$this->assertSame( 'dateFormat', Utility::snake_to_camel( 'date_format' ) );
		$this->assertSame( 'enableAnonymousRsvp', Utility::snake_to_camel( 'enable_anonymous_rsvp' ) );
		$this->assertSame( 'postOrEventDate', Utility::snake_to_camel( 'post_or_event_date' ) );
		$this->assertSame( 'simple', Utility::snake_to_camel( 'simple' ) );
	}

	/**
	 * Coverage for can_edit_post_meta — per-post auth callback shared by every
	 * editor-writable post meta GatherPress registers.
	 *
	 * Routes through `user_can( $user_id, 'edit_post', $object_id )` so the
	 * permission model matches what WP applies to the post itself: Editors
	 * (via `edit_others_posts`) can edit anyone's meta, Authors only their
	 * own posts, Subscribers and logged-out users are denied.
	 *
	 * @covers ::can_edit_post_meta
	 *
	 * @return void
	 */
	public function test_can_edit_post_meta(): void {
		$author_one_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$author_two_id = $this->factory->user->create( array( 'role' => 'author' ) );
		$editor_id     = $this->factory->user->create( array( 'role' => 'editor' ) );
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_author' => $author_one_id,
				'post_status' => 'publish',
			)
		);

		$this->assertTrue(
			Utility::can_edit_post_meta( false, 'any_meta_key', $post_id, $author_one_id ),
			'The post author should be able to edit their own post meta.'
		);
		$this->assertFalse(
			Utility::can_edit_post_meta( false, 'any_meta_key', $post_id, $author_two_id ),
			'A different author should not be able to edit a post they do not own.'
		);
		$this->assertTrue(
			Utility::can_edit_post_meta( false, 'any_meta_key', $post_id, $editor_id ),
			'An editor should be able to edit any post meta via edit_others_posts.'
		);
		$this->assertFalse(
			Utility::can_edit_post_meta( false, 'any_meta_key', $post_id, $subscriber_id ),
			'A subscriber should not be able to edit any post meta.'
		);
		$this->assertFalse(
			Utility::can_edit_post_meta( false, 'any_meta_key', $post_id, 0 ),
			'A logged-out user (user_id 0) should not be able to edit any post meta.'
		);
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
	 * Regression: option values must be raw timezone identifiers, never markup.
	 *
	 * WordPress's `wp_timezone_choice()` markup has shifted across releases —
	 * recent versions added a `dir="auto"` attribute on `<optgroup>` and
	 * `<option>` tags. A greedy `.+` capture in the parser used to pull that
	 * trailing attribute into the option value, so the saved timezone never
	 * matched any option in the editor's select and the dropdown silently
	 * fell back to the first item. This bug has shipped twice; lock it down
	 * with assertions that catch any HTML markup leaking into either group
	 * keys or option values, regardless of which WP version emitted them.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::timezone_choices
	 *
	 * @return void
	 */
	public function test_timezone_choices_keys_and_values_have_no_html_leakage(): void {
		$timezones = Utility::timezone_choices();

		$this->assertNotEmpty(
			$timezones,
			'timezone_choices() should return at least one group.'
		);

		// Tokens that should never appear in a group label or option value /
		// label — any presence indicates HTML markup leaked through the
		// parser. Mapped to a short noun phrase that completes the
		// assertion failure message ("Group label \"...\" contains <reason>").
		$forbidden = array(
			'"'    => 'a quote',
			'<'    => 'a tag character',
			'>'    => 'a tag character',
			'dir=' => 'a `dir=` attribute',
		);

		foreach ( $timezones as $group_label => $options ) {
			foreach ( $forbidden as $needle => $reason ) {
				$this->assertStringNotContainsString(
					$needle,
					$group_label,
					sprintf( 'Group label "%s" contains %s.', $group_label, $reason )
				);
			}

			$this->assertIsArray( $options );

			foreach ( $options as $value => $label ) {
				$value_string = (string) $value;
				foreach ( $forbidden as $needle => $reason ) {
					$this->assertStringNotContainsString(
						$needle,
						$value_string,
						sprintf( 'Option value "%s" contains %s.', $value_string, $reason )
					);
				}

				$label_string = (string) $label;
				foreach ( array( '<', '>' ) as $needle ) {
					$this->assertStringNotContainsString(
						$needle,
						$label_string,
						sprintf( 'Option label "%s" contains a tag character.', $label_string )
					);
				}
			}
		}
	}

	/**
	 * Regression: well-known timezone identifiers must round-trip as exact keys.
	 *
	 * If the parser corrupts option `value` attributes, common identifiers
	 * like `America/New_York` or `UTC` will not appear as exact keys in their
	 * groups — instead the keys carry trailing markup like
	 * `America/New_York" dir="auto`. Asserting that a few canonical
	 * identifiers exist as exact keys is a tight check that catches this
	 * class of regression early.
	 *
	 * @since 1.0.0
	 *
	 * @covers ::timezone_choices
	 *
	 * @return void
	 */
	public function test_timezone_choices_exposes_canonical_identifiers_as_exact_keys(): void {
		$timezones = Utility::timezone_choices();

		$this->assertArrayHasKey( 'America', $timezones );
		$this->assertArrayHasKey( 'America/New_York', $timezones['America'] );

		$this->assertArrayHasKey( 'Europe', $timezones );
		$this->assertArrayHasKey( 'Europe/Berlin', $timezones['Europe'] );

		$this->assertArrayHasKey( 'UTC', $timezones );
		$this->assertArrayHasKey( 'UTC', $timezones['UTC'] );
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
			// No gmt_offset and no timezone_string → defaults to UTC.
			array(
				false,
				false,
				'UTC',
			),
			// Whole positive offset.
			array(
				5,
				false,
				'+05:00',
			),
			// Whole negative offset.
			array(
				-4,
				false,
				'-04:00',
			),
			// Fractional offset (e.g. India).
			array(
				5.5,
				false,
				'+05:30',
			),
			// IANA passthrough.
			array(
				false,
				'Europe/London',
				'Europe/London',
			),
			// Etc/GMT is stripped; falls back to gmt_offset.
			array(
				-3,
				'Etc/GMT+3',
				'-03:00',
			),
		);
	}

	/**
	 * Data provider for test_offset_to_timezone_string.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function data_offset_to_timezone_string(): array {
		return array(
			'zero returns UTC'                  => array( 0.0, 'UTC' ),
			'positive whole hour'               => array( 5.0, '+05:00' ),
			'negative whole hour'               => array( -8.0, '-08:00' ),
			'positive half hour (India)'        => array( 5.5, '+05:30' ),
			'negative half hour (Newfoundland)' => array( -3.5, '-03:30' ),
			'positive quarter hour (Kathmandu)' => array( 5.75, '+05:45' ),
		);
	}

	/**
	 * Coverage for offset_to_timezone_string.
	 *
	 * @dataProvider data_offset_to_timezone_string
	 *
	 * @covers ::offset_to_timezone_string
	 *
	 * @param float  $offset   Decimal hour offset from UTC.
	 * @param string $expected Expected DateTimeZone-compatible string.
	 * @return void
	 */
	public function test_offset_to_timezone_string( float $offset, string $expected ): void {
		$this->assertSame( $expected, Utility::offset_to_timezone_string( $offset ) );
	}

	/**
	 * Data provider for test_normalize_timezone_string.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function data_normalize_timezone_string(): array {
		return array(
			'empty string becomes UTC'           => array( '', 'UTC' ),
			'whitespace-only string becomes UTC' => array( '   ', 'UTC' ),
			'offset +HH:MM passes through'       => array( '+05:30', '+05:30' ),
			'offset -HH:MM passes through'       => array( '-08:00', '-08:00' ),
			'literal UTC passes through'         => array( 'UTC', 'UTC' ),
			'UTC+0 normalizes to UTC'            => array( 'UTC+0', 'UTC' ),
			'UTC-0 normalizes to UTC'            => array( 'UTC-0', 'UTC' ),
			'UTC+5 becomes +05:00'               => array( 'UTC+5', '+05:00' ),
			'UTC-8 becomes -08:00'               => array( 'UTC-8', '-08:00' ),
			'UTC+5.5 becomes +05:30'             => array( 'UTC+5.5', '+05:30' ),
			'UTC+5:30 becomes +05:30'            => array( 'UTC+5:30', '+05:30' ),
			'UTC with zero colon form'           => array( 'UTC+0:00', 'UTC' ),
			'IANA identifier passes through'     => array( 'America/New_York', 'America/New_York' ),
		);
	}

	/**
	 * Coverage for normalize_timezone_string.
	 *
	 * @dataProvider data_normalize_timezone_string
	 *
	 * @covers ::normalize_timezone_string
	 *
	 * @param string $input    Input timezone string.
	 * @param string $expected Expected normalized output.
	 * @return void
	 */
	public function test_normalize_timezone_string( string $input, string $expected ): void {
		$this->assertSame( $expected, Utility::normalize_timezone_string( $input ) );
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

	/**
	 * Tests safe_exit returns early during unit tests instead of calling exit().
	 *
	 * @since 1.0.0
	 * @covers ::safe_exit
	 *
	 * @return void
	 */
	public function test_safe_exit_in_unit_tests(): void {
		// In unit test environment, safe_exit should return early instead of calling exit().
		// If this test completes, it means safe_exit returned instead of exiting.
		Utility::safe_exit();

		// If we reach this assertion, safe_exit returned successfully.
		$this->assertTrue( true, 'safe_exit should return early during unit tests' );
	}

	/**
	 * Coverage for get_block_names with simple block.
	 *
	 * @covers ::get_block_names
	 *
	 * @return void
	 */
	public function test_get_block_names_simple(): void {
		$blocks = array(
			'blockName' => 'core/paragraph',
		);

		$this->assertSame(
			array( 'core/paragraph' ),
			Utility::get_block_names( $blocks )
		);
	}

	/**
	 * Coverage for get_block_names with nested blocks.
	 *
	 * @covers ::get_block_names
	 *
	 * @return void
	 */
	public function test_get_block_names_nested(): void {
		$blocks = array(
			'blockName'   => 'core/group',
			'innerBlocks' => array(
				array(
					'blockName' => 'core/paragraph',
				),
				array(
					'blockName' => 'gatherpress/event-date',
				),
			),
		);

		$this->assertSame(
			array( 'core/group', 'core/paragraph', 'gatherpress/event-date' ),
			Utility::get_block_names( $blocks )
		);
	}

	/**
	 * Coverage for get_block_names with deeply nested blocks.
	 *
	 * @covers ::get_block_names
	 *
	 * @return void
	 */
	public function test_get_block_names_deeply_nested(): void {
		$blocks = array(
			'blockName'   => 'core/group',
			'innerBlocks' => array(
				array(
					'blockName'   => 'core/columns',
					'innerBlocks' => array(
						array(
							'blockName'   => 'core/column',
							'innerBlocks' => array(
								array(
									'blockName' => 'core/paragraph',
								),
							),
						),
					),
				),
			),
		);

		$this->assertSame(
			array( 'core/group', 'core/columns', 'core/column', 'core/paragraph' ),
			Utility::get_block_names( $blocks )
		);
	}

	/**
	 * Coverage for get_block_names with no blockName.
	 *
	 * @covers ::get_block_names
	 *
	 * @return void
	 */
	public function test_get_block_names_no_blockname(): void {
		$blocks = array(
			'attrs' => array(),
		);

		$this->assertSame(
			array(),
			Utility::get_block_names( $blocks )
		);
	}
}
