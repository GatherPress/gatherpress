/**
 * WordPress dependencies.
 */
import {
	Button,
	DateTimePicker,
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
import { hasEventPastNotice } from '../helpers/event';
import {
	createMomentWithTimezone,
	dateTimeDatabaseFormat,
	dateTimeLabelFormat,
	dateTimeOffset,
	getTimezone,
	updateDateTimeStart,
} from '../helpers/datetime';
import { getSettings } from '@wordpress/date';

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
		( select ) => ( {
			dateTimeStart: select( 'gatherpress/datetime' ).getDateTimeStart(),
			duration: select( 'gatherpress/datetime' ).getDuration(),
		} ),
		[],
	);
	const { setDateTimeStart, setDateTimeEnd } = useDispatch(
		'gatherpress/datetime',
	);
	const settings = getSettings();
	const is12HourTime = /a(?!\\)/i.test(
		settings.formats.time
			.toLowerCase()
			.replaceAll( '\\\\', '' )
			.split( '' )
			.reverse()
			.join( '' ),
	);

	useEffect( () => {
		setDateTimeStart(
			createMomentWithTimezone( dateTimeStart, getTimezone() )
				.format( dateTimeDatabaseFormat ),
		);

		if ( duration ) {
			setDateTimeEnd( dateTimeOffset( duration ) );
		}

		hasEventPastNotice();
	}, [ dateTimeStart, duration, setDateTimeStart, setDateTimeEnd ] );

	return (
		<PanelRow>
			<Flex direction="column" gap="1">
				<FlexItem>
					<h3 style={ { marginBottom: 0 } }>
						<label htmlFor="gatherpress-datetime-start">
							{ __( 'Date & time start', 'gatherpress' ) }
						</label>
					</h3>
				</FlexItem>
				<FlexItem>
					<Dropdown
						popoverProps={ { placement: 'bottom-end' } }
						renderToggle={ ( { isOpen, onToggle } ) => (
							<Button
								id="gatherpress-datetime-start"
								onClick={ onToggle }
								aria-expanded={ isOpen }
								variant="link"
							>
								{ createMomentWithTimezone( dateTimeStart, getTimezone() )
									.format( dateTimeLabelFormat() ) }
							</Button>
						) }
						renderContent={ () => (
							<DateTimePicker
								currentDate={ dateTimeStart }
								onChange={ ( date ) => {
									updateDateTimeStart(
										date,
										setDateTimeStart,
										setDateTimeEnd,
									);
								} }
								is12Hour={ is12HourTime }
							/>
						) }
					/>
				</FlexItem>
			</Flex>
		</PanelRow>
	);
};

export default DateTimeStart;
