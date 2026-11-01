import { __, sprintf } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

/**
 * Hook to calculate date offset help text for month, week, or day view.
 *
 * @param {number} dateModifier Offset integer.
 * @param {string} viewType     View type: 'month' | 'week' | 'day'.
 *
 * @return {string} Localized help text.
 */
export function useDateOffsetHelp( dateModifier = 0, viewType = 'month' ) {
	return useMemo( () => {
		if ( 0 === dateModifier ) {
			if ( 'day' === viewType ) {
				return __( 'Showing today', 'gatherpress' );
			}
			if ( 'week' === viewType ) {
				return __( 'Showing current week', 'gatherpress' );
			}
			return __( 'Showing current month', 'gatherpress' );
		}

		const absVal = Math.abs( dateModifier );

		if ( 'day' === viewType ) {
			return 0 > dateModifier
				? sprintf(
					/* translators: %d: number of days ago */
					__( 'Showing %d day(s) ago', 'gatherpress' ),
					absVal,
				)
				: sprintf(
					/* translators: %d: number of days ahead */
					__( 'Showing %d day(s) ahead', 'gatherpress' ),
					dateModifier,
				);
		}

		if ( 'week' === viewType ) {
			return 0 > dateModifier
				? sprintf(
					/* translators: %d: number of weeks ago */
					__( 'Showing %d week(s) ago', 'gatherpress' ),
					absVal,
				)
				: sprintf(
					/* translators: %d: number of weeks ahead */
					__(
						'Showing %d week(s) ahead',
						'gatherpress',
					),
					dateModifier,
				);
		}

		return 0 > dateModifier
			? sprintf(
				/* translators: %d: number of months ago */
				__( 'Showing %d month(s) ago', 'gatherpress' ),
				absVal,
			)
			: sprintf(
				/* translators: %d: number of months ahead */
				__( 'Showing %d month(s) ahead', 'gatherpress' ),
				dateModifier,
			);
	}, [ dateModifier, viewType ] );
}
