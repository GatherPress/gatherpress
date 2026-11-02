<?php
/**
 * Decides which blocks an RSVP Form shows for the state it renders in.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Blocks\Rsvp_Form;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Visibility.
 *
 * Reads a block's gatherpressRsvpFormVisibility rule against the form's
 * state: just submitted, the event has passed, or the event is canceled.
 *
 * @since TBD
 */
final class Visibility {

	/**
	 * Class constructor.
	 *
	 * @since TBD
	 *
	 * @param bool $is_success  Whether the form was successfully submitted.
	 * @param bool $is_past     Whether the event has passed.
	 * @param bool $is_canceled Whether the event is canceled.
	 */
	public function __construct(
		private bool $is_success,
		private bool $is_past,
		private bool $is_canceled = false
	) {}

	/**
	 * Whether a block with this visibility rule is shown.
	 *
	 * @since TBD
	 *
	 * @param string $visibility_rule The visibility rule as a JSON object.
	 *
	 * @return bool|null True to show, false to hide, null for no change (always visible).
	 */
	public function should_show( string $visibility_rule ): ?bool {
		$visibility = json_decode( $visibility_rule, true );

		if ( ! is_array( $visibility ) ) {
			return null;
		}

		if ( $this->is_canceled ) {
			return $this->should_show_when_canceled( $visibility );
		}

		// A block meant only for a canceled event stays hidden otherwise.
		if ( 'show' === ( $visibility['whenCanceled'] ?? '' ) ) {
			return false;
		}

		$on_success = $visibility['onSuccess'] ?? '';
		$when_past  = $visibility['whenPast'] ?? '';

		$result = null;

		if ( $this->is_past && ! empty( $when_past ) ) {
			// When event is past, whenPast takes precedence.
			$result = 'show' === $when_past;
		} elseif ( ! $this->is_past && ! empty( $when_past ) && empty( $on_success ) ) {
			// Not past but block has ONLY whenPast: hide-if-set-to-show
			// (it shouldn't appear yet).
			$result = 'show' !== $when_past;
		} elseif ( ! empty( $on_success ) ) {
			// Success: show-if-set-to-show. Not success: hide-if-set-to-show.
			$result = $this->is_success ? ( 'show' === $on_success ) : ( 'show' !== $on_success );
		}

		return $result;
	}

	/**
	 * Whether a form's blocks include one shown only for a canceled event.
	 *
	 * @since TBD
	 *
	 * @param array<int, array<string, mixed>> $blocks The parsed inner blocks.
	 *
	 * @return bool True when a block has whenCanceled set to show.
	 */
	public static function has_canceled_notice( array $blocks ): bool {
		foreach ( $blocks as $block ) {
			if ( 'show' === ( $block['attrs']['metadata']['gatherpressRsvpFormVisibility']['whenCanceled'] ?? '' ) ) {
				return true;
			}

			if ( self::has_canceled_notice( (array) ( $block['innerBlocks'] ?? array() ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a block is shown while the event is canceled.
	 *
	 * The whenCanceled rule decides when it is set. Otherwise a block tied
	 * to the RSVP flow, through onSuccess or whenPast, is hidden, since a
	 * canceled event takes no RSVPs.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $visibility The decoded visibility rule.
	 *
	 * @return bool|null True to show, false to hide, null for no change (always visible).
	 */
	private function should_show_when_canceled( array $visibility ): ?bool {
		$when_canceled = $visibility['whenCanceled'] ?? '';

		if ( ! empty( $when_canceled ) ) {
			return 'show' === $when_canceled;
		}

		if ( ! empty( $visibility['onSuccess'] ) || ! empty( $visibility['whenPast'] ) ) {
			return false;
		}

		return null;
	}
}
