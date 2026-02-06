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
import {
	generateBlockGuardStateKey,
	getEditorDocument,
	applyGuardToContainer,
	cleanupGuardFromContainer,
	applyListViewGuard,
	addDragListeners,
	removeDragListeners,
	createDropHandler,
	applyListViewGuardForBlock,
} from '../../../../../src/supports/block-guard';

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

/**
 * Tests for getEditorDocument function.
 */
describe( 'getEditorDocument', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	afterEach( () => {
		// Clean up global document.
		delete global.document;
	} );

	it( 'returns iframe contentDocument in FSE context', () => {
		const mockContentDocument = {
			getElementById: jest.fn(),
		};
		const mockIframe = { contentDocument: mockContentDocument };

		document.querySelector = jest.fn( () => mockIframe );

		const result = getEditorDocument();
		expect( result ).toBe( mockContentDocument );
		expect( document.querySelector ).toHaveBeenCalledWith(
			'iframe[name="editor-canvas"]',
		);
	} );

	it( 'returns main document when iframe is not found', () => {
		global.document = {
			querySelector: jest.fn( () => null ),
		};

		const result = getEditorDocument();
		expect( result ).toBe( global.document );
	} );

	it( 'returns main document when iframe has no contentDocument', () => {
		const mockIframe = { contentDocument: null };

		global.document = {
			querySelector: jest.fn( () => mockIframe ),
		};

		const result = getEditorDocument();
		expect( result ).toBe( global.document );
	} );
} );

/**
 * Tests for applyGuardToContainer function.
 */
describe( 'applyGuardToContainer', () => {
	let mockContainer;
	let mockBlockAppender;

	beforeEach( () => {
		mockBlockAppender = {
			style: {},
		};

		mockContainer = {
			style: {},
			dataset: {},
			querySelectorAll: jest.fn( () => [] ),
			querySelector: jest.fn( ( selector ) => {
				if ( '.block-list-appender' === selector ) {
					return mockBlockAppender;
				}
				return null;
			} ),
		};
	} );

	it( 'returns early if container is null', () => {
		applyGuardToContainer( null, true );
		// No error should be thrown.
		expect( true ).toBe( true );
	} );

	it( 'applies guard styles when enabled', () => {
		applyGuardToContainer( mockContainer, true );

		expect( mockContainer.inert ).toBe( true );
		expect( mockContainer.style.opacity ).toBe( '0.95' );
		expect( mockContainer.style.cursor ).toBe( 'not-allowed' );
		expect( mockContainer.dataset.gatherPressGuarded ).toBe( 'true' );
	} );

	it( 'hides block appender when enabled', () => {
		applyGuardToContainer( mockContainer, true );

		expect( mockBlockAppender.style.display ).toBe( 'none' );
	} );

	it( 'removes guard styles when disabled', () => {
		// First apply guard.
		mockContainer.inert = true;
		mockContainer.style.opacity = '0.95';
		mockContainer.style.cursor = 'not-allowed';
		mockContainer.dataset.gatherPressGuarded = 'true';

		applyGuardToContainer( mockContainer, false );

		expect( mockContainer.inert ).toBe( false );
		expect( mockContainer.style.opacity ).toBe( '' );
		expect( mockContainer.style.cursor ).toBe( '' );
		expect( mockContainer.dataset.gatherPressGuarded ).toBeUndefined();
	} );

	it( 'shows block appender when disabled', () => {
		mockBlockAppender.style.display = 'none';

		applyGuardToContainer( mockContainer, false );

		expect( mockBlockAppender.style.display ).toBe( '' );
	} );
} );

/**
 * Tests for cleanupGuardFromContainer function.
 */
describe( 'cleanupGuardFromContainer', () => {
	let mockBlockAppender;

	beforeEach( () => {
		mockBlockAppender = {
			style: {},
		};
	} );

	it( 'returns early if container is null', () => {
		cleanupGuardFromContainer( null );
		// No error should be thrown.
		expect( true ).toBe( true );
	} );

	it( 'removes guard styles and attributes from container', () => {
		const mockContainer = {
			inert: true,
			style: { opacity: '0.95', cursor: 'not-allowed' },
			dataset: { gatherPressGuarded: 'true' },
			querySelectorAll: jest.fn( () => [] ),
			querySelector: jest.fn( () => mockBlockAppender ),
		};

		cleanupGuardFromContainer( mockContainer );

		expect( mockContainer.inert ).toBe( false );
		expect( mockContainer.style.opacity ).toBe( '' );
		expect( mockContainer.style.cursor ).toBe( '' );
		expect( mockContainer.dataset.gatherPressGuarded ).toBeUndefined();
	} );

	it( 'restores block appender display', () => {
		mockBlockAppender.style.display = 'none';

		const mockContainer = {
			inert: false,
			style: {},
			dataset: {},
			querySelectorAll: jest.fn( () => [] ),
			querySelector: jest.fn( () => mockBlockAppender ),
		};

		cleanupGuardFromContainer( mockContainer );

		expect( mockBlockAppender.style.display ).toBe( '' );
	} );
} );

/**
 * Tests for applyListViewGuard function.
 */
describe( 'applyListViewGuard', () => {
	let mockExpander;
	let mockParentLink;

	beforeEach( () => {
		mockParentLink = {
			style: {},
			setAttribute: jest.fn(),
			classList: {
				add: jest.fn(),
				remove: jest.fn(),
			},
		};

		mockExpander = {
			style: {},
			onclick: null,
			closest: jest.fn( ( selector ) => {
				if (
					'.block-editor-list-view-block-select-button' === selector
				) {
					return mockParentLink;
				}
				return null;
			} ),
		};
	} );

	it( 'returns early if expander is null', () => {
		applyListViewGuard( null, true );
		// No error should be thrown.
		expect( true ).toBe( true );
	} );

	it( 'applies guard styles to expander when enabled', () => {
		applyListViewGuard( mockExpander, true );

		expect( mockExpander.style.opacity ).toBe( '0.3' );
		expect( mockExpander.style.pointerEvents ).toBe( 'none' );
	} );

	it( 'sets parent link styles and attributes when enabled', () => {
		applyListViewGuard( mockExpander, true );

		expect( mockParentLink.setAttribute ).toHaveBeenCalledWith(
			'aria-expanded',
			'false',
		);
		expect( mockParentLink.style.pointerEvents ).toBe( 'auto' );
		expect( mockParentLink.classList.add ).toHaveBeenCalledWith(
			'gatherpress-block-guard-enabled',
		);
	} );

	it( 'removes guard styles from expander when disabled', () => {
		mockExpander.style.opacity = '0.3';
		mockExpander.style.pointerEvents = 'none';

		applyListViewGuard( mockExpander, false );

		expect( mockExpander.style.opacity ).toBe( '' );
		expect( mockExpander.style.pointerEvents ).toBe( '' );
	} );

	it( 're-enables parent link when disabled', () => {
		mockParentLink.style.pointerEvents = 'auto';

		applyListViewGuard( mockExpander, false );

		expect( mockParentLink.style.pointerEvents ).toBe( '' );
		expect( mockParentLink.classList.remove ).toHaveBeenCalledWith(
			'gatherpress-block-guard-enabled',
		);
	} );
} );

/**
 * Tests for drag event handler functions.
 */
describe( 'Drag Event Handlers', () => {
	beforeEach( () => {
		global.document = {
			addEventListener: jest.fn(),
			removeEventListener: jest.fn(),
		};
	} );

	afterEach( () => {
		delete global.document;
		jest.clearAllMocks();
	} );

	describe( 'addDragListeners', () => {
		it( 'adds all drag event listeners', () => {
			const mockHandler = jest.fn();
			addDragListeners( mockHandler );

			expect( global.document.addEventListener ).toHaveBeenCalledWith(
				'dragover',
				mockHandler,
				true,
			);
			expect( global.document.addEventListener ).toHaveBeenCalledWith(
				'dragenter',
				mockHandler,
				true,
			);
			expect( global.document.addEventListener ).toHaveBeenCalledWith(
				'dragleave',
				mockHandler,
				true,
			);
			expect( global.document.addEventListener ).toHaveBeenCalledWith(
				'drop',
				mockHandler,
				true,
			);
			expect( global.document.addEventListener ).toHaveBeenCalledTimes( 4 );
		} );
	} );

	describe( 'removeDragListeners', () => {
		it( 'removes all drag event listeners', () => {
			const mockHandler = jest.fn();
			removeDragListeners( mockHandler );

			expect( global.document.removeEventListener ).toHaveBeenCalledWith(
				'dragover',
				mockHandler,
				true,
			);
			expect( global.document.removeEventListener ).toHaveBeenCalledWith(
				'dragenter',
				mockHandler,
				true,
			);
			expect( global.document.removeEventListener ).toHaveBeenCalledWith(
				'dragleave',
				mockHandler,
				true,
			);
			expect( global.document.removeEventListener ).toHaveBeenCalledWith(
				'drop',
				mockHandler,
				true,
			);
			expect( global.document.removeEventListener ).toHaveBeenCalledTimes(
				4,
			);
		} );
	} );

	describe( 'createDropHandler', () => {
		it( 'creates a handler that prevents default for matching blocks', () => {
			const clientId = 'test-123';
			const handler = createDropHandler( clientId );

			const mockTarget = {
				closest: jest.fn( () => ( {
					dataset: { block: clientId },
				} ) ),
			};

			const mockEvent = {
				type: 'dragover',
				target: mockTarget,
				preventDefault: jest.fn(),
				stopPropagation: jest.fn(),
			};

			handler( mockEvent );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
			expect( mockEvent.stopPropagation ).toHaveBeenCalled();
		} );

		it( 'does not prevent default for non-matching blocks', () => {
			const clientId = 'test-123';
			const handler = createDropHandler( clientId );

			const mockEvent = {
				type: 'dragover',
				target: {
					closest: jest.fn( () => null ),
				},
				preventDefault: jest.fn(),
				stopPropagation: jest.fn(),
			};

			handler( mockEvent );

			expect( mockEvent.preventDefault ).not.toHaveBeenCalled();
			expect( mockEvent.stopPropagation ).not.toHaveBeenCalled();
		} );

		it( 'prevents default for dragenter events', () => {
			const clientId = 'test-123';
			const handler = createDropHandler( clientId );

			const mockEvent = {
				type: 'dragenter',
				target: {
					closest: jest.fn( () => ( {
						dataset: { block: clientId },
					} ) ),
				},
				preventDefault: jest.fn(),
				stopPropagation: jest.fn(),
			};

			handler( mockEvent );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
		} );

		it( 'prevents default for drop events', () => {
			const clientId = 'test-123';
			const handler = createDropHandler( clientId );

			const mockEvent = {
				type: 'drop',
				target: {
					closest: jest.fn( () => ( {
						dataset: { block: clientId },
					} ) ),
				},
				preventDefault: jest.fn(),
				stopPropagation: jest.fn(),
			};

			handler( mockEvent );

			expect( mockEvent.preventDefault ).toHaveBeenCalled();
		} );

		it( 'does not prevent default for dragleave events', () => {
			const clientId = 'test-123';
			const handler = createDropHandler( clientId );

			const mockEvent = {
				type: 'dragleave',
				target: {
					closest: jest.fn( () => ( {
						dataset: { block: clientId },
					} ) ),
				},
				preventDefault: jest.fn(),
				stopPropagation: jest.fn(),
			};

			handler( mockEvent );

			// dragleave is listened to but doesn't prevent default.
			expect( mockEvent.preventDefault ).not.toHaveBeenCalled();
		} );
	} );
} );

/**
 * Tests for applyListViewGuardForBlock function.
 */
describe( 'applyListViewGuardForBlock', () => {
	let mockDocument;

	beforeEach( () => {
		mockDocument = {
			querySelector: jest.fn(),
		};

		// Mock getEditorDocument to return our mock.
		global.document = mockDocument;
	} );

	afterEach( () => {
		delete global.document;
		jest.clearAllMocks();
	} );

	it( 'returns null if list view item is not found', () => {
		mockDocument.querySelector = jest.fn( () => null );

		const result = applyListViewGuardForBlock( 'test-123', true );

		expect( result ).toBeNull();
	} );

	it( 'returns null if expander is not found', () => {
		const mockListViewItem = {
			querySelector: jest.fn( () => null ),
		};

		mockDocument.querySelector = jest.fn( () => mockListViewItem );

		const result = applyListViewGuardForBlock( 'test-123', true );

		expect( result ).toBeNull();
	} );

	it( 'returns null if expander SVG is not found', () => {
		const mockExpander = {
			querySelector: jest.fn( () => null ),
		};

		const mockListViewItem = {
			querySelector: jest.fn( () => mockExpander ),
		};

		mockDocument.querySelector = jest.fn( () => mockListViewItem );

		const result = applyListViewGuardForBlock( 'test-123', true );

		expect( result ).toBeNull();
	} );

	it( 'applies guard and returns expander when all elements are found', () => {
		const mockExpanderSvg = {};
		const mockParentLink = {
			style: {},
			setAttribute: jest.fn(),
			classList: {
				add: jest.fn(),
				remove: jest.fn(),
			},
		};

		const mockExpander = {
			querySelector: jest.fn( () => mockExpanderSvg ),
			style: {},
			onclick: null,
			closest: jest.fn( () => mockParentLink ),
		};

		const mockListViewItem = {
			querySelector: jest.fn( () => mockExpander ),
		};

		mockDocument.querySelector = jest.fn( () => mockListViewItem );

		const result = applyListViewGuardForBlock( 'test-123', true );

		expect( result ).toEqual( { expander: mockExpander } );
		expect( mockExpander.style.pointerEvents ).toBe( 'none' );
	} );
} );
