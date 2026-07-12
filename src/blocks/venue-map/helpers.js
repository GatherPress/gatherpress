/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { REST_NAMESPACE } from '../../helpers/namespace';

/**
 * POST regenerate-map / ensure-only and patch descriptors into the core store.
 *
 * @since 0.35.0
 *
 * @param {Object}   args                        Request inputs.
 * @param {number}   args.venuePostId            Venue post ID.
 * @param {string}   args.venuePostType          Venue post type slug.
 * @param {boolean}  [args.ensureOnly]           Lazy-generate one combo only.
 * @param {number}   args.zoom                   Block zoom level.
 * @param {number}   args.width                  Block width (0 = auto).
 * @param {number}   args.height                 Block height (0 = auto).
 * @param {string}   args.aspectRatio            Block aspect ratio string.
 * @param {string}   args.mapType                Block map type slug.
 * @param {Function} args.getCurrentEntityRecord Returns the cached entity record.
 * @param {Function} args.receiveEntityRecords   core/receiveEntityRecords dispatcher.
 * @param {Function} args.invalidateResolution   core/invalidateResolution dispatcher.
 *
 * @return {Promise<Object>} REST response.
 */
const postVenueMapDescriptors = async ( {
	venuePostId,
	venuePostType,
	ensureOnly = false,
	zoom,
	width,
	height,
	aspectRatio,
	mapType,
	getCurrentEntityRecord,
	receiveEntityRecords,
	invalidateResolution,
} ) => {
	const response = await apiFetch( {
		path: `/${ REST_NAMESPACE }/venue/${ venuePostId }/regenerate-map`,
		method: 'POST',
		data: {
			zoom: Number.isInteger( zoom ) ? zoom : undefined,
			width: Number.isInteger( width ) ? width : undefined,
			height: Number.isInteger( height ) ? height : undefined,
			aspect_ratio:
				'string' === typeof aspectRatio && '' !== aspectRatio
					? aspectRatio
					: undefined,
			map_type:
				'string' === typeof mapType && '' !== mapType
					? mapType
					: undefined,
			ensure_only: ensureOnly ? true : undefined,
		},
	} );

	if ( venuePostType && '' === response?.reason ) {
		const current = getCurrentEntityRecord();

		if ( current ) {
			receiveEntityRecords(
				'postType',
				venuePostType,
				[
					{
						...current,
						meta: {
							...current.meta,
							gatherpress_static_map:
								response?.descriptors || {},
						},
					},
				],
				undefined,
				false
			);
		}

		invalidateResolution( 'getEntityRecord', [
			'postType',
			venuePostType,
			venuePostId,
		] );
	}

	return response;
};

/**
 * Regenerate / Generate button for the venue-map block.
 *
 * Drives POST /{namespace}/venue/{id}/regenerate-map and refreshes the
 * venue's cached entity record so the editor preview picks up the fresh
 * static-map descriptors without a page reload.
 *
 * @since 0.34.0
 *
 * @param {Object}  props               Component props.
 * @param {number}  props.venuePostId   The venue post ID whose map to regenerate.
 * @param {string}  props.venuePostType The venue's post type slug (for the core store invalidation).
 * @param {number}  props.zoom          Current block zoom — passed through so the server renders this combo.
 * @param {number}  [props.width]       Current block width (0 = auto) — forwarded to the server.
 * @param {number}  [props.height]      Current block height (0 = auto) — forwarded to the server.
 * @param {string}  [props.aspectRatio] Current block aspect ratio (e.g. "16/9") — forwarded so the server can derive any auto dimension consistently with the client.
 * @param {string}  [props.mapType]     Current block map type — forwarded so regenerated static map images match the editor selection.
 * @param {boolean} props.disabled      When true, the button is disabled regardless of internal state.
 * @param {string}  [props.label]       Override the default "Regenerate map" label.
 * @param {string}  [props.variant]     Underlying Button variant (e.g. 'primary', 'secondary', 'link').
 *
 * @return {JSX.Element} The button.
 */
export const RegenerateMapButton = ( {
	venuePostId,
	venuePostType,
	zoom,
	width,
	height,
	aspectRatio,
	mapType,
	disabled = false,
	label,
	variant = 'secondary',
} ) => {
	const [ isBusy, setIsBusy ] = useState( false );
	const { receiveEntityRecords, invalidateResolution } =
		useDispatch( 'core' );
	const { createErrorNotice } = useDispatch( 'core/notices' );

	const getCurrentEntityRecord = useSelect(
		( select ) => () =>
			select( 'core' ).getEntityRecord(
				'postType',
				venuePostType,
				venuePostId
			),
		[ venuePostType, venuePostId ]
	);

	const handleClick = async () => {
		if ( ! venuePostId || isBusy ) {
			return;
		}

		setIsBusy( true );

		try {
			const response = await postVenueMapDescriptors( {
				venuePostId,
				venuePostType,
				zoom,
				width,
				height,
				aspectRatio,
				mapType,
				getCurrentEntityRecord,
				receiveEntityRecords,
				invalidateResolution,
			} );

			if ( 'generation_failed' === response?.reason ) {
				createErrorNotice?.(
					__(
						'The map server could not render the image. Check the tile provider and try again.',
						'gatherpress'
					),
					{ type: 'snackbar' }
				);
			}
		} catch ( error ) {
			createErrorNotice?.(
				error?.message ||
					__(
						'Could not regenerate the map. Please try again.',
						'gatherpress'
					),
				{ type: 'snackbar' }
			);
		} finally {
			setIsBusy( false );
		}
	};

	return (
		<Button
			variant={ variant }
			onClick={ handleClick }
			disabled={ disabled || isBusy }
			aria-busy={ isBusy }
		>
			{ isBusy && <Spinner /> }
			{ label || __( 'Regenerate map', 'gatherpress' ) }
		</Button>
	);
};

/**
 * Pick the best descriptor for a (provider, combo) lookup, with the same
 * fallback chain the PHP orchestrator uses in `Map::get_descriptor_for_post`:
 * active provider first, then any other provider's stored descriptor for
 * the same combo. Lets a site that just flipped `map_platform` keep
 * showing the previous provider's PNG until the new one renders.
 *
 * @since 0.34.0
 *
 * @param {Object} descriptors Provider-keyed descriptor map: `{ osm: { combo_key: { url, ... } } }`.
 * @param {string} comboKey    Combo key in the form `{zoom}x{width}x{height}x{map_type}`.
 * @param {string} activeSlug  Slug of the currently active provider (e.g. `'osm'`).
 *
 * @return {Object|undefined} Descriptor object, or undefined when no provider has one.
 */
export const pickDescriptorForCombo = ( descriptors, comboKey, activeSlug ) => {
	const active = descriptors?.[ activeSlug ]?.[ comboKey ];
	if ( active ) {
		return active;
	}

	for ( const slug of Object.keys( descriptors || {} ) ) {
		if ( slug === activeSlug ) {
			continue;
		}
		const candidate = descriptors[ slug ]?.[ comboKey ];
		if ( candidate ) {
			return candidate;
		}
	}

	return undefined;
};

/**
 * Build the meta-storage combo key matching the PHP orchestrator.
 *
 * @since 0.35.0
 *
 * @param {number} zoom    Map zoom level.
 * @param {number} width   Pixel width.
 * @param {number} height  Pixel height.
 * @param {string} mapType Map type slug (defaults to roadmap).
 *
 * @return {string} Combo key.
 */
export const buildComboKey = ( zoom, width, height, mapType = 'roadmap' ) =>
	`${ zoom }x${ width }x${ height }x${ mapType || 'roadmap' }`;

/**
 * Parse an aspect-ratio string (e.g. "16/9" or "4:3") into a float.
 *
 * Mirrors the server-side `Venue\Map::parse_aspect_ratio()` so the editor
 * can derive auto dimensions from the ratio without a round-trip to PHP.
 * Returns null for unparsable input.
 *
 * @since 0.34.0
 *
 * @param {string} ratio Raw aspect-ratio string.
 *
 * @return {number|null} Parsed ratio, or null if the input is invalid.
 */
export const parseAspectRatio = ( ratio ) => {
	if ( 'string' !== typeof ratio ) {
		return null;
	}
	const match = /^(\d+)\s*[/:]\s*(\d+)$/.exec( ratio.trim() );
	if ( ! match ) {
		return null;
	}
	const numerator = parseInt( match[ 1 ], 10 );
	const denominator = parseInt( match[ 2 ], 10 );
	if ( 0 >= numerator || 0 >= denominator ) {
		return null;
	}
	return numerator / denominator;
};

/**
 * Resolve a (width, height) pair from block attribute values.
 *
 * Mirrors `Venue\Map::resolve_dimensions()` so the editor renders the map
 * at the same effective pixel size the server will compose. Either
 * dimension can be 0 ("auto") and will be derived from the other side and
 * the aspect ratio. When both are auto, `DEFAULT_HEIGHT` seeds the math.
 *
 * @since 0.34.0
 *
 * @param {Object} args               Derivation inputs.
 * @param {number} args.width         Raw width (0 = auto).
 * @param {number} args.height        Raw height (0 = auto).
 * @param {string} args.aspectRatio   Aspect-ratio string.
 * @param {number} args.defaultHeight Fallback height for the both-auto case.
 *
 * @return {{width: number, height: number}} Concrete pixel dimensions.
 */
export const resolveDimensions = ( {
	width,
	height,
	aspectRatio,
	defaultHeight,
} ) => {
	const ratio = parseAspectRatio( aspectRatio ) ?? 2;
	let w = Number.isInteger( width ) && 0 < width ? width : 0;
	let h = Number.isInteger( height ) && 0 < height ? height : 0;

	if ( 0 === w && 0 === h ) {
		h = defaultHeight;
		w = Math.round( h * ratio );
	} else if ( 0 === w ) {
		w = Math.round( h * ratio );
	} else if ( 0 === h ) {
		h = Math.round( w / ratio );
	}

	// Mirror the server's clamp_width / clamp_height so the editor's cache
	// key matches the key the server stored the descriptor under.
	w = Math.max( 100, Math.min( 4000, w ) );
	h = Math.max( 100, Math.min( 4000, h ) );

	return { width: w, height: h };
};

/**
 * Poll cadence (ms) and cap for {@link usePlaceholderPolling}. Exported so
 * tests can reference them without duplicating the constants.
 *
 * @since 0.34.0
 */
export const POLL_INTERVAL_MS = 15000;
export const MAX_POLLS = 20;

/**
 * While the venue-map static placeholder is visible, lazily ensure the active
 * combo (when `combo` is set) and periodically invalidate the venue entity
 * record so background-generated descriptors surface in the editor.
 *
 * @since 0.34.0
 *
 * @param {Object}      args               Hook arguments.
 * @param {boolean}     args.active        Whether effects should run.
 * @param {number}      args.venuePostId   Venue post ID.
 * @param {string}      args.venuePostType Venue post type slug.
 * @param {Object|null} [args.combo]       When set, POST ensure-only once (`key`, `zoom`, `width`, `height`, `aspectRatio`, `mapType`).
 *
 * @return {void}
 */
export const usePlaceholderPolling = ( {
	active,
	venuePostId,
	venuePostType,
	combo = null,
} ) => {
	const { receiveEntityRecords, invalidateResolution } =
		useDispatch( 'core' );
	const inFlightKeyRef = useRef( '' );

	const getCurrentEntityRecord = useSelect(
		( select ) => () =>
			select( 'core' ).getEntityRecord(
				'postType',
				venuePostType,
				venuePostId
			),
		[ venuePostType, venuePostId ]
	);

	useEffect( () => {
		if ( ! active || ! venuePostId || ! venuePostType ) {
			inFlightKeyRef.current = '';
			return undefined;
		}

		let cancelled = false;

		if (
			combo?.key &&
			inFlightKeyRef.current !== combo.key
		) {
			inFlightKeyRef.current = combo.key;

			postVenueMapDescriptors( {
				venuePostId,
				venuePostType,
				ensureOnly: true,
				zoom: combo.zoom,
				width: combo.width,
				height: combo.height,
				aspectRatio: combo.aspectRatio,
				mapType: combo.mapType,
				getCurrentEntityRecord,
				receiveEntityRecords,
				invalidateResolution,
			} ).finally( () => {
				if ( ! cancelled && inFlightKeyRef.current === combo.key ) {
					inFlightKeyRef.current = '';
				}
			} );
		}

		let pollCount = 0;
		const interval = setInterval( () => {
			pollCount += 1;
			invalidateResolution( 'getEntityRecord', [
				'postType',
				venuePostType,
				venuePostId,
			] );
			if ( pollCount >= MAX_POLLS ) {
				clearInterval( interval );
			}
		}, POLL_INTERVAL_MS );

		return () => {
			cancelled = true;
			clearInterval( interval );
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [
		active,
		combo?.aspectRatio,
		combo?.height,
		combo?.key,
		combo?.mapType,
		combo?.width,
		combo?.zoom,
		venuePostId,
		venuePostType,
	] );
};
