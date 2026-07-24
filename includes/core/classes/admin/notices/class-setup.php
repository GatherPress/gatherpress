<?php
/**
 * Manages GatherPress admin notices.
 *
 * This file contains the Setup class, the registry that decides which
 * admin notices render and handles their dismissal.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Core\Admin\Notices;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;

/**
 * Class Setup.
 *
 * A registry for lifecycle notices: messages shown in wp-admin only while some
 * condition about the site holds, which disappear on their own once it stops.
 * Upcoming requirement bumps, migration prompts and deprecation warnings all
 * have that shape, so adding one is a single registration.
 *
 * This runs after the requirements gate. The blocking notices -- the ones whose
 * condition stops GatherPress loading at all -- are constructed directly by
 * `requirements-check.php`, because this class is autoloaded and the autoloader
 * is not registered yet at that point.
 *
 * @since 0.34.1
 */
final class Setup {

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
	 * Registered notices, keyed by slug.
	 *
	 * @since 0.34.1
	 * @var array<string, Base>
	 */
	protected array $notices = array();

	/**
	 * Class constructor.
	 *
	 * @since 0.34.1
	 */
	protected function __construct() {
		$this->register_default_notices();
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
	 * @param Base $notice The notice to register.
	 *
	 * @return void
	 */
	public function add( Base $notice ): void {
		$this->notices[ $notice->get_slug() ] = $notice;
	}

	/**
	 * Get the registered notices.
	 *
	 * @since 0.34.1
	 *
	 * @return array<string, Base> Registered notices, keyed by slug.
	 */
	public function get_notices(): array {
		return $this->notices;
	}

	/**
	 * Register the notices GatherPress ships with.
	 *
	 * @since 0.34.1
	 *
	 * @return void
	 */
	protected function register_default_notices(): void {
		$this->add( new Upcoming_Php_Requirement() );
		$this->add( new Upcoming_Wp_Requirement() );
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
}
