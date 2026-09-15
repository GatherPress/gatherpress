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
 * Frequency and interval controls for the recurrence panel.
 *
 * Renders the frequency select (Daily/Weekly/Monthly/Yearly) and the "repeat
 * every" interval number input. Clamping the interval to `Rule::MAX_INTERVAL`
 * (52) is the caller's responsibility, since the clamp also has to apply when
 * the value round-trips through the recurrence blob.
 *
 * @since 0.36.0
 *
 * @param {Object}   props                   Component props.
 * @param {string}   props.frequency         Current frequency value, one of `daily`, `weekly`, `monthly`, `yearly`.
 * @param {number}   props.interval          Current interval value.
 * @param {Function} props.onFrequencyChange Called with the new frequency string.
 * @param {Function} props.onIntervalChange  Called with the new interval number.
 *
 * @return {JSX.Element} The frequency and interval controls.
 */
const FrequencyControl = ( {
	frequency,
	interval,
	onFrequencyChange,
	onIntervalChange,
} ) => {
	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				label={ __( 'Frequency', 'gatherpress' ) }
				value={ frequency }
				options={ [
					{ label: __( 'Daily', 'gatherpress' ), value: 'daily' },
					{ label: __( 'Weekly', 'gatherpress' ), value: 'weekly' },
					{ label: __( 'Monthly', 'gatherpress' ), value: 'monthly' },
					{ label: __( 'Yearly', 'gatherpress' ), value: 'yearly' },
				] }
				onChange={ onFrequencyChange }
			/>
			<NumberControl
				label={ __( 'Repeat every', 'gatherpress' ) }
				value={ interval }
				min={ 1 }
				max={ 52 }
				onChange={ ( value ) => onIntervalChange( Number( value ) ) }
			/>
		</>
	);
};

export default FrequencyControl;
