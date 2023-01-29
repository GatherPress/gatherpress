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
	PanelBody,
	PanelRow,
	RadioControl,
	RangeControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';

import MapEmbed from '../../helpers/map-embed';

import './editor.scss';

const Edit = ({ attributes, setAttributes }) => {
	const {
		deskHeight,
		device,
		showEventMap,
		typeEventMap,
		venueAddress,
		zoomEventMap,
		tabHeight,
		mobileHeight,
	} = attributes;

	const blockProps = useBlockProps();
	const [venueSlug, setVenueSlug] = useState('');

	Listener({ setVenueSlug });

	let venuePost = useSelect((select) =>
		select('core').getEntityRecords('postType', 'gp_venue', {
			per_page: 1,
			slug: venueSlug,
		})
	);

	if (!venueSlug) {
		venuePost = null;
	}

	useEffect(() => {
		setAttributes({
			venueSlug: venueSlug ?? '',
		});
	});

	const VenueSelector = ({ venue }) => {
		if (venue) {
			venue = venue[0];
		}

		let jsonString = venue?.meta._venue_information ?? '{}';
		jsonString = '' !== jsonString ? jsonString : '{}';

		const venueInformation = JSON.parse(jsonString);
		const fullAddress = venueInformation?.fullAddress ?? '';
		const phoneNumber = venueInformation?.phoneNumber ?? '';
		const website = venueInformation?.website ?? '';

		const name =
			venue?.title.rendered ?? __('No venue selected.', 'gatherpress');

		useEffect(() => {
			setAttributes({
				venueName: name,
				venueAddress: fullAddress,
			});
		});

		return (
			<div>
				<p className="address-name">{name}</p>
				<p className="address-list">
					{fullAddress ? (
						<span className="dashicons dashicons-location"></span>
					) : (
						''
					)}{' '}
					{fullAddress}{' '}
					{phoneNumber ? (
						<span className="dashicons dashicons-phone"></span>
					) : (
						''
					)}{' '}
					{phoneNumber}{' '}
					{website ? (
						<span className="dashicons dashicons-admin-site-alt3"></span>
					) : (
						''
					)}{' '}
					{website}
				</p>
			</div>
		);
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Map Settings', 'gatherpress')}
					initialOpen={true}
				>
					<PanelRow>
						{__('Show map on Event', 'gatherpress')}
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={
								showEventMap
									? __('Display the map', 'gatherpress')
									: __('Hide the map', 'gatherpress')
							}
							checked={showEventMap}
							onChange={(value) =>
								setAttributes({ showEventMap: value })
							}
						/>
					</PanelRow>
					<RangeControl
						label={__('Zoom Level', 'gatherpress')}
						beforeIcon="search"
						value={zoomEventMap}
						onChange={(value) =>
							setAttributes({ zoomEventMap: value })
						}
						min={1}
						max={22}
					/>

					<RadioControl
						label={__('Map Type', 'gatherpress')}
						selected={typeEventMap}
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
							setAttributes({ typeEventMap: value });
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
				<VenueSelector venue={venuePost} />
				{venueAddress && showEventMap && (
					<MapEmbed
						location={venueAddress}
						zoom={zoomEventMap}
						type={typeEventMap}
						height={deskHeight}
					/>
				)}
			</div>
		</>
	);
};

export default Edit;
