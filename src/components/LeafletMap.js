/**
 * External dependencies.
 */
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
	console.log('is L defined', L);

	if (!latitude || !longitude) {
		return <></>;
	}

	useEffect(() => {
		if (typeof L === 'undefined') return;

		const map = L.map('map').setView([latitude, longitude], zoom);

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
		}).addTo(map);

		return () => {
			map.remove();
		};
	}, []);

	return (
		<div id="map" style={{ height: '400px' }}></div>
	);
};

export default LeafletMap;
