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
			<Flex>
				<FlexItem>{__('End', 'gatherpress')}</FlexItem>
				<FlexItem>
					<Dropdown
						popoverProps={{ placement: 'bottom-end' }}
						renderToggle={({ isOpen, onToggle }) => (
							<Button
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
