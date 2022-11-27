/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { dateI18n, getSettings } from '@wordpress/date';
import { useState } from '@wordpress/element';

export const dateTimeFormat = 'YYYY-MM-DDTHH:mm:ss';

export const updateDateTimeEnd = ( dateTime, setState = null ) => {
	validateDateTimeEnd( dateTime );

	// eslint-disable-next-line no-undef
	GatherPress.event_datetime.datetime_end = dateTime;

	// this.setState( {
	// 	dateTime,
	// } );

	// if ( null !== setState ) {
	// 	setState( { dateTime } );
	// }

	const payload = {
		setDateTimeEnd: dateTime,
	};

	// Broadcaster( payload );
	// enableSave();
}

export const validateDateTimeStart = ( dateTime ) => {
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

export const DateTimeStartLabel = ( props ) => {
	const settings = getSettings();
	const dateTimeStartNumeric = moment(
		// eslint-disable-next-line no-undef
		// GatherPress.event_datetime.datetime_start
	).valueOf();
	console.log('here');
	console.log(dateTimeStartNumeric)
	const [ dateTime, setDateTime ] = useState( dateTimeStartNumeric );

	return dateI18n(
		`${ settings.formats.date } ${ settings.formats.time }`,
		dateTime,
		false
	)
};

export const DateTimeEndLabel = ( props ) => {
	return (
	<div>
		DateTimeStartLabel
	</div>
	);
};
