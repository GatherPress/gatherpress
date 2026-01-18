/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import GoogleMap from './GoogleMap';
import OpenStreetMap from './OpenStreetMap';
import { getFromGlobal } from '../helpers/globals';

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
 * @param {string} props.latitude     - The latitdue of the location to be displayed on the map.
 * @param {string} props.longitude    - The longitude of the location to be displayed on the map.
 * @param {number} [props.zoom=10]    - The zoom level of the map.
 * @param {string} [props.type='m']   - The type of the map (e.g., 'm' for roadmap).
 * @param {number} [props.height=300] - The height of the map container.
 * @param {string} [props.className]  - Additional CSS class names for styling.
 *
 * @return {JSX.Element} The rendered React component.
 */
const MapEmbed = ( props ) => {
	const isAdmin = select( 'core' )?.canUser( 'create', 'posts' );
	const isPostEditor = Boolean( select( 'core/edit-post' ) );
	const { zoom, type, className, latitude, longitude } = props;
	let { location, height } = props;

	if ( ! height ) {
		height = 300;
	}

	if ( isAdmin && ! isPostEditor && ! location ) {
		location = '660 4th Street #119 San Francisco CA 94107, USA';
	}

	const mapPlatform = getFromGlobal( 'settings.mapPlatform' );
	if ( ! location || ! mapPlatform ) {
		return <></>;
	} else if ( 'google' === mapPlatform ) {
		return (
			<GoogleMap
				location={ location }
				latitude={ latitude }
				longitude={ longitude }
				className={ className }
				zoom={ zoom }
				type={ type }
				height={ height }
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
				height={ height }
			/>
		);
	}

	return <></>;
};

export default MapEmbed;
