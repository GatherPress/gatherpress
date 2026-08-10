/**
 * WordPress dependencies
 */
import { select } from '@wordpress/data';

/**
 * Internal dependencies
 */
import GoogleMap from './GoogleMap';
import OpenStreetMap from './OpenStreetMap';
import { getFromSettings } from '../helpers/editor-settings';

/**
 * MapEmbed component for GatherPress.
 *
 * This component is used to embed a Google Map with specified location,
 * zoom level, and map type. The embed fills its wrapper — the venue-map
 * wrapper is the single source of size.
 *
 * @since 0.27.0
 *
 * @param {Object} props             - Component properties.
 * @param {string} props.location    - The location to be displayed on the map.
 * @param {string} props.latitude    - The latitdue of the location to be displayed on the map.
 * @param {string} props.longitude   - The longitude of the location to be displayed on the map.
 * @param {number} [props.zoom=10]   - The zoom level of the map.
 * @param {string} [props.type='m']  - The type of the map (e.g., 'm' for roadmap).
 * @param {string} [props.className] - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered React component.
 */
const MapEmbed = ( props ) => {
	const isAdmin = select( 'core' )?.canUser( 'create', 'posts' );
	const isPostEditor = Boolean( select( 'core/edit-post' ) );
	const { zoom, type, className, latitude, longitude } = props;
	let { location } = props;

	if ( isAdmin && ! isPostEditor && ! location ) {
		location = '660 4th Street #119 San Francisco CA 94107, USA';
	}

	const mapPlatform =
		props.mapPlatform || getFromSettings( 'mapPlatform' );
	if ( ! mapPlatform ) {
		return <></>;
	} else if ( 'google' === mapPlatform ) {
		const apiKey =
			props.googleMapsApiKey ??
			getFromSettings( 'googleMapsApiKey' ) ??
			'';
		return (
			<GoogleMap
				location={ location }
				latitude={ latitude }
				longitude={ longitude }
				className={ className }
				zoom={ zoom }
				type={ type }
				apiKey={ apiKey }
			/>
		);
	} else if ( 'osm' === mapPlatform ) {
		return (
			<OpenStreetMap
				location={ location }
				latitude={ latitude }
				longitude={ longitude }
				className={ className }
				zoom={ zoom }
				pluginUrl={ props.pluginUrl }
			/>
		);
	}

	return <></>;
};

export default MapEmbed;
