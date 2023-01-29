
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { SelectControl } from '@wordpress/components';


export const VenueQuery = ({ attributes, setAttributes }) => {
	// querying venues
	const { venues } = useSelect( ( select ) => {
		const { getEntityRecords } = select( 'core' );

		// Query args
		const query = {
			status: 'publish'
		}

		return {
			venues: getEntityRecords( 'postType', 'gp_venue', query ),
		}
	} )

	// populate options for <SelectControl>
	let options = [];
	if( venues ) {
		options.push( {
			value: 0,
			label: 'Select a venue'
		} )
		venues.forEach( ( venue ) => {
			options.push( {
				value : venue.id,
				label : venue.title.rendered
			} )
		})
	} else {
		options.push( {
			value: 0,
			label: 'Loading...'
		} )
	}

	// display select dropdown
	return (
		<>
			<SelectControl
				label="Select a venue"
				options={ options }
			/>
		</>
	)
};
// export default VenueQuery;
