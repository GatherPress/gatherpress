/**
 * External dependencies
 */
import { act, screen } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
jest.mock( '@wordpress/dom-ready', () => jest.fn() );
jest.mock( '@wordpress/api-fetch', () => jest.fn() );
jest.mock( '@wordpress/data', () => {
	const stores = {
		'gatherpress/email-modal': {
			isModalOpen: () => true,
			isSaving: () => false,
		},
		'core/editor': {
			getEditedPostAttribute: () => 'Event',
			getCurrentPostId: () => 1,
		},
	};

	return {
		select: ( store ) => stores[ store ],
		useSelect: ( callback ) => callback( ( store ) => stores[ store ] ),
		useDispatch: () => ( { closeModal: jest.fn() } ),
	};
} );
jest.mock( '@wordpress/components', () => ( {
	Button: ( { children } ) => <button>{ children }</button>,
	CheckboxControl: ( { label } ) => (
		<input type="checkbox" aria-label={ label } />
	),
	Flex: ( { children } ) => <div>{ children }</div>,
	FlexItem: ( { children } ) => <div>{ children }</div>,
	Modal: ( { children } ) => <div>{ children }</div>,
	TextControl: () => <input type="text" />,
	// The modal hands the textarea a ref to focus it on open.
	TextareaControl: require( '@wordpress/element' ).forwardRef(
		( props, ref ) => <textarea ref={ ref } />,
	),
} ) );
jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromConfig: jest.fn(),
} ) );

import domReady from '@wordpress/dom-ready';
import { getFromConfig } from '@src/helpers/editor-settings';
import '@src/modals/email-communication';

// Captured once, when the module above registers its mount callback.
const mountModal = domReady.mock.calls[ 0 ][ 0 ];

/**
 * Mount the modal into a fresh wrapper element.
 *
 * @since TBD
 */
async function renderModal() {
	document.body.innerHTML =
		'<div id="gatherpress-event-communication-modal"></div>';

	await act( async () => {
		mountModal();
	} );
}

/**
 * Coverage for the event communication modal.
 */
describe( 'EventCommunicationModal', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'offers All Members when the user can email every member', async () => {
		getFromConfig.mockReturnValue( true );

		await renderModal();

		expect( screen.getByLabelText( 'All Members' ) ).toBeInTheDocument();
		expect( getFromConfig ).toHaveBeenCalledWith( 'canEmailAllUsers' );
		expect(
			screen.getByText( /"All Members" includes site users only\./ ),
		).toBeInTheDocument();
	} );

	it( 'hides All Members when the user cannot email every member', async () => {
		getFromConfig.mockReturnValue( false );

		await renderModal();

		expect( screen.queryByLabelText( 'All Members' ) ).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Attending' ) ).toBeInTheDocument();
		expect( screen.queryByText( /All Members/ ) ).not.toBeInTheDocument();
		expect(
			screen.getByText( /RSVP status options include both site users/ ),
		).toBeInTheDocument();
	} );
} );
