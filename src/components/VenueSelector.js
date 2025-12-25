/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { PanelRow, SelectControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../helpers/broadcasting';

/**
 * VenueSelector component for GatherPress.
 *
 * This component is responsible for selecting a venue for an event in the GatherPress application.
 * It includes a dropdown menu with a list of available venues, and it updates the event's venue
 * information based on the selected venue. It manages the state for venue-related data such as
 * name, fullAddress, phoneNumber, website, and isOnlineEventTerm. The selected venue is stored as a
 * term associated with the event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueSelector = () => {
	const [ venue, setVenue ] = useState( '' );
	const editPost = useDispatch( 'core/editor' ).editPost;
	const { unlockPostSaving } = useDispatch( 'core/editor' );
	const venueTermId = useSelect( ( select ) =>
		select( 'core/editor' ).getEditedPostAttribute( '_gatherpress_venue' ),
	);
	const venueTerm = useSelect( ( select ) =>
		select( 'core' ).getEntityRecord(
			'taxonomy',
			'_gatherpress_venue',
			venueTermId,
		),
	);
	const slug = venueTerm?.slug.replace( /^_/, '' );
	const [ venueSlug, setVenueSlug ] = useState( '' );
	const venueValue = venueTermId + ':' + venueSlug;
	const venuePost = useSelect( ( select ) =>
		select( 'core' ).getEntityRecords( 'postType', 'gatherpress_venue', {
			per_page: 1,
			slug: venueSlug,
		} ),
	);

	const { updateVenueLatitude, updateVenueLongitude } =
		useDispatch( 'gatherpress/venue' );

	useEffect( () => {
		let venueInformation = {};

		if ( venueSlug && Array.isArray( venuePost ) ) {
			const jsonString =
				venuePost[ 0 ]?.meta?.gatherpress_venue_information ?? '{}';

			if ( jsonString ) {
				venueInformation = JSON.parse( jsonString );
				venueInformation.name = venuePost[ 0 ]?.title.rendered ?? '';
			}
		}

		const nameUpdated =
			venueInformation?.name ?? __( 'No venue selected.', 'gatherpress' );
		const fullAddressUpdated = venueInformation?.fullAddress ?? '';
		const phoneNumberUpdated = venueInformation?.phoneNumber ?? '';
		const websiteUpdated = venueInformation?.website ?? '';
		const latitudeUpdated = venueInformation?.latitude ?? '0';
		const longitudeUpdated = venueInformation?.longitude ?? '0';

		// Will unset the venue if slug is `undefined` here.
		if ( slug ) {
			setVenueSlug( slug );
		}

		setVenue( venueValue ? String( venueValue ) : '' );

		updateVenueLatitude( latitudeUpdated );
		updateVenueLongitude( longitudeUpdated );

		Broadcaster( {
			setName: nameUpdated,
			setFullAddress: fullAddressUpdated,
			setPhoneNumber: phoneNumberUpdated,
			setWebsite: websiteUpdated,
			setLatitude: latitudeUpdated,
			setLongitude: longitudeUpdated,
			setIsOnlineEventTerm: 'online-event' === venueSlug,
		} );
	}, [
		venueSlug,
		venuePost,
		slug,
		venueValue,
		updateVenueLatitude,
		updateVenueLongitude,
	] );

	let venues = useSelect( ( select ) => {
		return select( 'core' ).getEntityRecords(
			'taxonomy',
			'_gatherpress_venue',
			{
				per_page: -1,
				context: 'view',
			},
		);
	}, [] );

	if ( venues ) {
		venues = venues.map( ( item ) => ( {
			label: item.name,
			value: item.id + ':' + item.slug.replace( /^_/, '' ),
		} ) );

		venues.unshift( {
			value: ':',
			label: __( 'Choose a venue', 'gatherpress' ),
		} );
	} else {
		venues = [];
	}

	const updateTerm = ( value ) => {
		setVenue( value );
		value = value.split( ':' );

		const term = '' === value[ 0 ] ? [] : [ value[ 0 ] ];

		editPost( { _gatherpress_venue: term } );
		setVenueSlug( value[ 1 ] );
		unlockPostSaving();
	};

	return (
		<PanelRow>
			<SelectControl
				label={ __( 'Venue Selector', 'gatherpress' ) }
				value={ venue }
				onChange={ ( value ) => {
					updateTerm( value );
				} }
				options={ venues }
			/>
		</PanelRow>
	);
};

export default VenueSelector;
