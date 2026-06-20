/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Computes the attribute updates needed when a dropdown's "Default Selected Item"
 * is removed while "Select Mode" is enabled.
 *
 * When an item set as the default selection is deleted, `selectedIndex` is left
 * pointing at an item that no longer exists, which keeps the stale label on the
 * trigger. This resolves that state:
 *
 * - Select mode off, or the selection still points at a real item: no change.
 * - The selected item was removed but others remain: fall back to the first item.
 * - Every item removed: turn select mode off and reset the label, so the block
 *   behaves like a fresh, empty dropdown.
 *
 * @since 0.34.0
 *
 * @param {boolean} actAsSelect   Whether "Select Mode" is enabled.
 * @param {Array}   innerBlocks   The dropdown's inner item blocks.
 * @param {number}  selectedIndex The currently selected item index.
 * @param {string}  defaultLabel  Label to fall back to when all items are removed.
 *
 * @return {Object|null} Attribute updates to apply, or null when nothing changes.
 */
export function getSelectedItemReset(
	actAsSelect,
	innerBlocks,
	selectedIndex,
	defaultLabel
) {
	// Only relevant while the dropdown is acting as a select.
	if ( ! actAsSelect ) {
		return null;
	}

	const itemCount = Array.isArray( innerBlocks ) ? innerBlocks.length : 0;

	// All items removed: behave like an empty dropdown and switch select mode off.
	if ( ! itemCount ) {
		return { actAsSelect: false, selectedIndex: 0, label: defaultLabel };
	}

	// Selected item removed but others remain: fall back to the first item.
	if ( 0 > selectedIndex || selectedIndex >= itemCount ) {
		return { selectedIndex: 0 };
	}

	// Selection still points at a valid item: nothing to do.
	return null;
}

/**
 * Custom hook to detect if a block or any of its children are currently selected.
 *
 * This hook is useful for UI components that need to show/hide based on whether
 * the block is in focus. For example, a dropdown that should auto-close when
 * clicking outside of it.
 *
 * @since 0.33.0
 *
 * @param {string} clientId The unique identifier for the block instance.
 *
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
