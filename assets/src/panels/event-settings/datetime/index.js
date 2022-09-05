/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { Dropdown, Button, PanelRow } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { subscribe } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { saveDateTime, dateTimeFormat } from './helpers';
import { DateTimeStart } from './datetime-start';
import { DateTimeStartLabel } from './datetime-start/label';
import { DateTimeEnd } from './datetime-end';
import { DateTimeEndLabel } from './datetime-end/label';

const currentDateTime = moment().format(dateTimeFormat);

// eslint-disable-next-line no-undef
let dateTimeStart = GatherPress.event_datetime.datetime_start;
// eslint-disable-next-line no-undef
let dateTimeEnd = GatherPress.event_datetime.datetime_end;

subscribe(saveDateTime);

dateTimeStart =
	'' !== dateTimeStart
		? moment(dateTimeStart).format(dateTimeFormat)
		: currentDateTime;
dateTimeEnd =
	'' !== dateTimeEnd
		? moment(dateTimeEnd).format(dateTimeFormat)
		: moment(currentDateTime).add(2, 'hours').format(dateTimeFormat);

// eslint-disable-next-line no-undef
GatherPress.event_datetime.datetime_start = dateTimeStart;
// eslint-disable-next-line no-undef
GatherPress.event_datetime.datetime_end = dateTimeEnd;

export const DateTimeStartSettingPanel = () => (
	<section>
		<h3>{__('Date & time', 'gatherpress')}</h3>
		<PanelRow>
			<span>{__('Start', 'gatherpress')}</span>
			<Dropdown
				position="bottom left"
				renderToggle={({ isOpen, onToggle }) => (
					<Button onClick={onToggle} aria-expanded={isOpen} isLink>
						<DateTimeStartLabel />
					</Button>
				)}
				renderContent={() => <DateTimeStart />}
			/>
		</PanelRow>
		<PanelRow>
			<span>{__('End', 'gatherpress')}</span>
			<Dropdown
				position="bottom left"
				renderToggle={({ isOpen, onToggle }) => (
					<Button onClick={onToggle} aria-expanded={isOpen} isLink>
						<DateTimeEndLabel />
					</Button>
				)}
				renderContent={() => <DateTimeEnd />}
			/>
		</PanelRow>
		{/*<PanelRow>*/}
		{/*	<h5>{ GatherPress.default_timezone }</h5>*/}
		{/*</PanelRow>*/}
	</section>
);
