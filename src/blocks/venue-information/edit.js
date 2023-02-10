/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	ButtonGroup,
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
import MapEmbed from '../../helpers/map-embed';
import VenueInformation from '../../components/VenueInformation';

import './editor.scss';

const Edit = ({ attributes, setAttributes, isSelected }) => {
	const {
		showVenueMap,
		fullAddress,
		phoneNumber,
		website,
		zoomVenueMap,
		typeVenueMap,
		deskHeight,
		tabHeight,
		mobileHeight,
		device,
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
								showVenueMap
									? __('Display the map', 'gatherpress')
									: __('Hide the map', 'gatherpress')
							}
							checked={showVenueMap}
							onChange={(value) => {
								setAttributes({ showVenueMap: value });
							}}
						/>
					</PanelRow>
					<RangeControl
						label={__('Zoom Level', 'gatherpress')}
						beforeIcon="search"
						value={zoomVenueMap}
						onChange={(value) =>
							setAttributes({ zoomVenueMap: value })
						}
						min={1}
						max={22}
					/>
					<RadioControl
						label={__('Map Type', 'gatherpress')}
						selected={typeVenueMap}
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
							setAttributes({ typeVenueMap: value });
						}}
					/>
					<ButtonGroup
						style={{ marginBottom: '10px', float: 'right' }}
					>
						<Button
							label={__('Desktop view', 'gatherpress')}
							isSmall={true}
							isPressed={'desktop' === device}
							onClick={() =>
								setAttributes({
									device: 'desktop',
								})
							}
						>
							<span className="dashicons dashicons-desktop"></span>
						</Button>
						<Button
							label={__('Tablet view', 'gatherpress')}
							isSmall={true}
							isPressed={'tablet' === device}
							onClick={() =>
								setAttributes({
									device: 'tablet',
								})
							}
						>
							<span className="dashicons dashicons-tablet"></span>
						</Button>
						<Button
							label={__('Mobile view', 'gatherpress')}
							isSmall={true}
							isPressed={'mobile' === device}
							onClick={() =>
								setAttributes({
									device: 'mobile',
								})
							}
						>
							<span className="dashicons dashicons-smartphone"></span>
						</Button>
					</ButtonGroup>
					{'desktop' === device && (
						<RangeControl
							label={__('Map Height', 'gatherpress')}
							beforeIcon="desktop"
							value={deskHeight}
							onChange={(height) =>
								setAttributes({ deskHeight: height })
							}
							min={1}
							max={2000}
						/>
					)}
					{'tablet' === device && (
						<RangeControl
							label={__('Map Height', 'gatherpress')}
							beforeIcon="tablet"
							value={tabHeight}
							onChange={(height) =>
								setAttributes({ tabHeight: height })
							}
							min={1}
							max={2000}
						/>
					)}
					{'mobile' === device && (
						<RangeControl
							label={__('Map Height', 'gatherpress')}
							beforeIcon="smartphone"
							value={mobileHeight}
							onChange={(height) =>
								setAttributes({ mobileHeight: height })
							}
							min={1}
							max={2000}
						/>
					)}
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
					{showVenueMap && (
						<MapEmbed
							location={fullAddress}
							zoom={zoomVenueMap}
							type={typeVenueMap}
							height={deskHeight}
						/>
					)}
				</div>
			</div>
		</>
	);
};

export default Edit;
