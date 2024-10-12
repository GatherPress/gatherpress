/**
 * WordPress dependencies.
 */
import { sprintf, __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/globals';

/**
 * OpenStreetMap component for GatherPress.
 *
 * This component is used to embed an OpenStreetMap with specified location,
 * zoom level, and height using the Leaflet platform.
 *
 * @since 1.0.0
 *
 * @param {Object} props              - Component properties.
 * @param {string} props.location     - The location to be displayed on the map.
 * @param {string} props.latitude     - The latitude of the location to be displayed on the map.
 * @param {string} props.longitude    - The longitude of the location to be displayed on the map.
 * @param {number} [props.zoom=10]    - The zoom level of the map.
 * @param {number} [props.height=300] - The height of the map container.
 * @param {string} [props.className]  - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered React component.
 */
const OpenStreetMap = (props) => {
	const {
		zoom = 10,
		className,
		location,
		height = 300,
		latitude,
		longitude,
	} = props;
	const [Leaflet, setLeaflet] = useState(null);
	const style = { height };

	useEffect(() => {
		// Load Leaflet and its assets dynamically
		const loadLeaflet = async () => {
			const { default: L } = await import('leaflet');

			await import('leaflet/dist/leaflet.css');
			await import('leaflet/dist/images/marker-icon-2x.png');
			await import('leaflet/dist/images/marker-shadow.png');

			setLeaflet(L);
		};

		loadLeaflet();
	}, []);

	useEffect(() => {
		if (!Leaflet || !latitude || !longitude) {
			return;
		}

		const map = Leaflet.map('map').setView([latitude, longitude], zoom);

		Leaflet.Icon.Default.imagePath =
			getFromGlobal('urls.pluginUrl') + 'build/images/';

		Leaflet.tileLayer(
			'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
			{
				attribution: sprintf(
					/* translators: %s: Link to OpenStreetMap contributors. */
					__('© %s contributors', 'gatherpress'),
					'<a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
				),
			}
		).addTo(map);

		Leaflet.marker([latitude, longitude]).addTo(map).bindPopup(location);

		return () => {
			map.remove();
		};
	}, [Leaflet, latitude, location, longitude, zoom]);

	if (!Leaflet || !latitude || !longitude) {
		return null;
	}

	return <div className={className} id="map" style={style}></div>;
};

export default OpenStreetMap;
