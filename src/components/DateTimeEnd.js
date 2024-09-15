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
import { DateTimeEndLabel, DateTimeEndPicker } from './DateTime';
import { hasEventPastNotice } from '../helpers/event';
import {
	dateTimeDatabaseFormat,
	getDateTimeEnd,
	getTimezone,
} from '../helpers/datetime';

/**
 * DateTimeEnd component for GatherPress.
 *
 * This component renders the end date and time selection in the editor.
 * It includes a DateTimeEndPicker for selecting the end date and time.
 * The component also updates the state using the setDateTimeEnd callback.
 * If the event has passed, it displays a notice using the hasEventPastNotice function.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeEnd = () => {
	const { dateTimeEnd } = useSelect(
		(select) => ({
			dateTimeEnd: select('gatherpress/datetime').getDateTimeEnd(),
		}),
		[]
	);
	const { setDateTimeEnd } = useDispatch('gatherpress/datetime');

	useEffect(() => {
		setDateTimeEnd(
			moment
				.tz(getDateTimeEnd(), getTimezone())
				.format(dateTimeDatabaseFormat)
		);

		hasEventPastNotice();
	});

	return (
		<PanelRow>
			<Flex direction="column" gap="1">
				<FlexItem>
					<h3 style={{ marginBottom: 0 }}>
						<label htmlFor="gatherpress-datetime-end">
							{__('Date & time end', 'gatherpress')}
						</label>
					</h3>
				</FlexItem>
				<FlexItem>
					<Dropdown
						popoverProps={{ placement: 'bottom-end' }}
						renderToggle={({ isOpen, onToggle }) => (
							<Button
								id="gatherpress-datetime-end"
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
