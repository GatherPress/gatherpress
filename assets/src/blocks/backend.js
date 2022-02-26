/**
 * Backend blocks loader.
 */
import './attendance-list/index';
import './attendance-selector/index';
import './upcoming-events/index';
import './past-events/index';

wp.blocks.updateCategory( 'gatherpress', {
	icon: 'nametag'
});
