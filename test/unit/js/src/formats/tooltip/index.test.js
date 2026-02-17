/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeAll } from '@jest/globals';

// Store the registration call arguments.
let registrationArgs = null;

// Mock WordPress modules before any imports.
jest.mock( '@wordpress/rich-text', () => ( {
	registerFormatType: jest.fn( ( name, settings ) => {
		registrationArgs = { name, settings };
	} ),
} ) );

// Mock the style import.
jest.mock( '../../../../../../src/formats/tooltip/style.scss', () => ( {} ) );

describe( 'Tooltip format registration', () => {
	beforeAll( async () => {
		// Import the module to trigger registration.
		await import( '../../../../../../src/formats/tooltip/index' );
	} );

	it( 'registers the tooltip format type', () => {
		expect( registrationArgs ).not.toBeNull();
	} );

	it( 'registers with correct format name', () => {
		expect( registrationArgs.name ).toBe( 'gatherpress/tooltip' );
	} );

	it( 'registers with correct title', () => {
		expect( registrationArgs.settings.title ).toBe( 'Tooltip' );
	} );

	it( 'registers with span tagName', () => {
		expect( registrationArgs.settings.tagName ).toBe( 'span' );
	} );

	it( 'registers with correct className', () => {
		expect( registrationArgs.settings.className ).toBe(
			'gatherpress-tooltip'
		);
	} );

	it( 'registers with tooltip data attribute', () => {
		expect( registrationArgs.settings.attributes ).toHaveProperty(
			'data-gatherpress-tooltip'
		);
		expect(
			registrationArgs.settings.attributes[ 'data-gatherpress-tooltip' ]
		).toBe( 'data-gatherpress-tooltip' );
	} );

	it( 'registers with text color data attribute', () => {
		expect( registrationArgs.settings.attributes ).toHaveProperty(
			'data-gatherpress-tooltip-text-color'
		);
		expect(
			registrationArgs.settings.attributes[
				'data-gatherpress-tooltip-text-color'
			]
		).toBe( 'data-gatherpress-tooltip-text-color' );
	} );

	it( 'registers with background color data attribute', () => {
		expect( registrationArgs.settings.attributes ).toHaveProperty(
			'data-gatherpress-tooltip-bg-color'
		);
		expect(
			registrationArgs.settings.attributes[
				'data-gatherpress-tooltip-bg-color'
			]
		).toBe( 'data-gatherpress-tooltip-bg-color' );
	} );

	it( 'registers with edit component', () => {
		expect( registrationArgs.settings.edit ).toBeDefined();
		expect( typeof registrationArgs.settings.edit ).toBe( 'function' );
	} );

	it( 'registers with exactly three attributes', () => {
		expect(
			Object.keys( registrationArgs.settings.attributes ).length
		).toBe( 3 );
	} );
} );
