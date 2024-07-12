/**
 * External dependencies.
 */
import { sprintf, __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

/**
 * Internal Dependencies.
 */
import { getFromGlobal } from '../helpers/globals';

/**
 * OpenStreetMap component for GatherPress.
 *
 * This component is used to embed an Open Street Map with specified location,
 * zoom level, and height using the Leaflet platform.
 *
 * @since 1.0.0
 *
 * @param {Object} props              - Component properties.
 * @param {string} props.location     - The location to be displayed on the map.
 * @param {string} props.latitude     - The latitdue of the location to be displayed on the map.
 * @param {string} props.longitude    - The longitude of the location to be displayed on the map.
 * @param {number} [props.zoom=10]    - The zoom level of the map.i
 * @param {number} [props.height=300] - The height of the map container.
 * @param {string} [props.className]  - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered React component.
 */
const OpenStreetMap = (props) => {
	const { zoom, className, location, height, latitude, longitude } = props;
	const style = { height };

	useEffect(() => {
		if (
			typeof window.L === 'undefined' ||
			(!latitude || !longitude)
		)
			return;

		const map = window.L.map('map').setView([latitude, longitude], zoom);

		window.L.Icon.Default.imagePath =
			getFromGlobal('urls.pluginUri') +
			'node_modules/leaflet/dist/images/';

		window.L.tileLayer(
			'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			{
				attribution: sprintf(
					__('© %s contributors'),
					'<a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
				),
			}
		).addTo(map);

		window.L.marker([latitude, longitude]).addTo(map).bindPopup(location);

		return () => {
			map.remove();
		};
	}, [latitude, location, longitude, zoom]);

	if (!latitude || !longitude) {
		return <></>;
	}

	return <div className={className} id="map" style={style}></div>;
};

export default OpenStreetMap;
