/**
 * Defines as extensibility slot for the Production panel.
 */

/**
 * WordPress dependencies
 */
import { createSlotFill, PanelRow } from '@wordpress/components';

export const { Fill, Slot } = createSlotFill('VenuePluginDocumentSettings');

// export const VenuePluginDocumentSettings = ({ children, className }) => (
export const VenuePluginDocumentSettings = ( props ) => {
	console.log(props);
	return (

	<Fill>
		<PanelRow className={props.className}>{props.children}</PanelRow>
	</Fill>

// <SlotFillProvider>
// 	<Panel header="Panel with slot">
// 		<PanelBody>
// 			<Slot name="ExampleSlot"/>
// 		</PanelBody>
// 	</Panel>
// 	<Fill name="ExampleSlot" >
// 		Panel body
// 	</Fill>
// </SlotFillProvider>
)};

VenuePluginDocumentSettings.Slot = Slot;
