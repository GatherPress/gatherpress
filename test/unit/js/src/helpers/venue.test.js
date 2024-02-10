/**
 * External dependencies.
 */
import { describe, expect, jest, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { isVenuePostType } from '../../../../../src/helpers/venue';

// Mock the @wordpress/data module
jest.mock('@wordpress/data', () => ({
	select: jest.fn(),
}));

/**
 * Coverage for isVenuePostType
 */
describe('isVenuePostType', () => {
	it('returns false when there is no current post type', () => {
		expect(isVenuePostType()).toBe(false);
	});

	it('returns false when current post type is gp_event', () => {
		require('@wordpress/data').select.mockImplementation((store) => ({
			getCurrentPostType: () => store === 'core/editor' ? 'gp_event' : null,
		}));
		expect(isVenuePostType()).toBe(false);
	});

	it('returns true when current post type is gp_venue', () => {
		require('@wordpress/data').select.mockImplementation((store) => ({
			getCurrentPostType: () => store === 'core/editor' ? 'gp_venue' : null,
		}));
		expect(isVenuePostType()).toBe(true);
	});
});
