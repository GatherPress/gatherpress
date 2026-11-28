/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	CheckboxControl,
	Notice,
	PanelRow,
	RadioControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { dateI18n } from '@wordpress/date';

/**
 * Internal dependencies
 */
import {
	REPEAT_FIELD,
	WEEKDAYS,
	defaultPattern,
	editablePattern,
	hasStarted,
	intervalUnit,
	monthlyChoiceOf,
	monthlyChoices,
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
	const { saved, edited, status, savedJson, dateTimeStart, timezone, isAllDay } = useSelect(
		( select ) => {
			const editor = select( 'core/editor' );

			return {
				saved: editor.getCurrentPostAttribute( REPEAT_FIELD ),
				edited: editor.getEditedPostAttribute( REPEAT_FIELD ),
				status: editor.getCurrentPostAttribute( 'status' ),
				savedJson: editor.getCurrentPostAttribute( 'meta' )?.gatherpress_datetime,
				dateTimeStart: select( 'gatherpress/datetime' ).getDateTimeStart(),
				timezone: select( 'gatherpress/datetime' ).getTimezone(),
				isAllDay: Boolean(
					editor.getEditedPostAttribute( 'meta' )?.gatherpress_is_all_day,
				),
			};
		},
		[],
	);

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

	const started = hasStarted( status, savedJson );
	const isEdited = edited !== saved;
	const tomorrow = tomorrowIn( timezone );
	const from = ( isEdited && edited?.from ) || tomorrow;
	const pattern = isEdited
		? edited?.pattern ?? null
		: editablePattern( saved?.ended ? null : saved?.pattern );
	// A change to a started series begins on its change date, so its days come from there.
	const baseDate = started ? from : dateTimeStart;

	/**
	 * Save an edited pattern, with the change date for a started series.
	 *
	 * @param {Object|null} next     The pattern, or null to stop repeating.
	 * @param {string}      nextFrom The change date.
	 *
	 * @return {void}
	 */
	const update = ( next, nextFrom = from ) => {
		editPost( { [ REPEAT_FIELD ]: toRequest( next, started ? nextFrom : null ) } );
	};

	/**
	 * Change some of the pattern's parts.
	 *
	 * @param {Object} changes The parts to change.
	 *
	 * @return {void}
	 */
	const change = ( changes ) => update( { ...pattern, ...changes } );

	return (
		<PanelRow>
			<div className="gatherpress-repeat-schedule">
				<h3>{ __( 'Repeat', 'gatherpress' ) }</h3>
				{ started && (
					<p className="gatherpress-repeat-schedule__note">
						{ __(
							'The first date has passed, so changes apply from a later date. Earlier dates stay as they were.',
							'gatherpress',
						) }
					</p>
				) }
				{ saved?.ended && ! isEdited && (
					<p className="gatherpress-repeat-schedule__note">
						{ sprintf(
							/* translators: %s: A date. */
							__( 'This series ended on %s.', 'gatherpress' ),
							dateI18n( 'F j, Y', saved.pattern.ends_on ),
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
							label={ sprintf(
								/* translators: %s: A unit, such as "weeks". */
								__( 'Every how many %s', 'gatherpress' ),
								intervalUnit( pattern.frequency, 2 ),
							) }
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
								<legend>{ __( 'On', 'gatherpress' ) }</legend>
								{ WEEKDAYS.map( ( code ) => (
									<CheckboxControl
										key={ code }
										label={ weekdayName( code ) }
										checked={ pattern.weekdays.includes( code ) }
										// At least one weekday stays checked.
										disabled={
											1 === pattern.weekdays.length &&
											pattern.weekdays.includes( code )
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
								) ) }
							</fieldset>
						) }
						{ 'MONTHLY' === pattern.frequency && (
							<RadioControl
								label={ __( 'Repeats on', 'gatherpress' ) }
								selected={ monthlyChoiceOf( pattern ) }
								options={ monthlyChoices( baseDate ).map( ( { value, label } ) => ( {
									value,
									label,
								} ) ) }
								onChange={ ( value ) =>
									change(
										monthlyChoices( baseDate ).find(
											( choice ) => choice.value === value,
										).pattern,
									)
								}
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
								change( { ends_on: 'on' === value ? baseDate.slice( 0, 10 ) : null } )
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
										update( pattern, value && value >= tomorrow ? value : tomorrow )
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
				<p className="gatherpress-repeat-schedule__summary">
					{ summarize( pattern ) }
				</p>
				{ saved?.scheduled && (
					<p className="gatherpress-repeat-schedule__note">
						{ sprintf(
							/* translators: 1: A date. 2: A repeating pattern, such as "Every week on Monday." */
							__( 'From %1$s: %2$s', 'gatherpress' ),
							dateI18n( 'F j, Y', saved.scheduled.starts_on ),
							summarize( editablePattern( saved.scheduled ) ),
						) }
					</p>
				) }
			</div>
		</PanelRow>
	);
};

export default RepeatSchedule;
