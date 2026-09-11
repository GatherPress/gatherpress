/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

/**
 * Monthly recurrence controls.
 *
 * Switches between the two `Rule::MONTHLY_MODE_*` shapes: a fixed day of the
 * month (e.g. the 15th), or an ordinal weekday (e.g. the last Wednesday).
 * Only the fields for the active mode are rendered, and each `onChange` call
 * carries exactly one field. The inactive mode's fields keep their defaults
 * because the panel merges every single-field partial onto a rule that
 * already holds all four `monthly_*` values.
 *
 * The day-of-month value is handed to `onChange` exactly as the control
 * produced it, without a `Number()` cast: a native number input accepts typed
 * and pasted values its `min`/`max` never constrain, and `Number( '31abc' )`
 * is `NaN` while `Number( '' )` is `0`, and both would silently become
 * a rule the server rejects. Normalization is the panel's job
 * (`normalizeMonthlyDay()`), which is also where the reject-versus-clamp
 * decision belongs.
 *
 * @since 0.36.0
 *
 * @param {Object}      props                Component props.
 * @param {string}      props.monthlyMode    One of `day_of_month` or `nth_weekday`.
 * @param {number|null} props.monthlyDay     Day of the month, 1 through 31, or null
 *                                           when the field has been cleared.
 * @param {number}      props.monthlyOrdinal Ordinal, 1 through 4, or -1 for "last".
 * @param {number}      props.monthlyWeekday Weekday number, 0 through 6.
 * @param {Function}    props.onChange       Called with a partial `monthly_*` field update.
 *
 * @return {JSX.Element} The monthly recurrence controls.
 */
const MonthlyControl = ( {
	monthlyMode,
	monthlyDay,
	monthlyOrdinal,
	monthlyWeekday,
	onChange,
} ) => {
	const ordinalOptions = [
		{ label: __( 'First', 'gatherpress' ), value: '1' },
		{ label: __( 'Second', 'gatherpress' ), value: '2' },
		{ label: __( 'Third', 'gatherpress' ), value: '3' },
		{ label: __( 'Fourth', 'gatherpress' ), value: '4' },
		{ label: __( 'Last', 'gatherpress' ), value: '-1' },
	];

	const weekdayOptions = [
		{ label: __( 'Sunday', 'gatherpress' ), value: '0' },
		{ label: __( 'Monday', 'gatherpress' ), value: '1' },
		{ label: __( 'Tuesday', 'gatherpress' ), value: '2' },
		{ label: __( 'Wednesday', 'gatherpress' ), value: '3' },
		{ label: __( 'Thursday', 'gatherpress' ), value: '4' },
		{ label: __( 'Friday', 'gatherpress' ), value: '5' },
		{ label: __( 'Saturday', 'gatherpress' ), value: '6' },
	];

	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				label={ __( 'Repeat by', 'gatherpress' ) }
				value={ monthlyMode }
				options={ [
					{
						label: __( 'Day of the month', 'gatherpress' ),
						value: 'day_of_month',
					},
					{
						label: __( 'Day of the week', 'gatherpress' ),
						value: 'nth_weekday',
					},
				] }
				onChange={ ( value ) =>
					onChange( { monthly_mode: value } )
				}
			/>
			{ 'day_of_month' === monthlyMode ? (
				<NumberControl
					label={ __( 'Day of the month', 'gatherpress' ) }
					value={ null === monthlyDay ? '' : monthlyDay }
					min={ 1 }
					max={ 31 }
					onChange={ ( value ) =>
						onChange( { monthly_day: value } )
					}
				/>
			) : (
				<>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Week', 'gatherpress' ) }
						value={ String( monthlyOrdinal ) }
						options={ ordinalOptions }
						onChange={ ( value ) =>
							onChange( { monthly_ordinal: Number( value ) } )
						}
					/>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Day', 'gatherpress' ) }
						value={ String( monthlyWeekday ) }
						options={ weekdayOptions }
						onChange={ ( value ) =>
							onChange( { monthly_weekday: Number( value ) } )
						}
					/>
				</>
			) }
		</>
	);
};

export default MonthlyControl;
