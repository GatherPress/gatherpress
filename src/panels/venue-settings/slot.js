/**
 * Defines as extensibility slot for the "Venue Settings" panel.
 */

/**
 * WordPress dependencies
 */
import { createSlotFill, PanelRow } from '@wordpress/components';

export const { Fill, Slot } = createSlotFill( 'VenuePluginDocumentSettings' );
export const VenuePluginDocumentSettings = ( { children, className } ) => (
	<Fill>
		<PanelRow className={ className }>{ children }</PanelRow>
	</Fill>
);

VenuePluginDocumentSettings.Slot = Slot;
