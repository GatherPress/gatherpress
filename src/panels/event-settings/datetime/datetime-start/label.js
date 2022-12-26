/**
 * WordPress dependencies.
 */
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { dateI18n, __experimentalGetSettings } from '@wordpress/date';
import { Component } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../../../../helpers/broadcasting';
import { validateDateTimeStart } from '../helpers';
import { enableSave } from '../../../helpers';

export function updateDateTimeStart(dateTime, setState = null) {
	validateDateTimeStart(dateTime);

	// eslint-disable-next-line no-undef
	GatherPress.event_datetime.datetime_start = dateTime;

	this.setState({
		dateTime,
	});

	if (null !== setState) {
		setState({ dateTime });
	}

	const payload = {
		setDateTimeStart: dateTime,
	};

	Broadcaster(payload);
	enableSave();
}

export function getDateTimeStart() {
	// eslint-disable-next-line no-undef
	GatherPress.event_datetime.datetime_start = this.state.dateTime;

	return this.state.dateTime;
}

export class DateTimeStartLabel extends Component {
	constructor(props) {
		super(props);

		this.state = {
			// eslint-disable-next-line no-undef
			dateTime: GatherPress.event_datetime.datetime_start,
		};
	}

	componentDidMount() {
		this.updateDateTimeStart = updateDateTimeStart;
		this.getDateTimeStart = getDateTimeStart;

		updateDateTimeStart = updateDateTimeStart.bind(this);
		getDateTimeStart = getDateTimeStart.bind(this);
	}

	componentWillUnmount() {
		updateDateTimeStart = this.updateDateTimeStart;
		getDateTimeStart = this.getDateTimeStart;
	}

	render() {
		const settings = __experimentalGetSettings();

		return dateI18n(
			`${settings.formats.date} ${settings.formats.time}`,
			this.state.dateTime
		);
	}
}
