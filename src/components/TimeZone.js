/**
 * WordPress dependencies.
 */
import { PanelRow, SelectControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../helpers/broadcasting';
import { enableSave, getFromGlobal, setToGlobal } from '../helpers/globals';
import {
	maybeConvertUtcOffsetForDatabase,
	maybeConvertUtcOffsetForSelect,
} from '../helpers/datetime';
import '../stores/datetime';

/**
 * TimeZone component for GatherPress.
 *
 * This component allows users to select their preferred time zone from a list of choices.
 * It includes a SelectControl with options grouped by regions. The selected time zone is
 * stored in the state and broadcasted using the Broadcaster utility.
 *
 * @since 1.0.0
 *
 * @param {Object}   props             - Component props.
 * @param {string}   props.timezone    - The current selected time zone.
 * @param {Function} props.setTimezone - Callback function to set the selected time zone.
 *
 * @return {JSX.Element} The rendered React component.
 */
const TimeZone = (props) => {
	const { timezone } = useSelect(
		(select) => ({
			timezone: select('gatherpress/datetime').getTimezone(),
		}),
		[]
	);
	const { setTimezone } = useDispatch('gatherpress/datetime');
	const choices = getFromGlobal('misc.timezoneChoices');

	// Run only once.
	useEffect(() => {
		setTimezone(getFromGlobal('eventDetails.dateTime.timezone'));
	}, [setTimezone]);

	return (
		<PanelRow>
			<SelectControl
				label={__('Time Zone', 'gatherpress')}
				value={maybeConvertUtcOffsetForSelect(timezone)}
				onChange={(value) => {
					value = maybeConvertUtcOffsetForDatabase(value);
					setTimezone(value);
					enableSave();
				}}
			>
				{Object.keys(choices).map((group) => {
					return (
						<optgroup key={group} label={group}>
							{Object.keys(choices[group]).map((item) => {
								return (
									<option key={item} value={item}>
										{choices[group][item]}
									</option>
								);
							})}
						</optgroup>
					);
				})}
			</SelectControl>
		</PanelRow>
	);
};

export default TimeZone;
