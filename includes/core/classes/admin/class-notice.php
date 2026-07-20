<?php
/**
 * Base class for GatherPress admin notices.
 *
 * This file contains the Notice class, the shared shape for every admin notice
 * GatherPress renders.
 *
 * IMPORTANT: this file is loaded by `requirements-check.php`, which runs before
 * the requirements gate and therefore before we know anything about the site's
 * PHP version. It must PARSE on the oldest PHP that could reach it, or a site
 * below the floor gets a fatal parse error instead of the notice telling it to
 * upgrade -- a white screen for the whole site, not just the plugin.
 *
 * That means, in this file only:
 *
 *   - no return types or scalar parameter types (PHP 7.0)
 *   - no nullable types or `void` (PHP 7.1)
 *   - no typed properties or arrow functions (PHP 7.4)
 *   - no null coalescing (PHP 7.0), constructor promotion or `readonly` (8.1)
 *
 * Types live in the docblocks so static analysis still sees them, and the
 * `npm run lint:php:early` script enforces the constraint against PHP 7.2 --
 * WordPress 6.7's own floor, and therefore the oldest PHP that can run a
 * WordPress capable of running this plugin. The syntax here is deliberately
 * older still, which costs nothing and leaves margin.
 *
 * @package GatherPress\Core\Admin
 * @since 0.34.1
 */

namespace GatherPress\Core\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Notice.
 *
 * A single admin notice, described by data rather than by a bespoke callback.
 *
 * Deliberately not `final`, unlike most classes here: this is an extension
 * point, and the codebase's final-by-default policy explicitly carves out
 * classes designed to be extended.
 *
 * @since 0.34.1
 */
class Notice {

	/**
	 * Option storing the slugs of permanently dismissed notices.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	const OPTION_NAME = 'gatherpress_admin_notifications';

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
	 * @var string
	 */
	protected $slug;

	/**
	 * The notice message, or a callable returning it.
	 *
	 * A callable defers building the message until render time. That matters:
	 * notices are often registered during bootstrap, and calling the
	 * translation functions before `init` is what WordPress 6.7 flags as
	 * loading a textdomain too early.
	 *
	 * @since 0.34.1
	 * @var string|callable
	 */
	protected $message;

	/**
	 * One of the TYPE_* constants.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	protected $type;

	/**
	 * Whether the notice can be dismissed for the current page view.
	 *
	 * @since 0.34.1
	 * @var bool
	 */
	protected $dismissible;

	/**
	 * Whether dismissing the notice should persist across page loads.
	 *
	 * @since 0.34.1
	 * @var bool
	 */
	protected $persistent;

	/**
	 * Capability required to see the notice, or an empty string for no gate.
	 *
	 * @since 0.34.1
	 * @var string
	 */
	protected $capability;

	/**
	 * Callable returning whether the notice currently applies, or null.
	 *
	 * @since 0.34.1
	 * @var callable|null
	 */
	protected $condition;

	/**
	 * Class constructor.
	 *
	 * Takes an argument array rather than a long parameter list, which also
	 * keeps the signature stable as options are added.
	 *
	 * @since 0.34.1
	 *
	 * @param string $slug Unique slug identifying this notice.
	 * @param array  $args {
	 *     Optional. Notice options.
	 *
	 *     @type string|callable $message     Message, or a callable returning it. Default ''.
	 *     @type string          $type        One of the TYPE_* constants. Default 'info'.
	 *     @type bool            $dismissible Whether the notice can be dismissed. Default true.
	 *     @type bool            $persistent  Whether dismissal is remembered. Default false.
	 *     @type string          $capability  Capability required to see it. Default ''.
	 *     @type callable|null   $condition   Returns whether the notice applies. Default null.
	 * }
	 */
	public function __construct( $slug, $args = array() ) {
		$this->slug        = (string) $slug;
		$this->message     = isset( $args['message'] ) ? $args['message'] : '';
		$this->type        = isset( $args['type'] ) ? (string) $args['type'] : self::TYPE_INFO;
		$this->dismissible = isset( $args['dismissible'] ) ? (bool) $args['dismissible'] : true;
		$this->persistent  = isset( $args['persistent'] ) ? (bool) $args['persistent'] : false;
		$this->capability  = isset( $args['capability'] ) ? (string) $args['capability'] : '';
		$this->condition   = isset( $args['condition'] ) ? $args['condition'] : null;
	}

	/**
	 * Get the notice's slug.
	 *
	 * @since 0.34.1
	 *
	 * @return string The slug.
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get the notice's type.
	 *
	 * @since 0.34.1
	 *
	 * @return string One of the TYPE_* constants.
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Whether dismissal of this notice is remembered across page loads.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when dismissal persists.
	 */
	public function is_persistent() {
		return $this->persistent;
	}

	/**
	 * Get the notice's message.
	 *
	 * @since 0.34.1
	 *
	 * @return string The message, resolving a callable if one was given.
	 */
	public function get_message() {
		if ( is_callable( $this->message ) ) {
			return (string) call_user_func( $this->message );
		}

		return (string) $this->message;
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
		if ( ! $this->persistent ) {
			return false;
		}

		$dismissed = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $dismissed ) ) {
			return false;
		}

		return array_key_exists( $this->slug, $dismissed );
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
		if ( ! $this->persistent ) {
			return false;
		}

		$dismissed = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}

		$dismissed[ $this->slug ] = time();

		return (bool) update_option( self::OPTION_NAME, $dismissed );
	}

	/**
	 * Whether the notice should render right now.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when the capability, dismissal and condition all allow it.
	 */
	public function should_render() {
		if ( '' !== $this->capability && ! current_user_can( $this->capability ) ) {
			return false;
		}

		if ( $this->is_dismissed() ) {
			return false;
		}

		if ( is_callable( $this->condition ) && ! call_user_func( $this->condition ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the URL that dismisses this notice.
	 *
	 * @since 0.34.1
	 *
	 * @return string Nonced dismissal URL, or an empty string when not persistent.
	 */
	public function get_dismiss_url() {
		if ( ! $this->persistent ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg( 'gatherpress_dismiss_notice', $this->slug ),
			'gatherpress_dismiss_notice_' . $this->slug
		);
	}

	/**
	 * Render the notice.
	 *
	 * Falls back to markup when `wp_admin_notice()` is unavailable. That
	 * function arrived in WordPress 6.4, and this class is reachable from the
	 * pre-requirements path on sites running older WordPress, where calling it
	 * would fatal on an undefined function.
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

		if ( $this->persistent ) {
			$message .= sprintf(
				' <a href="%s">%s</a>',
				esc_url( $this->get_dismiss_url() ),
				esc_html__( 'Dismiss this notice.', 'gatherpress' )
			);
		}

		$args = array(
			'type'               => $this->type,
			'dismissible'        => $this->dismissible,
			'id'                 => str_replace( '_', '-', $this->slug ),
			'additional_classes' => array( 'gatherpress-notice' ),
		);

		if ( function_exists( 'wp_admin_notice' ) ) {
			wp_admin_notice( $message, $args );

			return;
		}

		// wp_admin_notice() landed in WordPress 6.4 and always exists in the
		// test bootstrap, so this fallback cannot be exercised there. It stays
		// because this class is reachable from the pre-requirements path, where
		// the running WordPress may predate it.
		// @codeCoverageIgnoreStart
		printf(
			'<div id="%1$s" class="notice notice-%2$s%3$s gatherpress-notice"><p>%4$s</p></div>',
			esc_attr( str_replace( '_', '-', $this->slug ) ),
			esc_attr( $this->type ),
			$this->dismissible ? ' is-dismissible' : '',
			wp_kses_post( $message )
		);
		// @codeCoverageIgnoreEnd
	}
}
