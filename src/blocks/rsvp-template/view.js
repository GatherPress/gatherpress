import { createBlock, getBlockContent } from '@wordpress/blocks';

// Add commentId context to all blocks recursively
const addCommentIdContext = (blocks, commentId) => {
	return blocks.map((block) => {
		// Create the block with its attributes and recursively process innerBlocks
		// const updatedBlock = createBlock(
		const updatedBlock = wp.blocks.createBlock(
			block.blockName,
			{
				...block.attrs,
			},
			addCommentIdContext(block.innerBlocks || [], commentId) // Recursively process inner blocks
		);

		// Attach the commentId to the context of the block
		updatedBlock.context = {
			...updatedBlock.context,
			commentId,
		};

		return updatedBlock;
	});
};

// Function to recursively render blocks and their innerBlocks
const renderBlocksRecursively = (blocks) => {
	return blocks
		.map((block) => {
			// Render the current block's content
			// let blockMarkup = getBlockContent(block);
			let blockMarkup = wp.blocks.getBlockContent(block);

			// If the block has innerBlocks, render them recursively
			if (block.innerBlocks && block.innerBlocks.length > 0) {
				// Recursively render the inner blocks
				const innerBlocksMarkup = renderBlocksRecursively(block.innerBlocks);

				// Replace the block comments with the actual inner block markup
				blockMarkup = blockMarkup.replace(/<!-- wp:.* \/-->/g, innerBlocksMarkup);
			}

			return blockMarkup;
		})
		.join('');
};

// Example Test
const blockData = document.querySelector('.gatherpress-rsvp-template__inner-blocks-data')
	?.getAttribute('data-inner-blocks');

if (blockData) {
	// Parse the data-inner-blocks attribute
	const innerBlocks = JSON.parse(blockData);
console.log(innerBlocks);
	// Add the commentId context
	const blocksWithContext = addCommentIdContext(innerBlocks, 138);

	// Render the blocks recursively
	const fullMarkup = renderBlocksRecursively(blocksWithContext);

	// Log the final output
	console.log(fullMarkup);

	// Optionally, insert into the DOM for testing
	const container = document.querySelector('.gatherpress-rsvp-template__output');
	if (container) {
		container.innerHTML = fullMarkup;
	}
}
