/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexBlock, FlexItem, Icon, TextControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';
import {dateTimeFormat} from '../../panels/event-settings/datetime/helpers';

const formatDate = ( start, end ) => {
	const dateFormat = 'dddd, MMMM D, YYYY';
	const timeFormat = 'h:mm A';
	const timeZoneFormat = 'z';
	const timeZone = GatherPress.event_datetime.timezone;

	let startFormat = dateFormat + ' ' + timeFormat;
	let endFormat = dateFormat + ' ' + timeFormat + ' ' + timeZoneFormat;

	if ( moment( start ).format( dateFormat ) === moment( end ).format( dateFormat ) ) {
		endFormat = timeFormat + ' ' + timeZoneFormat;
	}

	return moment( start ).format( startFormat ) + ' to ' + moment.tz( end, timeZone ).format( endFormat ) ;
};

const Edit = ( props ) => {
	const { attributes, setAttributes, isSelected } = props;
	const blockProps = useBlockProps();
	const [ dateTimeEnd, setDateTimeEnd ] = useState( GatherPress.event_datetime.datetime_end );
	const [ dateTimeStart, setDateTimeStart ] = useState( GatherPress.event_datetime.datetime_start );

	Listener( { setDateTimeEnd, setDateTimeStart } );

	return (
		<div { ...blockProps }>
			<Flex justify="normal">
				<FlexItem display="flex">
					<Icon icon="clock" />
				</FlexItem>
				<FlexItem>
					{ formatDate( dateTimeStart, dateTimeEnd ) }
				</FlexItem>
			</Flex>
		</div>
	);
}

export default Edit;
