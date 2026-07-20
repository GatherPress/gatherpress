<?php
/**
 * Base class for GatherPress admin notices.
 *
 * This file contains the Base class that every GatherPress admin notice
 * extends.
 *
 * IMPORTANT: this class, and any notice constructed by `requirements-check.php`
 * or `duplicate-check.php`, load before the requirements gate -- and therefore
 * before we know anything about the site's PHP version. Those files must PARSE
 * on the oldest PHP that could reach them, or a site below the floor gets a
 * fatal parse error instead of the notice telling it to upgrade: the whole site
 * goes down rather than just the plugin, and the file whose job is explaining
 * the problem becomes a worse one.
 *
 * So in this class and in every blocking notice:
 *
 *   - no return types or scalar parameter types (PHP 7.0)
 *   - no nullable types or `void` (PHP 7.1)
 *   - no typed properties or arrow functions (PHP 7.4)
 *   - no null coalescing (PHP 7.0), constructor promotion or `readonly` (8.1)
 *
 * Types live in the docblocks so static analysis still sees them, and
 * `npm run lint:php:early` enforces the constraint against PHP 7.2 --
 * WordPress 6.7's own floor, and therefore the oldest PHP that can run a
 * WordPress capable of running this plugin.
 *
 * The lint script names the early-loaded files explicitly rather than globbing
 * this directory, because the notices that render *after* the gate (and the
 * Setup registry alongside them) are ordinary modern code. Adding a new
 * blocking notice means adding it to that list -- which is exactly the moment
 * to be thinking about this constraint.
 *
 * @package GatherPress\Core\Admin\Notices
 * @since 0.34.1
 */

namespace GatherPress\Core\Admin\Notices;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Base.
 *
 * One admin notice. Subclasses declare what the notice says and when it
 * applies; this class handles dismissal, gating and rendering.
 *
 * Two kinds of dismissal are distinct here. A *dismissible* notice can be
 * closed for the current page view, which is all WordPress does natively. A
 * *persistent* notice records its slug in the dismissal option and stays gone.
 *
 * @since 0.34.1
 */
abstract class Base {

	/**
	 * Option storing the slugs of permanently dismissed notices.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const OPTION_NAME = 'gatherpress_admin_notices';

	/**
	 * Notice type: a problem that needs attention.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const TYPE_ERROR = 'error';

	/**
	 * Notice type: something to act on before it becomes a problem.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const TYPE_WARNING = 'warning';

	/**
	 * Notice type: neutral information.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const TYPE_INFO = 'info';

	/**
	 * Notice type: confirmation that something worked.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const TYPE_SUCCESS = 'success';

	/**
	 * Unique slug identifying this notice.
	 *
	 * @since 0.34.1
	 *
	 * @return string The slug.
	 */
	abstract public function get_slug();

	/**
	 * The notice's message.
	 *
	 * Built here rather than stored on the instance so the translation
	 * functions run at render time. Notices are constructed during bootstrap,
	 * and translating that early is what WordPress 6.7 flags as loading a
	 * textdomain before `init`.
	 *
	 * @since 0.34.1
	 *
	 * @return string The translated, escaped message.
	 */
	abstract public function get_message();

	/**
	 * The notice's type.
	 *
	 * @since 0.34.1
	 *
	 * @return string One of the TYPE_* constants.
	 */
	public function get_type() {
		return self::TYPE_INFO;
	}

	/**
	 * Whether the notice can be closed for the current page view.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when the notice renders a close button.
	 */
	public function is_dismissible() {
		return true;
	}

	/**
	 * Whether dismissing the notice is remembered across page loads.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when dismissal persists.
	 */
	public function is_persistent() {
		return false;
	}

	/**
	 * Capability required to see the notice.
	 *
	 * @since 0.34.1
	 *
	 * @return string A capability, or an empty string for no gate.
	 */
	public function get_capability() {
		return '';
	}

	/**
	 * Whether the notice's subject matter currently applies to this site.
	 *
	 * This is the condition the notice exists to report on -- an unmet
	 * requirement, a pending migration. Kept separate from should_render() so
	 * callers outside the admin, like `requirements-check.php` deciding whether
	 * to halt loading, can ask the question without the capability and
	 * dismissal gates getting in the way.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when the notice's condition holds.
	 */
	public function applies() {
		return true;
	}

	/**
	 * Whether this notice has been permanently dismissed.
	 *
	 * Only meaningful for persistent notices; a non-persistent notice is never
	 * recorded, so it is never dismissed.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when the slug is recorded as dismissed.
	 */
	public function is_dismissed() {
		if ( ! $this->is_persistent() ) {
			return false;
		}

		$dismissed = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $dismissed ) ) {
			return false;
		}

		return array_key_exists( $this->get_slug(), $dismissed );
	}

	/**
	 * Record this notice as permanently dismissed.
	 *
	 * Stores a timestamp rather than a bare flag so the record is useful for
	 * debugging, and so a future notice could expire its own dismissal.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when the dismissal was recorded.
	 */
	public function dismiss() {
		if ( ! $this->is_persistent() ) {
			return false;
		}

		$dismissed = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}

		$dismissed[ $this->get_slug() ] = time();

		return (bool) update_option( self::OPTION_NAME, $dismissed );
	}

	/**
	 * Get the URL that dismisses this notice.
	 *
	 * @since 0.34.1
	 *
	 * @return string Nonced dismissal URL, or an empty string when not persistent.
	 */
	public function get_dismiss_url() {
		if ( ! $this->is_persistent() ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg( 'gatherpress_dismiss_notice', $this->get_slug() ),
			'gatherpress_dismiss_notice_' . $this->get_slug()
		);
	}

	/**
	 * Whether the notice should render right now.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when the capability, dismissal and condition all allow it.
	 */
	public function should_render() {
		$capability = $this->get_capability();

		if ( '' !== $capability && ! current_user_can( $capability ) ) {
			return false;
		}

		if ( $this->is_dismissed() ) {
			return false;
		}

		return $this->applies();
	}

	/**
	 * Render the notice.
	 *
	 * @since 0.34.1
	 *
	 * @return void
	 */
	public function render() {
		$message = $this->get_message();

		if ( '' === $message ) {
			return;
		}

		if ( $this->is_persistent() ) {
			$message .= sprintf(
				' <a href="%s">%s</a>',
				esc_url( $this->get_dismiss_url() ),
				esc_html__( 'Dismiss this notice.', 'gatherpress' )
			);
		}

		$id = str_replace( '_', '-', $this->get_slug() );

		if ( function_exists( 'wp_admin_notice' ) ) {
			wp_admin_notice(
				$message,
				array(
					'type'               => $this->get_type(),
					'dismissible'        => $this->is_dismissible(),
					'id'                 => $id,
					'additional_classes' => array( 'gatherpress-notice' ),
				)
			);

			return;
		}

		// wp_admin_notice() landed in WordPress 6.4 and always exists in the
		// test bootstrap, so this fallback cannot be exercised there. It stays
		// because these classes are reachable from the pre-requirements path,
		// where the running WordPress may predate it.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
		// @codeCoverageIgnoreStart
		printf(
			'<div id="%1$s" class="notice notice-%2$s%3$s gatherpress-notice"><p>%4$s</p></div>',
			esc_attr( $id ),
			esc_attr( $this->get_type() ),
			$this->is_dismissible() ? ' is-dismissible' : '',
			wp_kses_post( $message )
		);
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
		// @codeCoverageIgnoreEnd
	}
}
