/**
 * Internal dependencies
 */
import DateTimeRange from '../../../components/DateTimeRange';
import ShowTimezone from '../../../components/ShowTimezone';

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
	return (
		<>
			<DateTimeRange />
			{ /* Belongs to the event rather than to a block, and the Event
			     Date block already carries its own Append time zone toggle. */ }
			<section>
				<ShowTimezone />
			</section>
		</>
	);
};

export default DateTimeRangePanel;
