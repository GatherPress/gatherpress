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
	__experimentalInputControl as InputControl,
	TextControl,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import MapEmbed from '../../helpers/map-embed';

import './editor.scss';

const Edit = ({ attributes, isSelected, setAttributes }) => {
	const {
		fullAddress,
		phoneNumber,
		website,
		zoom,
		type,
		deskHeight,
		tabHeight,
		mobileHeight,
		device,
	} = attributes;

	const blockProps = useBlockProps();
	const editPost = useDispatch('core/editor').editPost;
	const [editFullAddress, setEditFullAddress] = useState(false);
	const [editPhoneNumber, setEditPhoneNumber] = useState(false);
	const [editWebsite, setEditWebsite] = useState(false);

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
					<div className="gp-venue">
						<Flex justify="normal">
							<FlexItem display="flex">
								<Icon icon="location" />
							</FlexItem>
							<FlexItem>
								{ editFullAddress && ( 
										<InputControl
											style={{ width: '300px' }}
											isPressEnterToChange={true}
											value={ fullAddress }
											onChange={(place) => {
												setAttributes({ fullAddress: place });
												onUpdate('fullAddress', place);
												setEditFullAddress(false);
											}}
								/>
								) }
								{ ! editFullAddress && (
									<em>
										<a href="#" onClick={() =>setEditFullAddress(true)}>
										{fullAddress
											? fullAddress
											: __(
													'Full Address',
													'gatherpress'
											)} 
										</a>
									</em>
								) }
							</FlexItem>
						</Flex>
						<Flex justify="normal">
							<FlexItem display="flex">
								<Icon icon="phone" />
							</FlexItem>
							<FlexItem>
								{ ! editPhoneNumber && (
									<em> 
										<a href="#" onClick={() =>setEditPhoneNumber(true)}>
											{phoneNumber
											? phoneNumber
											: __(
												'Phone Number',
												'gatherpress'
													)} 
										</a>
									</em>
								 ) }
								 { editPhoneNumber && (
								 	<InputControl
										isPressEnterToChange={true}
										value={ phoneNumber }
										onChange={(number) => {
											setAttributes({ phoneNumber: number });
											onUpdate('phoneNumber', number)
											setEditPhoneNumber(false);
										}}
									/>
								) }
							</FlexItem>
							<FlexItem display="flex">
								<Icon icon="admin-site-alt3" />
							</FlexItem>
							<FlexItem>
								{ editWebsite && ( 
									<InputControl
										isPressEnterToChange={true}
										value={website}
										onChange={(url) => {
											setAttributes({ website: url });
											onUpdate('website', url);
											setEditWebsite(false);
										}}
									/>
								) }
								{ ! editWebsite && (
									<em> 
										<a href="#" onClick={() =>setEditWebsite(true)}>
											{website
											? website
											: __(
												'Website',
												'gatherpress'
												)}
										</a>
									</em>
								) }
							</FlexItem>
						</Flex>
						<MapEmbed
							location={fullAddress}
							zoom={zoom}
							type={type}
							height={deskHeight}
						/>
					</div>
			</div>
		</>
	);
};

export default Edit;
