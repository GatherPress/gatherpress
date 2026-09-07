<?php
/**
 * Unit tests for the uninstall settings page.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Tests\Core\Settings;

use GatherPress\Core\Settings\Uninstall;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Tests\Base;
use PMC\Unit_Test\Utility;

/**
 * Class Test_Uninstall.
 *
 * @coversDefaultClass \GatherPress\Core\Settings\Uninstall
 */
class Test_Uninstall extends Base {

	/**
	 * Reset the opt-in map between tests.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Preferences::OPTION_NAME );
		delete_site_option( Preferences::OPTION_NAME );
		Preferences::flush_cache();

		parent::tearDown();
	}

	/**
	 * The page registers its hooks.
	 *
	 * @covers ::setup_hooks
	 *
	 * @return void
	 */
	public function test_setup_hooks(): void {
		$instance = Uninstall::get_instance();
		$hooks    = array(
			array(
				'type'     => 'action',
				'name'     => 'gatherpress_settings_section',
				'priority' => 9,
				'callback' => array( $instance, 'settings_section' ),
			),
			array(
				'type'     => 'action',
				'name'     => 'admin_post_' . Uninstall::SAVE_ACTION,
				'priority' => 10,
				'callback' => array( $instance, 'handle_save' ),
			),
		);

		$this->assert_hooks( $hooks, $instance );
	}

	/**
	 * The page identifies itself.
	 *
	 * @covers ::get_slug
	 * @covers ::get_name
	 * @covers ::get_priority
	 *
	 * @return void
	 */
	public function test_page_identity(): void {
		$instance = Uninstall::get_instance();

		$this->assertSame(
			'uninstall_settings',
			Utility::invoke_hidden_method( $instance, 'get_slug' ),
			'The slug identifies the page.'
		);
		$this->assertSame(
			'Uninstall',
			Utility::invoke_hidden_method( $instance, 'get_name' ),
			'The page is named for what it governs.'
		);
		$this->assertSame(
			PHP_INT_MAX - 1,
			Utility::invoke_hidden_method( $instance, 'get_priority' ),
			'The page sits after Tools and before Credits, behind everything used day to day.'
		);
	}

	/**
	 * Changing the setting needs the right capability for the scope.
	 *
	 * @covers ::required_capability
	 *
	 * @return void
	 */
	public function test_required_capability_matches_scope(): void {
		$expected = is_multisite() ? 'manage_network_options' : 'manage_options';

		$this->assertSame(
			$expected,
			Uninstall::required_capability(),
			'The opt-in is a network decision on multisite, because it is checked once for the network.'
		);
	}

	/**
	 * The form is rendered for the uninstall page only.
	 *
	 * @covers ::settings_section
	 *
	 * @return void
	 */
	public function test_settings_section_renders_for_its_own_page(): void {
		$instance = Uninstall::get_instance();

		ob_start();
		$instance->settings_section( 'gatherpress_uninstall_settings' );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString(
			'gatherpress_uninstall_posts',
			$output,
			'The form offers a checkbox for each gated task.'
		);
		$this->assertStringContainsString(
			Uninstall::SAVE_ACTION,
			$output,
			'The form posts to the save action.'
		);
	}

	/**
	 * Another settings page is left alone.
	 *
	 * @covers ::settings_section
	 *
	 * @return void
	 */
	public function test_settings_section_ignores_other_pages(): void {
		$instance = Uninstall::get_instance();

		ob_start();
		$instance->settings_section( 'gatherpress_general' );
		$output = (string) ob_get_clean();

		$this->assertSame( '', $output, 'The page renders nothing for a slug it does not own.' );
	}

	/**
	 * Every gated task gets a checkbox.
	 *
	 * @covers ::settings_section
	 *
	 * @return void
	 */
	public function test_every_task_has_a_checkbox(): void {
		$instance = Uninstall::get_instance();

		ob_start();
		$instance->settings_section( 'gatherpress_uninstall_settings' );
		$output = (string) ob_get_clean();

		foreach ( Preferences::task_keys() as $key ) {
			$this->assertStringContainsString(
				'gatherpress_uninstall_' . $key,
				$output,
				sprintf( 'Task "%s" must be visible on the screen that governs it.', $key )
			);
		}
	}

	/**
	 * A stored opt-in is reflected back on the form.
	 *
	 * @covers ::settings_section
	 *
	 * @return void
	 */
	public function test_stored_opt_in_is_checked(): void {
		Preferences::save( array( Preferences::TASK_POSTS => true ) );

		$instance = Uninstall::get_instance();

		ob_start();
		$instance->settings_section( 'gatherpress_uninstall_settings' );
		$output = (string) ob_get_clean();

		$this->assertMatchesRegularExpression(
			'/gatherpress_uninstall_posts.*?checked/s',
			$output,
			'A task that is on reads back as checked.'
		);
	}

	/**
	 * The redirect returns to the screen the form came from.
	 *
	 * @covers ::redirect_url
	 *
	 * @return void
	 */
	public function test_redirect_url_returns_to_the_page(): void {
		$instance = Uninstall::get_instance();

		$blog = (string) Utility::invoke_hidden_method( $instance, 'redirect_url', array( 'blog' ) );

		$this->assertStringContainsString(
			'gatherpress_uninstall_saved=1',
			$blog,
			'The redirect carries the saved flag.'
		);
		$this->assertStringContainsString(
			'post_type=gatherpress_event',
			$blog,
			'A site save returns to the site settings screen.'
		);

		$network = (string) Utility::invoke_hidden_method( $instance, 'redirect_url', array( 'network' ) );

		$this->assertStringContainsString(
			'gatherpress-network-settings',
			$network,
			'A network save returns to the network settings screen.'
		);
	}

	/**
	 * The scope travels with the request, not the environment.
	 *
	 * `admin-post.php` always runs in site-admin context, so reading
	 * `is_network_admin()` in the handler would always say 'blog'.
	 *
	 * @covers ::resolve_scope
	 *
	 * @return void
	 */
	public function test_resolve_scope_reads_the_submitted_value(): void {
		$instance = Uninstall::get_instance();

		unset( $_POST['gatherpress_scope'] );

		$this->assertSame(
			'blog',
			Utility::invoke_hidden_method( $instance, 'resolve_scope' ),
			'A missing scope falls back to the site.'
		);

		$_POST['gatherpress_scope'] = 'network';

		$this->assertSame(
			'network',
			Utility::invoke_hidden_method( $instance, 'resolve_scope' ),
			'A network submission is recognized.'
		);

		$_POST['gatherpress_scope'] = 'nonsense';

		$this->assertSame(
			'blog',
			Utility::invoke_hidden_method( $instance, 'resolve_scope' ),
			'An unrecognized scope falls back to the site.'
		);

		unset( $_POST['gatherpress_scope'] );
	}

	/**
	 * The rendered form carries the scope it was rendered in.
	 *
	 * @covers ::settings_section
	 *
	 * @return void
	 */
	public function test_form_carries_its_scope(): void {
		ob_start();
		Uninstall::get_instance()->settings_section( 'gatherpress_uninstall_settings' );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString(
			'name="gatherpress_scope"',
			$output,
			'The form tells the handler which screen it came from.'
		);
	}

	/**
	 * A user without the capability cannot arm data deletion.
	 *
	 * @covers ::handle_save
	 * @covers ::required_capability
	 *
	 * @return void
	 */
	public function test_handle_save_wp_dies_without_capability(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->expectException( \WPDieException::class );

		Uninstall::get_instance()->handle_save();
	}

	/**
	 * A request without a valid nonce cannot arm data deletion.
	 *
	 * The capability check passes here, so this reaches
	 * `check_admin_referer()` and proves the nonce is what stops it.
	 *
	 * @covers ::handle_save
	 *
	 * @return void
	 */
	public function test_handle_save_wp_dies_on_invalid_nonce(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST[ Uninstall::NONCE_NAME ] = 'not-a-valid-nonce';

		$this->expectException( \WPDieException::class );

		try {
			Uninstall::get_instance()->handle_save();
		} finally {
			unset( $_POST[ Uninstall::NONCE_NAME ] );
		}
	}
}
