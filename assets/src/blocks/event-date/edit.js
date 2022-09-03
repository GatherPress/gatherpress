/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';

const formatDate = ( start, end ) => {
	const dateFormat = 'dddd, MMMM D, YYYY';
	const timeFormat = 'h:mm A';
	const timeZoneFormat = 'z';
	// eslint-disable-next-line no-undef
	const timeZone = GatherPress.event_datetime.timezone;
	const startFormat = dateFormat + ' ' + timeFormat;
	let endFormat = dateFormat + ' ' + timeFormat + ' ' + timeZoneFormat;

	if ( moment( start ).format( dateFormat ) === moment( end ).format( dateFormat ) ) {
		endFormat = timeFormat + ' ' + timeZoneFormat;
	}

	return moment( start ).format( startFormat ) + ' to ' + moment.tz( end, timeZone ).format( endFormat );
};

const Edit = () => {
	const blockProps = useBlockProps();
	// eslint-disable-next-line no-undef
	const [ dateTimeEnd, setDateTimeEnd ] = useState( GatherPress.event_datetime.datetime_end );
	// eslint-disable-next-line no-undef
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
};

export default Edit;
