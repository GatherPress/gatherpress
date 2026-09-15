/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';

/**
 * End-of-series controls for the recurrence panel.
 *
 * Renders the Never / On date / After N times choice, plus whichever
 * companion field the choice needs. `until` and `count` are mutually
 * exclusive on the server (`Rule::is_valid_end_shape()`); this component
 * only ever renders one of the two companion fields at a time, and the
 * caller (the recurrence panel) is responsible for clearing the field that
 * does not belong to the selected `endType` when it changes.
 *
 * @since 0.36.0
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.endType  One of `never`, `until`, `count`.
 * @param {string}   props.until    End date, `Y-m-d`, when `endType` is `until`.
 * @param {number}   props.count    Occurrence count, when `endType` is `count`.
 * @param {Function} props.onChange Called with a partial end-condition field update.
 *
 * @return {JSX.Element} The end-condition controls.
 */
const EndConditionControl = ( { endType, until, count, onChange } ) => {
	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				label={ __( 'Ends', 'gatherpress' ) }
				value={ endType }
				options={ [
					{ label: __( 'Never', 'gatherpress' ), value: 'never' },
					{ label: __( 'On date', 'gatherpress' ), value: 'until' },
					{ label: __( 'After', 'gatherpress' ), value: 'count' },
				] }
				onChange={ ( value ) => onChange( { end_type: value } ) }
			/>
			{ 'until' === endType && (
				<TextControl
					type="date"
					label={ __( 'End date', 'gatherpress' ) }
					value={ until }
					onChange={ ( value ) => onChange( { until: value } ) }
				/>
			) }
			{ 'count' === endType && (
				<NumberControl
					label={ __( 'Number of occurrences', 'gatherpress' ) }
					value={ count }
					min={ 1 }
					max={ 730 }
					onChange={ ( value ) =>
						onChange( { count: Number( value ) } )
					}
				/>
			) }
		</>
	);
};

export default EndConditionControl;
