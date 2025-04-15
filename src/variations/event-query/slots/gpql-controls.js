/**
 * WordPress dependencies
 */
import { createSlotFill } from '@wordpress/components';

/**
 * Create our Slot and Fill components
 */
const { Fill, Slot } = createSlotFill( 'GPQLControls' );

const GPQLControls = ( { children } ) => <Fill>{ children }</Fill>;

GPQLControls.Slot = ( { fillProps } ) => (
	<Slot fillProps={ fillProps }>
		{ ( fills ) => {
			return fills.length ? fills : null;
		} }
	</Slot>
);

export default GPQLControls;
