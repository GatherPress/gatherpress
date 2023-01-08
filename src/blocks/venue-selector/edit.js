/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

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
	TextareaControl,
	TextControl,
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';

import VenueInformation from './venue-info';

import GoogleMapEmbed from './googlemap'

const Edit = ({ attributes, setAttributes, clientId }) => {
	const {
		mapId,
		location,
		zoom,
		type,
		deskHeight,
		tabHeight,
		mobileHeight,
		device,
	} = attributes;

	const blockProps = useBlockProps();

	const [venueId, setVenueId] = useState('');

	Listener({ setVenueId });

	useEffect(() => {
		setAttributes({
			venueId: venueId ?? '',
		});
	});

	const VenueSelector = ({ id }) => {
		const venuePost = useSelect((select) =>
			select('core').getEntityRecord('postType', 'gp_venue', id)
		);

		let jsonString = venuePost?.meta._venue_information ?? '{}';
		jsonString = '' !== jsonString ? jsonString : '{}';

		const venueInformation = JSON.parse(jsonString);
		const fullAddress = venueInformation?.fullAddress ?? '';
		const phoneNumber = venueInformation?.phoneNumber ?? '';
		const website = venueInformation?.website ?? '';
		const name =
			venuePost?.title.rendered ??
			__('No venue selected.', 'gatherpress');

		return (
			<VenueInformation
				name={name}
				fullAddress={fullAddress}
				phoneNumber={phoneNumber}
				website={website}
			/>
		);
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Map Settings', 'gatherpress')}>
					<TextareaControl
						label={__('Location Name', 'gatherpress')}
						value={location}
						onChange={(place) => setAttributes({ location: place })}
						placeholder={__('Enter a location', 'gatherpress')}
					/>
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
							isSmall={true}
							isPressed={device === 'desktop'}
							onClick={() =>
								setAttributes({
									device: 'desktop',
								})
							}
						>
							<span className="dashicons dashicons-desktop"></span>
						</Button>
						<Button
							isSmall={true}
							isPressed={device === 'tablet'}
							onClick={() =>
								setAttributes({
									device: 'tablet',
								})
							}
						>
							<span className="dashicons dashicons-tablet"></span>
						</Button>
						<Button
							isSmall={true}
							isPressed={device === 'mobile'}
							onClick={() =>
								setAttributes({
									device: 'mobile',
								})
							}
						>
							<span className="dashicons dashicons-smartphone"></span>
						</Button>
					</ButtonGroup>
					{device === 'desktop' && (
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
					{device === 'tablet' && (
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
					{device === 'mobile' && (
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
				<VenueSelector id={venueId} />
				<GoogleMapEmbed
					location={location}
					zoom={zoom}
					type={type}
					height={deskHeight}
					className={`emb__height_${mapId}`}
				/>
			</div>
		</>
	);
};

export default Edit;
