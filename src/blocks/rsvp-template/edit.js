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

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../helpers/globals';
import { memo, useState } from '@wordpress/element';
import TEMPLATE from './template';

const TemplateInnerBlocks = ({
	response,
	blocks,
	activeRsvpId,
	setActiveRsvpId,
	firstRsvpId,
}) => {
	const { children, ...innerBlocksProps } = useInnerBlocksProps(
		{},
		{ template: TEMPLATE }
	);

	return (
		<div {...innerBlocksProps}>
			{response.commentId === (activeRsvpId || firstRsvpId)
				? children
				: null}

			<MemoizedRsvpTemplatePreview
				blocks={blocks}
				commentId={response.commentId}
				setActiveRsvpId={setActiveRsvpId}
				isHidden={response.commentId === (activeRsvpId || firstRsvpId)}
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
	// the currently active block!

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
	firstRsvpId,
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
							firstRsvpId={firstRsvpId}
						/>
					</BlockContextProvider>
				);
			})}
	</>
);

const Edit = ({ clientId }) => {
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

	let rsvps = [{ commentId: -1 }];

	if (responses.attending.responses.length) {
		rsvps = responses.attending.responses;
	}

	return (
		<List
			responses={rsvps}
			blockProps={blockProps}
			blocks={blocks}
			activeRsvpId={activeRsvpId}
			setActiveRsvpId={setActiveRsvpId}
			firstRsvpId={rsvps[0]?.commentId}
		/>
	);
};

export default Edit;
