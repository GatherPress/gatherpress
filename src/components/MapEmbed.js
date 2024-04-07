/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import GoogleMap from './GoogleMap';
import LeafletMap from './LeafletMap';

/**
 * MapEmbed component for GatherPress.
 *
 * This component is used to embed a Google Map with specified location,
 * zoom level, map type, and height.
 *
 * @since 1.0.0
 *
 * @param {Object} props              - Component properties.
 * @param {string} props.location     - The location to be displayed on the map.
 * @param {number} [props.zoom=10]    - The zoom level of the map.
 * @param {string} [props.type='m']   - The type of the map (e.g., 'm' for roadmap).
 * @param {number} [props.height=300] - The height of the map container.
 * @param {string} [props.className]  - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered React component.
 */
const MapEmbed = (props) => {
	const isAdmin = select('core')?.canUser('create', 'posts');
	const isPostEditor = Boolean(select('core/edit-post'));
	const { zoom, type, className } = props;
	let { location, height } = props;

	const mapType = 'leaflet'; // test value

	if (!height) {
		height = 300;
	}

	if (isAdmin && !isPostEditor && !location) {
		location = '660 4th Street #119 San Francisco CA 94107, USA';
	}

	if (!location) {
		return <></>;
	} else if (mapType === 'google') {
		return (
			<GoogleMap
				location={location}
				className={className}
				zoom={zoom}
				type={type}
				height={height}
			/>
		);
	} else if (mapType === 'leaflet') {
		return (
			<LeafletMap
				location={location}
				className={className}
				zoom={zoom}
				height={height}
			/>
		);
	}

	return <></>;
};

export default MapEmbed;
