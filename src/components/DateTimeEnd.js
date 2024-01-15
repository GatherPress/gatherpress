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
import { DateTimeEndLabel, DateTimeEndPicker } from './DateTime';
import { hasEventPastNotice } from '../helpers/event';
import { Broadcaster } from '../helpers/broadcasting';
import {
	dateTimeMomentFormat,
	getDateTimeEnd,
	getTimeZone,
} from '../helpers/datetime';

/**
 * DateTimeEnd component for GatherPress.
 *
 * This component renders the end date and time selection in the editor.
 * It includes a DateTimeEndPicker for selecting the end date and time.
 * The component also updates the state using the setDateTimeEnd callback.
 * Additionally, it broadcasts the end date and time using the Broadcaster utility.
 * If the event has passed, it displays a notice using hasEventPastNotice function.
 *
 * @since 1.0.0
 *
 * @param {Object}   props                - Component props.
 * @param {Date}     props.dateTimeEnd    - The current date and time for the picker.
 * @param {Function} props.setDateTimeEnd - Callback function to update the end date and time.
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeEnd = (props) => {
	const { dateTimeEnd, setDateTimeEnd } = props;

	useEffect(() => {
		setDateTimeEnd(
			moment
				.tz(getDateTimeEnd(), getTimeZone())
				.format(dateTimeMomentFormat)
		);

		Broadcaster({
			setDateTimeEnd: dateTimeEnd,
		});

		hasEventPastNotice();
	});

	return (
		<PanelRow>
			<Flex direction="column" gap="0">
				<FlexItem>
					<label htmlFor="gp-datetime-end">
						{__('End', 'gatherpress')}
					</label>
				</FlexItem>
				<FlexItem>
					<Dropdown
						popoverProps={{ placement: 'bottom-end' }}
						renderToggle={({ isOpen, onToggle }) => (
							<Button
								id="gp-datetime-end"
								onClick={onToggle}
								aria-expanded={isOpen}
								isLink
							>
								<DateTimeEndLabel dateTimeEnd={dateTimeEnd} />
							</Button>
						)}
						renderContent={() => (
							<DateTimeEndPicker
								dateTimeEnd={dateTimeEnd}
								setDateTimeEnd={setDateTimeEnd}
							/>
						)}
					/>
				</FlexItem>
			</Flex>
		</PanelRow>
	);
};

export default DateTimeEnd;
