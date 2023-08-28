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
import VenueSelector from '../../components/VenueSelector';
import OnlineEventLink from '../../components/OnlineEventLink';

const Edit = ({ attributes, setAttributes }) => {
	const { mapHeight, mapShow, mapType, mapZoomLevel } = attributes;
	const blockProps = useBlockProps();
	const [venueSlug, setVenueSlug] = useState('');
	const venueTermId = useSelect((select) =>
		select('core/editor').getEditedPostAttribute('_gp_venue')
	);
	const venueTerm = useSelect((select) =>
		select('core').getEntityRecord('taxonomy', '_gp_venue', venueTermId)
	);

	let onlineEventTerm = null;

	if ('online-event' === venueTerm?.slug) {
		onlineEventTerm = venueTerm;
	}

	let venuePost = useSelect((select) =>
		select('core').getEntityRecords('postType', 'gp_venue', {
			per_page: 1,
			slug: venueSlug,
		})
	);

	let slug = null;

	if (venueTerm) {
		// Convert venue term slug to venue post type slug by removing leading underscore.
		slug = venueTerm.slug.replace(/^_/, '');
	}

	useEffect(() => {
		setVenueSlug(slug);
	}, [slug]);

	Listener({ setVenueSlug });

	if (!venueSlug) {
		venuePost = null;
	}

	const Venue = ({ venue, onlineEvent }) => {
		let jsonString = '';

		if (Array.isArray(venue)) {
			venue = venue[0];
			jsonString = venue?.meta?._venue_information ?? '{}';
		}

		let name =
			venue?.title.rendered ?? __('No venue selected.', 'gatherpress');

		if (onlineEvent) {
			name = onlineEvent?.name ?? name;
		}

		jsonString = '' !== jsonString ? jsonString : '{}';

		const venueInformation = JSON.parse(jsonString);
		const fullAddress = venueInformation?.fullAddress ?? '';
		const phoneNumber = venueInformation?.phoneNumber ?? '';
		const website = venueInformation?.website ?? '';

		return (
			<div className="gp-venue">
				<VenueInformation
					name={name}
					fullAddress={fullAddress}
					phoneNumber={phoneNumber}
					website={website}
					onlineEvent={onlineEvent}
				/>
				{fullAddress && mapShow && !onlineEvent && (
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
					title={__('Venue Settings', 'gatherpress')}
					initialOpen={true}
				>
					<PanelRow>
						<VenueSelector />
					</PanelRow>
					{onlineEventTerm && (
						<PanelRow>
							<OnlineEventLink />
						</PanelRow>
					)}
				</PanelBody>
				{!onlineEventTerm && (
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
				)}
			</InspectorControls>
			<div {...blockProps}>
				<Venue venue={venuePost} onlineEvent={onlineEventTerm} />
			</div>
		</>
	);
};

export default Edit;
