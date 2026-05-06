/**
 * Internal dependencies
 */
import ATTENDING from './attending';
import NO_STATUS from './no-status';
import NOT_ATTENDING from './not-attending';
import WAITING_LIST from './waiting-list';
import PAST from './past';

/**
 * RSVP Button with Modal — bundle of per-status templates that drive the
 * five RSVP block states (no_status, attending, waiting_list, not_attending,
 * past). Picking this pattern from the picker seeds all five templates into
 * `serializedInnerBlocks`; the editor then renders the active status's tree
 * as inner blocks and switches between them via the inspector dropdown.
 *
 * @type {Object<string, Array>}
 */
const RSVP_BUTTON_WITH_MODAL_TEMPLATES = {
	no_status: NO_STATUS,
	attending: ATTENDING,
	waiting_list: WAITING_LIST,
	not_attending: NOT_ATTENDING,
	past: PAST,
};

export default RSVP_BUTTON_WITH_MODAL_TEMPLATES;
