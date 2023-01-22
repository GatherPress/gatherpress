/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	ButtonGroup,
	Flex,
	FlexBlock,
	FlexItem,
	Icon,
	PanelBody,
	RadioControl,
	RangeControl,
	TextControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import VenueInformation from '../../components/VenueInformation';

import MapEmbed from '../../helpers/map-embed';

import './editor.scss';

const Edit = ({ attributes, clientId, isSelected, setAttributes }) => {
	const {
		mapId,
		fullAddress,
		phoneNumber,
		website,
		zoom,
		type,
		encodedAddressURL,
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

	const baseUrl = 'https://maps.google.com/maps';
	const params = new URLSearchParams({
		q: fullAddress,
		z: 10,
		t: 'm',
		output: 'embed',
	});
	const encodedMapURL = baseUrl + '?' + params.toString();

	useEffect(() => {
		setAttributes({
			encodedAddressURL: encodedMapURL ?? '',
			fullAddress: venueInformationMetaData.fullAddress,
			phoneNumber: venueInformationMetaData.phoneNumber ?? '',
			website: venueInformationMetaData.website ?? '',
			mapId: clientId,
		});
	}, []);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Venue Location', 'gatherpress')}
					initialOpen={ true }
				>
					<TextControl
						label={__('Venue Street Address', 'gatherpress')}
						value={fullAddress}
						onChange={(place) => {
							setAttributes({ fullAddress: place })
							onUpdate( 'fullAddress', place )
						}}
						placeholder={__('Enter address', 'gatherpress')}
					/>
				</PanelBody>
				<PanelBody
					title={__('Venue Settings', 'gatherpress')}
					initialOpen={ false }
				>
					<RangeControl
						label={__('Zoom Level', 'gatherpress')}
						beforeIcon="search"
						value={zoom}
						onChange={(value) => setAttributes({ zoom: value })}
						min={1}
						max={22}
					/>
					<RadioControl
						label={__('Map Type', 'gatherpress')}
						selected={type}
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
							setAttributes({ type: value });
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
				{!isSelected && (
					<div className="gp-venue">
						<Flex justify="normal">
							<FlexItem display="flex">
								<Icon icon="location" />
							</FlexItem>
							<FlexItem>
								<em>
									{( 
										fullAddress ?
										fullAddress :
										__(
											'Add venue information.',
											'gatherpress'
										)
									)}
								</em>
							</FlexItem>
						</Flex>
						<MapEmbed
							location={fullAddress}
							zoom={zoom}
							type={type}
							height={deskHeight}
							className={`emb__height_${mapId}`}
						/>
					</div>
				)}
				{isSelected && (
					<div className="gp-venue">
						<Flex justify="normal">
							<FlexItem display="flex">
								<Icon icon="location" />
							</FlexItem>
							<FlexItem>
								<em>
									{( fullAddress ?

										fullAddress :
										__(
											'Add venue information.',
											'gatherpress'
										)
									)}
								</em>
							</FlexItem>
						</Flex>
						<Flex>
							<FlexBlock>
								<MapEmbed
									location={fullAddress}
									zoom={zoom}
									type={type}
									height={deskHeight}
									className={`emb__height_${mapId}`}
								/>
							</FlexBlock>
						</Flex>
					</div>
				)}
			</div>
			{JSON.stringify(attributes)}
		</>
	);
};

export default Edit;
