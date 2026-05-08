/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { REST_NAMESPACE } from '../../helpers/namespace';

/**
 * Regenerate / Generate button for the venue-map block.
 *
 * Drives POST /{namespace}/venue/{id}/regenerate-map and refreshes the
 * venue's cached entity record so the editor preview picks up the fresh
 * static-map descriptors without a page reload.
 *
 * @since 1.0.0
 *
 * @param {Object}  props               Component props.
 * @param {number}  props.venuePostId   The venue post ID whose map to regenerate.
 * @param {string}  props.venuePostType The venue's post type slug (for the core store invalidation).
 * @param {number}  props.zoom          Current block zoom — passed through so the server renders this combo.
 * @param {number}  [props.width]       Current block width (0 = auto) — forwarded to the server.
 * @param {number}  [props.height]      Current block height (0 = auto) — forwarded to the server.
 * @param {string}  [props.aspectRatio] Current block aspect ratio (e.g. "16/9") — forwarded so the server can derive any auto dimension consistently with the client.
 * @param {boolean} props.disabled      When true, the button is disabled regardless of internal state.
 * @param {string}  [props.label]       Override the default "Regenerate map" label.
 * @param {string}  [props.variant]     Underlying Button variant (e.g. 'primary', 'secondary', 'link').
 * @return {JSX.Element} The button.
 */
export const RegenerateMapButton = ( {
	venuePostId,
	venuePostType,
	zoom,
	width,
	height,
	aspectRatio,
	disabled = false,
	label,
	variant = 'secondary',
} ) => {
	const [ isBusy, setIsBusy ] = useState( false );
	const { receiveEntityRecords, invalidateResolution } =
		useDispatch( 'core' );
	const { createErrorNotice } = useDispatch( 'core/notices' );

	// Non-reactive selector lookups — we only need the current record at
	// the moment of the click to merge fresh meta into it, not on every
	// render. handleClick already bails on missing venuePostId/venuePostType
	// before invoking this, so the inner closure doesn't need to re-guard.
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

		let response;
		try {
			response = await apiFetch( {
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
				},
			} );
		} catch ( error ) {
			// Network / permission failure (403, 500, offline). Keep the
			// cached descriptor intact so the preview doesn't flash blank,
			// surface the failure as an admin notice, and re-enable the
			// button so the user can retry.
			createErrorNotice?.(
				error?.message ||
					__(
						'Could not regenerate the map. Please try again.',
						'gatherpress'
					),
				{ type: 'snackbar' }
			);
			setIsBusy( false );
			return;
		}

		try {
			// Server reached but every combo failed to render (disk / GD /
			// tile host). Treat as an error: leave the cached descriptor
			// intact and surface a notice.
			if ( 'generation_failed' === response?.reason ) {
				createErrorNotice?.(
					__(
						'The map server could not render the image. Check the tile provider and try again.',
						'gatherpress'
					),
					{ type: 'snackbar' }
				);
				return;
			}

			if ( ! venuePostType ) {
				return;
			}

			// Patch the fresh descriptors straight into the `core` store
			// cache. Both core.getEntityRecord and
			// core/editor.getEditedPostAttribute read through this entity
			// record, so the block preview picks up the new PNG URL on the
			// next render without waiting for a separate refetch. The
			// trailing invalidateResolution is belt-and-suspenders for
			// anyone subscribed to the resolution state itself.
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
 * @since 1.0.0
 *
 * @param {Object} descriptors Provider-keyed descriptor map: `{ osm: { combo_key: { url, ... } } }`.
 * @param {string} comboKey    Combo key in the form `{zoom}x{width}x{height}`.
 * @param {string} activeSlug  Slug of the currently active provider (e.g. `'osm'`).
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
 * Parse an aspect-ratio string (e.g. "16/9" or "4:3") into a float.
 *
 * Mirrors the server-side `Venue\Map::parse_aspect_ratio()` so the editor
 * can derive auto dimensions from the ratio without a round-trip to PHP.
 * Returns null for unparsable input.
 *
 * @since 1.0.0
 *
 * @param {string} ratio Raw aspect-ratio string.
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
 * @since 1.0.0
 *
 * @param {Object} args               Derivation inputs.
 * @param {number} args.width         Raw width (0 = auto).
 * @param {number} args.height        Raw height (0 = auto).
 * @param {string} args.aspectRatio   Aspect-ratio string.
 * @param {number} args.defaultHeight Fallback height for the both-auto case.
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
 * @since 1.0.0
 */
export const POLL_INTERVAL_MS = 15000;
export const MAX_POLLS = 20;

/**
 * While the venue-map static placeholder is visible, periodically
 * invalidate the venue's `core` entity record so background-generated
 * descriptors (WP-Cron prewarm tick, out-of-band meta writes) surface in
 * the editor without a manual reload.
 *
 * The hook only schedules an interval when `active` is true AND the
 * venue is fully addressable (post id + type + resolved coordinates).
 * Stops polling after {@link MAX_POLLS} ticks so a persistently failing
 * generation doesn't pin the editor into forever polling; tears down
 * on unmount or when any dep transitions to a falsy state.
 *
 * Scheduling only — no server-side changes here. The same behavior works
 * whether generation runs via WP-Cron (today) or Action Scheduler (when
 * #1487 lands).
 *
 * @since 1.0.0
 *
 * @param {Object}  args               Hook arguments.
 * @param {boolean} args.active        Whether polling should run (typically `showStaticPlaceholder` gated on coords).
 * @param {number}  args.venuePostId   Venue post ID.
 * @param {string}  args.venuePostType Venue post type slug.
 * @return {void}
 */
export const usePlaceholderPolling = ( {
	active,
	venuePostId,
	venuePostType,
} ) => {
	const { invalidateResolution } = useDispatch( 'core' );

	useEffect( () => {
		if ( ! active || ! venuePostId || ! venuePostType ) {
			return undefined;
		}

		let pollCount = 0;
		const interval = setInterval( () => {
			pollCount += 1;
			invalidateResolution( 'getEntityRecord', [
				'postType',
				venuePostType,
				venuePostId,
			] );
			// Stop on the MAX_POLLS'th tick rather than letting a (MAX_POLLS + 1)th
			// tick fire just to clear — matches the cap literally.
			if ( pollCount >= MAX_POLLS ) {
				clearInterval( interval );
			}
		}, POLL_INTERVAL_MS );

		return () => clearInterval( interval );
		// `invalidateResolution` is a bound dispatch action; @wordpress/data
		// returns a stable reference for it across renders, so omitting it
		// from the deps is safe and avoids a spurious restart-on-rerender
		// if that contract ever regresses.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ active, venuePostId, venuePostType ] );
};
