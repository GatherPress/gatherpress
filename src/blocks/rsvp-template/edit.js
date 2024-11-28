/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	BlockContextProvider,
	useBlockProps,
	useInnerBlocksProps,
	store as blockEditorStore,
	__experimentalUseBlockPreview as useBlockPreview,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';
import { memo, useState } from '@wordpress/element';

const TEMPLATE = [
	['core/group', {}, [['core/avatar'], ['core/comment-author-name']]],
];

const getPlaceholder = () => {};

const TemplateInnerBlocks = ({
	response,
	blocks,
	blockProps,
	activeRsvpId,
	setActiveRsvpId,
}) => {
	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		{},
		{ template: TEMPLATE }
	);

	return (
		<div {...innerBlocksProps}>
			{response.commentId === activeRsvpId ? children : null}
			{/*{ children }*/}
			<MemoizedRsvpTemplatePreview
				blocks={blocks}
				commentId={response.commentId}
				setActiveRsvpId={setActiveRsvpId}
				isHidden={response.commentId === activeRsvpId}
			/>
		</div>
	);
};

const RsvpTemplatePreview = ({
	blocks,
	commentId,
	setActiveRsvpId,
	isHidden,
}) => {
	const blockPreviewProps = useBlockPreview({
		blocks,
	});

	const handleOnClick = () => {
		setActiveRsvpId(commentId);
	};

	// We have to hide the preview block if the `comment` props points to
	// the curently active block!

	// Or, to put it differently, every preview block is visible unless it is the
	// currently active block - in this case we render its inner blocks.
	const style = {
		display: isHidden ? 'none' : undefined,
	};

	return (
		<div
			{...blockPreviewProps}
			tabIndex={0}
			role="button"
			style={style}
			// eslint-disable-next-line jsx-a11y/no-noninteractive-element-to-interactive-role
			onClick={handleOnClick}
			onKeyPress={handleOnClick}
		/>
	);
};

const MemoizedRsvpTemplatePreview = memo(RsvpTemplatePreview);

const List = ({
	responses,
	blocks,
	blockProps,
	activeRsvpId,
	setActiveRsvpId,
}) => (
	<>
		{responses &&
			responses.map(({ commentId, ...response }, index) => {
				// Force commentId to be an integer
				const forcedCommentId = parseInt(commentId, 10);

				return (
					<BlockContextProvider
						key={forcedCommentId || index}
						value={{
							commentId:
								forcedCommentId < 0 ? null : forcedCommentId,
						}}
					>
						<TemplateInnerBlocks
							response={{
								commentId: forcedCommentId,
								...response,
							}}
							blockProps={blockProps}
							blocks={blocks}
							activeRsvpId={activeRsvpId}
							setActiveRsvpId={setActiveRsvpId}
						/>
					</BlockContextProvider>
				);
			})}
	</>
);

const Edit = ({ clientId, context: { postId } }) => {
	const blockProps = useBlockProps();
	const responses = getFromGlobal('eventDetails.responses');
	const [activeRsvpId, setActiveRsvpId] = useState(
		parseInt(responses.attending.responses[0]?.commentId, 10) ?? null
	);
	const { blocks } = useSelect(
		(select) => {
			const { getBlocks } = select(blockEditorStore);
			return {
				blocks: getBlocks(clientId),
			};
		},
		[clientId]
	);

	return (
		<List
			responses={responses.attending.responses}
			blockProps={blockProps}
			blocks={blocks}
			activeRsvpId={activeRsvpId}
			setActiveRsvpId={setActiveRsvpId}
		/>
	);
};

export default Edit;
