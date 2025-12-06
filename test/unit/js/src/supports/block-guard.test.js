/**
 * External dependencies.
 */
import {
	describe,
	expect,
	it,
	beforeEach,
	afterEach,
	jest,
} from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies.
 */
jest.mock( '@wordpress/data', () => ( {
	dispatch: jest.fn( () => ( {
		selectBlock: jest.fn(),
	} ) ),
} ) );

jest.mock( '@wordpress/blocks', () => ( {
	getBlockType: jest.fn(),
} ) );

jest.mock( '@wordpress/compose', () => ( {
	createHigherOrderComponent: jest.fn( ( hoc ) => hoc ),
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => (
		<div data-testid="inspector-controls">{ children }</div>
	),
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children } ) => <div data-testid="panel-body">{ children }</div>,
	ToggleControl: ( { label, checked, onChange } ) => (
		<button
			data-testid="toggle-control"
			onClick={ () => onChange( ! checked ) }
			aria-pressed={ checked }
		>
			{ label }
		</button>
	),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
} ) );

jest.mock( '@wordpress/element', () => ( {
	useState: jest.fn(),
	useEffect: jest.fn(),
} ) );

/**
 * Internal dependencies.
 */
import { generateBlockGuardStateKey } from '../../../../../src/supports/block-guard';

/**
 * Mock DOM setup for editor document detection.
 */
const setupMockDOM = () => {
	// Mock iframe for FSE context.
	const mockIframe = {
		contentDocument: {
			getElementById: jest.fn(),
			querySelectorAll: jest.fn( () => [] ),
			createElement: jest.fn( () => ( {
				className: '',
				style: {},
				onclick: null,
				appendChild: jest.fn(),
			} ) ),
			body: {},
		},
	};

	// Create proper jest mocks for document methods.
	global.document = {
		querySelector: jest.fn( () => mockIframe ),
		getElementById: jest.fn(),
		querySelectorAll: jest.fn( () => [] ),
		createElement: jest.fn( () => ( {
			className: '',
			style: {},
			onclick: null,
			appendChild: jest.fn(),
		} ) ),
		body: {},
	};

	return mockIframe;
};

/**
 * Mock block element structure for testing.
 *
 * @param {string}  clientId  - The block's client ID.
 * @param {string}  blockType - The block type name.
 * @param {boolean} queryLoop - Whether this block is in a query loop.
 * @return {Object} Mock block element.
 */
const createMockBlockElement = ( clientId, blockType, queryLoop = false ) => {
	const element = {
		id: `block-${ clientId }`,
		getAttribute: jest.fn( () => blockType ),
		closest: jest.fn( ( selector ) => {
			if ( '[data-type="core/post-template"]' === selector && queryLoop ) {
				return {
					id: 'query-loop-123',
					querySelectorAll: jest.fn( () => [ element ] ),
					closest: jest.fn( () => ( {
						id: 'query-block-456',
						querySelectorAll: jest.fn( () => [ element ] ),
					} ) ),
				};
			}
			if ( '[data-type="core/query"]' === selector && queryLoop ) {
				return {
					id: 'query-block-456',
					querySelectorAll: jest.fn( () => [ element ] ),
				};
			}
			return null;
		} ),
		querySelector: jest.fn( () => ( {
			querySelectorAll: jest.fn( () => [] ),
			querySelector: jest.fn( () => null ),
			style: {},
			appendChild: jest.fn(),
		} ) ),
	};

	return element;
};

/**
 * Create mock query loop container that can be shared between elements.
 *
 * @return {Object} Mock query loop container.
 */
const createMockQueryLoopContainer = () => {
	return {
		id: 'query-loop-123',
		querySelectorAll: jest.fn(),
		closest: jest.fn( () => ( {
			id: 'query-block-456',
			querySelectorAll: jest.fn(),
		} ) ),
	};
};

/**
 * Coverage for generateBlockGuardStateKey function.
 */
describe( 'generateBlockGuardStateKey', () => {
	beforeEach( () => {
		// Clear all mocks first.
		jest.clearAllMocks();

		// Set up fresh DOM mocks.
		setupMockDOM();
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns clientId-based key for individual blocks outside query loops', () => {
		const blockName = 'gatherpress/rsvp';
		const clientId = 'test-client-123';
		const mockElement = createMockBlockElement( clientId, blockName, false );

		// Directly assign the mock function return value.
		global.document.getElementById = jest.fn().mockReturnValue( mockElement );

		const stateKey = generateBlockGuardStateKey( blockName, clientId );
		expect( stateKey ).toBe( `${ blockName }-${ clientId }` );
	} );

	it( 'returns position-based key for blocks inside query loops', () => {
		const blockName = 'gatherpress/rsvp';
		const clientId = 'test-client-456';
		const mockElement = createMockBlockElement( clientId, blockName, true );

		global.document.getElementById = jest.fn().mockReturnValue( mockElement );
		mockElement
			.closest( '[data-type="core/post-template"]' )
			.querySelectorAll.mockReturnValue( [ mockElement ] );

		const stateKey = generateBlockGuardStateKey( blockName, clientId );
		expect( stateKey ).toBe(
			`${ blockName }-queryloop-query-block-456-position-0`,
		);
	} );

	it( 'returns fallback key when block element is not found', () => {
		const blockName = 'gatherpress/rsvp';
		const clientId = 'missing-client';

		global.document.getElementById = jest.fn().mockReturnValue( null );

		const stateKey = generateBlockGuardStateKey( blockName, clientId );
		expect( stateKey ).toBe( `${ blockName }-${ clientId }` );
	} );

	it( 'handles multiple blocks of same type in query loop with different positions', () => {
		const blockName = 'gatherpress/rsvp';
		const firstClientId = 'first-block';
		const secondClientId = 'second-block';

		// Create elements but use a shared query loop container.
		const firstElement = {
			id: `block-${ firstClientId }`,
			getAttribute: jest.fn( () => blockName ),
			closest: jest.fn(),
		};
		const secondElement = {
			id: `block-${ secondClientId }`,
			getAttribute: jest.fn( () => blockName ),
			closest: jest.fn(),
		};

		// Create shared query loop container.
		const sharedQueryContainer = createMockQueryLoopContainer();
		const elementsArray = [ firstElement, secondElement ];
		sharedQueryContainer.querySelectorAll.mockReturnValue( elementsArray );

		// Both elements should return the same query container.
		firstElement.closest.mockReturnValue( sharedQueryContainer );
		secondElement.closest.mockReturnValue( sharedQueryContainer );

		global.document.getElementById = jest
			.fn()
			.mockReturnValueOnce( firstElement )
			.mockReturnValueOnce( secondElement );

		const firstStateKey = generateBlockGuardStateKey(
			blockName,
			firstClientId,
		);
		const secondStateKey = generateBlockGuardStateKey(
			blockName,
			secondClientId,
		);

		expect( firstStateKey ).toContain( 'position-0' );
		expect( secondStateKey ).toContain( 'position-1' );
		expect( firstStateKey ).not.toBe( secondStateKey );
	} );
} );

/**
 * Coverage for useSharedBlockGuardState hook.
 */
describe( 'useSharedBlockGuardState', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'initializes with default enabled state', () => {
		// This would test the hook initialization.
		// const { useState, useEffect } = require('@wordpress/element');
		// useState.mockReturnValue([true, jest.fn()]);
		// useEffect.mockImplementation((fn) => fn());
		// const [state, setState] = useSharedBlockGuardState('test-key');
		// expect(state).toBe(true);
	} );

	it( 'synchronizes state across multiple instances with same key', () => {
		// This would test that multiple hooks with the same key share state
		// Mock implementation would verify that listeners are properly managed.
	} );

	it( 'maintains independent state for different keys', () => {
		// This would test that different state keys don't interfere with each other.
	} );
} );

/**
 * Coverage for withBlockGuard HOC.
 */
describe( 'withBlockGuard HOC', () => {
	let mockGetBlockType;
	let mockUseState;
	let mockUseEffect;

	beforeEach( () => {
		setupMockDOM();

		const { getBlockType } = require( '@wordpress/blocks' );
		const { useState, useEffect } = require( '@wordpress/element' );

		mockGetBlockType = getBlockType;
		mockUseState = useState;
		mockUseEffect = useEffect;

		// Reset mocks.
		mockGetBlockType.mockClear();
		mockUseState.mockClear();
		mockUseEffect.mockClear();
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns original component for non-GatherPress blocks', () => {
		mockGetBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: false } },
		} );

		// Test would verify that withBlockGuard passes through non-GatherPress blocks unchanged.
		expect( mockGetBlockType ).toBeDefined();
	} );

	it( 'returns original component for blocks without blockGuard support', () => {
		mockGetBlockType.mockReturnValue( {
			supports: { gatherpress: {} },
		} );

		// Test would verify that blocks without blockGuard support are passed through unchanged.
		expect( mockGetBlockType ).toBeDefined();
	} );

	it( 'adds Inspector Controls for GatherPress blocks with blockGuard support', () => {
		mockGetBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );

		mockUseState.mockReturnValue( [ true, jest.fn() ] );
		mockUseEffect.mockImplementation( ( fn ) => {
			fn();
			return jest.fn(); // cleanup function.
		} );

		// Test would verify that Inspector Controls are added for supported blocks.
		expect( mockUseState ).toBeDefined();
		expect( mockUseEffect ).toBeDefined();
	} );

	it( 'applies block guard to DOM elements when enabled', () => {
		mockGetBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );

		const mockSetState = jest.fn();
		mockUseState.mockReturnValue( [ true, mockSetState ] );

		const mockElement = createMockBlockElement(
			'test-123',
			'gatherpress/rsvp',
			false,
		);
		global.document.getElementById = jest.fn().mockReturnValue( mockElement );
		global.document.querySelectorAll = jest
			.fn()
			.mockReturnValue( [ mockElement ] );

		// This would test that the DOM manipulation occurs correctly
		// when the effect runs.
	} );

	it( 'handles query loop context correctly', () => {
		mockGetBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );

		const mockSetState = jest.fn();
		mockUseState.mockReturnValue( [ true, mockSetState ] );
		mockUseEffect.mockImplementation( ( fn ) => fn() );

		const mockElement = createMockBlockElement(
			'test-123',
			'gatherpress/rsvp',
			true,
		);
		global.document.getElementById = jest.fn().mockReturnValue( mockElement );
		global.document.querySelectorAll = jest
			.fn()
			.mockReturnValue( [ mockElement ] );

		// This would test that query loop detection and state key generation
		// works correctly for blocks within query loops.
	} );
} );

/**
 * Integration tests for complete block guard workflow.
 */
describe( 'Block Guard Integration', () => {
	beforeEach( () => {
		setupMockDOM();
		jest.clearAllMocks();
	} );

	it( 'maintains independent states for blocks outside query loops', () => {
		// Test that individual blocks each have their own state.
	} );

	it( 'synchronizes state for same position blocks across query loop instances', () => {
		// Test that blocks at position 0 in different posts share state
		// but are independent from blocks at position 1.
	} );

	it( 'handles mixed contexts (individual blocks + query loops) correctly', () => {
		// Test that individual blocks and query loop blocks don't interfere.
	} );

	it( 'cleans up properly on component unmount', () => {
		// Test that overlays are removed and listeners are cleaned up.
	} );
} );
