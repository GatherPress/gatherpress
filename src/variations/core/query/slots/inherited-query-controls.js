/**
 * WordPress dependencies
 */
import { createSlotFill } from '@wordpress/components';

/**
 * Create our Slot and Fill components
 */
const { Fill, Slot } = createSlotFill( 'GatherPressInheritedQueryControls' );

const GatherPressInheritedQueryControls = ( { children } ) => (
	<Fill>{ children }</Fill>
);

GatherPressInheritedQueryControls.Slot = ( { fillProps } ) => (
	<Slot fillProps={ fillProps }>
		{ ( fills ) => ( fills.length ? fills : null ) }
	</Slot>
);

export default GatherPressInheritedQueryControls;
