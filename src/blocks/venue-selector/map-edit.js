import { __ } from '@wordpress/i18n';

import {
	InspectorControls, useBlockProps
} from '@wordpress/block-editor';

import { Fragment } from '@wordpress/element';

import {
	Button,
	ButtonGroup,
	PanelBody,
	RadioControl,
	RangeControl,
	TextareaControl
} from '@wordpress/components';

// editor style
import './editor.scss';

import GoogleMapEmbed from './googlemap';

export default function Edit({ attributes, setAttributes, clientId }) {
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

	// set unique id
	setAttributes({
		mapId: clientId.slice(0, 8),
	});

	const blockProps = useBlockProps({
		className: `${attributes.align}`
	});

	return (
		<Fragment>
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
				<GoogleMapEmbed
					location={location}
					zoom={zoom}
					type={type}
					height={deskHeight}
					className={`emb__height_${mapId}`}
				/>
			</div>
		</Fragment>
	);
}
