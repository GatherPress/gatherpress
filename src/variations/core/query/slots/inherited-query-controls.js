/**
 * WordPress dependencies
 */
import { createSlotFill } from '@wordpress/components';

/**
 * Create our Slot and Fill components
 */
const { Fill, Slot } = createSlotFill( 'EventInheritedQueryControls' );

const EventInheritedQueryControls = ( { children } ) => (
	<Fill>{ children }</Fill>
);

EventInheritedQueryControls.Slot = ( { fillProps } ) => (
	<Slot fillProps={ fillProps }>
		{ ( fills ) => ( fills.length ? fills : null ) }
	</Slot>
);

export default EventInheritedQueryControls;
