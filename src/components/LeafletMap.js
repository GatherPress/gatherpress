/**
 * External dependencies.
 */
import { MapContainer, TileLayer, Marker, Popup } from 'react-leaflet';

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
 * @param {number} [props.zoom=10]    - The zoom level of the map.
 * @param {number} [props.height=300] - The height of the map container.
 * @param {string} [props.className]  - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered React component.
 */
const LeafletMap = (props) => {
	const { zoom, className, location, height } = props;
	const style = { height };

	//convert location to position here

	const testPostition = [51.505, -0.09]; // test value

	return (
		<MapContainer
			style={style}
			className={className}
			center={testPostition}
			zoom={zoom}
			scrollWheelZoom={false}
			height={height}
		>
			<TileLayer
				attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
				url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
			/>
			<Marker position={testPostition}>
				<Popup>{location}</Popup>
			</Marker>
		</MapContainer>
	);
};

export default LeafletMap;
