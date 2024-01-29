/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import {
	Button,
	Dropdown,
	Flex,
	FlexItem,
	PanelRow,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { DateTimeStartLabel, DateTimeStartPicker } from './DateTime';
import { hasEventPastNotice } from '../helpers/event';
import { Broadcaster } from '../helpers/broadcasting';
import {
	dateTimeMomentFormat,
	getDateTimeStart,
	getTimeZone,
} from '../helpers/datetime';

/**
 * DateTimeStart component for GatherPress.
 *
 * This component manages the selection of the start date and time. It uses
 * DateTimeStartPicker for the user to pick the date and time. The selected
 * values are formatted and broadcasted using Broadcaster. The component
 * subscribes to the saveDateTime function and triggers the hasEventPastNotice
 * function to handle any event past notices.
 *
 * @since 1.0.0
 *
 * @param {Object}   props                  - Component properties.
 * @param {string}   props.dateTimeStart    - The current start date and time.
 * @param {Function} props.setDateTimeStart - Function to set the start date and time.
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeStart = (props) => {
	const { dateTimeStart, setDateTimeStart } = props;

	useEffect(() => {
		setDateTimeStart(
			moment
				.tz(getDateTimeStart(), getTimeZone())
				.format(dateTimeMomentFormat)
		);

		Broadcaster({
			setDateTimeStart: dateTimeStart,
		});

		hasEventPastNotice();
	});

	return (
		<PanelRow>
			<Flex direction="column" gap="0">
				<FlexItem>
					<label htmlFor="gp-datetime-start">
						{__('Start', 'gatherpress')}
					</label>
				</FlexItem>
				<FlexItem>
					<Dropdown
						popoverProps={{ placement: 'bottom-end' }}
						renderToggle={({ isOpen, onToggle }) => (
							<Button
								id="gp-datetime-start"
								onClick={onToggle}
								aria-expanded={isOpen}
								isLink
							>
								<DateTimeStartLabel
									dateTimeStart={dateTimeStart}
								/>
							</Button>
						)}
						renderContent={() => (
							<DateTimeStartPicker
								dateTimeStart={dateTimeStart}
								setDateTimeStart={setDateTimeStart}
							/>
						)}
					/>
				</FlexItem>
			</Flex>
		</PanelRow>
	);
};

export default DateTimeStart;
