import { saveDateTime, dateTimeFormat } from './helpers';
import { Dropdown, Button, PanelRow } from '@wordpress/components';
import { DateTimeStart } from './datetime-start';
import { DateTimeStartLabel } from './datetime-start/label';
import { DateTimeEnd } from './datetime-end';
import { DateTimeEndLabel } from './datetime-end/label';

const { __ }          = wp.i18n;
const currentDateTime = moment().format( dateTimeFormat );

let dateTimeStart = GatherPress.event_datetime.datetime_start;
let dateTimeEnd   = GatherPress.event_datetime.datetime_end;

wp.data.subscribe( saveDateTime );

dateTimeStart = ( '' !== dateTimeStart ) ? moment( dateTimeStart ).format( dateTimeFormat ) : currentDateTime;
dateTimeEnd = ( '' !== dateTimeEnd ) ? moment( dateTimeEnd ).format( dateTimeFormat ) : moment( currentDateTime ).add( 2, 'hours' ).format( dateTimeFormat );

GatherPress.event_datetime.datetime_start = dateTimeStart;
GatherPress.event_datetime.datetime_end   = dateTimeEnd;

export const DateTimeStartSettingPanel = () =>
	(
		<section>
			<h3>{ __( 'Date & time', 'gatherpress' ) }</h3>
			<PanelRow>
				<span>
					{ __( 'Start', 'gatherpress' ) }
				</span>
				<Dropdown
					position         = 'bottom left'
					renderToggle     = { ({ isOpen, onToggle }) => (
						<Button
							onClick       = { onToggle }
							aria-expanded = { isOpen }
							isLink
						>
							<DateTimeStartLabel />
						</Button>
					) }
					renderContent    = { () => <DateTimeStart /> }
				/>
			</PanelRow>
			<PanelRow>
				<span>
					{ __( 'End', 'gatherpress' ) }
				</span>
				<Dropdown
					position         = 'bottom left'
					renderToggle     = { ({ isOpen, onToggle }) => (
						<Button
							onClick       = { onToggle }
							aria-expanded = { isOpen }
							isLink
						>
							<DateTimeEndLabel />
						</Button>
					) }
					renderContent    = { () => <DateTimeEnd /> }
				/>
			</PanelRow>
			{/*<PanelRow>*/}
			{/*	<h5>{ GatherPress.default_timezone }</h5>*/}
			{/*</PanelRow>*/}
		</section>
);
