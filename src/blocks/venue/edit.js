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
	const { showEventMap, zoomEventMap, typeEventMap, deskHeight, tabHeight, mobileHeight, device } =
		attributes;

	const blockProps = useBlockProps();
	const [venueId, setVenueId] = useState('');

	Listener({ setVenueId });

	useEffect(() => {
		setAttributes({
			venueId: venueId ?? '',
		});
	});

	const VenueSelector = ({ deskHeight, id, showEventMap, typeEventMap, zoomEventMap }) => {
		const venuePost = useSelect((select) =>
			select('core').getEntityRecord('postType', 'gp_venue', id)
		);

		let jsonString = venuePost?.meta._venue_information ?? '{}';
		jsonString = '' !== jsonString ? jsonString : '{}';

		const venueInformation = JSON.parse(jsonString);
		const fullAddress = venueInformation?.fullAddress ?? '';

		const name =
			venuePost?.title.rendered ??
			__('No venue selected.', 'gatherpress');

		useEffect(() => {
			setAttributes({
				venueName: name,
				venueAddress: fullAddress,
			});
		});

		return (
			<div>
				<p>{name}</p>
				{/* {showEventMap && ( */}
					<MapEmbed
						location={fullAddress}
						zoomEventMap={zoomEventMap}
						type={typeEventMap}
						height={deskHeight}
					/>
				{/* )} */}
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
						onChange={(value) => setAttributes({ zoomEventMap: value })}
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
				<VenueSelector id={venueId} />
			</div>
		</>
	);
};

export default Edit;
