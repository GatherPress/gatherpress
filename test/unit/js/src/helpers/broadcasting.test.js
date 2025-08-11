/**
 * External dependencies.
 */
import { expect, jest, test } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { Broadcaster, Listener } from '../../../../../src/helpers/broadcasting';

/**
 * Coverage for Broadcaster.
 * Coverage for Listener.
 */
test( 'Broadcaster and Listener with no identifier', () => {
	const unitTest = jest.fn();
	const payload = {
		unitTest: 'unit-test',
	};

	Listener( { unitTest } );
	expect( unitTest ).not.toHaveBeenCalled();
	Broadcaster( payload );
	expect( unitTest ).toHaveBeenCalled();
} );

test( 'Broadcaster and Listener with an identifier', () => {
	const unitTest = jest.fn();
	const payload = {
		unitTest: 'unit-test',
	};

	Listener( { unitTest }, '1' );
	expect( unitTest ).not.toHaveBeenCalled();
	Broadcaster( payload );
	expect( unitTest ).not.toHaveBeenCalled();
	Broadcaster( payload, '1' );
	expect( unitTest ).toHaveBeenCalled();
} );
