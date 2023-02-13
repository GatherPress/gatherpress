/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Flex,
	FlexItem,
	FlexBlock,
	Icon,
	PanelBody,
	PanelRow,
	RadioControl,
	RangeControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import MapEmbed from '../../components/MapEmbed';
import VenueInformation from '../../components/VenueInformation';

const Edit = ({ attributes, setAttributes, isSelected }) => {
	const {
		mapShow,
		fullAddress,
		phoneNumber,
		website,
		mapZoomLevel,
		mapType,
		mapHeight,
	} = attributes;

	const blockProps = useBlockProps();
	const editPost = useDispatch('core/editor').editPost;

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

	const onUpdate = (key, value) => {
		const payload = JSON.stringify({
			...venueInformationMetaData,
			[key]: value,
		});
		const meta = { _venue_information: payload };

		setAttributes({ [key]: value });
		editPost({ meta });
	};

	useEffect(() => {
		setAttributes({
			fullAddress: venueInformationMetaData.fullAddress,
			phoneNumber: venueInformationMetaData.phoneNumber ?? '',
			website: venueInformationMetaData.website ?? '',
		});
	}, []);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Map Settings', 'gatherpress')}
					initialOpen={true}
				>
					<PanelRow>
						{__('Show map on Venue', 'gatherpress')}
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
						label={__('Zoom Level', 'gatherpress')}
						beforeIcon="search"
						value={mapZoomLevel}
						onChange={(value) =>
							setAttributes({ mapZoomLevel: value })
						}
						min={1}
						max={22}
					/>
					<RadioControl
						label={__('Map Type', 'gatherpress')}
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
						label={__('Map Height', 'gatherpress')}
						beforeIcon="location"
						value={mapHeight}
						onChange={(height) =>
							setAttributes({ mapHeight: height })
						}
						min={100}
						max={1000}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<div className="gp-venue">
					{!isSelected && (
						<>
							{!fullAddress && !phoneNumber && !website && (
								<Flex justify="normal">
									<FlexItem display="flex">
										<Icon icon="location" />
									</FlexItem>
									<FlexItem>
										<em>
											{__(
												'Add venue information.',
												'gatherpress'
											)}
										</em>
									</FlexItem>
								</Flex>
							)}
							<VenueInformation
								fullAddress={fullAddress}
								phoneNumber={phoneNumber}
								website={website}
							/>
						</>
					)}
					{isSelected && (
						<>
							<Flex>
								<FlexBlock>
									<TextControl
										label={__(
											'Full Address',
											'gatherpress'
										)}
										value={fullAddress}
										onChange={(value) => {
											onUpdate('fullAddress', value);
										}}
									/>
								</FlexBlock>
							</Flex>
							<Flex>
								<FlexBlock>
									<TextControl
										label={__(
											'Phone Number',
											'gatherpress'
										)}
										value={phoneNumber}
										onChange={(value) => {
											onUpdate('phoneNumber', value);
										}}
									/>
								</FlexBlock>
								<FlexBlock>
									<TextControl
										label={__('Website', 'gatherpress')}
										value={website}
										type="url"
										onChange={(value) => {
											onUpdate('website', value);
										}}
									/>
								</FlexBlock>
							</Flex>
						</>
					)}
					{mapShow && (
						<MapEmbed
							location={fullAddress}
							zoom={mapZoomLevel}
							type={mapType}
							height={mapHeight}
						/>
					)}
				</div>
			</div>
		</>
	);
};

export default Edit;
