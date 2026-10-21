/**
 * ShadowSourceFilterControls component.
 *
 * Reuses the ShadowSourceFilterControls of the Event Query Loop controls
 * to expose "Filter by current {source}" in the Calendar inspector panel.
 *
 * @package
 * @since TBD
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useCallback, useMemo } from '@wordpress/element';

import { ShadowSourceFilterControls as GatherPressShadowSourceControl } from '../../../../variations/core/query/components';

import {
	isContextDateSource,
	isSelectedDateSource,
} from '../utils/source-utils';

/**
 * Renders GatherPress's ShadowSourceFilterControls scoped to the calendar's parent Query block.
 *
 * All hooks are called unconditionally at the top of the component to preserve React 18 fiber stability.
 *
 * @param {Object} props                 Component props.
 * @param {string} props.clientId        Calendar block client ID.
 * @param {string} props.dateRangeSource Source mode ('default', 'context', 'selected').
 * @param {number} props.postId          Selected post ID in 'selected' mode.
 * @param {string} props.sourcePostType  Selected post type in 'selected' mode.
 * @param {Object} props.context         Block context dictionary.
 *
 * @return {Element|null} The GatherPress control or null.
 */
export function ShadowSourceFilterControls( {
	clientId,
	dateRangeSource = 'default',
	postId = 0,
	sourcePostType = '',
	context = {},
} ) {
	const isContext = isContextDateSource( dateRangeSource );
	const isSelected = isSelectedDateSource( dateRangeSource );
	const isPostAnchored = isContext || ( isSelected && 0 < Number( postId ) );

	const { parentQueryId, parentQueryAttributes } = useSelect(
		( select ) => {
			const { getBlockParentsByBlockName, getBlock } =
				select( blockEditorStore );
			const parents = getBlockParentsByBlockName(
				clientId,
				'core/query',
			);
			const pId = parents?.at( -1 );
			const pBlock = pId ? getBlock( pId ) : null;

			return {
				parentQueryId: pId,
				parentQueryAttributes: pBlock?.attributes,
			};
		},
		[ clientId ],
	);

	const { updateBlockAttributes } = useDispatch( blockEditorStore );

	const setParentQueryAttributes = useCallback(
		( newAttributes ) => {
			if ( parentQueryId ) {
				updateBlockAttributes( parentQueryId, newAttributes );
			}
		},
		[ parentQueryId, updateBlockAttributes ],
	);

	const effectiveContext = useMemo( () => {
		if ( isSelected ) {
			return { ...context, postId, postType: sourcePostType };
		}
		return context;
	}, [ isSelected, context, postId, sourcePostType ] );

	// Guard checks and early exits are strictly performed after all hooks have executed:
	if ( ! isPostAnchored || ! parentQueryId || ! parentQueryAttributes ) {
		return null;
	}

	const Control =
		GatherPressShadowSourceControl ||
		window.gatherpress?.queryControls?.ShadowSourceFilterControls;

	if ( ! Control ) {
		return null;
	}

	return (
		<div style={ { marginBottom: '16px' } }>
			<Control
				attributes={ parentQueryAttributes }
				setAttributes={ setParentQueryAttributes }
				context={ effectiveContext }
			/>
		</div>
	);
}
