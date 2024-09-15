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
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { DateTimeStartLabel, DateTimeStartPicker } from './DateTime';
import { hasEventPastNotice } from '../helpers/event';
import {
	dateTimeDatabaseFormat,
	dateTimeOffset,
	getDateTimeStart,
	getTimezone,
} from '../helpers/datetime';

/**
 * DateTimeStart component for GatherPress.
 *
 * This component manages the selection of the start date and time. It uses
 * DateTimeStartPicker for the user to pick the date and time. The selected
 * values are formatted and saved. The component subscribes to the saveDateTime
 * function and triggers the hasEventPastNotice function to handle any event past notices.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeStart = () => {
	const { dateTimeStart, duration } = useSelect(
		(select) => ({
			dateTimeStart: select('gatherpress/datetime').getDateTimeStart(),
			duration: select('gatherpress/datetime').getDuration(),
		}),
		[]
	);
	const { setDateTimeStart, setDateTimeEnd } = useDispatch(
		'gatherpress/datetime'
	);

	useEffect(() => {
		setDateTimeStart(
			moment
				.tz(getDateTimeStart(), getTimezone())
				.format(dateTimeDatabaseFormat)
		);

		if (duration) {
			setDateTimeEnd(dateTimeOffset(duration));
		}

		hasEventPastNotice();
	}, [dateTimeStart, duration, setDateTimeStart, setDateTimeEnd]);

	return (
		<PanelRow>
			<Flex direction="column" gap="1">
				<FlexItem>
					<h3 style={{ marginBottom: 0 }}>
						<label htmlFor="gatherpress-datetime-start">
							{__('Date & time start', 'gatherpress')}
						</label>
					</h3>
				</FlexItem>
				<FlexItem>
					<Dropdown
						popoverProps={{ placement: 'bottom-end' }}
						renderToggle={({ isOpen, onToggle }) => (
							<Button
								id="gatherpress-datetime-start"
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
