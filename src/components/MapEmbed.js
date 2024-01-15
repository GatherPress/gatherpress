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
	const { location, zoom, type, className } = props;
	let { height } = props;

	if (!height) {
		height = 300;
	}

	const style = { border: 0, height, width: '100%' };
	const baseUrl = 'https://maps.google.com/maps';

	if (!location) {
		return <></>;
	}

	const params = new URLSearchParams({
		q: location,
		z: zoom || 10,
		t: type || 'm',
		output: 'embed',
	});

	const srcURL = baseUrl + '?' + params.toString();
	return (
		<iframe
			src={srcURL}
			style={style}
			className={className}
			title={location}
		></iframe>
	);
};

export default MapEmbed;
