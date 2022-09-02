/**
 * Backend blocks loader.
 */
import './attendance-list/index';
import './attendance-selector/index';
import './events-list/index';
import './venue-information/index';

wp.blocks.updateCategory( 'gatherpress', {
	icon: 'nametag',
} );
