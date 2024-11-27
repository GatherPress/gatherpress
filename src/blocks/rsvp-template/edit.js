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
	[
		'core/image',
		{
			url: 'https://via.placeholder.com/150', // Replace with your default image URL
			alt: 'Default image', // Optional: Add default alt text
		},
	],
	['core/paragraph'],
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

	console.log(children);
	return(
		<div { ...innerBlocksProps}>
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
			responses.map( ( response, index ) => (
				<BlockContextProvider
					key={ index }
				>
					<TemplateInnerBlocks
						response={ response }
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
