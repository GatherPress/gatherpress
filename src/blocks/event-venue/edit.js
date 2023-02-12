/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
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
import MapEmbed from '../../components/MapEmbed';
import VenueInformation from '../../components/VenueInformation';

const Edit = ({ attributes, setAttributes }) => {
	const { mapHeight, mapShow, mapType, mapZoomLevel } = attributes;

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
			slug: venueSlug ?? '',
		});
	});

	const Venue = ({ venue }) => {
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

		return (
			<div className="gp-venue">
				<VenueInformation
					name={name}
					fullAddress={fullAddress}
					phoneNumber={phoneNumber}
					website={website}
				/>
				{fullAddress && mapShow && (
					<MapEmbed
						location={fullAddress}
						zoom={mapZoomLevel}
						type={mapType}
						height={mapHeight}
					/>
				)}
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
								mapShow
									? __('Display the map', 'gatherpress')
									: __('Hide the map', 'gatherpress')
							}
							checked={mapShow}
							onChange={(value) =>
								setAttributes({ mapShow: value })
							}
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
						min={50}
						max={1000}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<Venue venue={venuePost} />
			</div>
		</>
	);
};

export default Edit;
