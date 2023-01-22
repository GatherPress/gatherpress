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

const DateTimeEndPanel = (props) => {
	const { dateTimeEnd, setDateTimeEnd } = props;

	useEffect(() => {
		hasEventPastNotice();

		Broadcaster({
			setDateTimeEnd: dateTimeEnd,
		});
	});

	return (
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

export default DateTimeEndPanel;
