/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';
import VenueInformation from '../../components/VenueInformation';

const Edit = ( props ) => {
	const { setAttributes } = props;
	const blockProps = useBlockProps();
	const [ venueId, setVenueId ] = useState( '' );

	Listener( { setVenueId } );

	useEffect( () => {
		setAttributes( {
			venueId: venueId ?? '',
		} );
	} );

	const Venue = ( { id } ) => {
		const venuePost = useSelect(
			( select ) => select( 'core' ).getEntityRecord( 'postType', 'gp_venue', id ),
		);
		let jsonString = venuePost?.meta._venue_information ?? '{}';
		jsonString = ( '' !== jsonString ) ? jsonString : '{}';

		const venueInformation = JSON.parse( jsonString );
		const fullAddress = venueInformation?.fullAddress ?? '';
		const phoneNumber = venueInformation?.phoneNumber ?? '';
		const website = venueInformation?.website ?? '';

		return (
			<div className="gp-venue">
				<div className="has-medium-font-size">
					<strong>{ venuePost?.title.rendered }</strong>
				</div>
				<VenueInformation fullAddress={ fullAddress } phoneNumber={ phoneNumber } website={ website } />
			</div>
		);
	};

	return (
		<div { ...blockProps }>
			<Venue id={ venueId } />
		</div>
	);
};

export default Edit;
