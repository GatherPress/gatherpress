/**
 * Internal dependencies.
 */
import DateTimeRange from '../../../components/DateTimeRange';

/**
 * A panel component for managing date and time ranges.
 *
 * This component serves as a panel containing the `DateTimeRange` component
 * for managing date and time ranges in a specific context.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The JSX element for the DateTimeRangePanel.
 */
const DateTimeRangePanel = () => {
	return (
		<section>
			<DateTimeRange />
		</section>
	);
};

export default DateTimeRangePanel;
