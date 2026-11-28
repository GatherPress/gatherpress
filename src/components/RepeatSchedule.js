/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	CheckboxControl,
	Notice,
	PanelRow,
	RadioControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	FROZEN_NOTE_ID,
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
} from '../helpers/repeat';

/**
 * RepeatSchedule component.
 *
 * Sets how an event repeats, under its date fields, which still give the
 * first date, its time, length and timezone. The schedule saves with the
 * post through the `gatherpress_repeat` REST field, so the Save button,
 * undo and the unsaved-changes warning all cover it.
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
			<PanelRow>
				<Notice status="info" isDismissible={ false }>
					{ __(
						'This event has a schedule the editor can\'t show as one pattern, so it can\'t be changed here.',
						'gatherpress',
					) }
				</Notice>
			</PanelRow>
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
		<PanelRow>
			<div className="gatherpress-repeat-schedule">
				<h3>{ __( 'Repeat', 'gatherpress' ) }</h3>
				{ started && (
					<p id={ FROZEN_NOTE_ID } className="gatherpress-repeat-schedule__note">
						{ __(
							'The first date has passed, so it can\'t change, and schedule changes apply from a later date. Earlier dates stay as they were.',
							'gatherpress',
						) }
					</p>
				) }
				{ saved?.ended && ! isEdited && (
					<p className="gatherpress-repeat-schedule__note">
						{ sprintf(
							/* translators: %s: A date. */
							__( 'This series ended on %s.', 'gatherpress' ),
							formatDate( saved.pattern.ends_on ),
						) }
					</p>
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
				/>
				{ pattern && (
					<>
						<TextControl
							type="number"
							min={ 1 }
							max={ 1000 }
							label={ intervalLabel( pattern.frequency ) }
							value={ pattern.interval }
							onChange={ ( value ) =>
								change( {
									interval: Math.min( 1000, Math.max( 1, parseInt( value, 10 ) || 1 ) ),
								} )
							}
							__next40pxDefaultSize
						/>
						{ 'WEEKLY' === pattern.frequency && (
							<fieldset className="gatherpress-repeat-schedule__weekdays">
								<legend>{ __( 'Repeats on', 'gatherpress' ) }</legend>
								{ WEEKDAYS.map( ( code ) => {
									const isOnlyDay =
										1 === pattern.weekdays.length && pattern.weekdays.includes( code );

									return (
										<CheckboxControl
											key={ code }
											label={ weekdayName( code ) }
											checked={ pattern.weekdays.includes( code ) }
											disabled={ isOnlyDay }
											help={
												isOnlyDay
													? __( 'At least one day stays selected.', 'gatherpress' )
													: undefined
											}
											onChange={ ( checked ) =>
												change( {
													weekdays: WEEKDAYS.filter( ( day ) =>
														day === code
															? checked
															: pattern.weekdays.includes( day ),
													),
												} )
											}
										/>
									);
								} ) }
							</fieldset>
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
						<RadioControl
							label={ __( 'Ends', 'gatherpress' ) }
							selected={ pattern.ends_on ? 'on' : 'never' }
							options={ [
								{ value: 'never', label: __( 'Never', 'gatherpress' ) },
								{ value: 'on', label: __( 'On a date', 'gatherpress' ) },
							] }
							onChange={ ( value ) =>
								change( { ends_on: 'on' === value ? defaultEndDate( baseDate ) : null } )
							}
						/>
						{ pattern.ends_on && (
							<TextControl
								type="date"
								label={ __( 'End date', 'gatherpress' ) }
								min={ baseDate.slice( 0, 10 ) }
								value={ pattern.ends_on }
								onChange={ ( value ) => change( { ends_on: value || null } ) }
								__next40pxDefaultSize
							/>
						) }
						{ started && (
							<>
								<TextControl
									type="date"
									label={ __( 'Changes apply from', 'gatherpress' ) }
									min={ tomorrow }
									value={ from }
									onChange={ ( value ) =>
										update( pattern, { from: value && value >= tomorrow ? value : tomorrow } )
									}
									__next40pxDefaultSize
								/>
								{ ! isAllDay && (
									<>
										<TextControl
											type="time"
											label={ __( 'Time from then on', 'gatherpress' ) }
											value={ pattern.times?.[ 0 ] ?? '' }
											onChange={ ( value ) =>
												change( value ? { times: [ value ] } : {} )
											}
											__next40pxDefaultSize
										/>
										<TextControl
											type="number"
											min={ 0 }
											label={ __( 'Length from then on, in minutes', 'gatherpress' ) }
											value={ pattern.duration ?? '' }
											onChange={ ( value ) =>
												change( { duration: Math.max( 0, parseInt( value, 10 ) || 0 ) } )
											}
											__next40pxDefaultSize
										/>
									</>
								) }
							</>
						) }
					</>
				) }
				<p className="gatherpress-repeat-schedule__summary" aria-live="polite">
					{ summarize( pattern ) }
				</p>
				{ showScheduled && (
					<div className="gatherpress-repeat-schedule__scheduled">
						<p className="gatherpress-repeat-schedule__note">
							{ sprintf(
								/* translators: 1: A date. 2: A repeating pattern, such as "Every week on Monday." */
								__( 'From %1$s: %2$s', 'gatherpress' ),
								formatDate( saved.scheduled.starts_on ),
								summarize( editablePattern( saved.scheduled ) ),
							) }
						</p>
						<Button
							variant="link"
							onClick={ () =>
								update( pattern ?? editablePattern( saved.pattern ), { cancel: true } )
							}
						>
							{ __( 'Cancel this scheduled change', 'gatherpress' ) }
						</Button>
					</div>
				) }
			</div>
		</PanelRow>
	);
};

export default RepeatSchedule;
