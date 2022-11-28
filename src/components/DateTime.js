/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { dateI18n, getSettings } from '@wordpress/date';
import { useState } from '@wordpress/element';
import { DateTimePicker } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { enableSave, hasEventPast, isEventPostType } from '../panels/helpers';
import { Broadcaster } from '../helpers/broadcasting';
import {__} from '@wordpress/i18n';

export const dateTimeFormat = 'YYYY-MM-DDTHH:mm:ss';
export const defaultDateTimeStart = moment()
	.add( 1, 'day' )
	.set( 'hour', 18 )
	.set( 'minute', 0 )
	.format( dateTimeFormat );

export const getDateTimeStart = () => {
	let dateTime = GatherPress.event_datetime.datetime_start;

	dateTime =
		( '' !== dateTime )
			? moment( dateTime ).format( dateTimeFormat )
			: defaultDateTimeStart;

	GatherPress.event_datetime.datetime_start = dateTime;

	return dateTime;
};

export const getDateTimeEnd = () => {
	let dateTime = global.GatherPress.event_datetime.datetime_end;

	dateTime =
		( '' !== dateTime )
			? moment( dateTime ).format( dateTimeFormat )
			: moment( defaultDateTimeStart ).add( 2, 'hours' ).format( dateTimeFormat );

	GatherPress.event_datetime.datetime_end = dateTime;

	return dateTime;
};

export const DateTimeStartLabel = ( props ) => {
	const { dateTimeStart } = props;
	const settings = getSettings();

	return dateI18n(
		`${ settings.formats.date } ${ settings.formats.time }`,
		dateTimeStart,
		false
	);
};

export const DateTimeEndLabel = ( props ) => {
	const { dateTimeEnd } = props;
	const settings = getSettings();

	return dateI18n(
		`${ settings.formats.date } ${ settings.formats.time }`,
		dateTimeEnd,
		false
	);
};

export const updateDateTimeEnd = ( date, setDateTimeEnd = null ) => {
	validateDateTimeEnd( date );

	GatherPress.event_datetime.datetime_start = date;

	if ( null !== setDateTimeEnd ) {
		setDateTimeEnd( date );
	}

	const payload = {
		setDateTimeEnd: date,
	};

	Broadcaster( payload );
	enableSave();
};

export const updateDateTimeStart = ( date, setDateTimeStart = null ) => {
	validateDateTimeStart( date );

	GatherPress.event_datetime.datetime_start = date;

	if ( null !== setDateTimeStart ) {
		setDateTimeStart( date );
	}

	const payload = {
		setDateTimeEnd: date,
	};

	Broadcaster( payload );
	enableSave();
};

export function validateDateTimeStart( dateTime ) {
	const dateTimeEndNumeric = moment(
		// eslint-disable-next-line no-undef
		GatherPress.event_datetime.datetime_end
	).valueOf();
	const dateTimeNumeric = moment( dateTime ).valueOf();

	if ( dateTimeNumeric >= dateTimeEndNumeric ) {
		const dateTimeEnd = moment( dateTimeNumeric )
			.add( 2, 'hours' )
			.format( dateTimeFormat );

		updateDateTimeEnd( dateTimeEnd );
	}

	hasEventPastNotice();
}

export function hasEventPastNotice() {
	const id = 'gp_event_past';
	const notices = wp.data.dispatch( 'core/notices' );
	const eventPastStatus = hasEventPast();

	notices.removeNotice( id );

	if ( eventPastStatus ) {
		notices.createNotice(
			'warning',
			__( 'This event has already past.', 'gatherpress' ),
			{
				id,
				isDismissible: false,
			}
		);
	}
}

export function validateDateTimeEnd( dateTime ) {
	const dateTimeStartNumeric = moment(
		// eslint-disable-next-line no-undef
		GatherPress.event_datetime.datetime_start
	).valueOf();
	const dateTimeNumeric = moment( dateTime ).valueOf();

	if ( dateTimeNumeric <= dateTimeStartNumeric ) {
		const dateTimeStart = moment( dateTimeNumeric )
			.subtract( 2, 'hours' )
			.format( dateTimeFormat );
		updateDateTimeStart( dateTimeStart );
	}

	hasEventPastNotice();
}

export const DateTimeStartPicker = ( props ) => {
	const { dateTimeStart, setDateTimeStart } = props;
	const settings = getSettings();
	const is12HourTime = /a(?!\\)/i.test(
		settings.formats.time
			.toLowerCase()
			.replace( /\\\\/g, '' )
			.split( '' )
			.reverse()
			.join( '' )
	);

	return (
		<DateTimePicker
			currentDate={ dateTimeStart }
			onChange={ ( date ) => updateDateTimeStart( date, setDateTimeStart ) }
			is12Hour={ is12HourTime }
		/>
	);
};

export const DateTimeEndPicker = ( props ) => {
	const { dateTimeEnd, setDateTimeEnd } = props;
	const settings = getSettings();
	const is12HourTime = /a(?!\\)/i.test(
		settings.formats.time
			.toLowerCase()
			.replace( /\\\\/g, '' )
			.split( '' )
			.reverse()
			.join( '' )
	);

	return (
		<DateTimePicker
			currentDate={ dateTimeEnd }
			onChange={ ( date ) => updateDateTimeStart( date, setDateTimeEnd ) }
			is12Hour={ is12HourTime }
		/>
	);
};

export function saveDateTime() {
	const isSavingPost = wp.data.select( 'core/editor' ).isSavingPost(),
	isAutosavingPost = wp.data.select( 'core/editor' ).isAutosavingPost();

	if ( isEventPostType() && isSavingPost && ! isAutosavingPost ) {
		apiFetch( {
			path: '/gatherpress/v1/event/datetime/',
			method: 'POST',
			data: {
				// eslint-disable-next-line no-undef
				post_id: GatherPress.post_id,
				datetime_start: moment(
				// eslint-disable-next-line no-undef
				GatherPress.event_datetime.datetime_start
				).format( 'YYYY-MM-DD HH:mm:ss' ),
				datetime_end: moment(
				// eslint-disable-next-line no-undef
				GatherPress.event_datetime.datetime_end
				).format( 'YYYY-MM-DD HH:mm:ss' ),
				// eslint-disable-next-line no-undef
				_wpnonce: GatherPress.nonce,
			},
		} ).then( () => {
			// Saved.
		} );
	}
}
