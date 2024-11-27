/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	BlockContextProvider,
	useBlockProps, useInnerBlocksProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';

const TEMPLATE = [
	['core/avatar'],
	['core/comment-author-name'],
];

const getPlaceholder = () => {};

const TemplateInnerBlocks = ( {
	response,
	blocks,
} ) => {
	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		{},
		{ template: TEMPLATE }
	);

	return(
		<div { ...innerBlocksProps }>
			{ children }
		</div>
	);
};

const List = ({
	responses,
	blocks
} ) => (
	<>
		{ responses &&
			responses.map( ( { commentId, ...response }, index ) => (
				<BlockContextProvider
					key={ response.commentId || index }
					value={ {
						commentId: commentId< 0 ? null : commentId,
					} }
				>
					<TemplateInnerBlocks
						response={ { commentId, ...response } }
						blocks={ blocks }
					/>
				</BlockContextProvider>
			) )
		}
	</>
);

const Edit = ( { clientId, context: { postId } } ) => {
	const blockProps = useBlockProps();
	const responses = getFromGlobal('eventDetails.responses');
	const { blocks } = useSelect(
		( select ) => {
			const { getBlocks } = select( blockEditorStore );
			return {
				blocks: getBlocks( clientId ),
			};
		},
		[ clientId ]
	);

	if (!responses.attending.count) {
		return (
			<p {...blockProps}>
				{__('No one is attending this event yet.', 'gatherpress')}
			</p>
		);
	}

	return (
		<List
			responses={ responses.attending.responses }
			blocks={ blocks }
		/>
	);
};

export default Edit;
