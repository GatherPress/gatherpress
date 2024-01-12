/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { isVenuePostType } from '../../../../../src/helpers/venue';

/**
 * Coverage for isVenuePostType.
 */
describe('isVenuePostType', () => {
	it('should return false', () => {
		expect(isVenuePostType()).toBe(false);
	});

	it('should return false', () => {
		global.GatherPress = {
			post_type: 'gp_event',
		};

		expect(isVenuePostType()).toBe(false);
	});

	it('should return true', () => {
		global.GatherPress = {
			post_type: 'gp_venue',
		};

		expect(isVenuePostType()).toBe(true);
	});
});
