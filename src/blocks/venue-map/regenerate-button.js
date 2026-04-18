/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
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
 * @param {number}  props.height        Current block height — passed through so the server renders this combo.
 * @param {boolean} props.disabled      When true, the button is disabled regardless of internal state.
 * @param {string}  [props.label]       Override the default "Regenerate map" label.
 * @param {string}  [props.variant]     Underlying Button variant (e.g. 'primary', 'secondary', 'link').
 * @return {JSX.Element} The button.
 */
const RegenerateMapButton = ( {
	venuePostId,
	venuePostType,
	zoom,
	height,
	disabled = false,
	label,
	variant = 'secondary',
} ) => {
	const [ isBusy, setIsBusy ] = useState( false );
	const { receiveEntityRecords, invalidateResolution } =
		useDispatch( 'core' );

	// Non-reactive selector lookups — we only need the current record at
	// the moment of the click to merge fresh meta into it, not on every
	// render.
	const getCurrentEntityRecord = useSelect(
		( select ) => () =>
			venuePostType && venuePostId
				? select( 'core' ).getEntityRecord(
						'postType',
						venuePostType,
						venuePostId
				  )
				: null,
		[ venuePostType, venuePostId ]
	);

	const handleClick = async () => {
		if ( ! venuePostId || isBusy ) {
			return;
		}

		setIsBusy( true );

		try {
			const response = await apiFetch( {
				path: `/${ REST_NAMESPACE }/venue/${ venuePostId }/regenerate-map`,
				method: 'POST',
				data: {
					zoom: Number.isInteger( zoom ) ? zoom : undefined,
					height: Number.isInteger( height )
						? height
						: undefined,
				},
			} );

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
								...( current.meta || {} ),
								gatherpress_venue_static_map:
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

export default RegenerateMapButton;
