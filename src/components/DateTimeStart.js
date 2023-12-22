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
			<Flex>
				<FlexItem>{__('Start', 'gatherpress')}</FlexItem>
				<FlexItem>
					<Dropdown
						popoverProps={{ placement: 'bottom-end' }}
						renderToggle={({ isOpen, onToggle }) => (
							<Button
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
