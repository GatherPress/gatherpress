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
	toDayStart,
	dateTimeLabelFormat,
	dateTimeOffset,
	getTimezone,
	updateDateTimeStart,
	useMatchedDuration,
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
 * @since 0.27.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeStart = () => {
	const dateTimeStart = useSelect(
		( select ) => select( 'gatherpress/datetime' ).getDateTimeStart(),
		[],
	);
	// An all-day event has no time to pick, and shows only its date.
	const isAllDay = useSelect(
		( select ) =>
			Boolean(
				select( 'core/editor' ).getEditedPostAttribute( 'meta' )
					?.gatherpress_is_all_day
			),
		[],
	);
	// Use the memoized matched-preset hook so the gating below only fires
	// the auto-end-sync when the current end actually equals start + N
	// hours — same semantics as the previous getDuration selector, but
	// without that selector's per-call moment.tz comparison loop (#1607).
	const duration = useMatchedDuration();
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
							{ isAllDay
								? __( 'Date start', 'gatherpress' )
								: __( 'Date & time start', 'gatherpress' ) }
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
								{ createMomentWithTimezone( dateTimeStart, getTimezone() ).format(
									isAllDay ? dateLabelFormat() : dateTimeLabelFormat(),
								) }
							</Button>
						) }
						renderContent={ () => (
							isAllDay ? (
								<DatePicker
									currentDate={ dateTimeStart }
									onChange={ ( date ) => {
										updateDateTimeStart(
											toDayStart( date ),
											setDateTimeStart,
											setDateTimeEnd,
										);
									} }
									startOfWeek={ getStartOfWeek() }
								/>
							) : (
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

export default DateTimeStart;
