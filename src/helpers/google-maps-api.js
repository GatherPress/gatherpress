/**
 * Loader for the Google Maps JavaScript API.
 *
 * The interactive Google map runs on the Maps JavaScript API whenever a
 * Google Maps API key is configured — that is the only Google surface that
 * renders all four map types (roadmap, satellite, hybrid, terrain). The
 * keyless Maps Embed API iframe stays as the no-key fallback and only
 * renders roadmap and satellite.
 *
 * Loading is per document on purpose. The block editor renders the canvas
 * inside an iframe, so the `<script>` has to land in the document that owns
 * the map container — otherwise the API bootstraps against the wrong
 * `window` and the map never paints.
 *
 * @since 0.35.0
 */

const GOOGLE_MAPS_API_URL = 'https://maps.googleapis.com/maps/api/js';

/**
 * In-flight and settled loads, keyed by document and then by API key.
 *
 * A WeakMap on the document keeps the editor iframe's entry from leaking
 * once that iframe is torn down.
 *
 * @since 0.35.0
 *
 * @type {WeakMap<Document, Map<string, Promise<Object>>>}
 */
let loadsByDocument = new WeakMap();

/**
 * Counter feeding the unique global callback name handed to the API.
 *
 * @since 0.35.0
 *
 * @type {number}
 */
let callbackCounter = 0;

/**
 * Reset the loader cache.
 *
 * Only used by tests — each case needs a fresh document/key cache so a
 * previously resolved (or rejected) load doesn't leak into the next one.
 *
 * @since 0.35.0
 *
 * @return {void}
 */
export function clearGoogleMapsApiCache() {
	loadsByDocument = new WeakMap();
	callbackCounter = 0;
}

/**
 * Load the Google Maps JavaScript API into a document and resolve its
 * `google.maps` namespace.
 *
 * Repeat calls for the same document and key share one `<script>` and one
 * promise. A key that is empty (or whitespace) rejects rather than issuing
 * a request the API would answer with an auth error.
 *
 * @since 0.35.0
 *
 * @param {string}   apiKey Google Maps API key.
 * @param {Document} doc    Document that owns the map container.
 *
 * @return {Promise<Object>} Resolves with the `google.maps` namespace.
 */
export function loadGoogleMapsApi( apiKey, doc ) {
	const key = ( apiKey || '' ).trim();
	const targetDocument = doc || document;

	if ( ! key ) {
		return Promise.reject(
			new Error( 'A Google Maps API key is required.' )
		);
	}

	if ( ! loadsByDocument.has( targetDocument ) ) {
		loadsByDocument.set( targetDocument, new Map() );
	}

	const loads = loadsByDocument.get( targetDocument );

	if ( loads.has( key ) ) {
		return loads.get( key );
	}

	const targetWindow = targetDocument.defaultView || window;

	// Another plugin, the theme, or an earlier mount in a document we no
	// longer hold a cache entry for already brought the API in.
	if ( targetWindow.google?.maps ) {
		const resolved = Promise.resolve( targetWindow.google.maps );
		loads.set( key, resolved );

		return resolved;
	}

	callbackCounter += 1;
	const callbackName = `gatherpressGoogleMapsApiReady_${ callbackCounter }`;

	const load = new Promise( ( resolve, reject ) => {
		const script = targetDocument.createElement( 'script' );

		const cleanUp = () => {
			delete targetWindow[ callbackName ];
		};

		targetWindow[ callbackName ] = () => {
			cleanUp();
			resolve( targetWindow.google.maps );
		};

		script.addEventListener( 'error', () => {
			cleanUp();
			// Drop the cache entry so a later mount (or a re-save that
			// fixes the key) gets a fresh attempt instead of replaying
			// this rejection forever.
			loads.delete( key );
			script.remove();
			reject(
				new Error(
					'The Google Maps JavaScript API could not be loaded.'
				)
			);
		} );

		script.src = `${ GOOGLE_MAPS_API_URL }?${ new URLSearchParams( {
			key,
			// `loading=async` is Google's recommended bootstrap; paired
			// with `callback` it avoids polling for the namespace.
			loading: 'async',
			callback: callbackName,
		} ).toString() }`;
		script.async = true;

		targetDocument.head.appendChild( script );
	} );

	loads.set( key, load );

	return load;
}
