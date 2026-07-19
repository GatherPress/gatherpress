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
	useBlockEditingMode: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/compose', () => ( {
	createHigherOrderComponent: jest.fn( ( hoc ) => hoc ),
} ) );

/**
 * Internal dependencies
 */
import { isEditingModeGuarded } from '../../../../../src/supports/block-guard-editing-mode';

describe( 'isEditingModeGuarded', () => {
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'returns true for a prototype block that supports blockGuard', () => {
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );

		expect( isEditingModeGuarded( 'gatherpress/add-to-calendar' ) ).toBe(
			true,
		);
	} );

	it( 'returns false for a prototype block that does not support blockGuard', () => {
		getBlockType.mockReturnValue( { supports: {} } );

		expect( isEditingModeGuarded( 'gatherpress/add-to-calendar' ) ).toBe(
			false,
		);
	} );

	it( 'returns false for a guarded block outside the prototype set', () => {
		getBlockType.mockReturnValue( {
			supports: { gatherpress: { blockGuard: true } },
		} );

		// venue supports blockGuard but is still on the legacy toggle.
		expect( isEditingModeGuarded( 'gatherpress/venue' ) ).toBe( false );
	} );

	it( 'returns false when the block type is unregistered', () => {
		getBlockType.mockReturnValue( undefined );

		expect( isEditingModeGuarded( 'gatherpress/add-to-calendar' ) ).toBe(
			false,
		);
	} );
} );
