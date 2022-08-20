/**
 * Backend blocks loader.
 */
import './attendance-list/index';
import './attendance-selector/index';
import './events-list/index';
// import './date-block';
import './event-starter';

wp.blocks.updateCategory( 'gatherpress', {
	icon: 'nametag',
} );
