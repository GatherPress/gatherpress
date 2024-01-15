/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	RadioControl,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
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
const Edit = ({ attributes, setAttributes, isSelected }) => {
	const { mapShow, mapZoomLevel, mapType, mapHeight } = attributes;
	const [name, setName] = useState('');
	const [fullAddress, setFullAddress] = useState('');
	const [phoneNumber, setPhoneNumber] = useState('');
	const [website, setWebsite] = useState('');
	const [isOnlineEventTerm, setIsOnlineEventTerm] = useState(false);
	const blockProps = useBlockProps();
	const onlineEventLink = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				._online_event_link
	);

	let venueInformationMetaData = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				._venue_information
	);

	if (venueInformationMetaData) {
		venueInformationMetaData = JSON.parse(venueInformationMetaData);
	} else {
		venueInformationMetaData = {};
	}

	Listener({
		setName,
		setFullAddress,
		setPhoneNumber,
		setWebsite,
		setIsOnlineEventTerm,
	});

	useEffect(() => {
		if (isVenuePostType()) {
			setFullAddress(venueInformationMetaData.fullAddress);
			setPhoneNumber(venueInformationMetaData.phoneNumber);
			setWebsite(venueInformationMetaData.website);

			if (!fullAddress && !phoneNumber && !website) {
				setName(__('Add venue information.', 'gatherpress'));
			} else {
				setName('');
			}
		}

		if (isEventPostType()) {
			if (!fullAddress && !phoneNumber && !website) {
				setName(__('No venue selected.', 'gatherpress'));
			} else {
				setName('');
			}
		}
	}, [
		venueInformationMetaData.fullAddress,
		venueInformationMetaData.phoneNumber,
		venueInformationMetaData.website,
		fullAddress,
		phoneNumber,
		website,
	]);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Venue settings', 'gatherpress')}
					initialOpen={true}
				>
					<PanelRow>
						{!isVenuePostType() && <VenueSelector />}
						{isVenuePostType() && <VenueInformation />}
					</PanelRow>
					{isOnlineEventTerm && (
						<PanelRow>
							<OnlineEventLink />
						</PanelRow>
					)}
				</PanelBody>
				{!isOnlineEventTerm && (
					<PanelBody
						title={__('Map settings', 'gatherpress')}
						initialOpen={true}
					>
						<PanelRow>
							{__('Show map on venue', 'gatherpress')}
						</PanelRow>
						<PanelRow>
							<ToggleControl
								label={
									mapShow
										? __('Display the map', 'gatherpress')
										: __('Hide the map', 'gatherpress')
								}
								checked={mapShow}
								onChange={(value) => {
									setAttributes({ mapShow: value });
								}}
							/>
						</PanelRow>
						<RangeControl
							label={__('Zoom level', 'gatherpress')}
							beforeIcon="search"
							value={mapZoomLevel}
							onChange={(value) =>
								setAttributes({ mapZoomLevel: value })
							}
							min={1}
							max={22}
						/>
						<RadioControl
							label={__('Map type', 'gatherpress')}
							selected={mapType}
							options={[
								{
									label: __('Roadmap', 'gatherpress'),
									value: 'm',
								},
								{
									label: __('Satellite', 'gatherpress'),
									value: 'k',
								},
							]}
							onChange={(value) => {
								setAttributes({ mapType: value });
							}}
						/>
						<RangeControl
							label={__('Map height', 'gatherpress')}
							beforeIcon="location"
							value={mapHeight}
							onChange={(height) =>
								setAttributes({ mapHeight: height })
							}
							min={100}
							max={1000}
						/>
					</PanelBody>
				)}
			</InspectorControls>
			<div {...blockProps}>
				<EditCover isSelected={isSelected}>
					<div className="gp-venue">
						<VenueOrOnlineEvent
							name={name}
							fullAddress={fullAddress}
							phoneNumber={phoneNumber}
							website={website}
							isOnlineEventTerm={isOnlineEventTerm}
							onlineEventLink={onlineEventLink}
						/>
						{mapShow && fullAddress && (
							<MapEmbed
								location={fullAddress}
								zoom={mapZoomLevel}
								type={mapType}
								height={mapHeight}
							/>
						)}
					</div>
				</EditCover>
			</div>
		</>
	);
};

export default Edit;
