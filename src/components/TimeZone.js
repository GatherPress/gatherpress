/**
 * WordPress dependencies.
 */
import { PanelRow, SelectControl } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../helpers/broadcasting';
import { enableSave, getFromGlobal, setToGlobal } from '../helpers/globals';
import {
	maybeConvertUtcOffsetForDatabase,
	maybeConvertUtcOffsetForSelect,
} from '../helpers/datetime';

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
	const { timezone, setTimezone } = props;
	const choices = getFromGlobal('timezone_choices');

	// Run only once.
	useEffect(() => {
		setTimezone(getFromGlobal('event_datetime.timezone'));
	}, [setTimezone]);

	useEffect(() => {
		Broadcaster({
			setTimezone: getFromGlobal('event_datetime.timezone'),
		});
	});

	return (
		<PanelRow>
			<SelectControl
				label={__('Time Zone', 'gatherpress')}
				value={maybeConvertUtcOffsetForSelect(timezone)}
				onChange={(value) => {
					value = maybeConvertUtcOffsetForDatabase(value);
					setTimezone(value);
					setToGlobal('event_datetime.timezone', value);
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
