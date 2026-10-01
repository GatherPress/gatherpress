/**
 * External dependencies
 */
import { afterEach, describe, expect, it, jest } from '@jest/globals';

const PUBLISHED = [
	'EventCountControls',
	'EventExcludeControls',
	'EventIncludeUnfinishedControls',
	'EventListTypeControls',
	'EventOffsetControls',
	'EventOrderControls',
	'ShadowSourceFilterControls',
];

/**
 * Load expose.js fresh, so each test sees its side effect on window run
 * against whatever state that test set up first.
 *
 * @return {Object} The mocked components module, for identity checks.
 */
const loadExpose = () => {
	let components;

	jest.isolateModules( () => {
		jest.doMock( '@src/variations/core/query/components', () =>
			Object.fromEntries(
				PUBLISHED.map( ( name ) => [ name, function Control() {} ] ),
			),
		);

		components = require( '@src/variations/core/query/components' );
		require( '@src/variations/core/query/expose' );
	} );

	return components;
};

describe( 'window.gatherpress.queryControls', () => {
	afterEach( () => {
		delete window.gatherpress;
	} );

	it( 'creates the namespace when nothing is there yet', () => {
		delete window.gatherpress;

		loadExpose();

		expect( window.gatherpress.queryControls ).toBeDefined();
	} );

	it( 'publishes exactly the seven controls, as the same components', () => {
		const components = loadExpose();

		expect( Object.keys( window.gatherpress.queryControls ).sort() ).toEqual(
			[ ...PUBLISHED ].sort(),
		);

		PUBLISHED.forEach( ( name ) => {
			expect( window.gatherpress.queryControls[ name ] ).toBe(
				components[ name ],
			);
		} );
	} );

	it( 'keeps whatever else is already on the namespace', () => {
		window.gatherpress = { somethingElse: 'kept' };

		loadExpose();

		expect( window.gatherpress.somethingElse ).toBe( 'kept' );
		expect( window.gatherpress.queryControls ).toBeDefined();
	} );
} );
