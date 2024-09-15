/**
 * WordPress dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { dateTimeOffset, durationOptions } from '../helpers/datetime';

/**
 * Internal dependencies.
 */

/**
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const Duration = () => {
	const { duration } = useSelect(
		(select) => ({
			duration: select('gatherpress/datetime').getDuration(),
		}),
		[]
	);
	const dispatch = useDispatch();
	const { setDateTimeEnd, setDuration } = dispatch('gatherpress/datetime');
	return (
		<SelectControl
			label={__('Duration', 'gatherpress')}
			value={
				durationOptions.some((option) => option.value === duration)
					? duration
					: false
			}
			options={durationOptions}
			onChange={(value) => {
				value = 'false' === value ? false : parseFloat(value);

				if (value) {
					setDateTimeEnd(dateTimeOffset(value));
				}

				setDuration(value);
			}}
			__nexthasnomarginbottom
		/>
	);
};

export default Duration;
