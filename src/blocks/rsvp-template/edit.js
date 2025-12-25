/**
 * WordPress dependencies.
 */
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseBlockPreview as useBlockPreview,
	BlockContextProvider,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { memo, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';

const TemplateInnerBlocks = ( {
	response,
	blocks,
	activeRsvpId,
	setActiveRsvpId,
	firstRsvpId,
} ) => {
	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		{},
		{ template: TEMPLATE },
	);

	return (
		<div { ...innerBlocksProps }>
			{ response.commentId === ( activeRsvpId || firstRsvpId )
				? children
				: null }

			<MemoizedRsvpTemplatePreview
				blocks={ blocks }
				commentId={ response.commentId }
				setActiveRsvpId={ setActiveRsvpId }
				isHidden={ response.commentId === ( activeRsvpId || firstRsvpId ) }
			/>
		</div>
	);
};

const RsvpTemplatePreview = ( {
	blocks,
	commentId,
	setActiveRsvpId,
	isHidden,
} ) => {
	const blockPreviewProps = useBlockPreview( {
		blocks,
	} );

	const handleOnClick = () => {
		setActiveRsvpId( commentId );
	};

	// We have to hide the preview block if the `comment` props points to
	// the currently active block!

	// Or, to put it differently, every preview block is visible unless it is the
	// currently active block - in this case we render its inner blocks.
	const style = {
		display: isHidden ? 'none' : undefined,
	};

	return (
		<div
			{ ...blockPreviewProps }
			tabIndex={ 0 }
			role="button" // NOSONAR - WordPress block preview requires div container.
			style={ style }
			// eslint-disable-next-line jsx-a11y/no-noninteractive-element-to-interactive-role
			onClick={ handleOnClick }
			onKeyUp={ handleOnClick }
		/>
	);
};

const MemoizedRsvpTemplatePreview = memo( RsvpTemplatePreview );

const List = ( {
	responses,
	blocks,
	blockProps,
	activeRsvpId,
	setActiveRsvpId,
	firstRsvpId,
	postId,
} ) => (
	<>
		{ responses?.map( ( { commentId, ...response }, index ) => {
			// Force commentId to be an integer
			const forcedCommentId = parseInt( commentId, 10 );

			return (
				<BlockContextProvider
					key={ forcedCommentId || index }
					value={ {
						commentId:
								0 > forcedCommentId ? null : forcedCommentId,
						postId,
					} }
				>
					<TemplateInnerBlocks
						response={ {
							commentId: forcedCommentId,
							...response,
						} }
						blockProps={ blockProps }
						blocks={ blocks }
						activeRsvpId={ activeRsvpId }
						setActiveRsvpId={ setActiveRsvpId }
						firstRsvpId={ firstRsvpId }
					/>
				</BlockContextProvider>
			);
		} ) }
	</>
);

const Edit = ( { clientId, context } ) => {
	const blockProps = useBlockProps();

	// Access the provided RSVP responses context from the parent block.
	const rsvpResponses = context?.[ 'gatherpress/rsvpResponses' ] ?? null;
	const rsvpLimitEnabled = context?.[ 'gatherpress/rsvpLimitEnabled' ] ?? false;
	const rsvpLimit = context?.[ 'gatherpress/rsvpLimit' ] ?? 8;
	const postId = context?.postId;

	// Initialize active RSVP ID.
	const [ activeRsvpId, setActiveRsvpId ] = useState(
		parseInt( rsvpResponses?.attending?.records?.[ 0 ]?.commentId, 10 ) ?? null,
	);

	// Get the block's inner blocks.
	const { blocks } = useSelect(
		( select ) => {
			const { getBlocks } = select( blockEditorStore );
			return {
				blocks: getBlocks( clientId ),
			};
		},
		[ clientId ],
	);

	// Prepare RSVP data.
	let rsvps = [ { commentId: -1 } ];

	if ( rsvpResponses?.attending?.records?.length ) {
		rsvps = rsvpResponses.attending.records;

		// Apply limit if enabled.
		if ( rsvpLimitEnabled ) {
			rsvps = rsvps.slice( 0, rsvpLimit );
		}
	}

	return (
		<List
			responses={ rsvps }
			blockProps={ blockProps }
			blocks={ blocks }
			activeRsvpId={ activeRsvpId }
			setActiveRsvpId={ setActiveRsvpId }
			firstRsvpId={ rsvps[ 0 ]?.commentId }
			postId={ postId }
		/>
	);
};

export default Edit;
