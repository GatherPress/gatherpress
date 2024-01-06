/**
 * External dependencies.
 */
import { expect, test } from '@jest/globals';
import moment from 'moment';
import 'moment-timezone';

/**
 * Internal dependencies.
 */
import { hasEventPast } from '../../../../../src/helpers/event';
import { dateTimeMomentFormat } from '../../../../../src/helpers/datetime';

/**
 * Coverage for getTimeZone.
 */
test('hasEventPast returns true', () => {
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

test('hasEventPast returns false', () => {
	global.GatherPress = {
		event_datetime: {
			datetime_end: moment().add(1, 'days').format(dateTimeMomentFormat),
			timezone: 'America/New_York',
		},
	};

	expect(hasEventPast()).toBe(false);
});
