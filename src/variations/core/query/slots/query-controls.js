/**
 * WordPress dependencies
 */
import { createSlotFill } from '@wordpress/components';

/**
 * Create our Slot and Fill components
 */
const { Fill, Slot } = createSlotFill( 'GatherPressQueryControls' );

const GatherPressQueryControls = ( { children } ) => <Fill>{ children }</Fill>;

GatherPressQueryControls.Slot = ( { fillProps } ) => (
	<Slot fillProps={ fillProps }>
		{ ( fills ) => ( fills.length ? fills : null ) }
	</Slot>
);

export default GatherPressQueryControls;
