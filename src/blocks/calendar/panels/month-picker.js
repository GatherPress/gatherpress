import { __ } from '@wordpress/i18n';
/* eslint-disable @wordpress/no-unsafe-wp-apis */
import {
	Flex,
	FlexBlock,
	SelectControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */
import { useEffect, useMemo, useState } from '@wordpress/element';

import { generateMonthOptions } from '../utils/calendar-utils';
import { calculateTargetDate } from '../utils/date-utils';

/**
 * MonthPicker Component
 *
 * Renders a month select and a year input. Both controls stay in place
 * when the value changes, so keyboard focus is not lost.
 *
 * @since TBD
 *
 * @param {Object}   props               - Component props.
 * @param {string}   props.selectedMonth - Currently selected month ("YYYY-MM"), or '' for the current month.
 * @param {number}   props.dateModifier  - Month offset, applied to the shown month.
 * @param {Function} props.onChange      - Callback with the new month ("YYYY-MM").
 *
 * @return {Element} Month picker component.
 */
export function MonthPicker( { selectedMonth, dateModifier = 0, onChange } ) {
	const monthOptions = useMemo( () => generateMonthOptions(), [] );

	// The month the calendar shows, also when it follows the current month.
	const shownDate = calculateTargetDate( {
		viewType: 'month',
		selectedDate: selectedMonth,
		dateModifier,
	} );
	const year = shownDate.getFullYear();
	const month = String( shownDate.getMonth() + 1 ).padStart( 2, '0' );

	// The typed text, which can be an incomplete year while the user types.
	const [ yearDraft, setYearDraft ] = useState( String( year ) );
	useEffect( () => setYearDraft( String( year ) ), [ year ] );

	const handleYearChange = ( value ) => {
		setYearDraft( value ?? '' );
		// Save only a complete year from 1000 to 9999. JavaScript reads
		// years 0-99 as 1900-1999, but PHP does not.
		if ( /^[1-9]\d{3}$/.test( String( value ) ) ) {
			onChange( `${ value }-${ month }` );
		}
	};

	return (
		<Flex align="flex-start" style={ { marginBottom: '8px' } }>
			<FlexBlock>
				<SelectControl
					label={ __( 'Month', 'gatherpress' ) }
					value={ month }
					options={ monthOptions }
					onChange={ ( value ) => onChange( `${ year }-${ value }` ) }
					__next40pxDefaultSize
				/>
			</FlexBlock>
			<FlexBlock>
				<NumberControl
					label={ __( 'Year', 'gatherpress' ) }
					value={ yearDraft }
					onChange={ handleYearChange }
					// An incomplete year is not saved: show the saved year again.
					onBlur={ () => setYearDraft( String( year ) ) }
					step={ 1 }
					__next40pxDefaultSize
				/>
			</FlexBlock>
		</Flex>
	);
}
