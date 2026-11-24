/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	CHANGE_NOTICE_ID,
	getChangeNoticeText,
} from '@src/helpers/event-change';

/**
 * Coverage for the change notice helpers.
 */
describe( 'event change helpers', () => {
	it( 'keeps the notice ID the event update notice always had', () => {
		expect( CHANGE_NOTICE_ID ).toBe( 'gatherpress_event_communication' );
	} );

	it( 'names the change and the number of attendees', () => {
		expect(
			getChangeNoticeText( {
				headline: 'The venue changed.',
				attendees: 12,
			} ),
		).toBe( 'The venue changed. Tell 12 attendees?' );
	} );

	it( 'uses the singular for one attendee', () => {
		expect(
			getChangeNoticeText( {
				headline: 'The time zone changed.',
				attendees: 1,
			} ),
		).toBe( 'The time zone changed. Tell 1 attendee?' );
	} );
} );
