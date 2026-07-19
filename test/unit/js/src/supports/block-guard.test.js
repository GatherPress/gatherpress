/**
 * External dependencies
 */
import {
	describe,
	expect,
	it,
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
	withBlockGuard,
} from '@src/supports/block-guard';

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
	const BlockListBlock = jest.fn( () => <div data-testid="block" /> );
	const Guarded = withBlockGuard( BlockListBlock );

	const renderGuard = ( { isSelf = false, isInner = false, ...props } = {} ) => {
		useSelect.mockImplementation( ( mapSelect ) =>
			mapSelect( () => ( {
				isBlockSelected: () => isSelf,
				hasSelectedInnerBlock: () => isInner,
			} ) )
		);

		return render(
			<Guarded
				name="gatherpress/add-to-calendar"
				clientId="abc"
				wrapperProps={ {} }
				{ ...props }
			/>
		);
	};

	const lastProps = () => BlockListBlock.mock.calls.at( -1 )[ 0 ];

	beforeEach( () => {
		jest.clearAllMocks();
		// The sealed state persists across mounts by design (so moving a block
		// doesn't slam it shut), so reset it between tests.
		publishSealedState( 'abc', true );
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );
	} );

	it( 'passes a non-guarded block straight through', () => {
		getBlockType.mockReturnValue( { supports: {} } );
		renderGuard( { name: 'core/paragraph' } );

		expect( lastProps().className ).toBeUndefined();
	} );

	it( 'seals a guarded block by default', () => {
		renderGuard();

		expect( lastProps().className ).toContain( 'has-block-overlay' );
	} );

	it( 'does not tint while the block is unselected', () => {
		renderGuard();

		expect( lastProps().wrapperProps.style ).toBeUndefined();
	} );

	it( 'tints once the block is selected and sealed', () => {
		renderGuard( { isSelf: true } );

		expect( lastProps().wrapperProps.style ).toMatchObject( {
			cursor: 'pointer',
		} );
	} );

	it( 'unseals when an inner block is selected (the List View route)', () => {
		renderGuard( { isInner: true } );

		expect( lastProps().className ).not.toContain( 'has-block-overlay' );
	} );

	it( 'publishes its sealed state for descendants', () => {
		renderGuard();

		const { result } = renderHook( () => useIsBlockSealed( 'abc' ) );
		expect( result.current ).toBe( true );
	} );

	it( 'unseals on Enter when selected', () => {
		renderGuard( { isSelf: true } );

		act( () => {
			lastProps().wrapperProps.onKeyDown( {
				key: 'Enter',
				preventDefault() {},
				stopPropagation() {},
			} );
		} );

		expect( lastProps().className ).not.toContain( 'has-block-overlay' );
	} );

	it( 'unseals on Space when selected', () => {
		renderGuard( { isSelf: true } );

		act( () => {
			lastProps().wrapperProps.onKeyDown( {
				key: ' ',
				preventDefault() {},
				stopPropagation() {},
			} );
		} );

		expect( lastProps().className ).not.toContain( 'has-block-overlay' );
	} );

	it( 'ignores other keys and chains to the original handler', () => {
		const onKeyDown = jest.fn();
		renderGuard( { isSelf: true, wrapperProps: { onKeyDown } } );

		act( () => {
			lastProps().wrapperProps.onKeyDown( {
				key: 'a',
				preventDefault() {},
				stopPropagation() {},
			} );
		} );

		expect( onKeyDown ).toHaveBeenCalled();
		expect( lastProps().className ).toContain( 'has-block-overlay' );
	} );

	it( 'chains to an existing onClick handler', () => {
		const onClick = jest.fn();
		renderGuard( { isSelf: true, wrapperProps: { onClick } } );

		act( () => {
			lastProps().wrapperProps.onClick( {} );
		} );

		expect( onClick ).toHaveBeenCalled();
	} );

	it( 'does not unseal on a click that did not begin on a selected block', () => {
		renderGuard( { isSelf: true } );

		act( () => {
			lastProps().wrapperProps.onClick( {} );
		} );

		expect( lastProps().className ).toContain( 'has-block-overlay' );
	} );
} );

describe( 'withBlockGuard re-seal transitions', () => {
	const BlockListBlock = jest.fn( () => <div /> );
	const Guarded = withBlockGuard( BlockListBlock );
	const lastProps = () => BlockListBlock.mock.calls.at( -1 )[ 0 ];
	const sealedNow = () =>
		lastProps().className.includes( 'has-block-overlay' );

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
			clientId="abc"
			wrapperProps={ {} }
		/>
	);

	beforeEach( () => {
		jest.clearAllMocks();
		publishSealedState( 'abc', true );
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );
	} );

	it( 're-seals when selection leaves the block entirely', () => {
		setSelection( { isInner: true } );
		const { rerender } = render( element() );
		expect( sealedNow() ).toBe( false );

		setSelection( {} );
		rerender( element() );

		expect( sealedNow() ).toBe( true );
	} );

	it( 're-seals when selection returns to the block after being inside', () => {
		setSelection( { isInner: true } );
		const { rerender } = render( element() );
		expect( sealedNow() ).toBe( false );

		setSelection( { isSelf: true } );
		rerender( element() );

		expect( sealedNow() ).toBe( true );
	} );

	it( 'stays unsealed while selection moves between inner blocks', () => {
		setSelection( { isInner: true } );
		const { rerender } = render( element() );

		setSelection( { isInner: true } );
		rerender( element() );

		expect( sealedNow() ).toBe( false );
	} );

	it( 'unseals on a click whose mousedown began on the selected block', () => {
		setSelection( { isSelf: true } );
		render( element() );
		expect( sealedNow() ).toBe( true );

		act( () => {
			lastProps().wrapperProps.onMouseDown( {} );
		} );

		act( () => {
			lastProps().wrapperProps.onClick( {} );
		} );

		expect( sealedNow() ).toBe( false );
	} );

	it( 'chains to an existing onMouseDown handler', () => {
		const onMouseDown = jest.fn();
		setSelection( { isSelf: true } );
		render(
			<Guarded
				name="gatherpress/add-to-calendar"
				clientId="abc"
				wrapperProps={ { onMouseDown } }
			/>
		);

		act( () => {
			lastProps().wrapperProps.onMouseDown( {} );
		} );

		expect( onMouseDown ).toHaveBeenCalled();
	} );
} );

describe( 'useIsBlockSealed subscriptions', () => {
	it( 'shares one listener set between subscribers to the same block', () => {
		const first = renderHook( () => useIsBlockSealed( 'shared-id' ) );
		const second = renderHook( () => useIsBlockSealed( 'shared-id' ) );

		act( () => {
			publishSealedState( 'shared-id', false );
		} );

		expect( first.result.current ).toBe( false );
		expect( second.result.current ).toBe( false );
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
		publishSealedState( 'a11y', true );
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );
		document.getElementById( 'gatherpress-block-guard-hint' )?.remove();
	} );

	it( 'adds a visually hidden hint to the canvas describing how to get in', () => {
		renderWith( {} );

		const hint = document.getElementById( 'gatherpress-block-guard-hint' );
		expect( hint ).not.toBeNull();
		expect( hint.className ).toBe( 'screen-reader-text' );
		expect( hint.textContent ).toContain( 'Press Enter' );
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

		expect(
			lastProps().wrapperProps[ 'aria-describedby' ]
		).toBeUndefined();
	} );

	it( 'announces to assistive technology when the block unseals', () => {
		// eslint-disable-next-line global-require
		const { speak } = require( '@wordpress/a11y' );
		speak.mockClear();

		renderWith( { isInner: true } );

		expect( speak ).toHaveBeenCalledWith(
			expect.stringContaining( 'unlocked' ),
			'polite'
		);
	} );

	it( 'does not announce while the block stays sealed', () => {
		// eslint-disable-next-line global-require
		const { speak } = require( '@wordpress/a11y' );
		speak.mockClear();

		renderWith( { isSelf: true } );

		expect( speak ).not.toHaveBeenCalled();
	} );
} );

describe( 'sealed state across a move', () => {
	const BlockListBlock = jest.fn( () => <div /> );
	const Guarded = withBlockGuard( BlockListBlock );
	const lastProps = () => BlockListBlock.mock.calls.at( -1 )[ 0 ];

	beforeEach( () => {
		jest.clearAllMocks();
		publishSealedState( 'moved', true );
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );
		useSelect.mockImplementation( ( mapSelect ) =>
			mapSelect( () => ( {
				isBlockSelected: () => true,
				hasSelectedInnerBlock: () => false,
			} ) )
		);
	} );

	const element = () => (
		<Guarded
			name="gatherpress/add-to-calendar"
			clientId="moved"
			wrapperProps={ {} }
		/>
	);

	it( 'stays open across a remount, so moving an open block does not shut it', () => {
		const first = render( element() );
		act( () => {
			lastProps().wrapperProps.onMouseDown( {} );
			lastProps().wrapperProps.onClick( {} );
		} );
		expect( lastProps().className ).not.toContain( 'has-block-overlay' );

		// a move unmounts and remounts the block
		first.unmount();
		render( element() );

		expect( lastProps().className ).not.toContain( 'has-block-overlay' );
	} );

	it( 'defaults to sealed for a block with no recorded state', () => {
		render(
			<Guarded
				name="gatherpress/add-to-calendar"
				clientId="never-seen-before"
				wrapperProps={ {} }
			/>
		);

		expect( lastProps().className ).toContain( 'has-block-overlay' );
	} );

	it( 'remounts sealed when it was sealed before the move', () => {
		const first = render( element() );
		expect( lastProps().className ).toContain( 'has-block-overlay' );

		first.unmount();
		render( element() );

		expect( lastProps().className ).toContain( 'has-block-overlay' );
	} );
} );
