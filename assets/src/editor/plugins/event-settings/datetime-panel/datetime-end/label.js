import React, { Component } from 'react';
import { dateI18n, __experimentalGetSettings } from '@wordpress/date';
import { validateDateTimeEnd } from '../helpers';
import { hasEventPast, enableSave } from '../../../helpers';

const { __ } = wp.i18n;

export function updateDateTimeEnd( dateTime, setState = null ) {
	validateDateTimeEnd( dateTime );

	GatherPress.event_datetime.datetime_end = dateTime;

	this.setState({
		dateTime: dateTime
	});

	if ( null !== setState ) {
		setState({ dateTime });
	}

	enableSave();
}

export function getDateTimeEnd() {
	GatherPress.event_datetime.datetime_end = this.state.dateTime;

	hasEventPastNotice();

	return this.state.dateTime;
}

export function hasEventPastNotice() {
	const id               = 'gp_event_past';
	const notices          = wp.data.dispatch( 'core/notices' );
	const eventPastStatus  = hasEventPast();

	notices.removeNotice( id );

	if ( eventPastStatus ) {
		notices.createNotice(
			'warning',
			__( 'This event has already past.', 'gatherpress' ),
			{
				id: id,
				isDismissible: true
			}
		);
	}
}

export class DateTimeEndLabel extends Component {

	constructor( props ) {
		super( props );

		this.state = {
			dateTime: GatherPress.event_datetime.datetime_end
		};
	}

	componentDidMount() {
		this.updateDateTimeEnd = updateDateTimeEnd;
		this.getDateTimeEnd    = getDateTimeEnd;

		updateDateTimeEnd = updateDateTimeEnd.bind( this );
		getDateTimeEnd    = getDateTimeEnd.bind( this );

		hasEventPastNotice();
	}

	componentWillUnmount() {
		updateDateTimeEnd = this.updateDateTimeEnd;
		getDateTimeEnd    = this.getDateTimeEnd;
	}

	componentDidUpdate() {
		hasEventPastNotice();
	}

	render() {
		const settings = __experimentalGetSettings();

		return (
			dateI18n(
				`${ settings.formats.date } ${ settings.formats.time }`,
				this.state.dateTime
			)
		);
	}

}
