/**
 * WordPress dependencies.
 */
import { useSelect } from '@wordpress/data';

/**
 * Custom hook to detect if a block or any of its children are currently selected.
 *
 * This hook is useful for UI components that need to show/hide based on whether
 * the block is in focus. For example, a dropdown that should auto-close when
 * clicking outside of it.
 *
 * @since 1.0.0
 *
 * @param {string} clientId The unique identifier for the block instance.
 * @return {boolean} True if the block or any of its children are selected, false otherwise.
 */
export function useIsBlockOrDescendantSelected( clientId ) {
	return useSelect(
		( select ) => {
			const selectedBlockId =
				select( 'core/block-editor' ).getSelectedBlockClientId();

			// If no block is selected, this block is not selected.
			if ( ! selectedBlockId ) {
				return false;
			}

			// Check if selected block is this block itself.
			if ( selectedBlockId === clientId ) {
				return true;
			}

			// Check if selected block is a child of this block.
			const selectedBlockParents =
				select( 'core/block-editor' ).getBlockParents( selectedBlockId );

			return selectedBlockParents.includes( clientId );
		},
		[ clientId ]
	);
}
