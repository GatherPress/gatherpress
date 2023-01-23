/**
 * WordPress dependencies.
 */
import { PanelRow, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/misc';

const TimeZonePanel = (props) => {
	const choices = getFromGlobal('timezone_choices');
	const { timezone, setTimezone } = props;

	return (
		<PanelRow>
			<SelectControl
				label={__('Time Zone')}
				value={timezone}
				onChange={(value) => {
					setTimezone(value);
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

export default TimeZonePanel;
