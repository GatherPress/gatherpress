/**
 * WordPress dependencies.
 */
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { dateTimeOffset, durationOptions } from '../helpers/datetime';

/**
 * Duration component for GatherPress.
 *
 * This component allows users to select the duration of an event from predefined options.
 * It uses the `SelectControl` component to display a dropdown menu with duration options,
 * such as 1 hour, 1.5 hours, etc., as well as an option to set a custom end time.
 * The selected duration is managed through the WordPress data store and updated accordingly.
 *
 * When a duration is selected, the component calculates the new end time based on the
 * duration and updates the event's end date and time. It also updates the duration value
 * in the state.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered Duration React component.
 */
const Duration = () => {
	const { duration } = useSelect(
		( select ) => ( {
			duration: select( 'gatherpress/datetime' ).getDuration(),
		} ),
		[],
	);
	const dispatch = useDispatch();
	const { setDateTimeEnd, setDuration } = dispatch( 'gatherpress/datetime' );
	return (
		<SelectControl
			label={ __( 'Duration', 'gatherpress' ) }
			value={
				durationOptions().some( ( option ) => option.value === duration )
					? duration
					: false
			}
			options={ durationOptions() }
			onChange={ ( value ) => {
				value = 'false' === value ? false : parseFloat( value );

				if ( value ) {
					setDateTimeEnd( dateTimeOffset( value ) );
				}

				setDuration( value );
			} }
			__nexthasnomarginbottom
		/>
	);
};

export default Duration;
