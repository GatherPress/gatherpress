/**
 * External dependencies
 */
import { execFileSync } from 'child_process';
import fs from 'fs';
import os from 'os';
import path from 'path';
import {
	afterEach,
	beforeEach,
	describe,
	expect,
	test,
} from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	resolveBaseUrl,
	resolveWpEnvPort,
} from '../../../e2e/resolve-wp-env-port';

/**
 * Create a temporary wp-env config directory holding the given files.
 *
 * @param {Object} files File name to raw-content map.
 *
 * @return {string} The directory path.
 */
function makeConfigDir( files = {} ) {
	const dir = fs.mkdtempSync( path.join( os.tmpdir(), 'gp-wp-env-' ) );

	for ( const [ name, contents ] of Object.entries( files ) ) {
		fs.writeFileSync( path.join( dir, name ), contents );
	}

	return dir;
}

const BOTH_FILES = {
	'.wp-env.test.override.json': JSON.stringify( { port: 4000 } ),
	'.wp-env.test.json': JSON.stringify( { port: 5000 } ),
};

let tempDirs;

/**
 * Create and track a temporary config directory for cleanup.
 *
 * @param {Object} files File name to raw-content map.
 *
 * @return {string} The directory path.
 */
function configDir( files = {} ) {
	const dir = makeConfigDir( files );

	tempDirs.push( dir );

	return dir;
}

const originalEnv = {
	WP_BASE_URL: process.env.WP_BASE_URL,
	WP_ENV_PORT: process.env.WP_ENV_PORT,
};

beforeEach( () => {
	tempDirs = [];
	delete process.env.WP_BASE_URL;
	delete process.env.WP_ENV_PORT;
} );

afterEach( () => {
	for ( const dir of tempDirs ) {
		fs.rmSync( dir, { recursive: true, force: true } );
	}

	for ( const [ name, value ] of Object.entries( originalEnv ) ) {
		if ( undefined === value ) {
			delete process.env[ name ];
		} else {
			process.env[ name ] = value;
		}
	}
} );

describe( 'resolveWpEnvPort', () => {
	// wp-env's own resolution consults WP_ENV_PORT ahead of every config
	// file (see @wordpress/env's cli, "the port can be overridden via
	// WP_ENV_PORT"), so `WP_ENV_PORT=19984 npm run test:e2e` starts
	// WordPress on 19984. Playwright has to follow it there or it targets
	// whatever holds the file-configured port: the README's own "green
	// authentication, wrong site" trap.
	test( 'prefers a valid WP_ENV_PORT over both config files', () => {
		expect(
			resolveWpEnvPort( {
				env: { WP_ENV_PORT: '19984' },
				configDir: configDir( BOTH_FILES ),
			} ),
		).toBe( 19984 );
	} );

	test( 'ignores a non-numeric WP_ENV_PORT and falls through to the files', () => {
		expect(
			resolveWpEnvPort( {
				env: { WP_ENV_PORT: 'banana' },
				configDir: configDir( BOTH_FILES ),
			} ),
		).toBe( 4000 );
	} );

	test( 'ignores an out-of-range WP_ENV_PORT', () => {
		expect(
			resolveWpEnvPort( {
				env: { WP_ENV_PORT: '0' },
				configDir: configDir( BOTH_FILES ),
			} ),
		).toBe( 4000 );
		expect(
			resolveWpEnvPort( {
				env: { WP_ENV_PORT: '70000' },
				configDir: configDir( BOTH_FILES ),
			} ),
		).toBe( 4000 );
	} );

	test( 'ignores a fractional WP_ENV_PORT', () => {
		expect(
			resolveWpEnvPort( {
				env: { WP_ENV_PORT: '80.5' },
				configDir: configDir( BOTH_FILES ),
			} ),
		).toBe( 4000 );
	} );

	test( 'reads the override file ahead of .wp-env.test.json with no env override', () => {
		expect(
			resolveWpEnvPort( { env: {}, configDir: configDir( BOTH_FILES ) } ),
		).toBe( 4000 );
	} );

	test( 'falls back to .wp-env.test.json when the override file is absent', () => {
		expect(
			resolveWpEnvPort( {
				env: {},
				configDir: configDir( {
					'.wp-env.test.json': JSON.stringify( { port: 5000 } ),
				} ),
			} ),
		).toBe( 5000 );
	} );

	test( 'skips an override file that carries no port', () => {
		expect(
			resolveWpEnvPort( {
				env: {},
				configDir: configDir( {
					'.wp-env.test.override.json': JSON.stringify( {} ),
					'.wp-env.test.json': JSON.stringify( { port: 5000 } ),
				} ),
			} ),
		).toBe( 5000 );
	} );

	test( 'skips a malformed override file', () => {
		expect(
			resolveWpEnvPort( {
				env: {},
				configDir: configDir( {
					'.wp-env.test.override.json': 'not json at all',
					'.wp-env.test.json': JSON.stringify( { port: 5000 } ),
				} ),
			} ),
		).toBe( 5000 );
	} );

	test( "falls back to wp-env's default with no sources at all", () => {
		expect(
			resolveWpEnvPort( { env: {}, configDir: configDir() } ),
		).toBe( 8888 );
	} );

	test( 'defaults to the real process env and repo config directory', () => {
		// The bare calls `playwright.config.js` makes must resolve exactly
		// what an explicit call against the repo root and the live process
		// env resolves. The port itself varies per checkout (a local
		// override file is the point of the mechanism), so the assertion
		// pins the equivalence rather than a number.
		const expected = resolveWpEnvPort( {
			env: process.env,
			configDir: path.join( __dirname, '..', '..', '..', '..' ),
		} );

		expect( resolveWpEnvPort() ).toBe( expected );
		expect( resolveBaseUrl() ).toBe( `http://localhost:${ expected }` );
	} );
} );

describe( 'resolveBaseUrl', () => {
	test( 'WP_BASE_URL is the escape hatch that wins over port resolution', () => {
		expect(
			resolveBaseUrl( {
				env: {
					WP_BASE_URL: 'http://example.test:1234',
					WP_ENV_PORT: '19984',
				},
				configDir: configDir( BOTH_FILES ),
			} ),
		).toBe( 'http://example.test:1234' );
	} );

	test( 'builds the URL from the resolved port when WP_BASE_URL is unset', () => {
		expect(
			resolveBaseUrl( {
				env: { WP_ENV_PORT: '19984' },
				configDir: configDir( BOTH_FILES ),
			} ),
		).toBe( 'http://localhost:19984' );
	} );
} );

describe( 'playwright.config.js baseURL', () => {
	/**
	 * Read the baseURL the real Playwright config resolves under an env.
	 *
	 * `@playwright/test` cannot be required inside Jest, so the config is
	 * loaded in a child Node process: this drives the exact file
	 * `npx playwright test` reads, wiring included, not a re-derivation of
	 * it.
	 *
	 * @param {Object} env Environment overrides for the child process.
	 *
	 * @return {string} The resolved baseURL.
	 */
	function configBaseUrl( env ) {
		return execFileSync(
			process.execPath,
			[ '-e', 'console.log(require("./playwright.config.js").use.baseURL)' ],
			{
				cwd: path.join( __dirname, '..', '..', '..', '..' ),
				env: { ...process.env, ...env },
				encoding: 'utf8',
			},
		)
			.trim()
			.split( '\n' )
			.at( -1 );
	}

	test( 'honors WP_ENV_PORT, the same override wp-env itself applies', () => {
		expect(
			configBaseUrl( { WP_ENV_PORT: '19984', WP_BASE_URL: '' } ),
		).toBe( 'http://localhost:19984' );
	} );

	test( 'still lets WP_BASE_URL point the suite somewhere else entirely', () => {
		expect(
			configBaseUrl( {
				WP_ENV_PORT: '19984',
				WP_BASE_URL: 'http://example.test:1234',
			} ),
		).toBe( 'http://example.test:1234' );
	} );
} );
