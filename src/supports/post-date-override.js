/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import { getFromSettings } from '../helpers/editor-settings';
import { isEventPostType } from '../helpers/event';
import {
	convertPHPToMomentFormat,
	createMomentWithTimezone,
	getTimezone,
	getUtcOffset,
	isManualOffset,
	removeNonTimePHPFormatChars,
} from '../helpers/datetime';
import { __ } from '@wordpress/i18n';

/**
 * Formats event datetime for display, matching the PHP get_display_datetime output.
 *
 * @param {string} dateTimeStart - Event start datetime.
 * @param {string} dateTimeEnd   - Event end datetime.
 * @param {string} timezone      - Event timezone.
 * @return {string} Formatted display datetime.
 */
const formatEventDateTime = ( dateTimeStart, dateTimeEnd, timezone ) => {
	const dateFormat = getFromSettings( 'dateFormat' );
	const timeFormat = getFromSettings( 'timeFormat' );
	const showTimezone = getFromSettings( 'showTimezone' );
	const fullFormat = `${ dateFormat } ${ timeFormat }`;

	timezone = getTimezone( timezone );
	let sameStartEndDay = false;

	if ( dateTimeStart && dateTimeEnd ) {
		const sameDayFormat = convertPHPToMomentFormat( dateFormat );
		sameStartEndDay =
			createMomentWithTimezone( dateTimeStart, timezone ).format(
				sameDayFormat
			) ===
			createMomentWithTimezone( dateTimeEnd, timezone ).format(
				sameDayFormat
			);
	}

	const parts = [];

	// Add start date/time.
	if ( dateTimeStart ) {
		const startFormat = convertPHPToMomentFormat( fullFormat );
		parts.push(
			createMomentWithTimezone( dateTimeStart, timezone ).format(
				startFormat
			)
		);
	}

	// Determine end format.
	let endFormat = fullFormat;
	let showEnd = true;

	if ( dateTimeEnd ) {
		endFormat = sameStartEndDay
			? removeNonTimePHPFormatChars( endFormat )
			: endFormat;

		if ( ! endFormat ) {
			showEnd = false;
		}
	}

	// Add separator if start + end.
	if ( dateTimeStart && dateTimeEnd && showEnd ) {
		parts.push( __( 'to', 'gatherpress' ) );
	}

	// Add end date/time.
	if ( dateTimeEnd && showEnd && endFormat ) {
		const momentEndFormat = convertPHPToMomentFormat( endFormat );
		parts.push(
			createMomentWithTimezone( dateTimeEnd, timezone ).format(
				momentEndFormat
			)
		);
	}

	// Add timezone.
	if ( showTimezone ) {
		if ( isManualOffset( timezone ) ) {
			const sign = timezone.charAt( 0 );
			const offset = timezone.substring( 1 ).replace( ':', '' );
			parts.push( `GMT${ sign }${ offset }` );
		} else {
			parts.push(
				createMomentWithTimezone(
					dateTimeEnd || dateTimeStart,
					timezone
				).format( 'z' )
			);
		}
	}

	// Add UTC offset if GMT (invalid site timezone).
	parts.push( getUtcOffset( timezone ) );

	return parts.filter( Boolean ).join( ' ' );
};

/**
 * Higher-Order Component to override the core/post-date block display
 * with event datetime when the "Display event date instead of publish date"
 * setting is enabled.
 *
 * @param {Function} BlockEdit - The original BlockEdit component.
 * @return {Function} Enhanced BlockEdit component.
 */
const withEventPostDateOverride = createHigherOrderComponent(
	( BlockEdit ) => {
		return ( props ) => {
			const { name } = props;

			// Only apply to the core/post-date block.
			if ( 'core/post-date' !== name ) {
				return <BlockEdit { ...props } />;
			}

			// Check if the setting is enabled and we are editing an event.
			const postOrEventDate = getFromSettings(
				'postOrEventDate'
			);

			if ( ! postOrEventDate || ! isEventPostType() ) {
				return <BlockEdit { ...props } />;
			}

			// Get event datetime from the gatherpress/datetime store.
			const { dateTimeStart, dateTimeEnd, timezone } = useSelect(
				( select ) => {
					const datetimeStore =
						select( 'gatherpress/datetime' );
					return {
						dateTimeStart:
							datetimeStore.getDateTimeStart(),
						dateTimeEnd: datetimeStore.getDateTimeEnd(),
						timezone: datetimeStore.getTimezone(),
					};
				},
				[]
			);

			if ( ! dateTimeStart ) {
				return <BlockEdit { ...props } />;
			}

			const blockProps = useBlockProps();
			const displayDate = formatEventDateTime(
				dateTimeStart,
				dateTimeEnd,
				timezone
			);

			return <div { ...blockProps }>{ displayDate }</div>;
		};
	},
	'withEventPostDateOverride'
);

/**
 * Register the HOC as a filter for the BlockEdit component.
 */
addFilter(
	'editor.BlockEdit',
	'gatherpress/with-event-post-date-override',
	withEventPostDateOverride
);
