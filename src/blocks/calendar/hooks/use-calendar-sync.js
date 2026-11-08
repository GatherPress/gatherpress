/**
 * Hook to synchronize parent Query Loop, Heading, and Pagination names/labels.
 *
 * @package
 * @since TBD
 */

import { __ } from '@wordpress/i18n';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useMemo, useRef } from '@wordpress/element';

import {
	findHeadingBlock,
	findBlockByName,
	getCalendarBlockName,
	getPaginationContainerName,
	getPaginationLabel,
} from '../utils/block-sync-utils';

/**
 * Synchronizes parent Query and child helper blocks with calendar settings.
 *
 * @param {Object} props           Hook properties.
 * @param {string} props.clientId  Client ID of the calendar block.
 * @param {string} props.viewType  Current view type ('month', 'week', 'day').
 * @param {number} props.unitCount Number of calendar units to show.
 */
export function useCalendarSync( { clientId, viewType, unitCount } ) {
	const {
		isFirstCalendar,
		parentQueryClientId,
		parentQueryMetadata,
		headingClientId,
		headingMetadata,
		paginationContainerClientId,
		paginationContainerMetadata,
		paginationPrevClientId,
		paginationPrevMetadata,
		paginationNextClientId,
		paginationNextMetadata,
	} = useSelect(
		( select ) => {
			const { getBlockParentsByBlockName, getBlock } =
				select( blockEditorStore );

			const parents = getBlockParentsByBlockName(
				clientId,
				'core/query',
			);
			const parentId = parents?.at( -1 );
			const parentBlock = parentId ? getBlock( parentId ) : null;

			const headingBlock = parentBlock?.innerBlocks
				? findHeadingBlock( parentBlock.innerBlocks )
				: null;

			const paginationBlock = parentBlock?.innerBlocks
				? findBlockByName(
					parentBlock.innerBlocks,
					'core/query-pagination',
				)
				: null;

			const prevBlock = parentBlock?.innerBlocks
				? findBlockByName(
					parentBlock.innerBlocks,
					'core/query-pagination-previous',
				)
				: null;

			const nextBlock = parentBlock?.innerBlocks
				? findBlockByName(
					parentBlock.innerBlocks,
					'core/query-pagination-next',
				)
				: null;

			// With two calendars in one Query, only the first names its blocks,
			// so the two do not overwrite each other's names in turn.
			const firstCalendar = parentBlock?.innerBlocks
				? findBlockByName( parentBlock.innerBlocks, 'gatherpress/calendar' )
				: null;

			return {
				isFirstCalendar: ! firstCalendar || firstCalendar.clientId === clientId,
				parentQueryClientId: parentId ?? null,
				parentQueryMetadata: parentBlock?.attributes?.metadata,
				headingClientId: headingBlock?.clientId ?? null,
				headingMetadata: headingBlock?.attributes?.metadata,
				paginationContainerClientId: paginationBlock?.clientId ?? null,
				paginationContainerMetadata:
					paginationBlock?.attributes?.metadata,
				paginationPrevClientId: prevBlock?.clientId ?? null,
				paginationPrevMetadata: prevBlock?.attributes?.metadata,
				paginationNextClientId: nextBlock?.clientId ?? null,
				paginationNextMetadata: nextBlock?.attributes?.metadata,
			};
		},
		[ clientId ],
	);

	const { updateBlockAttributes } = useDispatch( blockEditorStore );

	const targetQueryName = useMemo(
		() => getCalendarBlockName( viewType, unitCount ),
		[ viewType, unitCount ],
	);

	const targetHeadingName = useMemo(
		() =>
			getCalendarBlockName(
				viewType,
				unitCount,
				__( 'Heading', 'gatherpress' ),
			),
		[ viewType, unitCount ],
	);

	const targetPaginationContainerName = useMemo(
		() => getPaginationContainerName( viewType, unitCount ),
		[ viewType, unitCount ],
	);

	const targetPrevLabel = useMemo(
		() => getPaginationLabel( 'previous', viewType, unitCount ),
		[ viewType, unitCount ],
	);

	const targetNextLabel = useMemo(
		() => getPaginationLabel( 'next', viewType, unitCount ),
		[ viewType, unitCount ],
	);

	// Track previous view configuration so names and labels are only reset when config changes.
	const prevConfigRef = useRef( { viewType, unitCount } );

	useEffect( () => {
		if ( ! isFirstCalendar ) {
			return;
		}

		// Only overwrite block names and pagination labels when unitCount or viewType changes,
		// so opening the editor never writes. (While the post type loads, core's Event Query
		// controls blank the Query name for a moment, so "no name yet" is not a safe test.)
		const hasConfigChanged =
			prevConfigRef.current.viewType !== viewType ||
			prevConfigRef.current.unitCount !== unitCount;

		if ( ! hasConfigChanged ) {
			return;
		}

		prevConfigRef.current = { viewType, unitCount };

		// The Query block is named after the calendar it contains.
		if (
			parentQueryClientId &&
			parentQueryMetadata?.name !== targetQueryName
		) {
			updateBlockAttributes( parentQueryClientId, {
				metadata: {
					...parentQueryMetadata,
					name: targetQueryName,
				},
			} );
		}

		if ( headingClientId ) {
			updateBlockAttributes( headingClientId, {
				metadata: {
					...headingMetadata,
					name: targetHeadingName,
				},
			} );
		}

		if ( paginationContainerClientId ) {
			updateBlockAttributes( paginationContainerClientId, {
				metadata: {
					...paginationContainerMetadata,
					name: targetPaginationContainerName,
				},
			} );
		}

		if ( paginationPrevClientId ) {
			updateBlockAttributes( paginationPrevClientId, {
				label: targetPrevLabel,
				metadata: {
					...paginationPrevMetadata,
					name: targetPrevLabel,
				},
			} );
		}

		if ( paginationNextClientId ) {
			updateBlockAttributes( paginationNextClientId, {
				label: targetNextLabel,
				metadata: {
					...paginationNextMetadata,
					name: targetNextLabel,
				},
			} );
		}
	}, [
		isFirstCalendar,
		parentQueryClientId,
		parentQueryMetadata,
		targetQueryName,
		headingClientId,
		headingMetadata,
		targetHeadingName,
		paginationContainerClientId,
		paginationContainerMetadata,
		targetPaginationContainerName,
		viewType,
		unitCount,
		targetPrevLabel,
		targetNextLabel,
		paginationPrevClientId,
		paginationPrevMetadata,
		paginationNextClientId,
		paginationNextMetadata,
		updateBlockAttributes,
	] );
}
