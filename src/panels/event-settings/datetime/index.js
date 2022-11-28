/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button, Dropdown, Flex, FlexItem, PanelRow, SelectControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch, subscribe } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../../../helpers/broadcasting';
import {
	DateTimeStartLabel,
	DateTimeEndLabel,
	dateTimeFormat,
	getDateTimeStart,
	getDateTimeEnd,
	DateTimeStartPicker,
	DateTimeEndPicker,
	saveDateTime
} from '../../../components/DateTime';

// subscribe( saveDateTime );

const DateTimePanel = ( props ) => {
	const [ dateTimeStart, setDateTimeStart ] = useState();
	const [ dateTimeEnd, setDateTimeEnd ] = useState();

	useEffect( () => {
		setDateTimeStart( moment( getDateTimeStart() ).format( dateTimeFormat ) );
		setDateTimeEnd( moment( getDateTimeEnd() ).format( dateTimeFormat ) );
	} );

	return (
		<section>
			<h3>{ __( 'Date & time', 'gatherpress' ) }</h3>
			<PanelRow>
				<Flex>
					<FlexItem>{ __( 'Start', 'gatherpress' ) }</FlexItem>
					<FlexItem>
						<Dropdown
							position="bottom left"
							renderToggle={ ( { isOpen, onToggle } ) => (
								<Button
									onClick={ onToggle }
									aria-expanded={ isOpen }
									isLink
								>
									<DateTimeStartLabel dateTimeStart={ dateTimeStart } />
								</Button>
							) }
							renderContent={ () => <DateTimeStartPicker dateTimeStart={ dateTimeStart } setDateTimeStart={ setDateTimeStart } /> }
						/>
					</FlexItem>
				</Flex>
			</PanelRow>
			<PanelRow>
				<Flex>
					<FlexItem>{ __( 'End', 'gatherpress' ) }</FlexItem>
					<FlexItem>
						<Dropdown
							position="bottom left"
							renderToggle={ ( { isOpen, onToggle } ) => (
								<Button
									onClick={ onToggle }
									aria-expanded={ isOpen }
									isLink
								>
									<DateTimeEndLabel dateTimeEnd={ dateTimeEnd }/>
								</Button>
							) }
							renderContent={ () => <DateTimeEndPicker dateTimeEnd={ dateTimeEnd } setDateTimeEnd={ setDateTimeEnd } /> }
						/>
					</FlexItem>
				</Flex>
			</PanelRow>
		</section>
	);
};

export default DateTimePanel;
