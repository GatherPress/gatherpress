/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Dropdown,
	Flex,
	FlexItem,
	PanelRow,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { subscribe } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import {
	DateTimeStartLabel,
	DateTimeEndLabel,
	DateTimeStartPicker,
	DateTimeEndPicker,
} from '../../../components/DateTime';
import {
	dateTimeMomentFormat,
	getDateTimeStart,
	getDateTimeEnd,
	saveDateTime,
	timeZone,
} from '../../../helpers/datetime';
import { hasEventPastNotice } from '../../../helpers/event';
import { Broadcaster } from '../../../helpers/broadcasting';

hasEventPastNotice();
subscribe(saveDateTime);

const DateTimePanel = () => {
	const [dateTimeStart, setDateTimeStart] = useState();
	const [dateTimeEnd, setDateTimeEnd] = useState();

	useEffect(() => {
		setDateTimeStart(
			moment.tz(getDateTimeStart(), timeZone).format(dateTimeMomentFormat)
		);
		setDateTimeEnd(
			moment.tz(getDateTimeEnd(), timeZone).format(dateTimeMomentFormat)
		);

		hasEventPastNotice();

		Broadcaster({
			setDateTimeStart: dateTimeStart,
			setDateTimeEnd: dateTimeEnd,
		});
	});

	return (
		<section>
			<h3>{__('Date & time', 'gatherpress')}</h3>
			<PanelRow>
				<Flex>
					<FlexItem>{__('Start', 'gatherpress')}</FlexItem>
					<FlexItem>
						<Dropdown
							position="bottom left"
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
			<PanelRow>
				<Flex>
					<FlexItem>{__('End', 'gatherpress')}</FlexItem>
					<FlexItem>
						<Dropdown
							position="bottom left"
							renderToggle={({ isOpen, onToggle }) => (
								<Button
									onClick={onToggle}
									aria-expanded={isOpen}
									isLink
								>
									<DateTimeEndLabel
										dateTimeEnd={dateTimeEnd}
									/>
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
		</section>
	);
};

export default DateTimePanel;
