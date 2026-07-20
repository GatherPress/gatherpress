<?php
/**
 * Manages GatherPress admin notices.
 *
 * This file contains the Notifications class, the registry that decides which
 * admin notices render and handles their dismissal.
 *
 * @package GatherPress\Core\Admin
 * @since 0.34.1
 */

namespace GatherPress\Core\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Notifications.
 *
 * A registry for lifecycle notices: messages shown in wp-admin only while some
 * condition about the site holds, which disappear on their own once it stops.
 * Upcoming requirement bumps, migration prompts and deprecation warnings all
 * have that shape, so the mechanism stays generic and each notice is a single
 * registration.
 *
 * This runs after the requirements gate. Notices that need to render *before*
 * it -- the ones in `requirements-check.php` -- build a Notice directly and
 * hook it themselves, because this class is autoloaded and the autoloader is
 * not registered yet at that point.
 *
 * @since 0.34.1
 */
final class Notifications {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Query argument carrying the slug of a notice being dismissed.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const DISMISS_QUERY_ARG = 'gatherpress_dismiss_notice';

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
	 * Registered notices, keyed by slug.
	 *
	 * @since 0.34.1
	 * @var array<string, Notice>
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
		add_action( 'admin_init', array( $this, 'handle_dismissal' ) );
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Add a notice to the registry.
	 *
	 * @since 0.34.1
	 *
	 * @param Notice $notice The notice to register.
	 *
	 * @return void
	 */
	public function add( Notice $notice ): void {
		$this->notices[ $notice->get_slug() ] = $notice;
	}

	/**
	 * Get the registered notices.
	 *
	 * @since 0.34.1
	 *
	 * @return array<string, Notice> Registered notices, keyed by slug.
	 */
	public function get_notices(): array {
		return $this->notices;
	}

	/**
	 * Record a persistent notice as dismissed.
	 *
	 * Deliberately does not redirect afterwards. Redirecting would mean
	 * `exit`, which is untestable without a shim, and the dismissal is
	 * idempotent -- reloading the same URL simply re-records it -- so the only
	 * cost of staying put is a spent query argument in the address bar.
	 *
	 * Returns void rather than a success flag because this is an action
	 * callback, and WordPress action callbacks must not return anything. The
	 * outcome is observable through the dismissal option.
	 *
	 * @since 0.34.1
	 *
	 * @return void
	 */
	public function handle_dismissal(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked below, once the slug identifies a real notice.
		$requested = isset( $_GET[ self::DISMISS_QUERY_ARG ] )
			? sanitize_key( wp_unslash( $_GET[ self::DISMISS_QUERY_ARG ] ) )
			: '';

		if ( '' === $requested || ! isset( $this->notices[ $requested ] ) ) {
			return;
		}

		$notice = $this->notices[ $requested ];

		// wp_verify_nonce rather than check_admin_referer: the latter calls
		// wp_die() on a bad nonce, so a stale dismiss link would replace the
		// admin screen with an error page instead of being quietly ignored.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! $notice->is_persistent() || ! wp_verify_nonce( $nonce, 'gatherpress_dismiss_notice_' . $requested ) ) {
			return;
		}

		$notice->dismiss();
	}

	/**
	 * Render every notice that currently applies.
	 *
	 * @since 0.34.1
	 *
	 * @return void
	 */
	public function render(): void {
		foreach ( $this->notices as $notice ) {
			if ( ! $notice->should_render() ) {
				continue;
			}

			$notice->render();
		}
	}

	/**
	 * Register the notices warning about the upcoming requirement bump.
	 *
	 * Two separate notices, each gated on its own condition, so a site failing
	 * both floors sees both messages and a site failing neither sees nothing.
	 *
	 * These are dismissible but not persistent: they can be waved away while
	 * reading a screen, but they come back on the next load and keep coming
	 * back until the site is actually updated. A requirement warning that can
	 * be silenced forever defeats its own purpose.
	 *
	 * @since 0.34.1
	 *
	 * @return void
	 */
	protected function register_requirement_notices(): void {
		$this->add(
			new Notice(
				'gatherpress_upcoming_php_requirement',
				array(
					'type'       => Notice::TYPE_WARNING,
					'capability' => self::CAPABILITY,
					'condition'  => function (): bool {
						return $this->is_below_upcoming_php( PHP_VERSION );
					},
					'message'    => function (): string {
						return sprintf(
							/* translators: %1$s: GatherPress version, %2$s: required PHP, %3$s: current PHP. */
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
					},
				)
			)
		);

		$this->add(
			new Notice(
				'gatherpress_upcoming_wp_requirement',
				array(
					'type'       => Notice::TYPE_WARNING,
					'capability' => self::CAPABILITY,
					'condition'  => function (): bool {
						return $this->is_below_upcoming_wp( get_bloginfo( 'version' ) );
					},
					'message'    => function (): string {
						return sprintf(
							/* translators: %1$s: GatherPress version, %2$s: required WP, %3$s: current WP. */
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
					},
				)
			)
		);
	}

	/**
	 * Whether a PHP version falls below the upcoming requirement.
	 *
	 * Takes the version as an argument rather than reading PHP_VERSION
	 * directly so the comparison is testable without the suite having to run
	 * on an old PHP.
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
	 * @param string $wp_version Version to test, e.g. get_bloginfo( 'version' ).
	 *
	 * @return bool True when the version is older than the upcoming requirement.
	 */
	public function is_below_upcoming_wp( string $wp_version ): bool {
		return version_compare( $wp_version, self::UPCOMING_REQUIRES_WP, '<' );
	}
}
