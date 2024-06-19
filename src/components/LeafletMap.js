/**
 * External dependencies.
 */
import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet';
import { useEffect } from '@wordpress/element';

/**
 * LeafletMap component for GatherPress.
 *
 * This component is used to embed a Leaflet Map with specified location,
 * zoom level, and height.
 *
 * @since 1.0.0
 *
 * @param {Object} props              - Component properties.
 * @param {string} props.location     - The location to be displayed on the map.
 * @param {string} props.latitude     - The latitdue of the location to be displayed on the map.
 * @param {string} props.longitude    - The longitude of the location to be displayed on the map.
 * @param {number} [props.zoom=10]    - The zoom level of the map.
 * @param {number} [props.height=300] - The height of the map container.
 * @param {string} [props.className]  - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered React component.
 */
const LeafletMap = (props) => {
	const { zoom, className, location, height, latitude, longitude } = props;
	const style = { height };
	const position = [latitude, longitude];

	function FlyMapTo() {
		const map = useMap();

		useEffect(() => {
			map.setView(position, zoom);
		}, [map]);

		return null;
	}

	return (
		<MapContainer
			style={style}
			className={className}
			center={position}
			zoom={zoom}
			scrollWheelZoom={false}
			height={height}
		>
			<TileLayer
				attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
				url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
			/>
			<Marker position={position}>
				<Popup>{location}</Popup>
			</Marker>
			<FlyMapTo />
		</MapContainer>
	);
};

export default LeafletMap;
