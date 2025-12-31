/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * WordPress dependencies.
 */
import { select, dispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
// Import the actual store to get coverage.
import '../../../../../src/stores/email-modal';

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
	} );

	describe( 'selectors', () => {
		it( 'isModalOpen returns the isOpen state when true', () => {
			// First set the state to true.
			dispatch( STORE_NAME ).setModalOpen( true );

			const result = select( STORE_NAME ).isModalOpen();

			expect( result ).toBe( true );
		} );

		it( 'isModalOpen returns the isOpen state when false', () => {
			dispatch( STORE_NAME ).setModalOpen( false );

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
