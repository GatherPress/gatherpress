/**
 * WordPress dependencies
 */
import {
	Button,
	DateTimePicker,
	DatePicker,
	Dropdown,
	Flex,
	FlexItem,
	PanelRow,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getStartOfWeek } from '../helpers/editor';
import { hasEventPastNotice } from '../helpers/event';
import {
	createMomentWithTimezone,
	dateTimeDatabaseFormat,
	dateLabelFormat,
	toDayEnd,
	dateTimeLabelFormat,
	getTimezone,
	updateDateTimeEnd,
} from '../helpers/datetime';
import { getSettings } from '@wordpress/date';

/**
 * DateTimeEnd component for GatherPress.
 *
 * This component renders the end date and time selection in the editor.
 * It includes a DateTimeEndPicker for selecting the end date and time.
 * The component also updates the state using the setDateTimeEnd callback.
 * If the event has passed, it displays a notice using the hasEventPastNotice function.
 *
 * @since 0.27.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeEnd = () => {
	// An all-day event has no time to pick, and shows only its date.
	const { dateTimeEnd, isAllDay } = useSelect(
		( select ) => ( {
			dateTimeEnd: select( 'gatherpress/datetime' ).getDateTimeEnd(),
			isAllDay: Boolean(
				select( 'core/editor' ).getEditedPostAttribute( 'meta' )
					?.gatherpress_is_all_day,
			),
		} ),
		[],
	);
	const { setDateTimeEnd, setDateTimeStart } = useDispatch(
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
		setDateTimeEnd(
			createMomentWithTimezone( dateTimeEnd, getTimezone() ).format( dateTimeDatabaseFormat ),
		);

		hasEventPastNotice();
	} );

	return (
		<PanelRow>
			<Flex direction="column" gap="1">
				<FlexItem>
					<h3 style={ { marginBottom: 0 } }>
						<label htmlFor="gatherpress-datetime-end">
							{ __( 'Date & time end', 'gatherpress' ) }
						</label>
					</h3>
				</FlexItem>
				<FlexItem>
					<Dropdown
						popoverProps={ { placement: 'bottom-end' } }
						renderToggle={ ( { isOpen, onToggle } ) => (
							<Button
								id="gatherpress-datetime-end"
								onClick={ onToggle }
								aria-expanded={ isOpen }
								variant="link"
							>
								{ createMomentWithTimezone( dateTimeEnd, getTimezone() ).format(
									isAllDay ? dateLabelFormat() : dateTimeLabelFormat(),
								) }
							</Button>
						) }
						renderContent={ () => (
							isAllDay ? (
								<DatePicker
									currentDate={ dateTimeEnd }
									onChange={ ( date ) =>
										updateDateTimeEnd(
											toDayEnd( date ),
											setDateTimeEnd,
											setDateTimeStart,
										)
									}
									startOfWeek={ getStartOfWeek() }
								/>
							) : (
								<DateTimePicker
									currentDate={ dateTimeEnd }
									onChange={ ( date ) =>
										updateDateTimeEnd(
											date,
											setDateTimeEnd,
											setDateTimeStart,
										)
									}
									is12Hour={ is12HourTime }
									startOfWeek={ getStartOfWeek() }
								/>
							)
						) }
					/>
				</FlexItem>
			</Flex>
		</PanelRow>
	);
};

export default DateTimeEnd;
