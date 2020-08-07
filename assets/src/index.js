/**
 * Internal dependencies
 */
import './block-editor';

// svg code
import gatherPressIcon from './../images/gatherpress-icon';

// alter the icon slot
wp.blocks.updateCategory( 'gatherpress', {
	icon: gatherPressIcon
} );
