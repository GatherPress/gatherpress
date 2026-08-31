<?php
/**
 * Base class for GatherPress admin notices.
 *
 * This file contains the Base class that every GatherPress admin notice
 * extends.
 *
 * IMPORTANT: this class, and any notice constructed by `requirements-check.php`
 * or `duplicate-check.php`, load before the requirements gate -- so they parse
 * on every site that has the plugin active, including one running the oldest
 * PHP the plugin supports. GatherPress's floor is PHP 7.4 (the 0.34.x line, via
 * which these classes ship in 0.34.1), so everything here must parse on 7.4. A
 * site that only needs the "please upgrade to PHP 8.1 for 0.35.0" notice would
 * otherwise get a fatal parse error instead -- taking down the whole site
 * rather than just the plugin, and turning the file whose job is explaining the
 * problem into a worse one.
 *
 * So in this class and in every blocking notice, stay within PHP 7.4. Return
 * types, scalar and nullable parameter types, `void`, typed properties and
 * arrow functions are all fine (all <= 7.4). What is *not* allowed is anything
 * 8.0+: union types, `mixed`, constructor property promotion, `readonly`,
 * `match`, the nullsafe operator `?->`, named arguments, and enums.
 *
 * `npm run lint:php:early` enforces this against PHP 7.4 over the early-loaded
 * files. It names them explicitly rather than globbing this directory, because
 * the notices that render *after* the gate (and the Setup registry alongside
 * them) are ordinary modern code. Adding a new blocking notice means adding it
 * to that list -- which is exactly the moment to be thinking about this
 * constraint.
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
	abstract public function get_slug(): string;

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
	abstract public function get_message(): string;

	/**
	 * The notice's type.
	 *
	 * @since 0.34.1
	 *
	 * @return string One of the TYPE_* constants.
	 */
	public function get_type(): string {
		return self::TYPE_INFO;
	}

	/**
	 * Whether the notice can be closed for the current page view.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when the notice renders a close button.
	 */
	public function is_dismissible(): bool {
		return true;
	}

	/**
	 * Whether dismissing the notice is remembered across page loads.
	 *
	 * @since 0.34.1
	 *
	 * @return bool True when dismissal persists.
	 */
	public function is_persistent(): bool {
		return false;
	}

	/**
	 * Capability required to see the notice.
	 *
	 * @since 0.34.1
	 *
	 * @return string A capability, or an empty string for no gate.
	 */
	public function get_capability(): string {
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
	public function applies(): bool {
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
	public function is_dismissed(): bool {
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
	public function dismiss(): bool {
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
	public function get_dismiss_url(): string {
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
	public function should_render(): bool {
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
	 * Options this notice owns, beyond the shared dismissal record.
	 *
	 * Uninstall reads this rather than naming options itself, so a notice
	 * that keeps its own state is cleaned up by declaring it here and
	 * nothing else has to change.
	 *
	 * @since 0.36.0
	 *
	 * @return string[] Option names.
	 */
	public function get_options(): array {
		return array();
	}

	/**
	 * The notice's headline, when it should render as a card.
	 *
	 * A notice with a headline renders as a card -- mark, headline, message
	 * and one call to action -- instead of the usual paragraph. Returning an
	 * empty string, which is the default, keeps the paragraph.
	 *
	 * @since 0.36.0
	 *
	 * @return string The headline, or an empty string for a plain notice.
	 */
	public function get_headline(): string {
		return '';
	}

	/**
	 * Where a card's call to action goes.
	 *
	 * @since 0.36.0
	 *
	 * @return string A URL, or an empty string for no call to action.
	 */
	public function get_action_url(): string {
		return '';
	}

	/**
	 * What a card's call to action reads.
	 *
	 * @since 0.36.0
	 *
	 * @return string The label, or an empty string for no call to action.
	 */
	public function get_action_label(): string {
		return '';
	}

	/**
	 * Render the notice.
	 *
	 * @since 0.34.1
	 *
	 * @return void
	 */
	public function render(): void {
		$message = $this->get_message();

		if ( '' === $message ) {
			return;
		}

		$is_card = '' !== $this->get_headline();

		if ( $is_card ) {
			$message = $this->build_card( $message );

			// wp_admin_notice() runs the message through wp_kses_post(), which
			// allows neither svg nor path. Permit them for the length of this
			// one call rather than hand-building the notice to get around it.
			add_filter( 'wp_kses_allowed_html', array( $this, 'allow_mark_markup' ), 10, 2 );
		} elseif ( $this->is_persistent() ) {
			$message .= sprintf(
				' <a href="%s">%s</a>',
				esc_url( $this->get_dismiss_url() ),
				esc_html__( 'Dismiss this notice.', 'gatherpress' )
			);
		}

		$this->emit( $message, ! $is_card );

		if ( $is_card ) {
			remove_filter( 'wp_kses_allowed_html', array( $this, 'allow_mark_markup' ) );
		}
	}

	/**
	 * Build the card markup for a notice that has a headline.
	 *
	 * Styles are inline because this is one notice on screens that otherwise
	 * have no reason to load a GatherPress stylesheet, and because inline
	 * beats core's `div.notice a`, which underlines every link in a notice.
	 *
	 * @since 0.36.0
	 *
	 * @param string $message The notice's message.
	 *
	 * @return string The card markup.
	 */
	protected function build_card( string $message ): string {
		$action  = '';
		$dismiss = '';

		if ( '' !== $this->get_action_url() && '' !== $this->get_action_label() ) {
			$action = sprintf(
				'<p style="margin:0;"><a href="%1$s" class="button button-primary">%2$s</a></p>',
				esc_url( $this->get_action_url() ),
				esc_html( $this->get_action_label() )
			);
		}

		if ( $this->is_persistent() ) {
			// Core's own class, so the control looks like every other notice's
			// dismiss. Core's script only binds to the button it injects into
			// an `is-dismissible` notice, so this link is left alone.
			$dismiss = sprintf(
				// The offsets cancel the notice's own 8px/12px padding so the
				// control sits where core's dismiss button does, and the 9px
				// is core's own padding, which `div.notice a` resets away.
				'<a href="%1$s" class="notice-dismiss"'
				. ' style="text-decoration:none;padding:0.5625rem;top:-0.5rem;right:-0.75rem;">'
				. '<span class="screen-reader-text">%2$s</span></a>',
				esc_url( $this->get_dismiss_url() ),
				esc_html__( 'Dismiss this notice.', 'gatherpress' )
			);
		}

		return sprintf(
			'<div style="display:flex;align-items:flex-start;gap:1.25rem;position:relative;">%1$s<div>'
			. '<p style="margin:0 0 0.375rem;font-size:0.875rem;font-weight:600;color:#1769AA;">%2$s</p>'
			. '<p style="margin:0 0 0.75rem;">%3$s</p>%4$s</div>%5$s</div>',
			$this->get_mark(),
			esc_html( $this->get_headline() ),
			esc_html( $message ),
			$action,
			$dismiss
		);
	}

	/**
	 * Permit the inline mark through wp_kses_post().
	 *
	 * Added immediately before the notice renders and removed immediately
	 * after, so the widened allowlist never applies to anything else. Scoped
	 * to the post context, which is the one wp_admin_notice() uses.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, mixed> $tags    Allowed HTML tags and their attributes.
	 * @param string               $context The context the allowlist is being used in.
	 *
	 * @return array<string, mixed> Allowed tags, with svg and path added in the post context.
	 */
	public function allow_mark_markup( array $tags, string $context ): array {
		if ( 'post' !== $context ) {
			return $tags;
		}

		$tags['svg']  = array(
			'xmlns'       => true,
			'viewbox'     => true,
			'width'       => true,
			'height'      => true,
			'aria-hidden' => true,
			'focusable'   => true,
			'style'       => true,
		);
		$tags['path'] = array(
			'fill' => true,
			'd'    => true,
		);

		return $tags;
	}

	/**
	 * The GatherPress mark, inlined as an SVG.
	 *
	 * Inlined rather than loaded from a file because the logo asset lives in
	 * `.wordpress-org/`, which `.distignore` keeps out of the distributed
	 * plugin, so there is nothing to link to at runtime.
	 *
	 * @since 0.36.0
	 *
	 * @return string Inline SVG markup.
	 */
	protected function get_mark(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="60" height="60"'
			. ' aria-hidden="true" focusable="false" style="flex-shrink:0;">'
			. '<path fill="#1769AA" d="'
			. 'M125.4,50.8V13.4c0-6.8-5.6-12.4-12.4-12.4H88.1c-6.8,0-12.4,5.6-'
			. '12.4,12.4v37.3c0,6.8,5.6,12.4,12.4,12.4h24.9C119.8,63.2,125.4,57.6,125.4,50.8zM100.5,13.4c6.8,0'
			. ',12.4,5.6,12.4,12.4s-5.6,12.4-12.4,12.4s-12.4-5.6-12.4-'
			. '12.4S93.7,13.4,100.5,13.4zM200,175.1V75.6c0-13.7-11.2-24.9-24.9-24.9h-37.3v4.1c0,11.4-9.3,20.8-'
			. '20.8,20.8H84c-11.4,0-20.8-9.3-20.8-20.8v-'
			. '4.1H25.9C12.2,50.8,1,61.9,1,75.6v99.5C1,188.8,12.2,200,25.9,200h149.2C188.8,200,200,188.8,200,1'
			. '75.1zM187.6,100.5v74.6H13.4v-74.6H187.6zM88.1,125.4c0-6.8-2.7-12.4-6.2-12.4s-6.2,5.6-'
			. '6.2,12.4c0,6.8,2.7,12.4,6.2,12.4S88.1,132.2,88.1,125.4zM125.4,125.4c0-6.8-2.7-12.4-6.2-12.4s-'
			. '6.2,5.6-'
			. '6.2,12.4c0,6.8,2.7,12.4,6.2,12.4S125.4,132.2,125.4,125.4zM51.2,140.4c11.4,6,29.1,9.8,49.3,9.8s3'
			. '7.8-3.9,49.3-9.8c-2.6,12.4-23.5,22.3-49.3,22.3S53.9,152.9,51.2,140.4z'
			. '"/></svg>';
	}

	/**
	 * Emit the notice.
	 *
	 * @since 0.36.0
	 *
	 * @param string $message        The notice's message.
	 * @param bool   $paragraph_wrap Whether to wrap the message in a paragraph.
	 *
	 * @return void
	 */
	protected function emit( string $message, bool $paragraph_wrap ): void {
		$id = str_replace( '_', '-', $this->get_slug() );

		if ( function_exists( 'wp_admin_notice' ) ) {
			wp_admin_notice(
				$message,
				array(
					'type'               => $this->get_type(),
					'dismissible'        => $this->is_dismissible(),
					'id'                 => $id,
					'additional_classes' => array( 'gatherpress-notice' ),
					'paragraph_wrap'     => $paragraph_wrap,
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
			'<div id="%1$s" class="notice notice-%2$s%3$s gatherpress-notice">%4$s</div>',
			esc_attr( $id ),
			esc_attr( $this->get_type() ),
			$this->is_dismissible() ? ' is-dismissible' : '',
			wp_kses_post( $paragraph_wrap ? '<p>' . $message . '</p>' : $message )
		);
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar -- PHPUnit annotation must match exactly.
		// @codeCoverageIgnoreEnd
	}
}
