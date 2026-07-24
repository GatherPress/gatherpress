/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import { REST_NAMESPACE } from '@src/helpers/namespace';

/**
 * Coverage for namespace constants.
 */
describe( 'namespace', () => {
	it( 'exports the correct REST namespace', () => {
		expect( REST_NAMESPACE ).toBe( 'gatherpress/v1' );
	} );
} );
