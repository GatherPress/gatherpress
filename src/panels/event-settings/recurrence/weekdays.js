/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';

/**
 * Weekday multi-select for a weekly recurrence rule.
 *
 * Renders one checkbox per day, Sunday (0) through Saturday (6), matching the
 * weekday numbering `Rule::from_array()` expects. Only rendered by the parent
 * panel when `frequency` is `weekly`.
 *
 * @since 0.36.0
 *
 * @param {Object}   props          Component props.
 * @param {number[]} props.weekdays Currently selected weekday numbers, 0 through 6.
 * @param {Function} props.onChange Called with the next weekday-number array.
 *
 * @return {JSX.Element} The weekday checkbox group.
 */
const WeekdaysControl = ( { weekdays, onChange } ) => {
	const labels = [
		__( 'Sunday', 'gatherpress' ),
		__( 'Monday', 'gatherpress' ),
		__( 'Tuesday', 'gatherpress' ),
		__( 'Wednesday', 'gatherpress' ),
		__( 'Thursday', 'gatherpress' ),
		__( 'Friday', 'gatherpress' ),
		__( 'Saturday', 'gatherpress' ),
	];

	const toggleWeekday = ( day, checked ) => {
		const next = checked
			? [ ...weekdays, day ]
			: weekdays.filter( ( value ) => day !== value );

		onChange( next );
	};

	return (
		<div className="gatherpress-recurrence-weekdays">
			{ labels.map( ( label, day ) => (
				<CheckboxControl
					key={ day }
					label={ label }
					checked={ weekdays.includes( day ) }
					onChange={ ( checked ) => toggleWeekday( day, checked ) }
				/>
			) ) }
		</div>
	);
};

export default WeekdaysControl;
