import { useBlockProps } from '@wordpress/block-editor';

import GoogleMap from './googlemap';

export default function save({ attributes }) {
	const { id, align, location, zoom, type, deskHeight} =
		attributes;

	const blockProps = useBlockProps.save({
		className: `${align}`
	});

	return (
		<div {...blockProps}>
			<GoogleMap
				location={location}
				zoom={zoom}
				type={type}
				height={deskHeight}
				className={`emb__height_${id}`}
			/>
		</div>
	);
}
