/**
 * WordPress dependencies
 */
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import {
	BaseControl,
	Button,
	DatePicker,
	Dropdown,
	Notice,
	RadioControl,
	SelectControl,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalHStack as HStack,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalInputControlSuffixWrapper as InputControlSuffixWrapper,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalText as Text,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';

/**
 * External dependencies
 */
import moment from 'moment';

/**
 * Internal dependencies
 */
import { getStartOfWeek } from '../../helpers/editor';
import {
	REPEAT_FIELD,
	WEEKDAYS,
	defaultEndDate,
	defaultPattern,
	editablePattern,
	formatDate,
	hasStarted,
	intervalLabel,
	matchingChoice,
	monthlyChoices,
	monthlyLabel,
	summarize,
	toRequest,
	tomorrowIn,
	weekdayName,
} from '../../helpers/repeat';

/**
 * A weekday's one- or two-letter abbreviation for a compact button.
 *
 * @since TBD
 *
 * @param {string} code A weekday code.
 *
 * @return {string} The abbreviation.
 */
const weekdayInitial = ( code ) => {
	const initials = {
		MO: _x( 'M', 'Monday initial', 'gatherpress' ),
		TU: _x( 'T', 'Tuesday initial', 'gatherpress' ),
		WE: _x( 'W', 'Wednesday initial', 'gatherpress' ),
		TH: _x( 'T', 'Thursday initial', 'gatherpress' ),
		FR: _x( 'F', 'Friday initial', 'gatherpress' ),
		SA: _x( 'S', 'Saturday initial', 'gatherpress' ),
		SU: _x( 'S', 'Sunday initial', 'gatherpress' ),
	};

	return initials[ code ];
};

/**
 * The unit an interval counts in, shown after its number.
 *
 * @since TBD
 *
 * @param {string} frequency The frequency.
 * @param {number} count     The interval.
 *
 * @return {string} Such as "weeks".
 */
const intervalSuffix = ( frequency, count ) => {
	switch ( frequency ) {
		case 'DAILY':
			return _n( 'day', 'days', count, 'gatherpress' );
		case 'WEEKLY':
			return _n( 'week', 'weeks', count, 'gatherpress' );
		case 'MONTHLY':
			return _n( 'month', 'months', count, 'gatherpress' );
		default:
			return _n( 'year', 'years', count, 'gatherpress' );
	}
};

/**
 * A date field that opens the same date picker as the event's own dates.
 *
 * @since TBD
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.label    The field's label.
 * @param {string}   props.value    The date, as `YYYY-MM-DD`.
 * @param {string}   props.min      The earliest date allowed, as `YYYY-MM-DD`.
 * @param {Function} props.onChange Called with the picked date, as `YYYY-MM-DD`.
 *
 * @return {JSX.Element} The field.
 */
const DateField = ( { label, value, min, onChange } ) => {
	const id = useInstanceId( DateField, 'gatherpress-repeat-schedule-date' );

	return (
		<BaseControl id={ id } label={ label } __nextHasNoMarginBottom>
			<Dropdown
				popoverProps={ { placement: 'bottom-end' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						id={ id }
						variant="link"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						aria-label={ sprintf(
						/* translators: 1: A field's label. 2: A date. */
							__( '%1$s: %2$s', 'gatherpress' ),
							label,
							formatDate( value ),
						) }
					>
						{ formatDate( value ) }
					</Button>
				) }
				renderContent={ () => (
					<DatePicker
						currentDate={ value }
						isInvalidDate={ ( date ) => moment( date ).format( 'YYYY-MM-DD' ) < min }
						onChange={ ( date ) => onChange( moment( date ).format( 'YYYY-MM-DD' ) ) }
						startOfWeek={ getStartOfWeek() }
					/>
				) }
			/>
		</BaseControl>
	);
};

/**
 * RepeatSchedule component.
 *
 * The Repeat schedule panel's controls: how an event repeats, from its
 * date fields in Event settings, which give the first date, its time,
 * length and timezone. The schedule saves with the post through the
 * `gatherpress_repeat` REST field, so the Save button, undo and the
 * unsaved-changes warning all cover it.
 *
 * Once a published event's first date has passed, a change applies from a
 * later date, tomorrow by default, and can set a new time and length. The
 * dates before it stay as they were, as the server guarantees. A schedule
 * the editor can't show as one pattern is left alone, with a note.
 *
 * @since TBD
 *
 * @return {JSX.Element} The repeat controls.
 */
const RepeatSchedule = () => {
	const { editPost } = useDispatch( 'core/editor' );
	const weekdaysId = useInstanceId( RepeatSchedule, 'gatherpress-repeat-schedule-weekdays' );
	const state = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const datetime = select( 'gatherpress/datetime' );

		return {
			saved: editor.getCurrentPostAttribute( REPEAT_FIELD ),
			edited: editor.getEditedPostAttribute( REPEAT_FIELD ),
			status: editor.getCurrentPostAttribute( 'status' ),
			savedJson: editor.getCurrentPostAttribute( 'meta' )?.gatherpress_datetime,
			dateTimeStart: datetime.getDateTimeStart(),
			dateTimeEnd: datetime.getDateTimeEnd(),
			timezone: datetime.getTimezone(),
			isAllDay: Boolean( editor.getEditedPostAttribute( 'meta' )?.gatherpress_is_all_day ),
		};
	}, [] );
	const { saved, edited, status, savedJson, dateTimeStart, dateTimeEnd, timezone, isAllDay } = state;
	const started = hasStarted( status, savedJson );
	const isEdited = edited !== saved;
	const tomorrow = tomorrowIn( timezone );
	// A change date left from before midnight moves to tomorrow.
	const editedFrom = isEdited && edited?.from;
	const from = editedFrom && editedFrom >= tomorrow ? editedFrom : tomorrow;
	const pattern = isEdited
		? edited?.pattern ?? null
		: editablePattern( saved?.ended ? null : saved?.pattern );
	// A change to a started series begins on its change date, so new days come from there.
	const baseDate = started ? from : dateTimeStart;

	/**
	 * Save an edited pattern, with the change date for a started series.
	 *
	 * @param {Object|null} next           The pattern, or null to stop repeating.
	 * @param {Object}      options        Options.
	 * @param {string}      options.from   The change date for a started series.
	 * @param {boolean}     options.cancel Whether to cancel a scheduled change.
	 *
	 * @return {void}
	 */
	const update = ( next, { from: nextFrom = from, cancel = false } = {} ) => {
		editPost( {
			[ REPEAT_FIELD ]: toRequest( next, { from: started ? nextFrom : null, cancel } ),
		} );
	};

	// A repeating draft's rule follows its dates, so a date edit saves the pattern again.
	const datesKey = [ dateTimeStart, dateTimeEnd, isAllDay, timezone ].join( '|' );
	const lastDates = useRef( datesKey );

	useEffect( () => {
		if ( lastDates.current === datesKey ) {
			return;
		}

		lastDates.current = datesKey;

		if ( pattern && ! started ) {
			editPost( { [ REPEAT_FIELD ]: toRequest( pattern ) } );
		}
	}, [ datesKey, pattern, started, editPost ] );

	// A change date stored before midnight would be refused, so it moves to tomorrow.
	useEffect( () => {
		if ( started && editedFrom && editedFrom < tomorrow ) {
			editPost( { [ REPEAT_FIELD ]: toRequest( edited.pattern, { from: tomorrow } ) } );
		}
	}, [ started, editedFrom, tomorrow, edited, editPost ] );

	if ( false === saved?.showable ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __(
					'This event has a schedule the editor can\'t show as one pattern, so it can\'t be changed here.',
					'gatherpress',
				) }
			</Notice>
		);
	}

	/**
	 * Change some of the pattern's parts.
	 *
	 * @param {Object} changes The parts to change.
	 *
	 * @return {void}
	 */
	const change = ( changes ) => update( { ...pattern, ...changes } );
	const choices = monthlyChoices( baseDate );
	const selectedChoice = pattern ? matchingChoice( choices, pattern ) : null;
	// A started series' pattern can pick a day the change date doesn't offer; it stays a choice.
	const monthlyOptions = [
		...( null === selectedChoice ? [ { value: 'current', label: monthlyLabel( pattern ) } ] : [] ),
		...choices,
	].map( ( { value, label } ) => ( { value, label } ) );
	const showScheduled = saved?.scheduled && ! ( isEdited && null === edited?.scheduled );

	return (
		<VStack spacing={ 4 }>
			{ started && (
				<Text variant="muted">
					{ __(
						'Changes apply from a later date, and earlier dates stay as they were.',
						'gatherpress',
					) }
				</Text>
			) }
			{ saved?.ended && ! isEdited && (
				<Text variant="muted">
					{ sprintf(
						/* translators: %s: A date. */
						__( 'This series ended on %s.', 'gatherpress' ),
						formatDate( saved.pattern.ends_on ),
					) }
				</Text>
			) }
			<SelectControl
				label={ __( 'Repeats', 'gatherpress' ) }
				value={ pattern?.frequency ?? '' }
				options={ [
					{ value: '', label: __( 'Does not repeat', 'gatherpress' ) },
					{ value: 'DAILY', label: __( 'Daily', 'gatherpress' ) },
					{ value: 'WEEKLY', label: __( 'Weekly', 'gatherpress' ) },
					{ value: 'MONTHLY', label: __( 'Monthly', 'gatherpress' ) },
					{ value: 'YEARLY', label: __( 'Yearly', 'gatherpress' ) },
				] }
				onChange={ ( frequency ) =>
					update( frequency ? defaultPattern( frequency, baseDate ) : null )
				}
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			{ pattern && (
				<>
					<NumberControl
						label={ intervalLabel( pattern.frequency ) }
						min={ 1 }
						max={ 1000 }
						value={ pattern.interval }
						suffix={
							<InputControlSuffixWrapper>
								{ intervalSuffix( pattern.frequency, pattern.interval ) }
							</InputControlSuffixWrapper>
						}
						onChange={ ( value ) =>
							change( {
								interval: Math.min( 1000, Math.max( 1, parseInt( value, 10 ) || 1 ) ),
							} )
						}
						__next40pxDefaultSize
					/>
					{ 'WEEKLY' === pattern.frequency && (
						<BaseControl
							id={ weekdaysId }
							label={ __( 'Repeats on', 'gatherpress' ) }
							help={ __( 'Choose at least one day.', 'gatherpress' ) }
							__nextHasNoMarginBottom
						>
							<HStack
								id={ weekdaysId }
								role="group"
								aria-label={ __( 'Repeats on', 'gatherpress' ) }
								spacing={ 1 }
								justify="flex-start"
							>
								{ WEEKDAYS.map( ( code ) => {
									const isOn = pattern.weekdays.includes( code );

									return (
										<Button
											key={ code }
											size="compact"
											variant={ isOn ? 'primary' : 'secondary' }
											isPressed={ isOn }
											aria-label={ weekdayName( code ) }
											onClick={ () => {
												// The last day chosen stays chosen.
												if ( isOn && 1 === pattern.weekdays.length ) {
													return;
												}

												change( {
													weekdays: WEEKDAYS.filter( ( day ) =>
														day === code ? ! isOn : pattern.weekdays.includes( day ),
													),
												} );
											} }
										>
											{ weekdayInitial( code ) }
										</Button>
									);
								} ) }
							</HStack>
						</BaseControl>
					) }
					{ 'MONTHLY' === pattern.frequency && (
						<RadioControl
							label={ __( 'Repeats on', 'gatherpress' ) }
							selected={ selectedChoice ?? 'current' }
							options={ monthlyOptions }
							onChange={ ( value ) => {
								const choice = choices.find( ( option ) => option.value === value );

								if ( choice ) {
									change( choice.pattern );
								}
							} }
						/>
					) }
					<ToggleGroupControl
						label={ __( 'Ends', 'gatherpress' ) }
						value={ pattern.ends_on ? 'on' : 'never' }
						onChange={ ( value ) =>
							change( { ends_on: 'on' === value ? defaultEndDate( baseDate ) : null } )
						}
						isBlock
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					>
						<ToggleGroupControlOption value="never" label={ __( 'Never', 'gatherpress' ) } />
						<ToggleGroupControlOption value="on" label={ __( 'On a date', 'gatherpress' ) } />
					</ToggleGroupControl>
					{ pattern.ends_on && (
						<DateField
							label={ __( 'End date', 'gatherpress' ) }
							value={ pattern.ends_on }
							min={ baseDate.slice( 0, 10 ) }
							onChange={ ( value ) => change( { ends_on: value } ) }
						/>
					) }
					{ started && (
						<>
							<DateField
								label={ __( 'Changes apply from', 'gatherpress' ) }
								value={ from }
								min={ tomorrow }
								onChange={ ( value ) =>
									update( pattern, { from: value >= tomorrow ? value : tomorrow } )
								}
							/>
							{ ! isAllDay && (
								<HStack spacing={ 2 } alignment="top">
									<TextControl
										type="time"
										label={ __( 'Time from then on', 'gatherpress' ) }
										value={ pattern.times?.[ 0 ] ?? '' }
										onChange={ ( value ) => change( value ? { times: [ value ] } : {} ) }
										__next40pxDefaultSize
										__nextHasNoMarginBottom
									/>
									<NumberControl
										label={ __( 'Length', 'gatherpress' ) }
										min={ 0 }
										value={ pattern.duration ?? '' }
										suffix={
											<InputControlSuffixWrapper>
												{ __( 'min', 'gatherpress' ) }
											</InputControlSuffixWrapper>
										}
										onChange={ ( value ) =>
											change( { duration: Math.max( 0, parseInt( value, 10 ) || 0 ) } )
										}
										__next40pxDefaultSize
									/>
								</HStack>
							) }
						</>
					) }
				</>
			) }
			<Text weight={ 500 } aria-live="polite">
				{ summarize( pattern ) }
			</Text>
			{ showScheduled && (
				<VStack spacing={ 1 }>
					<Text variant="muted">
						{ sprintf(
							/* translators: 1: A date. 2: A repeating pattern, such as "Every week on Monday." */
							__( 'From %1$s: %2$s', 'gatherpress' ),
							formatDate( saved.scheduled.starts_on ),
							summarize( editablePattern( saved.scheduled ) ),
						) }
					</Text>
					<Button
						variant="link"
						isDestructive
						onClick={ () =>
							update( pattern ?? editablePattern( saved.pattern ), { cancel: true } )
						}
					>
						{ __( 'Cancel this scheduled change', 'gatherpress' ) }
					</Button>
				</VStack>
			) }
		</VStack>
	);
};

export default RepeatSchedule;
