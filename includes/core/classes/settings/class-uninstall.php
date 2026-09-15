<?php
/**
 * Uninstall settings page for GatherPress.
 *
 * Declares what deleting the plugin is allowed to remove.
 *
 * @package GatherPress\Core
 * @since 0.36.0
 */

namespace GatherPress\Core\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Settings;
use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Uninstall\Preferences;
use GatherPress\Core\Utility;

/**
 * Class Uninstall.
 *
 * The opt-in screen for the destructive uninstall tasks. Everything here is
 * off until an administrator turns it on, so deleting the plugin removes
 * nothing a reinstall would want back unless that was asked for.
 *
 * Rendered as its own form rather than through `get_sections()`, because a
 * declared settings field is written by `Settings::import_settings()`, and
 * importing a settings file from elsewhere must not be able to arm data
 * deletion. `Preferences` stores the values in its own option, which the
 * importer never sees.
 *
 * @since 0.36.0
 */
final class Uninstall extends Base {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Action name for the save request.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const SAVE_ACTION = 'gatherpress_save_uninstall';

	/**
	 * Nonce name for the save request.
	 *
	 * @since 0.36.0
	 * @var string
	 */
	const NONCE_NAME = 'gatherpress_uninstall_nonce';

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		parent::setup_hooks();

		add_action( 'gatherpress_settings_section', array( $this, 'settings_section' ), 9 );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
	}

	/**
	 * Get the slug for the uninstall settings page.
	 *
	 * @since 0.36.0
	 *
	 * @return string The slug for the uninstall settings page.
	 */
	protected function get_slug(): string {
		return 'uninstall_settings';
	}

	/**
	 * Get the name for the uninstall settings page.
	 *
	 * @since 0.36.0
	 *
	 * @return string The localized name for the uninstall settings page.
	 */
	protected function get_name(): string {
		return __( 'Uninstall', 'gatherpress' );
	}

	/**
	 * Get the priority for displaying the uninstall settings page.
	 *
	 * At the end of the list, after Tools and before Credits. Nothing an
	 * administrator reaches for day to day should sit in front of it, and
	 * Credits stays last where people expect to find it.
	 *
	 * The three tail pages hold distinct priorities rather than sharing
	 * one, so their order does not depend on the sort being stable.
	 *
	 * @since 0.36.0
	 *
	 * @return int The priority for displaying the uninstall settings page.
	 */
	protected function get_priority(): int {
		return PHP_INT_MAX - 1;
	}

	/**
	 * Capability required to change what uninstall removes.
	 *
	 * The preference is a network option on multisite, because
	 * `Uninstall\Base::run()` checks it once for the whole network, so
	 * changing it is a network administrator's decision there.
	 *
	 * @since 0.36.0
	 *
	 * @return string The capability name.
	 */
	public static function required_capability(): string {
		return is_multisite() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Render the uninstall form instead of the default settings form.
	 *
	 * @since 0.36.0
	 *
	 * @param string $page The current settings page slug.
	 *
	 * @return void
	 */
	public function settings_section( string $page ): void {
		if ( Utility::unprefix_key( $page ) !== $this->slug ) {
			return;
		}

		remove_action(
			'gatherpress_settings_section',
			array( Settings::get_instance(), 'render_settings_form' )
		);

		Utility::render_template(
			sprintf( '%s/includes/templates/admin/settings/uninstall.php', GATHERPRESS_CORE_PATH ),
			array(
				'preferences' => Preferences::all(),
				'can_edit'    => current_user_can( self::required_capability() ),
				'scope'       => is_network_admin() ? 'network' : 'blog',
			),
			true
		);
	}

	/**
	 * Save the submitted preferences.
	 *
	 * @since 0.36.0
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( self::required_capability() ) ) {
			wp_die(
				esc_html__( 'You are not allowed to change what uninstall removes.', 'gatherpress' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::SAVE_ACTION, self::NONCE_NAME );

		// The body below runs only on a valid nonce and terminates via
		// wp_safe_redirect → exit, so it's untestable under PHPUnit without
		// subprocess isolation.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
		// @codeCoverageIgnoreStart
		$submitted = array();

		foreach ( Preferences::task_keys() as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran above.
			$submitted[ $key ] = ! empty( $_POST[ 'gatherpress_uninstall_' . $key ] );
		}

		Preferences::save( $submitted );

		wp_safe_redirect( $this->redirect_url( $this->resolve_scope() ) );

		exit;
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Which screen the form was submitted from.
	 *
	 * The scope travels with the request rather than being read from the
	 * environment, because `admin-post.php` always runs in site-admin
	 * context: `is_network_admin()` is false here even when the form was
	 * rendered on the network screen. `Settings\Tools` resolves its own
	 * scope the same way, for the same reason.
	 *
	 * @since 0.36.0
	 *
	 * @return string Either 'network' or 'blog'.
	 */
	protected function resolve_scope(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran in the caller.
		$raw = isset( $_POST['gatherpress_scope'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() ran in the caller.
			? sanitize_key( wp_unslash( $_POST['gatherpress_scope'] ) )
			: 'blog';

		return 'network' === $raw ? 'network' : 'blog';
	}

	/**
	 * Where to send the administrator after a save.
	 *
	 * @since 0.36.0
	 *
	 * @param string $scope Either 'network' or 'blog'.
	 *
	 * @return string The settings page URL.
	 */
	protected function redirect_url( string $scope = 'blog' ): string {
		if ( 'network' === $scope ) {
			return add_query_arg(
				array(
					'page'                        => Network::PAGE_SLUG,
					'gatherpress_uninstall_saved' => 1,
				),
				network_admin_url( 'settings.php' )
			);
		}

		return add_query_arg(
			array(
				'post_type'                   => 'gatherpress_event',
				'page'                        => Utility::prefix_key( $this->slug ),
				'gatherpress_uninstall_saved' => 1,
			),
			admin_url( 'edit.php' )
		);
	}
}
