/**
 * External dependencies
 */
import { readdirSync, readFileSync } from 'fs';
import { join, relative, sep } from 'path';
import { describe, expect, it } from '@jest/globals';

const SUITE_ROOT = __dirname;

/**
 * Collects every `*.test.js` file in the JavaScript unit-test suite.
 *
 * @return {string[]} Absolute paths to the suite's test files.
 */
function testFiles() {
	return readdirSync( SUITE_ROOT, { recursive: true } )
		.filter( ( entry ) => entry.endsWith( '.test.js' ) )
		.map( ( entry ) => join( SUITE_ROOT, entry ) );
}

/**
 * Finds the module names a file mocks with `{ virtual: true }`.
 *
 * Each `jest.mock(` opens a chunk that runs to the next one; the first quoted
 * string in the chunk is the mocked module name. When the flag is present, it
 * is the same call's third argument.
 *
 * @param {string} source The file's contents.
 *
 * @return {string[]} The virtually mocked module names, in source order.
 */
function virtuallyMockedModules( source ) {
	return source
		.split( 'jest.mock(' )
		.slice( 1 )
		.filter( ( chunk ) => chunk.includes( 'virtual: true' ) )
		.map( ( chunk ) => /'([^']+)'/.exec( chunk )?.[ 1 ] )
		.filter( Boolean );
}

/**
 * `jest.mock( name, factory, { virtual: true } )` changes how Jest derives the
 * module ID for `name`: instead of the resolved file path it uses the bare
 * specifier, but only for importers that resolve the name *after* the flag has
 * been registered. `Resolver.getModuleID()` memoizes each `(importer, name)`
 * pair, and `jest-runner`'s test worker keeps one `Resolver` for every test
 * file it runs. If another test file in the same worker pulls the same
 * importer in first, the real resolved path is cached and the later
 * `jest.mock()` registration no longer matches it. The importer then receives
 * the *real* module while the mocking test file holds the mock, and the
 * mismatch surfaces as a mock with zero recorded calls. Which files share a
 * worker is scheduling-dependent, so this fails intermittently.
 *
 * The flag exists for modules Jest cannot resolve at all (`@wordpress/interactivity`
 * publishes an `import`-only `exports` map, so there is no CommonJS entry point
 * for the test runner to find). For anything Jest *can* resolve, the flag buys
 * nothing and creates the race above. A plain `jest.mock( name, factory )`
 * keys the mock to the resolved path, which every importer agrees on.
 */
describe( 'jest.mock virtual-flag hygiene', () => {
	it( 'only marks a mock virtual when the module cannot be resolved', () => {
		const offenders = [];

		testFiles().forEach( ( file ) => {
			virtuallyMockedModules( readFileSync( file, 'utf8' ) ).forEach(
				( moduleName ) => {
					try {
						require.resolve( moduleName );
					} catch {
						// Unresolvable, so `virtual: true` is load-bearing.
						return;
					}

					offenders.push(
						`${ relative( SUITE_ROOT, file ).split( sep ).join( '/' ) }: ${ moduleName }`
					);
				}
			);
		} );

		expect( offenders ).toEqual( [] );
	} );

	it( 'inspects the suite it is meant to guard', () => {
		// Without this, a broken glob would leave the check above green while
		// scanning nothing at all.
		const files = testFiles();

		expect( files.length ).toBeGreaterThan( 50 );
		expect(
			files.some( ( file ) =>
				file.endsWith( join( 'helpers', 'interactivity.test.js' ) )
			)
		).toBe( true );
	} );
} );
