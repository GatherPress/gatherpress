import React, { Component } from 'react';
import { dateI18n, __experimentalGetSettings } from '@wordpress/date';
import { validateDateTimeStart } from '../helpers';
import { enableSave } from '../../../helpers';

export function updateDateTimeStart( dateTime, setState = null ) {
	validateDateTimeStart( dateTime );

	GatherPress.event_datetime.datetime_start = dateTime;

	this.setState({
		dateTime: dateTime
	});

	if ( null !== setState ) {
		setState({ dateTime });
	}

	enableSave();
}

export function getDateTimeStart() {
	GatherPress.event_datetime.datetime_start = this.state.dateTime;

	return this.state.dateTime;
}

export class DateTimeStartLabel extends Component {

	constructor( props ) {
		super( props );

		this.state = {
			dateTime: GatherPress.event_datetime.datetime_start
		};
	}

	componentDidMount() {
		this.updateDateTimeStart = updateDateTimeStart;
		this.getDateTimeStart    = getDateTimeStart;

		updateDateTimeStart = updateDateTimeStart.bind( this );
		getDateTimeStart    = getDateTimeStart.bind( this );
	}

	componentWillUnmount() {
		updateDateTimeStart = this.updateDateTimeStart;
		getDateTimeStart    = this.getDateTimeStart;
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
