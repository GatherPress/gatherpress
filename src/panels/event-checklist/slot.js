/**
 * Defines an extensibility slot for the "Checklist" panel.
 */

/**
 * WordPress dependencies
 */
import { createSlotFill, PanelRow } from '@wordpress/components';

export const { Fill, Slot } = createSlotFill(
	'ChecklistPluginDocumentSettings',
);
export const ChecklistPluginDocumentSettings = ( { children, className } ) => (
	<Fill>
		<PanelRow className={ className }>{ children }</PanelRow>
	</Fill>
);

ChecklistPluginDocumentSettings.Slot = Slot;
