/**
 * Backend blocks loader.
 */
import './attendance-list/index';
import './attendance-selector/index';
import './upcoming-events/index';
import './past-events/index';

// @todo update svg code to new logo
import gatherPressIcon from './../../images/gatherpress-icon';

// alter the icon slot
wp.blocks.updateCategory( 'gatherpress', {
	icon: gatherPressIcon
});
