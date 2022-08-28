/**
 * WordPress dependencies.
 */

import { __ } from '@wordpress/i18n';
import {  Flex, FlexBlock, FlexItem, Icon, TextControl } from '@wordpress/components';
import { SelectControl, PanelRow } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
const VenuePanel = ( props ) => {
	const { venue, setVenue } = props;

	let venues = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords(
			'postType',
			'gp_venue',
			{
				per_page: -1,
				context: 'view',
			},
		);
	}, [ venue ] );

	if ( venues ) {
		venues = venues.map( ( item ) => ( {
			label: item.title.rendered,
			value: item.id || item.value,
		} ) );

		venues.unshift( { value: '', label: __( 'Choose a venue', 'gatherpress' ), disabled: true } );
	} else {
		venues = [];
	}

	return(
		<PanelRow>
			<Flex>
				<FlexItem>
					{ __( 'Venue', 'gatherpress' ) }
				</FlexItem>
				<FlexItem>
					<SelectControl
						label={ __( 'Venue', 'gatherpress' ) }
						hideLabelFromVision="true"
						value={ venue }
						onChange={ ( value ) => setVenue( value ) }
						options={ venues }
						style={{ width: '10rem' }}
					/>
				</FlexItem>
			</Flex>
		</PanelRow>
	);
}

export default VenuePanel;
