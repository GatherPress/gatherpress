/**
 * MapEmbed component renders an embedded Google Map based on provided location and settings.
 *
 * @since 1.0.0
 *
 * @param {number} props             - The properties passed to the component.
 * @param {string} props.location    - The location to be displayed on the map (address or coordinates).
 * @param {number} [props.zoom=1]    - The zoom level of the map.
 * @param {string} [props.type='']   - The map type (e.g., 'roadmap', 'satellite').
 * @param {number} [props.height]    - The height of the embedded map.
 * @param {string} [props.className] - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered MapEmbed component.
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
