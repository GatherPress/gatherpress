/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { act, fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import OnlineEvent from '@src/components/OnlineEvent';
import { usePostTypeLabel } from '@src/helpers/editor';
import {
	getOnlineEventTermId,
	getVenuePostType,
	getVenueTaxonomy,
} from '@src/helpers/venue';

// The props the component last passed to ToggleControl, so a test can drive
// onChange with a value the rendered checkbox would not send.
let mockToggleProps = {};

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => ( {
	__experimentalVStack: ( { children } ) => <div>{ children }</div>,
	ToggleControl: ( props ) => {
		mockToggleProps = props;
		return (
			<div>
				<input
					id="mock-online-toggle"
					type="checkbox"
					checked={ props.checked }
					onChange={ ( e ) => props.onChange( e.target.checked ) }
				/>
				<label htmlFor="mock-online-toggle">{ props.label }</label>
			</div>
		);
	},
	TextControl: ( { label, value, placeholder, onChange } ) => (
		<div>
			<label htmlFor="mock-online-link">{ label }</label>
			<input
				id="mock-online-link"
				type="url"
				value={ value }
				placeholder={ placeholder }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
		</div>
	),
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	usePostTypeLabel: jest.fn(),
} ) );

jest.mock( '@src/helpers/venue', () => ( {
	getOnlineEventTermId: jest.fn(),
	getVenuePostType: jest.fn(),
	getVenueTaxonomy: jest.fn(),
} ) );

const TAXONOMY = '_gatherpress_venue';
const TOGGLE_LABEL = 'This is an online Event';
const LINK_LABEL = 'Online Event link';

/**
 * Points the mocked `core/editor` selectors at the given editor state.
 *
 * @since TBD
 *
 * @param {string|undefined}              link  The stored online event link.
 * @param {Array|number|string|undefined} terms The venue terms on the post.
 *
 * @return {void}
 */
const mockEditor = ( link, terms ) => {
	useSelect.mockImplementation( ( callback ) =>
		callback( () => ( {
			getCurrentPostType: () => 'gatherpress_event',
			getEditedPostAttribute: ( key ) =>
				'meta' === key ? { gatherpress_online_event_link: link } : terms,
		} ) ),
	);
};

describe( 'OnlineEvent component', () => {
	const mockEditPost = jest.fn();
	const mockUnlockPostSaving = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		mockToggleProps = {};
		useDispatch.mockReturnValue( {
			editPost: mockEditPost,
			unlockPostSaving: mockUnlockPostSaving,
		} );
		usePostTypeLabel.mockImplementation(
			( key, postType, fallback ) => fallback,
		);
		getVenuePostType.mockReturnValue( 'gatherpress_venue' );
		getVenueTaxonomy.mockReturnValue( TAXONOMY );
		getOnlineEventTermId.mockReturnValue( 42 );
	} );

	it( 'names the controls after the post type label', () => {
		usePostTypeLabel.mockReturnValue( 'Happening' );
		mockEditor( 'https://example.com/live', [ 42 ] );

		render( <OnlineEvent /> );

		expect( usePostTypeLabel ).toHaveBeenCalledWith(
			'singular_name',
			'gatherpress_event',
			'Event',
		);
		expect( getVenuePostType ).toHaveBeenCalledWith( 'gatherpress_event' );
		expect( getVenueTaxonomy ).toHaveBeenCalledWith( 'gatherpress_venue' );
		expect(
			screen.getByLabelText( 'This is an online Happening' ),
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Online Happening link' ) ).toHaveAttribute(
			'placeholder',
			'Add link to online Happening',
		);
	} );

	describe( 'reading the online-event term', () => {
		it( 'is off and hides the link field when the post has no venue terms', () => {
			mockEditor( '', undefined );

			render( <OnlineEvent /> );

			expect( screen.getByLabelText( TOGGLE_LABEL ) ).not.toBeChecked();
			expect( screen.queryByLabelText( LINK_LABEL ) ).not.toBeInTheDocument();
		} );

		it( 'is off when no online-event term ID is known', () => {
			getOnlineEventTermId.mockReturnValue( null );
			mockEditor( '', [ 42 ] );

			render( <OnlineEvent /> );

			expect( screen.getByLabelText( TOGGLE_LABEL ) ).not.toBeChecked();
		} );

		it( 'is off when the terms do not include the online-event term', () => {
			mockEditor( '', [ 7, 8 ] );

			render( <OnlineEvent /> );

			expect( screen.getByLabelText( TOGGLE_LABEL ) ).not.toBeChecked();
		} );

		it( 'is on when the terms include the online-event term as a string', () => {
			mockEditor( 'https://example.com/live', [ 7, '42' ] );

			render( <OnlineEvent /> );

			expect( screen.getByLabelText( TOGGLE_LABEL ) ).toBeChecked();
			expect( screen.getByLabelText( LINK_LABEL ) ).toHaveValue(
				'https://example.com/live',
			);
		} );

		it( 'is on when a single term ID, not an array, is the online-event term', () => {
			mockEditor( '', 42 );

			render( <OnlineEvent /> );

			expect( screen.getByLabelText( TOGGLE_LABEL ) ).toBeChecked();
		} );
	} );

	describe( 'turning it on', () => {
		it( 'adds the online-event term to the existing venue terms', () => {
			mockEditor( '', [ 7 ] );

			render( <OnlineEvent /> );
			fireEvent.click( screen.getByLabelText( TOGGLE_LABEL ) );

			expect( mockEditPost ).toHaveBeenCalledWith( {
				[ TAXONOMY ]: [ 7, 42 ],
			} );
			expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
			expect( screen.getByLabelText( LINK_LABEL ) ).toBeInTheDocument();
		} );

		it( 'wraps a single existing term ID in an array', () => {
			mockEditor( '', 7 );

			render( <OnlineEvent /> );
			fireEvent.click( screen.getByLabelText( TOGGLE_LABEL ) );

			expect( mockEditPost ).toHaveBeenCalledWith( {
				[ TAXONOMY ]: [ 7, 42 ],
			} );
		} );

		it( 'starts from an empty list when the post has no venue terms', () => {
			mockEditor( '', undefined );

			render( <OnlineEvent /> );
			fireEvent.click( screen.getByLabelText( TOGGLE_LABEL ) );

			expect( mockEditPost ).toHaveBeenCalledWith( { [ TAXONOMY ]: [ 42 ] } );
		} );

		it( 'does not add the term twice when it is already assigned', () => {
			mockEditor( '', [ '42' ] );

			render( <OnlineEvent /> );
			act( () => mockToggleProps.onChange( true ) );

			expect( mockEditPost ).toHaveBeenCalledWith( {
				[ TAXONOMY ]: [ '42' ],
			} );
		} );

		it( 'shows the link field without saving terms when no term ID is known', () => {
			getOnlineEventTermId.mockReturnValue( null );
			mockEditor( '', [] );

			render( <OnlineEvent /> );
			fireEvent.click( screen.getByLabelText( TOGGLE_LABEL ) );

			expect( mockEditPost ).not.toHaveBeenCalled();
			expect( screen.getByLabelText( LINK_LABEL ) ).toBeInTheDocument();
		} );
	} );

	describe( 'turning it off', () => {
		it( 'removes the online-event term and clears the link', () => {
			mockEditor( 'https://example.com/live', [ 7, '42' ] );

			render( <OnlineEvent /> );
			fireEvent.click( screen.getByLabelText( TOGGLE_LABEL ) );

			expect( mockEditPost ).toHaveBeenCalledWith( { [ TAXONOMY ]: [ 7 ] } );
			expect( mockEditPost ).toHaveBeenCalledWith( {
				meta: { gatherpress_online_event_link: '' },
			} );
			expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 2 );
			expect( screen.queryByLabelText( LINK_LABEL ) ).not.toBeInTheDocument();
		} );
	} );

	it( 'saves the link and unlocks saving on change', () => {
		mockEditor( '', [ 42 ] );

		render( <OnlineEvent /> );
		fireEvent.change( screen.getByLabelText( LINK_LABEL ), {
			target: { value: 'https://example.com/room' },
		} );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_online_event_link: 'https://example.com/room' },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
		expect( screen.getByLabelText( LINK_LABEL ) ).toHaveValue(
			'https://example.com/room',
		);
	} );

	it( 'follows a link changed in the post meta', () => {
		mockEditor( 'https://example.com/old', [ 42 ] );

		const { rerender } = render( <OnlineEvent /> );
		mockEditor( 'https://example.com/new', [ 42 ] );
		rerender( <OnlineEvent /> );

		expect( screen.getByLabelText( LINK_LABEL ) ).toHaveValue(
			'https://example.com/new',
		);
	} );
} );
