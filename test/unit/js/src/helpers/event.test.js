/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';
import moment from 'moment';
import 'moment-timezone';

/**
 * WordPress dependencies.
 */
import { select } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies.
 */
import {
	hasEventPast,
	hasEventPastNotice,
} from '../../../../../src/helpers/event';
import { dateTimeMomentFormat } from '../../../../../src/helpers/datetime';

/**
 * Coverage for hasEventPast.
 */
describe('hasEventPast', () => {
	it('returns true', () => {
		global.GatherPress = {
			event_datetime: {
				datetime_end: moment()
					.subtract(1, 'days')
					.format(dateTimeMomentFormat),
				timezone: 'America/New_York',
			},
		};

		expect(hasEventPast()).toBe(true);
	});

	it('returns false', () => {
		global.GatherPress = {
			event_datetime: {
				datetime_end: moment()
					.add(1, 'days')
					.format(dateTimeMomentFormat),
				timezone: 'America/New_York',
			},
		};

		expect(hasEventPast()).toBe(false);
	});
});

/**
 * Coverage for hasEventPastNotice.
 */
describe('hasEventPastNotice', () => {
	it('no notice if not set', () => {
		hasEventPastNotice();

		const notices = select(noticesStore).getNotices();

		expect(notices).toHaveLength(0);
	});

	it('notice is set', () => {
		global.GatherPress = {
			event_datetime: {
				datetime_end: moment()
					.subtract(1, 'days')
					.format(dateTimeMomentFormat),
				timezone: 'America/New_York',
			},
		};

		hasEventPastNotice();

		const notices = select(noticesStore).getNotices();

		expect(notices[0].content).toBe('This event has already past.');
	});
});
