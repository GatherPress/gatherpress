/**
 * WordPress dependencies
 */
import { createSlotFill } from '@wordpress/components';

/**
 * Create our Slot and Fill components
 */
const { Fill, Slot } = createSlotFill( 'GPQLControlsInheritedQuery' );

const GPQLControlsInheritedQuery = ( { children } ) => <Fill>{ children }</Fill>;

GPQLControlsInheritedQuery.Slot = Slot;

export default GPQLControlsInheritedQuery;
