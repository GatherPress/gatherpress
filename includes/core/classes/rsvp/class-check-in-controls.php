<?php
/**
 * The check-in controls of the RSVP screen.
 *
 * @package GatherPress\Core
 * @since TBD
 */

namespace GatherPress\Core\Rsvp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Rsvp\Flag\Base as Flag;
use GatherPress\Core\Rsvp\Flag\Check_In_Registry;

/**
 * Class Check_In_Controls.
 *
 * What the RSVP screen shows and does for check-in: the Check-in cell, the
 * check-in filter and its query, and the check-in bulk actions. `List_Table`
 * calls these; the states come from `Check_In_Registry`.
 *
 * @since TBD
 */
final class Check_In_Controls {

	/**
	 * Prefix of the action that sets a check-in state, followed by the state's slug.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const ACTION_PREFIX = 'check_in-';

	/**
	 * Action that removes an RSVP's check-in state.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	public const CLEAR_ACTION = 'clear_check_in';

	/**
	 * The check-in state the current request is filtered to, if any.
	 *
	 * Unknown values are dropped so a hand-edited URL cannot query arbitrary terms.
	 *
	 * @since TBD
	 *
	 * @return string A registered state's slug, `not-recorded`, or an empty string when unfiltered.
	 */
	public static function get_filtered(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_REQUEST['check_in'] ) || ! is_string( $_REQUEST['check_in'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state   = sanitize_key( wp_unslash( $_REQUEST['check_in'] ) );
		$allowed = wp_list_pluck( Check_In_Registry::get_instance()->get_options(), 'value' );

		return in_array( $state, $allowed, true ) ? $state : '';
	}


	/**
	 * Adds the check-in filter to a comment query, when one is requested.
	 *
	 * Not recorded means none of the registered check-in states. The clause joins
	 * any response filter already on the query, and both must match.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $args The comment query arguments.
	 *
	 * @return array<string, mixed> The arguments, filtered when a check-in state was requested.
	 */
	public static function filter_query( array $args ): array {
		$state = self::get_filtered();

		if ( '' === $state ) {
			return $args;
		}

		$clause = array(
			'taxonomy' => Flag::TAXONOMY,
			'field'    => 'slug',
			'terms'    => $state,
		);

		if ( Check_In_Registry::NOT_RECORDED === $state ) {
			$clause['terms']    = array_keys( Check_In_Registry::get_instance()->get_all() );
			$clause['operator'] = 'NOT IN';
		}

		$tax_query   = is_array( $args['tax_query'] ?? null ) ? $args['tax_query'] : array();
		$tax_query[] = $clause;

		$tax_query['relation'] = 'AND';
		$args['tax_query']     = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query

		return $args;
	}


	/**
	 * The check-in field, before script takes it over.
	 *
	 * @since TBD
	 *
	 * @param string $selected The state the request is filtered to, or an empty string.
	 *
	 * @return string The field markup.
	 */
	public static function render_filter( string $selected ): string {
		$options = sprintf( '<option value="">%s</option>', esc_html__( 'All check-ins', 'gatherpress' ) );

		foreach ( Check_In_Registry::get_instance()->get_options() as $option ) {
			$options .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $option['value'] ),
				selected( $selected, $option['value'], false ),
				esc_html( $option['label'] )
			);
		}

		return sprintf(
			'<div class="gatherpress-rsvp-filters__check-in">' .
			'<label class="screen-reader-text" for="gatherpress-rsvp-check-in">%1$s</label>' .
			'<select id="gatherpress-rsvp-check-in">%2$s</select></div>',
			esc_html__( 'Filter by check-in', 'gatherpress' ),
			$options
		);
	}


	/**
	 * The check-in cell of an RSVP: the icon and name of its state.
	 *
	 * For an approved RSVP, script turns the cell into a dropdown that sets the
	 * state in place. Another RSVP shows its state read-only, or a dash without one.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $item The RSVP comment data.
	 *
	 * @return string The cell markup.
	 */
	public static function render_cell( array $item ): string {
		$registry    = Check_In_Registry::get_instance();
		$state       = $registry->get_state( (int) $item['comment_ID'] );
		$is_approved = '1' === $item['comment_approved'];

		if ( null === $state && ! $is_approved ) {
			return '-';
		}

		$option = $registry->describe( $state );

		return sprintf(
			'<div class="gatherpress-rsvp-check-in"%1$s>%2$s<span>%3$s</span></div>',
			$is_approved
				? sprintf(
					' data-rsvp-id="%1$d" data-state="%2$s"',
					absint( $item['comment_ID'] ),
					esc_attr( $option['value'] )
				)
				: '',
			$option['icon'],
			esc_html( $option['label'] )
		);
	}


	/**
	 * The check-in actions: one for each registered state, then Clear check-in.
	 *
	 * @since TBD
	 *
	 * @return array<string, string> Action labels, keyed by action.
	 */
	public static function get_actions(): array {
		$actions = array();

		foreach ( Check_In_Registry::get_instance()->get_all() as $slug => $state_class ) {
			$actions[ self::ACTION_PREFIX . $slug ] = ( new $state_class( 0 ) )->action_label();
		}

		$actions[ self::CLEAR_ACTION ] = __( 'Clear check-in', 'gatherpress' );

		return $actions;
	}


	/**
	 * Sets the check-in state of an RSVP from a check-in action.
	 *
	 * @since TBD
	 *
	 * @param int    $rsvp_id The RSVP comment ID.
	 * @param string $action  A key of `get_actions()`.
	 *
	 * @return void
	 */
	public static function apply_action( int $rsvp_id, string $action ): void {
		$state = self::CLEAR_ACTION === $action
			? Check_In_Registry::NOT_RECORDED
			: substr( $action, strlen( self::ACTION_PREFIX ) );

		Check_In_Registry::get_instance()->set_state( $rsvp_id, $state );
	}
}
