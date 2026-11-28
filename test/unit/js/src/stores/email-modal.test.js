/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * WordPress dependencies
 */
import { select, dispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
// Import the actual store to get coverage.
import '@src/stores/email-modal';

describe( 'Email Modal store', () => {
	const STORE_NAME = 'gatherpress/email-modal';

	describe( 'initial state', () => {
		it( 'has isOpen set to false by default', () => {
			const isOpen = select( STORE_NAME ).isModalOpen();

			expect( isOpen ).toBe( false );
		} );

		it( 'has isSaving set to false by default', () => {
			const isSaving = select( STORE_NAME ).isSaving();

			expect( isSaving ).toBe( false );
		} );

		it( 'has no change by default', () => {
			expect( select( STORE_NAME ).getChange() ).toBeNull();
		} );
	} );

	describe( 'change', () => {
		const change = { keys: [ 'venue' ], headline: 'The venue changed.' };

		it( 'keeps the change the modal opens for, and drops it on close', () => {
			dispatch( STORE_NAME ).openModal( change );

			expect( select( STORE_NAME ).getChange() ).toEqual( change );

			dispatch( STORE_NAME ).closeModal();

			expect( select( STORE_NAME ).getChange() ).toBeNull();
		} );

		it( 'ignores a click event passed as the argument', () => {
			// The panel button passes `openModal` as its `onClick`.
			dispatch( STORE_NAME ).openModal( { type: 'click' } );

			expect( select( STORE_NAME ).isModalOpen() ).toBe( true );
			expect( select( STORE_NAME ).getChange() ).toBeNull();

			dispatch( STORE_NAME ).closeModal();
		} );
	} );

	describe( 'selectors', () => {
		it( 'isModalOpen returns the isOpen state when true', () => {
			dispatch( STORE_NAME ).openModal();

			const result = select( STORE_NAME ).isModalOpen();

			expect( result ).toBe( true );
		} );

		it( 'isModalOpen returns the isOpen state when false', () => {
			dispatch( STORE_NAME ).closeModal();

			const result = select( STORE_NAME ).isModalOpen();

			expect( result ).toBe( false );
		} );

		it( 'isSaving returns the isSaving state when true', () => {
			dispatch( STORE_NAME ).setSaving( true );

			const result = select( STORE_NAME ).isSaving();

			expect( result ).toBe( true );
		} );

		it( 'isSaving returns the isSaving state when false', () => {
			dispatch( STORE_NAME ).setSaving( false );

			const result = select( STORE_NAME ).isSaving();

			expect( result ).toBe( false );
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
