/**
 * Internal dependencies
 */
import DAY_TEMPLATE from '../calendar-day/template';

/**
 * Default template for the calendar-week block: one Calendar Day, which the
 * week repeats for each day it shows.
 */
const TEMPLATE = [ [ 'gatherpress/calendar-day', {}, DAY_TEMPLATE ] ];

export default TEMPLATE;
