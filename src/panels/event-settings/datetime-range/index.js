/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __experimentalText as Text } from '@wordpress/components';

/**
 * Internal dependencies
 */
import DateTimeRange from '../../../components/DateTimeRange';
import ShowTimezone from '../../../components/ShowTimezone';
import EventStatus from '../../../components/EventStatus';
import { FROZEN_NOTE_ID, useIsStartedSeries } from '../../../helpers/repeat';

/**
 * A panel component for managing date and time ranges.
 *
 * This component serves as a panel containing the `DateTimeRange` component
 * for managing date and time ranges in a specific context.
 *
 * @since 0.27.0
 *
 * @return {JSX.Element} The JSX element for the DateTimeRangePanel.
 */
const DateTimeRangePanel = () => {
	const frozen = useIsStartedSeries();

	return (
		<>
			<DateTimeRange />
			{ frozen && (
				<Text id={ FROZEN_NOTE_ID } variant="muted">
					{ __(
						'The first date has passed, so it can\'t change. Change the schedule under Repeat schedule.',
						'gatherpress',
					) }
				</Text>
			) }
			{ /* Belongs to the event rather than to a block, and the Event
			     Date block already carries its own Append time zone toggle. */ }
			<section>
				<ShowTimezone />
			</section>
			<section>
				<EventStatus />
			</section>
		</>
	);
};

export default DateTimeRangePanel;
