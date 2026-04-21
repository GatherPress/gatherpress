/**
 * WordPress dependencies.
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { useCallback } from '@wordpress/element';

/**
 * Custom hook for handling block insertion on Enter key.
 *
 * Provides keyboard handling for RichText fields that inserts
 * new paragraph blocks when Enter is pressed at the beginning
 * or end of the field.
 *
 * @since 1.0.0
 *
 * @param {string}   clientId          - The block's client ID.
 * @param {Function} insertBlocksAfter - Function to insert blocks after this block.
 * @return {Object} Block insertion handlers.
 */
export function useBlockInsertion( clientId, insertBlocksAfter ) {
	// Block insertion dispatch functions.
	const { insertBlocks, selectBlock } = useDispatch( blockEditorStore );

	// Block position selectors.
	const { getBlockRootClientId, getBlockIndex } = useSelect(
		( selectEditor ) => selectEditor( blockEditorStore ),
		[]
	);

	/**
	 * Handles Enter key press in RichText fields.
	 *
	 * - At beginning: Inserts paragraph above
	 * - At end: Inserts paragraph below
	 * - In middle: Prevents default (no line break)
	 *
	 * @param {KeyboardEvent} event - The keyboard event.
	 */
	const handleKeyDown = useCallback(
		( event ) => {
			if ( 'Enter' === event.key && ! event.shiftKey ) {
				// Always prevent default to avoid line break/snap behavior.
				event.preventDefault();

				const contentElement = event.currentTarget;
				const selection =
					contentElement.ownerDocument.defaultView.getSelection();
				if ( ! selection.rangeCount ) {
					return;
				}

				const range = selection.getRangeAt( 0 );
				const textContent = contentElement.textContent || '';

				// Calculate cursor position.
				const preRange = document.createRange();
				preRange.selectNodeContents( contentElement );
				preRange.setEnd( range.startContainer, range.startOffset );
				const cursorPosition = preRange.toString().length;

				// At the beginning.
				if ( 0 === cursorPosition ) {
					const newBlock = createBlock( 'core/paragraph' );
					const rootClientId = getBlockRootClientId( clientId );
					const blockIndex = getBlockIndex( clientId );
					// Insert at the current block's index (pushes current block down).
					insertBlocks( newBlock, blockIndex, rootClientId );
					// Select the newly created block to move focus to it.
					selectBlock( newBlock.clientId );
				} else if ( cursorPosition === textContent.length ) {
					// At the end.
					const newBlock = createBlock( 'core/paragraph' );
					insertBlocksAfter( [ newBlock ] );
				}
				// In the middle - do nothing (already prevented default).
			}
		},
		[
			clientId,
			getBlockRootClientId,
			getBlockIndex,
			insertBlocks,
			selectBlock,
			insertBlocksAfter,
		]
	);

	return {
		handleKeyDown,
	};
}
