/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	PanelBody,
	PanelRow,
	RadioControl,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import MapEmbed from '../../components/MapEmbed';
import VenueOrOnlineEvent from '../../components/VenueOrOnlineEvent';
import EditCover from '../../components/EditCover';
import { isVenuePostType } from '../../helpers/venue';
import VenueSelector from '../../components/VenueSelector';
import VenueInformation from '../../panels/venue-settings/venue-information';
import OnlineEventLink from '../../components/OnlineEventLink';
import { Listener } from '../../helpers/broadcasting';
import { isEventPostType } from '../../helpers/event';
import { getFromGlobal } from '../../helpers/globals';
import { isGatherPressPostType } from '../../helpers/editor';

/**
 * Edit component for the GatherPress Venue block.
 *
 * This component renders the edit view of the GatherPress Venue block in the WordPress block editor.
 * It provides an interface for users to add and configure venue information, including map settings.
 * The component includes controls for selecting a venue, entering venue details, and configuring map display options.
 *
 * @since 1.0.0
 *
 * @param {Object}   props               - Component properties.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to set block attributes.
 * @param {boolean}  props.isSelected    - Flag indicating if the block is selected in the editor.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { attributes, setAttributes, isSelected } ) => {
	const { mapZoomLevel, mapType, mapHeight } = attributes;
	const [ name, setName ] = useState( '' );
	const [ fullAddress, setFullAddress ] = useState( '' );
	const [ phoneNumber, setPhoneNumber ] = useState( '' );
	const [ website, setWebsite ] = useState( '' );
	const [ isOnlineEventTerm, setIsOnlineEventTerm ] = useState( false );
	const blockProps = useBlockProps();
	const mapPlatform = getFromGlobal( 'settings.mapPlatform' );

	const { latitude } = useSelect(
		( select ) => ( {
			latitude: select( 'gatherpress/venue' ).getVenueLatitude(),
		} ),
		[],
	);
	const { longitude } = useSelect(
		( select ) => ( {
			longitude: select( 'gatherpress/venue' ).getVenueLongitude(),
		} ),
		[],
	);

	const {
		updateVenueLatitude,
		updateVenueLongitude,
		updateMapCustomLatLong,
	} = useDispatch( 'gatherpress/venue' );

	const onlineEventLink = useSelect(
		( select ) =>
			select( 'core/editor' )?.getEditedPostAttribute( 'meta' )
				?.gatherpress_online_event_link,
	);

	let { mapShow, mapCustomLatLong } = attributes;

	useEffect( () => {
		updateMapCustomLatLong( mapCustomLatLong );
	}, [ mapCustomLatLong, updateMapCustomLatLong ] );

	const editPost = useDispatch( 'core/editor' ).editPost;
	const updateVenueMeta = ( metaData ) => {
		const payload = JSON.stringify( {
			...venueInformationMetaData,
			...metaData,
		} );
		const meta = { gatherpress_venue_information: payload };

		editPost( { meta } );
	};

	let venueInformationMetaData = useSelect(
		( select ) =>
			select( 'core/editor' )?.getEditedPostAttribute( 'meta' )
				?.gatherpress_venue_information,
	);

	if ( venueInformationMetaData ) {
		venueInformationMetaData = JSON.parse( venueInformationMetaData );
	} else {
		venueInformationMetaData = {};
	}

	if ( mapShow && fullAddress ) {
		mapShow = true;
	}

	if ( mapShow && ! isGatherPressPostType() ) {
		mapShow = true;
	}

	Listener( {
		setName,
		setFullAddress,
		setPhoneNumber,
		setWebsite,
		setIsOnlineEventTerm,
	} );

	useEffect( () => {
		if ( isVenuePostType() ) {
			setFullAddress( venueInformationMetaData.fullAddress );
			setPhoneNumber( venueInformationMetaData.phoneNumber );
			setWebsite( venueInformationMetaData.website );
			updateVenueLatitude( venueInformationMetaData.latitude );
			updateVenueLongitude( venueInformationMetaData.longitude );

			if ( ! fullAddress && ! phoneNumber && ! website ) {
				setName( __( 'Add venue information.', 'gatherpress' ) );
			} else {
				setName( '' );
			}
		}

		if ( isEventPostType() || ! isGatherPressPostType() ) {
			if ( ! fullAddress && ! phoneNumber && ! website ) {
				setName( __( 'No venue selected.', 'gatherpress' ) );
			} else {
				setName( '' );
			}
		}
	}, [
		venueInformationMetaData.fullAddress,
		venueInformationMetaData.phoneNumber,
		venueInformationMetaData.website,
		venueInformationMetaData.latitude,
		venueInformationMetaData.longitude,
		fullAddress,
		phoneNumber,
		website,
		latitude,
		longitude,
		updateVenueLongitude,
		updateVenueLatitude,
	] );

	useEffect( () => {
		// Trigger a window resize event
		const resizeEvent = new Event( 'resize' );
		window.dispatchEvent( resizeEvent );
	}, [ mapHeight ] );

	return (
		<>
			<InspectorControls>
				{ isGatherPressPostType() && (
					<PanelBody
						title={ __( 'Venue settings', 'gatherpress' ) }
						initialOpen={ true }
					>
						<PanelRow>
							{ ! isVenuePostType() && <VenueSelector /> }
							{ isVenuePostType() && <VenueInformation /> }
						</PanelRow>
						{ isOnlineEventTerm && (
							<PanelRow>
								<OnlineEventLink />
							</PanelRow>
						) }
					</PanelBody>
				) }
				{ ! isOnlineEventTerm && (
					<PanelBody
						title={ __( 'Map settings', 'gatherpress' ) }
						initialOpen={ true }
					>
						<PanelRow>
							{ __( 'Show map on venue', 'gatherpress' ) }
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={
									mapShow
										? __( 'Display the map', 'gatherpress' )
										: __( 'Hide the map', 'gatherpress' )
								}
								checked={ mapShow }
								onChange={ ( value ) => {
									setAttributes( { mapShow: value } );
								} }
							/>
						</PanelRow>
						<RangeControl
							label={ __( 'Zoom level', 'gatherpress' ) }
							beforeIcon="search"
							value={ mapZoomLevel }
							onChange={ ( value ) =>
								setAttributes( { mapZoomLevel: value } )
							}
							min={ 1 }
							max={ 22 }
						/>
						{ 'google' === mapPlatform && (
							<RadioControl
								label={ __( 'Map type', 'gatherpress' ) }
								selected={ mapType }
								options={ [
									{
										label: __( 'Roadmap', 'gatherpress' ),
										value: 'm',
									},
									{
										label: __( 'Satellite', 'gatherpress' ),
										value: 'k',
									},
								] }
								onChange={ ( value ) => {
									setAttributes( { mapType: value } );
								} }
							/>
						) }
						<RangeControl
							label={ __( 'Map height', 'gatherpress' ) }
							beforeIcon="location"
							value={ mapHeight }
							onChange={ ( height ) =>
								setAttributes( { mapHeight: height } )
							}
							min={ 100 }
							max={ 1000 }
						/>
						{ isVenuePostType() && (
							<PanelRow>
								{ __( 'Latitude / Longitude', 'gatherpress' ) }
							</PanelRow>
						) }
						{ isVenuePostType() && (
							<PanelRow>
								<ToggleControl
									label={
										mapCustomLatLong
											? __(
												'Use custom values',
												'gatherpress',
											)
											: __(
												'Use default values',
												'gatherpress',
											)
									}
									checked={ mapCustomLatLong }
									onChange={ ( value ) => {
										setAttributes( {
											mapCustomLatLong: value,
										} );
										updateMapCustomLatLong( value );
									} }
								/>
							</PanelRow>
						) }
						{ mapCustomLatLong && isVenuePostType() && (
							<>
								<NumberControl
									label={ __( 'Latitude', 'gatherpress' ) }
									value={ latitude }
									onChange={ ( value ) => {
										updateVenueLatitude( value );
										updateVenueMeta( { latitude: value } );
									} }
								/>
								<NumberControl
									label={ __( 'Longitude', 'gatherpress' ) }
									value={ longitude }
									onChange={ ( value ) => {
										updateVenueLongitude( value );
										updateVenueMeta( { longitude: value } );
									} }
								/>
							</>
						) }
					</PanelBody>
				) }
			</InspectorControls>

			<div { ...blockProps }>
				<EditCover isSelected={ isSelected }>
					<div className="gatherpress-venue">
						<VenueOrOnlineEvent
							name={ name }
							fullAddress={ fullAddress }
							phoneNumber={ phoneNumber }
							website={ website }
							isOnlineEventTerm={ isOnlineEventTerm }
							onlineEventLink={ onlineEventLink }
						/>
						{ mapShow && ! isOnlineEventTerm && (
							<MapEmbed
								mapCustomLatLong={ mapCustomLatLong }
								location={ fullAddress }
								latitude={ latitude }
								longitude={ longitude }
								zoom={ mapZoomLevel }
								type={ mapType }
								height={ mapHeight }
							/>
						) }
					</div>
				</EditCover>
			</div>
		</>
	);
};

export default Edit;
