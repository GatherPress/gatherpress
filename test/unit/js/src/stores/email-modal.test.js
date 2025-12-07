/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { select, dispatch, register, createReduxStore } from '@wordpress/data';

describe( 'Email Modal store', () => {
	const STORE_NAME = 'gatherpress/email-modal';

	const DEFAULT_STATE = {
		isOpen: false,
		isSaving: false,
	};

	const actions = {
		setModalOpen( isOpen ) {
			return {
				type: 'SET_MODAL_OPEN',
				isOpen,
			};
		},

		openModal() {
			return {
				type: 'SET_MODAL_OPEN',
				isOpen: true,
			};
		},

		closeModal() {
			return {
				type: 'SET_MODAL_OPEN',
				isOpen: false,
			};
		},

		setSaving( isSaving ) {
			return {
				type: 'SET_SAVING',
				isSaving,
			};
		},
	};

	const selectors = {
		isModalOpen( state ) {
			return state.isOpen;
		},

		isSaving( state ) {
			return state.isSaving;
		},
	};

	const reducer = ( state = DEFAULT_STATE, action ) => {
		switch ( action.type ) {
			case 'SET_MODAL_OPEN':
				return {
					...state,
					isOpen: action.isOpen,
				};
			case 'SET_SAVING':
				return {
					...state,
					isSaving: action.isSaving,
				};
			default:
				return state;
		}
	};

	const store = createReduxStore( STORE_NAME, {
		reducer,
		actions,
		selectors,
	} );

	register( store );

	describe( 'initial state', () => {
		it( 'has isOpen set to false by default', () => {
			const isOpen = select( STORE_NAME ).isModalOpen();

			expect( isOpen ).toBe( false );
		} );

		it( 'has isSaving set to false by default', () => {
			const isSaving = select( STORE_NAME ).isSaving();

			expect( isSaving ).toBe( false );
		} );
	} );

	describe( 'action creators', () => {
		it( 'setModalOpen creates correct action object', () => {
			const action = actions.setModalOpen( true );

			expect( action ).toEqual( {
				type: 'SET_MODAL_OPEN',
				isOpen: true,
			} );
		} );

		it( 'openModal creates correct action object', () => {
			const action = actions.openModal();

			expect( action ).toEqual( {
				type: 'SET_MODAL_OPEN',
				isOpen: true,
			} );
		} );

		it( 'closeModal creates correct action object', () => {
			const action = actions.closeModal();

			expect( action ).toEqual( {
				type: 'SET_MODAL_OPEN',
				isOpen: false,
			} );
		} );

		it( 'setSaving creates correct action object', () => {
			const action = actions.setSaving( true );

			expect( action ).toEqual( {
				type: 'SET_SAVING',
				isSaving: true,
			} );
		} );
	} );

	describe( 'selectors', () => {
		it( 'isModalOpen returns the isOpen state', () => {
			const state = { isOpen: true, isSaving: false };

			const result = selectors.isModalOpen( state );

			expect( result ).toBe( true );
		} );

		it( 'isSaving returns the isSaving state', () => {
			const state = { isOpen: false, isSaving: true };

			const result = selectors.isSaving( state );

			expect( result ).toBe( true );
		} );
	} );

	describe( 'state changes', () => {
		it( 'openModal updates isOpen to true', () => {
			dispatch( STORE_NAME ).openModal();

			const isOpen = select( STORE_NAME ).isModalOpen();

			expect( isOpen ).toBe( true );
		} );

		it( 'closeModal updates isOpen to false', () => {
			dispatch( STORE_NAME ).openModal();
			dispatch( STORE_NAME ).closeModal();

			const isOpen = select( STORE_NAME ).isModalOpen();

			expect( isOpen ).toBe( false );
		} );

		it( 'setModalOpen updates isOpen state', () => {
			dispatch( STORE_NAME ).setModalOpen( true );

			expect( select( STORE_NAME ).isModalOpen() ).toBe( true );

			dispatch( STORE_NAME ).setModalOpen( false );

			expect( select( STORE_NAME ).isModalOpen() ).toBe( false );
		} );

		it( 'setSaving updates isSaving state', () => {
			dispatch( STORE_NAME ).setSaving( true );

			expect( select( STORE_NAME ).isSaving() ).toBe( true );

			dispatch( STORE_NAME ).setSaving( false );

			expect( select( STORE_NAME ).isSaving() ).toBe( false );
		} );

		it( 'can have isOpen and isSaving true simultaneously', () => {
			dispatch( STORE_NAME ).openModal();
			dispatch( STORE_NAME ).setSaving( true );

			expect( select( STORE_NAME ).isModalOpen() ).toBe( true );
			expect( select( STORE_NAME ).isSaving() ).toBe( true );
		} );
	} );
} );
