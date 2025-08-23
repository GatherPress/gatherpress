/**
 * Defines an extensibility slot for the "Event Settings" panel.
 */

/**
 * WordPress dependencies
 */
import { createSlotFill, PanelRow } from '@wordpress/components';

export const { Fill, Slot } = createSlotFill( 'EventPluginDocumentSettings' );
export const EventPluginDocumentSettings = ( { children, className } ) => (
	<Fill>
		<PanelRow className={ className }>{ children }</PanelRow>
	</Fill>
);

EventPluginDocumentSettings.Slot = Slot;
