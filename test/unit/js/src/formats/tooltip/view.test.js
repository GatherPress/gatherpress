/**
 * External dependencies.
 */
import {
	describe,
	expect,
	it,
	jest,
	beforeEach,
	afterEach,
} from '@jest/globals';

// Store the captured actions for testing.
let capturedActions = null;

// Mock WordPress interactivity store.
jest.mock( '@wordpress/interactivity', () => ( {
	store: jest.fn( ( namespace, config ) => {
		capturedActions = config.actions;
		return config;
	} ),
} ) );

describe( 'Tooltip view', () => {
	let originalReadyState;
	let originalQuerySelectorAll;
	let originalAddEventListener;

	beforeEach( () => {
		// Reset mocks.
		jest.clearAllMocks();
		capturedActions = null;

		// Store original values.
		originalReadyState = Object.getOwnPropertyDescriptor(
			document,
			'readyState'
		);
		originalQuerySelectorAll = document.querySelectorAll;
		originalAddEventListener = document.addEventListener;

		// Reset module cache to re-import view.js fresh.
		jest.resetModules();
	} );

	afterEach( () => {
		// Restore original values.
		if ( originalReadyState ) {
			Object.defineProperty(
				document,
				'readyState',
				originalReadyState
			);
		}
		document.querySelectorAll = originalQuerySelectorAll;
		document.addEventListener = originalAddEventListener;
	} );

	describe( 'store registration', () => {
		it( 'registers store with gatherpress namespace', async () => {
			const { store } = await import( '@wordpress/interactivity' );

			// Set readyState to loading to prevent immediate execution.
			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );
			document.addEventListener = jest.fn();

			await import( '../../../../../../src/formats/tooltip/view' );

			expect( store ).toHaveBeenCalledWith(
				'gatherpress',
				expect.objectContaining( {
					actions: expect.objectContaining( {
						initTooltips: expect.any( Function ),
					} ),
				} )
			);
		} );

		it( 'defines initTooltips action', async () => {
			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );
			document.addEventListener = jest.fn();

			await import( '../../../../../../src/formats/tooltip/view' );

			expect( capturedActions ).toBeDefined();
			expect( capturedActions.initTooltips ).toBeDefined();
			expect( typeof capturedActions.initTooltips ).toBe( 'function' );
		} );
	} );

	describe( 'initTooltips action', () => {
		it( 'queries for tooltip elements', async () => {
			const mockQuerySelectorAll = jest.fn( () => [] );
			document.querySelectorAll = mockQuerySelectorAll;

			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );
			document.addEventListener = jest.fn();

			await import( '../../../../../../src/formats/tooltip/view' );

			capturedActions.initTooltips();

			expect( mockQuerySelectorAll ).toHaveBeenCalledWith(
				'.gatherpress-tooltip[data-gatherpress-tooltip]'
			);
		} );

		it( 'sets text color CSS property when data attribute exists', async () => {
			const mockSetProperty = jest.fn();
			const mockTooltip = {
				dataset: {
					gatherpressTooltipTextColor: '#ff0000',
				},
				style: {
					setProperty: mockSetProperty,
				},
			};
			document.querySelectorAll = jest.fn( () => [ mockTooltip ] );

			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );
			document.addEventListener = jest.fn();

			await import( '../../../../../../src/formats/tooltip/view' );

			capturedActions.initTooltips();

			expect( mockSetProperty ).toHaveBeenCalledWith(
				'--gatherpress-tooltip-text-color',
				'#ff0000'
			);
		} );

		it( 'sets background color CSS property when data attribute exists', async () => {
			const mockSetProperty = jest.fn();
			const mockTooltip = {
				dataset: {
					gatherpressTooltipBgColor: '#00ff00',
				},
				style: {
					setProperty: mockSetProperty,
				},
			};
			document.querySelectorAll = jest.fn( () => [ mockTooltip ] );

			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );
			document.addEventListener = jest.fn();

			await import( '../../../../../../src/formats/tooltip/view' );

			capturedActions.initTooltips();

			expect( mockSetProperty ).toHaveBeenCalledWith(
				'--gatherpress-tooltip-bg-color',
				'#00ff00'
			);
		} );

		it( 'sets both color properties when both exist', async () => {
			const mockSetProperty = jest.fn();
			const mockTooltip = {
				dataset: {
					gatherpressTooltipTextColor: '#ffffff',
					gatherpressTooltipBgColor: '#333333',
				},
				style: {
					setProperty: mockSetProperty,
				},
			};
			document.querySelectorAll = jest.fn( () => [ mockTooltip ] );

			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );
			document.addEventListener = jest.fn();

			await import( '../../../../../../src/formats/tooltip/view' );

			capturedActions.initTooltips();

			expect( mockSetProperty ).toHaveBeenCalledTimes( 2 );
			expect( mockSetProperty ).toHaveBeenCalledWith(
				'--gatherpress-tooltip-text-color',
				'#ffffff'
			);
			expect( mockSetProperty ).toHaveBeenCalledWith(
				'--gatherpress-tooltip-bg-color',
				'#333333'
			);
		} );

		it( 'does not set properties when data attributes are missing', async () => {
			const mockSetProperty = jest.fn();
			const mockTooltip = {
				dataset: {},
				style: {
					setProperty: mockSetProperty,
				},
			};
			document.querySelectorAll = jest.fn( () => [ mockTooltip ] );

			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );
			document.addEventListener = jest.fn();

			await import( '../../../../../../src/formats/tooltip/view' );

			capturedActions.initTooltips();

			expect( mockSetProperty ).not.toHaveBeenCalled();
		} );

		it( 'handles multiple tooltip elements', async () => {
			const mockSetProperty1 = jest.fn();
			const mockSetProperty2 = jest.fn();
			const mockTooltips = [
				{
					dataset: { gatherpressTooltipTextColor: '#111111' },
					style: { setProperty: mockSetProperty1 },
				},
				{
					dataset: { gatherpressTooltipBgColor: '#222222' },
					style: { setProperty: mockSetProperty2 },
				},
			];
			document.querySelectorAll = jest.fn( () => mockTooltips );

			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );
			document.addEventListener = jest.fn();

			await import( '../../../../../../src/formats/tooltip/view' );

			capturedActions.initTooltips();

			expect( mockSetProperty1 ).toHaveBeenCalledWith(
				'--gatherpress-tooltip-text-color',
				'#111111'
			);
			expect( mockSetProperty2 ).toHaveBeenCalledWith(
				'--gatherpress-tooltip-bg-color',
				'#222222'
			);
		} );
	} );

	describe( 'initialization timing', () => {
		it( 'calls initTooltips immediately when document is not loading', async () => {
			document.querySelectorAll = jest.fn( () => [] );

			Object.defineProperty( document, 'readyState', {
				value: 'complete',
				configurable: true,
			} );

			await import( '../../../../../../src/formats/tooltip/view' );

			// Should have been called because readyState is 'complete'.
			expect( document.querySelectorAll ).toHaveBeenCalled();
		} );

		it( 'adds event listener when document is loading', async () => {
			const mockAddEventListener = jest.fn();
			document.addEventListener = mockAddEventListener;
			document.querySelectorAll = jest.fn( () => [] );

			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );

			await import( '../../../../../../src/formats/tooltip/view' );

			expect( mockAddEventListener ).toHaveBeenCalledWith(
				'DOMContentLoaded',
				expect.any( Function )
			);
		} );
	} );
} );
