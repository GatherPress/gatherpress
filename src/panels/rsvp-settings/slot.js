/**
 * Defines an extensibility slot for the "RSVP Settings" panel.
 */

/**
 * WordPress dependencies
 */
import { createSlotFill, PanelRow } from '@wordpress/components';

export const { Fill, Slot } = createSlotFill( 'RsvpPluginDocumentSettings' );
export const RsvpPluginDocumentSettings = ( { children, className } ) => (
	<Fill>
		<PanelRow className={ className }>{ children }</PanelRow>
	</Fill>
);

RsvpPluginDocumentSettings.Slot = Slot;
