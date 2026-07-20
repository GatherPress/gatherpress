<?php
/**
 * Manages conditional admin notifications.
 *
 * This file contains the Notifications class, a small registry for admin
 * notices that should only appear while some condition about the site holds.
 *
 * @package GatherPress\Core
 * @since 0.34.1
 */

namespace GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Notifications.
 *
 * A registry for lifecycle notices: messages shown in wp-admin only while a
 * condition about the site holds, and which disappear on their own once it
 * stops holding. Upcoming requirement bumps, migration prompts, and
 * deprecation warnings all have this shape, so the mechanism is generic and
 * each notice is a single registration.
 *
 * This is distinct from `requirements-check.php`, which enforces the floors
 * that are in effect *now* and blocks the plugin from loading. Notices
 * registered here are advisory: the site works fine today, but something is
 * coming that the administrator should act on first.
 *
 * @since 0.34.1
 */
final class Notifications {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * The PHP version GatherPress will require as of self::UPCOMING_VERSION.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const UPCOMING_REQUIRES_PHP = '8.1';

	/**
	 * The WordPress version GatherPress will require as of self::UPCOMING_VERSION.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const UPCOMING_REQUIRES_WP = '7.0';

	/**
	 * The GatherPress release that raises the requirements above.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const UPCOMING_VERSION = '0.35.0';

	/**
	 * Capability required to see requirement notices.
	 *
	 * Updating PHP or WordPress is not something a subscriber or an editor can
	 * act on, and on multisite it is not something a site administrator can act
	 * on either. `update_plugins` lines up with who can actually respond.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const CAPABILITY = 'update_plugins';

	/**
	 * Registered notices, keyed by id.
	 *
	 * @since 0.34.1
	 * @var array<string, array<string, mixed>>
	 */
	protected array $notices = array();

	/**
	 * Class constructor.
	 *
	 * @since 0.34.1
	 */
	protected function __construct() {
		$this->register_requirement_notices();
		$this->setup_hooks();
	}

	/**
	 * Set up hooks for various purposes.
	 *
	 * @since 0.34.1
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Register a conditional notice.
	 *
	 * The condition and the message are both callables so that neither runs
	 * until render time. That matters for the message in particular: building
	 * it eagerly would call the translation functions before `init`, which
	 * WordPress 6.7 flags as loading a textdomain too early.
	 *
	 * @since 0.34.1
	 *
	 * @param string   $id         Unique slug for the notice.
	 * @param callable $condition  Returns true while the notice should show.
	 * @param callable $message    Returns the notice's translated message.
	 * @param string   $capability Capability required to see the notice.
	 * @param string   $type       Notice type: 'warning', 'error', 'info', 'success'.
	 *
	 * @return void
	 */
	public function register(
		string $id,
		callable $condition,
		callable $message,
		string $capability = self::CAPABILITY,
		string $type = 'warning'
	): void {
		$this->notices[ $id ] = array(
			'condition'  => $condition,
			'message'    => $message,
			'capability' => $capability,
			'type'       => $type,
		);
	}

	/**
	 * Get the registered notices.
	 *
	 * @since 0.34.1
	 *
	 * @return array<string, array<string, mixed>> Registered notices, keyed by id.
	 */
	public function get_notices(): array {
		return $this->notices;
	}

	/**
	 * Register the notices warning about the upcoming requirement bump.
	 *
	 * Two separate notices, each gated on its own condition, so a site failing
	 * both floors sees both messages and a site failing neither sees nothing.
	 *
	 * @since 0.34.1
	 *
	 * @return void
	 */
	protected function register_requirement_notices(): void {
		$this->register(
			'gatherpress_upcoming_php_requirement',
			function (): bool {
				return $this->is_below_upcoming_php( PHP_VERSION );
			},
			function (): string {
				return sprintf(
					/* translators: %1$s: GatherPress version, %2$s: required PHP version, %3$s: current PHP version. */
					esc_html__(
						// phpcs:disable Generic.Files.LineLength.TooLong
						'GatherPress %1$s will require PHP %2$s or higher. This site is running PHP %3$s. Update PHP, or ask your host to, to keep receiving GatherPress updates.',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					esc_html( self::UPCOMING_VERSION ),
					esc_html( self::UPCOMING_REQUIRES_PHP ),
					esc_html( phpversion() )
				);
			}
		);

		$this->register(
			'gatherpress_upcoming_wp_requirement',
			function (): bool {
				return $this->is_below_upcoming_wp( get_bloginfo( 'version' ) );
			},
			function (): string {
				return sprintf(
					/* translators: %1$s: GatherPress version, %2$s: required WP version, %3$s: current WP version. */
					esc_html__(
						// phpcs:disable Generic.Files.LineLength.TooLong
						'GatherPress %1$s will require WordPress %2$s or higher. This site is running WordPress %3$s. Update WordPress to keep receiving GatherPress updates.',
						// phpcs:enable Generic.Files.LineLength.TooLong
						'gatherpress'
					),
					esc_html( self::UPCOMING_VERSION ),
					esc_html( self::UPCOMING_REQUIRES_WP ),
					esc_html( get_bloginfo( 'version' ) )
				);
			}
		);
	}

	/**
	 * Whether a PHP version falls below the upcoming requirement.
	 *
	 * Takes the version as an argument rather than reading PHP_VERSION directly
	 * so the comparison is testable without the test run having to happen on an
	 * old PHP.
	 *
	 * @since 0.34.1
	 *
	 * @param string $php_version Version to test, e.g. PHP_VERSION.
	 *
	 * @return bool True when the version is older than the upcoming requirement.
	 */
	public function is_below_upcoming_php( string $php_version ): bool {
		return version_compare( $php_version, self::UPCOMING_REQUIRES_PHP, '<' );
	}

	/**
	 * Whether a WordPress version falls below the upcoming requirement.
	 *
	 * @since 0.34.1
	 *
	 * @param string $wp_version Version to test, e.g. the output of get_bloginfo( 'version' ).
	 *
	 * @return bool True when the version is older than the upcoming requirement.
	 */
	public function is_below_upcoming_wp( string $wp_version ): bool {
		return version_compare( $wp_version, self::UPCOMING_REQUIRES_WP, '<' );
	}

	/**
	 * Render every notice whose condition currently holds.
	 *
	 * Notices are marked dismissible, which in WordPress means dismissed for
	 * the current page view only. That is the behavior we want for a
	 * requirement warning: it can be waved away while reading a screen, but it
	 * comes back on the next load and keeps coming back until the site is
	 * actually updated.
	 *
	 * @since 0.34.1
	 *
	 * @return void
	 */
	public function render(): void {
		foreach ( $this->notices as $id => $notice ) {
			if ( ! current_user_can( $notice['capability'] ) ) {
				continue;
			}

			if ( ! call_user_func( $notice['condition'] ) ) {
				continue;
			}

			wp_admin_notice(
				call_user_func( $notice['message'] ),
				array(
					'type'               => $notice['type'],
					'dismissible'        => true,
					'id'                 => str_replace( '_', '-', $id ),
					'additional_classes' => array( 'gatherpress-notification' ),
				)
			);
		}
	}
}
