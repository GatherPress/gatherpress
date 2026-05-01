/**
 * WordPress dependencies
 */
import { createSlotFill } from '@wordpress/components';

/**
 * Create our Slot and Fill components
 */
const { Fill, Slot } = createSlotFill( 'EventQueryControls' );

const EventQueryControls = ( { children } ) => <Fill>{ children }</Fill>;

EventQueryControls.Slot = ( { fillProps } ) => (
	<Slot fillProps={ fillProps }>
		{ ( fills ) => ( fills.length ? fills : null ) }
	</Slot>
);

export default EventQueryControls;
