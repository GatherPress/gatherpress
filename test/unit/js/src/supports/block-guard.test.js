/**
 * External dependencies
 */
import {
	describe,
	expect,
	it,
	beforeAll,
	beforeEach,
	afterEach,
	jest,
} from '@jest/globals';
import { render, renderHook, act } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import { getBlockType } from '@wordpress/blocks';

jest.mock( '@wordpress/blocks', () => ( {
	getBlockType: jest.fn(),
} ) );

// Stub block-editor so importing the module under test does not pull in the
// real editor store (its private-apis unlock throws under jsdom).
jest.mock( '@wordpress/block-editor', () => ( {
	store: {},
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
	select: jest.fn( () => ( { getSelectedBlockClientId: () => null } ) ),
} ) );

/**
 * WordPress dependencies
 */
// eslint-disable-next-line import/order
import { useSelect } from '@wordpress/data';

jest.mock( '@wordpress/compose', () => ( {
	createHigherOrderComponent: jest.fn( ( hoc ) => hoc ),
} ) );

jest.mock( '@wordpress/a11y', () => ( {
	speak: jest.fn(),
} ) );

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
	hasFilter: jest.fn( () => false ),
} ) );

/**
 * Internal dependencies
 */
import {
	isBlockGuarded,
	useIsBlockSealed,
	publishSealedState,
	getCanvasDocument,
	placeCaretAtPoint,
	withBlockGuard,
} from '@src/supports/block-guard';

// jsdom does not implement elementFromPoint and throws; the caret forwarding
// calls it whenever a double-click opens a block, so give every suite a
// harmless default (individual tests override it to observe calls).
beforeAll( () => {
	document.elementFromPoint = jest.fn( () => null );
} );

describe( 'isBlockGuarded', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns true when the block declares the blockGuard support', () => {
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );

		expect( isBlockGuarded( 'gatherpress/add-to-calendar' ) ).toBe( true );
	} );

	it( 'returns false when the block does not declare blockGuard', () => {
		getBlockType.mockReturnValue( { supports: { gatherpress: {} } } );

		expect( isBlockGuarded( 'gatherpress/venue' ) ).toBe( false );
	} );

	it( 'returns false when the block has no gatherpress supports', () => {
		getBlockType.mockReturnValue( { supports: {} } );

		expect( isBlockGuarded( 'core/paragraph' ) ).toBe( false );
	} );

	it( 'returns false when the block type is unregistered', () => {
		getBlockType.mockReturnValue( undefined );

		expect( isBlockGuarded( 'gatherpress/nope' ) ).toBe( false );
	} );
} );

describe( 'useIsBlockSealed', () => {
	it( 'reports sealed for a block that has not published state yet', () => {
		const { result } = renderHook( () => useIsBlockSealed( 'unknown-id' ) );

		expect( result.current ).toBe( true );
	} );

	it( 'reports sealed when there is no client ID', () => {
		const { result } = renderHook( () => useIsBlockSealed( '' ) );

		expect( result.current ).toBe( true );
	} );

	it( 'reflects the published sealed state for a block', () => {
		const { result } = renderHook( () => useIsBlockSealed( 'block-1' ) );

		act( () => {
			publishSealedState( 'block-1', false );
		} );

		expect( result.current ).toBe( false );

		act( () => {
			publishSealedState( 'block-1', true );
		} );

		expect( result.current ).toBe( true );
	} );

	it( 'shares one listener set between subscribers to the same block', () => {
		const first = renderHook( () => useIsBlockSealed( 'shared-id' ) );
		const second = renderHook( () => useIsBlockSealed( 'shared-id' ) );

		act( () => {
			publishSealedState( 'shared-id', false );
		} );

		expect( first.result.current ).toBe( false );
		expect( second.result.current ).toBe( false );
	} );

	it( 'does not react to another block changing state', () => {
		const { result } = renderHook( () => useIsBlockSealed( 'block-a' ) );

		act( () => {
			publishSealedState( 'block-b', false );
		} );

		expect( result.current ).toBe( true );
	} );
} );

describe( 'getCanvasDocument', () => {
	afterEach( () => {
		document.querySelector( 'iframe[name="editor-canvas"]' )?.remove();
	} );

	it( 'falls back to the main document when there is no canvas iframe', () => {
		expect( getCanvasDocument() ).toBe( document );
	} );

	it( 'returns the canvas iframe document when one is present', () => {
		const iframe = document.createElement( 'iframe' );
		iframe.name = 'editor-canvas';
		document.body.appendChild( iframe );

		expect( getCanvasDocument() ).toBe( iframe.contentDocument );
	} );
} );

describe( 'withBlockGuard', () => {
	const BlockListBlock = jest.fn( () => <div /> );
	const Guarded = withBlockGuard( BlockListBlock );
	const lastProps = () => BlockListBlock.mock.calls.at( -1 )[ 0 ];
	const sealedNow = () =>
		( lastProps().className || '' ).includes( 'has-block-overlay' );

	const setSelection = ( { isSelf = false, isInner = false } ) => {
		useSelect.mockImplementation( ( mapSelect ) =>
			mapSelect( () => ( {
				isBlockSelected: () => isSelf,
				hasSelectedInnerBlock: () => isInner,
			} ) )
		);
	};

	const element = ( extra = {} ) => (
		<Guarded
			name="gatherpress/add-to-calendar"
			clientId="abc"
			wrapperProps={ {} }
			{ ...extra }
		/>
	);

	beforeEach( () => {
		jest.clearAllMocks();
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );
		document.getElementById( 'gatherpress-block-guard-hint' )?.remove();
	} );

	it( 'passes a non-guarded block straight through', () => {
		getBlockType.mockReturnValue( { supports: {} } );
		setSelection( {} );
		render( element( { name: 'core/paragraph' } ) );

		expect( lastProps().className ).toBeUndefined();
	} );

	it( 'seals a guarded block while selection is outside it', () => {
		setSelection( {} );
		render( element() );

		expect( sealedNow() ).toBe( true );
	} );

	it( 'stays sealed when merely selected, so the whole block can be dragged', () => {
		setSelection( { isSelf: true } );
		render( element() );

		expect( sealedNow() ).toBe( true );
	} );

	it( 'opens on a double-click', () => {
		setSelection( { isSelf: true } );
		render( element() );
		expect( sealedNow() ).toBe( true );

		act( () => {
			lastProps().wrapperProps.onDoubleClick( {} );
		} );

		expect( sealedNow() ).toBe( false );
	} );

	it( 'opens on Enter while the block is selected', () => {
		setSelection( { isSelf: true } );
		render( element() );

		act( () => {
			lastProps().wrapperProps.onKeyDown( {
				key: 'Enter',
				preventDefault() {},
				stopPropagation() {},
			} );
		} );

		expect( sealedNow() ).toBe( false );
	} );

	it( 'chains other keys to the original handler', () => {
		const onKeyDown = jest.fn();
		setSelection( { isSelf: true } );
		render( element( { wrapperProps: { onKeyDown } } ) );

		act( () => {
			lastProps().wrapperProps.onKeyDown( {
				key: 'a',
				preventDefault() {},
				stopPropagation() {},
			} );
		} );

		expect( onKeyDown ).toHaveBeenCalled();
		expect( sealedNow() ).toBe( true );
	} );

	it( 'chains an existing onDoubleClick handler', () => {
		const onDoubleClick = jest.fn();
		setSelection( { isSelf: true } );
		render( element( { wrapperProps: { onDoubleClick } } ) );

		act( () => {
			lastProps().wrapperProps.onDoubleClick( {} );
		} );

		expect( onDoubleClick ).toHaveBeenCalled();
	} );

	it( 're-arms after being opened once selection leaves', () => {
		setSelection( { isSelf: true } );
		const { rerender } = render( element() );
		act( () => {
			lastProps().wrapperProps.onDoubleClick( {} );
		} );
		expect( sealedNow() ).toBe( false );

		setSelection( {} );
		rerender( element() );

		expect( sealedNow() ).toBe( true );
	} );

	it( 'unseals while an inner block is selected', () => {
		setSelection( { isInner: true } );
		render( element() );

		expect( sealedNow() ).toBe( false );
	} );

	it( 're-arms after a remount, and can be opened again', () => {
		setSelection( { isSelf: true } );
		const first = render( element() );
		act( () => {
			lastProps().wrapperProps.onDoubleClick( {} );
		} );
		expect( sealedNow() ).toBe( false );

		// a move unmounts and remounts the block
		first.unmount();
		render( element() );
		expect( sealedNow() ).toBe( true );

		// and re-opening still works — no stranded state
		act( () => {
			lastProps().wrapperProps.onDoubleClick( {} );
		} );
		expect( sealedNow() ).toBe( false );
	} );

	it( 'shows a pointer cursor while sealed, and no tint yet', () => {
		setSelection( {} );
		render( element() );

		expect( lastProps().wrapperProps.style ).toMatchObject( {
			cursor: 'pointer',
		} );
		expect( lastProps().wrapperProps.style.backgroundColor ).toBeUndefined();
	} );

	it( 'tints the block once it is selected', () => {
		setSelection( { isSelf: true } );
		render( element() );

		expect( lastProps().wrapperProps.style ).toMatchObject( {
			backgroundColor: 'rgba(30, 58, 233, 0.04)',
		} );
	} );

	it( 'does not tint while only an inner block is selected', () => {
		setSelection( { isInner: true } );
		render( element() );

		expect( lastProps().wrapperProps.style.backgroundColor ).toBeUndefined();
	} );

	it( 'preserves any style core already set on the wrapper', () => {
		setSelection( { isSelf: true } );
		render( element( { wrapperProps: { style: { marginTop: '8px' } } } ) );

		expect( lastProps().wrapperProps.style ).toMatchObject( {
			marginTop: '8px',
			backgroundColor: 'rgba(30, 58, 233, 0.04)',
		} );
	} );

	it( 'publishes its sealed state for descendants', () => {
		setSelection( {} );
		render( element() );

		const { result } = renderHook( () => useIsBlockSealed( 'abc' ) );
		expect( result.current ).toBe( true );
	} );
} );

describe( 'accessibility', () => {
	const BlockListBlock = jest.fn( () => <div /> );
	const Guarded = withBlockGuard( BlockListBlock );
	const lastProps = () => BlockListBlock.mock.calls.at( -1 )[ 0 ];

	const renderWith = ( { isSelf = false, isInner = false, wrapperProps = {} } ) => {
		useSelect.mockImplementation( ( mapSelect ) =>
			mapSelect( () => ( {
				isBlockSelected: () => isSelf,
				hasSelectedInnerBlock: () => isInner,
			} ) )
		);

		return render(
			<Guarded
				name="gatherpress/add-to-calendar"
				clientId="a11y"
				wrapperProps={ wrapperProps }
			/>
		);
	};

	beforeEach( () => {
		jest.clearAllMocks();
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );
		document.getElementById( 'gatherpress-block-guard-hint' )?.remove();
	} );

	it( 'adds a visually hidden hint describing how to get in', () => {
		renderWith( {} );

		const hint = document.getElementById( 'gatherpress-block-guard-hint' );
		expect( hint ).not.toBeNull();
		expect( hint.className ).toBe( 'screen-reader-text' );
	} );

	it( 'only ever creates one hint element', () => {
		renderWith( {} );
		renderWith( {} );

		expect(
			document.querySelectorAll( '#gatherpress-block-guard-hint' )
		).toHaveLength( 1 );
	} );

	it( 'describes the block by the hint while sealed', () => {
		renderWith( {} );

		expect( lastProps().wrapperProps[ 'aria-describedby' ] ).toBe(
			'gatherpress-block-guard-hint'
		);
	} );

	it( 'preserves an existing aria-describedby alongside the hint', () => {
		renderWith( { wrapperProps: { 'aria-describedby': 'other-id' } } );

		expect( lastProps().wrapperProps[ 'aria-describedby' ] ).toBe(
			'other-id gatherpress-block-guard-hint'
		);
	} );

	it( 'drops the hint description once unsealed', () => {
		renderWith( { isInner: true } );

		expect( lastProps().wrapperProps[ 'aria-describedby' ] ).toBeUndefined();
	} );

	it( 'announces when the block unseals', () => {
		// eslint-disable-next-line global-require
		const { speak } = require( '@wordpress/a11y' );
		const { rerender } = renderWith( {} );
		speak.mockClear();

		// an inner block becoming selected opens it
		useSelect.mockImplementation( ( mapSelect ) =>
			mapSelect( () => ( {
				isBlockSelected: () => false,
				hasSelectedInnerBlock: () => true,
			} ) )
		);
		rerender(
			<Guarded
				name="gatherpress/add-to-calendar"
				clientId="a11y"
				wrapperProps={ {} }
			/>
		);

		expect( speak ).toHaveBeenCalledWith(
			expect.stringContaining( 'unlocked' ),
			'polite'
		);
	} );

	it( 'does not announce while the block stays sealed', () => {
		// eslint-disable-next-line global-require
		const { speak } = require( '@wordpress/a11y' );
		speak.mockClear();

		renderWith( {} );

		expect( speak ).not.toHaveBeenCalled();
	} );
} );

describe( 'filter registration', () => {
	// eslint-disable-next-line global-require
	const hooks = require( '@wordpress/hooks' );

	beforeEach( () => {
		hooks.addFilter.mockClear();
	} );

	it( 'registers the BlockListBlock filter when not already present', () => {
		hooks.hasFilter.mockReturnValue( false );

		jest.isolateModules( () => {
			require( '@src/supports/block-guard' );
		} );

		expect( hooks.addFilter ).toHaveBeenCalledWith(
			'editor.BlockListBlock',
			'gatherpress/with-block-guard',
			expect.anything()
		);
	} );

	it( 'does not register a second time when the filter already exists', () => {
		hooks.hasFilter.mockReturnValue( true );

		jest.isolateModules( () => {
			require( '@src/supports/block-guard' );
		} );

		expect( hooks.addFilter ).not.toHaveBeenCalled();
	} );
} );

describe( 'placeCaretAtPoint', () => {
	const makeDoc = ( overrides = {} ) => {
		const editable = {
			focus: jest.fn(),
		};
		const target = {
			closest: jest.fn( () => editable ),
		};
		const selection = {
			removeAllRanges: jest.fn(),
			addRange: jest.fn(),
		};
		return {
			editable,
			target,
			selection,
			doc: {
				elementFromPoint: jest.fn( () => target ),
				getSelection: jest.fn( () => selection ),
				...overrides,
			},
		};
	};

	it( 'does nothing when no element sits at the point', () => {
		const { doc } = makeDoc( { elementFromPoint: jest.fn( () => null ) } );

		expect( () => placeCaretAtPoint( doc, 5, 5 ) ).not.toThrow();
	} );

	it( 'does nothing when the element is not inside editable text', () => {
		const { doc, editable } = makeDoc();
		doc.elementFromPoint = jest.fn( () => ( {
			closest: () => null,
		} ) );

		placeCaretAtPoint( doc, 5, 5 );

		expect( editable.focus ).not.toHaveBeenCalled();
	} );

	it( 'focuses and places the caret via caretRangeFromPoint', () => {
		const { doc, editable, selection } = makeDoc();
		const range = {};
		doc.caretRangeFromPoint = jest.fn( () => range );

		placeCaretAtPoint( doc, 5, 5 );

		expect( editable.focus ).toHaveBeenCalled();
		expect( selection.removeAllRanges ).toHaveBeenCalled();
		expect( selection.addRange ).toHaveBeenCalledWith( range );
	} );

	it( 'falls back to caretPositionFromPoint when needed', () => {
		const { doc, editable, selection } = makeDoc();
		const node = {};
		const builtRange = {
			setStart: jest.fn(),
			collapse: jest.fn(),
		};
		doc.caretPositionFromPoint = jest.fn( () => ( {
			offsetNode: node,
			offset: 2,
		} ) );
		doc.createRange = jest.fn( () => builtRange );

		placeCaretAtPoint( doc, 5, 5 );

		expect( editable.focus ).toHaveBeenCalled();
		expect( builtRange.setStart ).toHaveBeenCalledWith( node, 2 );
		expect( selection.addRange ).toHaveBeenCalledWith( builtRange );
	} );

	it( 'stops at focus when caretPositionFromPoint yields nothing', () => {
		const { doc, editable, selection } = makeDoc();
		doc.caretPositionFromPoint = jest.fn( () => null );

		placeCaretAtPoint( doc, 5, 5 );

		expect( editable.focus ).toHaveBeenCalled();
		expect( selection.addRange ).not.toHaveBeenCalled();
	} );

	it( 'stops at focus when neither caret API exists', () => {
		const { doc, editable, selection } = makeDoc();

		placeCaretAtPoint( doc, 5, 5 );

		expect( editable.focus ).toHaveBeenCalled();
		expect( selection.addRange ).not.toHaveBeenCalled();
	} );

	it( 'copes with a document that has no selection', () => {
		const { doc, editable } = makeDoc( {
			getSelection: jest.fn( () => null ),
		} );

		placeCaretAtPoint( doc, 5, 5 );

		expect( editable.focus ).toHaveBeenCalled();
	} );
} );

describe( 'double-click caret forwarding', () => {
	const BlockListBlock = jest.fn( () => <div /> );
	const Guarded = withBlockGuard( BlockListBlock );
	const lastProps = () => BlockListBlock.mock.calls.at( -1 )[ 0 ];

	const setSelection = ( { isSelf = false, isInner = false } ) => {
		useSelect.mockImplementation( ( mapSelect ) =>
			mapSelect( () => ( {
				isBlockSelected: () => isSelf,
				hasSelectedInnerBlock: () => isInner,
			} ) )
		);
	};

	const element = () => (
		<Guarded
			name="gatherpress/add-to-calendar"
			clientId="caret"
			wrapperProps={ {} }
		/>
	);

	beforeEach( () => {
		jest.clearAllMocks();
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );
		document.elementFromPoint = jest.fn( () => null );
	} );

	it( 'forwards the caret to the double-click point once the seal lifts', () => {
		setSelection( { isSelf: true } );
		render( element() );

		act( () => {
			lastProps().wrapperProps.onDoubleClick( {
				clientX: 42,
				clientY: 24,
			} );
		} );

		// the seal has lifted and the caret was sought at the click point
		expect( document.elementFromPoint ).toHaveBeenCalledWith( 42, 24 );
	} );

	it( 'does not forward for a double-click on an already-open block', () => {
		setSelection( { isInner: true } ); // open via inner selection
		render( element() );

		act( () => {
			lastProps().wrapperProps.onDoubleClick( {
				clientX: 42,
				clientY: 24,
			} );
		} );

		expect( document.elementFromPoint ).not.toHaveBeenCalled();
	} );
} );
