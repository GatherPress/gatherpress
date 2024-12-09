/**
 * Internal dependencies.
 */
import ATTENDING from './templates/attending';
import NO_STATUS from './templates/no-status';
import NOT_ATTENDING from './templates/not-attending';
import WAITING_LIST from './templates/waiting-list';

/**
 * RSVP block templates mapped by status.
 *
 * This file aggregates all RSVP templates into a single object, allowing
 * easy access to templates based on RSVP status.
 *
 * @type {Object}
 */
const TEMPLATES = {
	no_status: NO_STATUS,
	attending: ATTENDING,
	waiting_list: WAITING_LIST,
	not_attending: NOT_ATTENDING,
};

export default TEMPLATES;
