/**
 * Backend blocks loader.
 */
import './attendance-list/index';
import './attendance-selector/index';
import './events-list/index';

wp.blocks.updateCategory( 'gatherpress', {
	icon: 'nametag',
} );
