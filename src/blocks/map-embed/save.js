import { useBlockProps } from '@wordpress/block-editor';

import GoogleMapEmbed from './googlemap';

export default function save({ attributes }) {
	const { mapId, align, location, zoom, type, deskHeight} =
		attributes;

	const blockProps = useBlockProps.save();

	return (
		<div {...blockProps}>
			<GoogleMapEmbed
				location={location}
				zoom={zoom}
				type={type}
				height={deskHeight}
				className={`embed-height_${mapId}`}
			/>
		</div>
	);
}
